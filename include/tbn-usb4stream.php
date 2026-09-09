<?php
/**
 * USB4STREAM — configfs streams, flash persist, copy helper.
 *
 * Kernel: thunderbolt_stream (mainline ~7.2+). ConfigFS:
 *   /sys/kernel/config/thunderbolt/stream/<domain-route.index>/<name>/
 * Character device: /dev/tbstreamN (index attr).
 * Stream name max 8 chars (TB_PROPERTY_KEY_SIZE). HopID -1 = auto.
 */

if (!function_exists('tbn_cfg_dir')) {
  require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';
}

function tbn_stream_cfg_path() {
  return tbn_cfg_dir() . '/streams.json';
}

function tbn_stream_configfs_roots() {
  return [
    '/sys/kernel/config/thunderbolt/stream',
    '/sys/kernel/config/usb4stream',
  ];
}

function tbn_stream_configfs_root() {
  foreach (tbn_stream_configfs_roots() as $r) {
    if (is_dir($r)) {
      return $r;
    }
  }
  return tbn_stream_configfs_roots()[0];
}

/** Live ConfigFS dir, or empty if none mounted. */
function tbn_stream_configfs_live_root() {
  foreach (tbn_stream_configfs_roots() as $r) {
    if (is_dir($r)) {
      return $r;
    }
  }
  return '';
}

function tbn_stream_lab_ko_paths() {
  $kver = trim((string)@shell_exec('uname -r 2>/dev/null'));
  $paths = [];
  if ($kver !== '') {
    $paths[] = '/lib/modules/' . $kver . '/extra/thunderbolt_stream.ko';
  }
  $paths[] = tbn_cfg_dir() . '/lab/thunderbolt_stream.ko';
  return $paths;
}

function tbn_stream_name_ok($name) {
  $name = (string)$name;
  return (bool)preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,7}$/', $name);
}

function tbn_stream_path_ok($path) {
  return (bool)preg_match('/^\d+-[0-9a-fA-F]+\.\d+$/', (string)$path);
}

function tbn_stream_dev_ok($dev) {
  $dev = (string)$dev;
  if (strpos($dev, '/dev/') !== 0) {
    $dev = '/dev/' . $dev;
  }
  return (bool)preg_match('#^/dev/tbstream[0-9]+$#', $dev);
}

/**
 * Whether the running kernel can load USB4STREAM.
 */
function tbn_usb4stream_module_available($refresh = false) {
  static $cached = null;
  if ($refresh) {
    $cached = null;
  }
  if ($cached !== null) {
    return $cached;
  }
  $out = [];
  @exec('modinfo thunderbolt_stream 2>/dev/null', $out, $rc1);
  if ($rc1 === 0 && $out) {
    $cached = true;
    return true;
  }
  $out = [];
  @exec('modinfo thunderbolt-stream 2>/dev/null', $out, $rc2);
  if ($rc2 === 0 && $out) {
    $cached = true;
    return true;
  }
  foreach (tbn_stream_lab_ko_paths() as $p) {
    if (is_file($p)) {
      $cached = true;
      return true;
    }
  }
  $cached = false;
  return $cached;
}

function tbn_stream_ensure_configfs_mount() {
  if (!is_dir('/sys/kernel/config')) {
    @mkdir('/sys/kernel/config', 0755, true);
  }
  if (!is_dir('/sys/kernel/config')) {
    return false;
  }
  if (!is_dir('/sys/kernel/config/thunderbolt') && is_file('/proc/mounts')) {
    $mounts = (string)@file_get_contents('/proc/mounts');
    if (strpos($mounts, ' /sys/kernel/config ') === false) {
      @exec('mount -t configfs configfs /sys/kernel/config 2>/dev/null');
    }
  }
  return is_dir('/sys/kernel/config');
}

function tbn_stream_load_module() {
  if (!tbn_usb4stream_module_available(true)) {
    return ['ok' => false, 'error' => 'thunderbolt_stream is not in this kernel'];
  }
  @exec('modprobe thunderbolt 2>/dev/null');
  @exec('modprobe thunderbolt_stream 2>/dev/null', $o1, $rc1);
  if ($rc1 !== 0) {
    @exec('modprobe thunderbolt-stream 2>/dev/null', $o2, $rc2);
    $rc1 = $rc2;
  }
  if ($rc1 !== 0) {
    $ins = 1;
    foreach (tbn_stream_lab_ko_paths() as $p) {
      if (!is_file($p)) {
        continue;
      }
      @exec('insmod ' . escapeshellarg($p) . ' 2>/dev/null', $o3, $ins);
      if ($ins === 0) {
        break;
      }
    }
    if ($ins !== 0) {
      return ['ok' => false, 'error' => 'modprobe/insmod thunderbolt_stream failed'];
    }
  }
  tbn_usb4stream_module_available(true);
  tbn_stream_ensure_configfs_mount();
  for ($i = 0; $i < 40; $i++) {
    foreach (tbn_stream_configfs_roots() as $r) {
      if (is_dir($r)) {
        return ['ok' => true, 'configfs' => $r];
      }
    }
    usleep(50000);
  }
  return [
    'ok' => false,
    'error' => 'configfs usb4stream/thunderbolt/stream did not appear after load',
  ];
}

function tbn_stream_load_saved() {
  $path = tbn_stream_cfg_path();
  if (!is_readable($path)) {
    return [];
  }
  $j = json_decode((string)@file_get_contents($path), true);
  if (!is_array($j)) {
    return [];
  }
  $rows = $j['streams'] ?? $j;
  if (!is_array($rows)) {
    return [];
  }
  $out = [];
  foreach ($rows as $row) {
    if (!is_array($row)) {
      continue;
    }
    $p = trim((string)($row['path'] ?? ''));
    $n = trim((string)($row['name'] ?? ''));
    if (!tbn_stream_path_ok($p) || !tbn_stream_name_ok($n)) {
      continue;
    }
    $out[] = [
      'path' => $p,
      'name' => $n,
      'in_hopid' => (int)($row['in_hopid'] ?? -1),
      'out_hopid' => (int)($row['out_hopid'] ?? -1),
      'enabled' => (($row['enabled'] ?? 'yes') !== 'no' && ($row['enabled'] ?? true) !== false),
    ];
  }
  return $out;
}

function tbn_stream_save_saved(array $rows) {
  $dir = tbn_cfg_dir();
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  $clean = [];
  foreach ($rows as $row) {
    $p = trim((string)($row['path'] ?? ''));
    $n = trim((string)($row['name'] ?? ''));
    if (!tbn_stream_path_ok($p) || !tbn_stream_name_ok($n)) {
      continue;
    }
    $clean[] = [
      'path' => $p,
      'name' => $n,
      'in_hopid' => (int)($row['in_hopid'] ?? -1),
      'out_hopid' => (int)($row['out_hopid'] ?? -1),
      'enabled' => !empty($row['enabled']),
    ];
  }
  $json = json_encode(['version' => 1, 'streams' => $clean], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  return @file_put_contents(tbn_stream_cfg_path(), $json . "\n") !== false;
}

function tbn_stream_saved_upsert($path, $name, $in_hopid, $out_hopid, $enabled = true) {
  $rows = tbn_stream_load_saved();
  $found = false;
  foreach ($rows as $i => $row) {
    if ($row['path'] === $path && $row['name'] === $name) {
      $rows[$i]['in_hopid'] = (int)$in_hopid;
      $rows[$i]['out_hopid'] = (int)$out_hopid;
      $rows[$i]['enabled'] = (bool)$enabled;
      $found = true;
      break;
    }
  }
  if (!$found) {
    $rows[] = [
      'path' => $path,
      'name' => $name,
      'in_hopid' => (int)$in_hopid,
      'out_hopid' => (int)$out_hopid,
      'enabled' => (bool)$enabled,
    ];
  }
  return tbn_stream_save_saved($rows);
}

function tbn_stream_saved_remove($path, $name) {
  $rows = tbn_stream_load_saved();
  $keep = [];
  foreach ($rows as $row) {
    if ($row['path'] === $path && $row['name'] === $name) {
      continue;
    }
    $keep[] = $row;
  }
  return tbn_stream_save_saved($keep);
}

/**
 * Thunderbolt service device ids (domain-route.index) for the path dropdown.
 */
function tbn_stream_sysfs_services() {
  $out = [];
  foreach (@glob('/sys/bus/thunderbolt/devices/*') ?: [] as $dir) {
    $id = basename($dir);
    if (!tbn_stream_path_ok($id)) {
      continue;
    }
    $parent = preg_replace('/\.\d+$/', '', $id);
    $out[] = [
      'id' => $id,
      'device_name' => function_exists('tbn_sysfs_str') ? tbn_sysfs_str($dir . '/device_name') : '',
      'parent' => $parent,
      'parent_name' => function_exists('tbn_sysfs_str')
        ? tbn_sysfs_str('/sys/bus/thunderbolt/devices/' . $parent . '/device_name')
        : '',
    ];
  }
  usort($out, static function ($a, $b) {
    return strcmp($a['id'], $b['id']);
  });
  return $out;
}

function tbn_stream_cfgfs_read($file) {
  if (!is_readable($file)) {
    return '';
  }
  $s = @file_get_contents($file);
  return $s === false ? '' : trim($s);
}

function tbn_stream_cfgfs_write($file, $value) {
  $fh = @fopen($file, 'wb');
  if ($fh === false) {
    return false;
  }
  $ok = @fwrite($fh, (string)$value) !== false;
  @fclose($fh);
  return $ok;
}

function tbn_stream_list_live() {
  $root = tbn_stream_configfs_root();
  $out = [];
  if (!is_dir($root)) {
    return $out;
  }
  foreach (@glob($root . '/*', GLOB_ONLYDIR) ?: [] as $pathDir) {
    $path = basename($pathDir);
    if (!tbn_stream_path_ok($path)) {
      continue;
    }
    foreach (@glob($pathDir . '/*', GLOB_ONLYDIR) ?: [] as $nameDir) {
      $name = basename($nameDir);
      if ($name === '.' || $name === '..') {
        continue;
      }
      $index = tbn_stream_cfgfs_read($nameDir . '/index');
      $dev = ($index !== '' && ctype_digit($index)) ? ('tbstream' . $index) : '';
      $node = $dev !== '' ? ('/dev/' . $dev) : '';
      $out[] = [
        'path' => $path,
        'name' => $name,
        'index' => $index,
        'device' => $dev,
        'node' => (is_file($node) || is_link($node) || file_exists($node)) ? $node : '',
        'in_hopid' => tbn_stream_cfgfs_read($nameDir . '/in_hopid'),
        'out_hopid' => tbn_stream_cfgfs_read($nameDir . '/out_hopid'),
        'ring_size' => tbn_stream_cfgfs_read($nameDir . '/ring_size'),
        'busy_poll' => tbn_stream_cfgfs_read($nameDir . '/busy_poll'),
      ];
    }
  }
  return $out;
}

function tbn_stream_create($path, $name, $in_hopid = -1, $out_hopid = -1, $persist = true) {
  $path = trim((string)$path);
  $name = trim((string)$name);
  if (!tbn_stream_path_ok($path)) {
    return ['ok' => false, 'error' => 'invalid stream path (want domain-route.index, e.g. 0-1.0)'];
  }
  if (!tbn_stream_name_ok($name)) {
    return ['ok' => false, 'error' => 'invalid stream name (1–8 chars, letter then A–Z a–z 0–9 _ -)'];
  }
  $load = tbn_stream_load_module();
  if (empty($load['ok'])) {
    return $load;
  }
  $root = tbn_stream_configfs_root();
  $pathDir = $root . '/' . $path;
  $nameDir = $pathDir . '/' . $name;
  if (!is_dir($pathDir)) {
    if (!@mkdir($pathDir, 0755)) {
      $err = error_get_last();
      return ['ok' => false, 'error' => 'mkdir path failed: ' . ($err['message'] ?? $path)];
    }
  }
  if (!is_dir($nameDir)) {
    if (!@mkdir($nameDir, 0755)) {
      $err = error_get_last();
      return ['ok' => false, 'error' => 'mkdir stream failed: ' . ($err['message'] ?? $name)];
    }
  }
  $in = (int)$in_hopid;
  $out = (int)$out_hopid;
  if (!tbn_stream_cfgfs_write($nameDir . '/in_hopid', (string)$in . "\n")) {
    return ['ok' => false, 'error' => 'write in_hopid failed'];
  }
  if (!tbn_stream_cfgfs_write($nameDir . '/out_hopid', (string)$out . "\n")) {
    return ['ok' => false, 'error' => 'write out_hopid failed'];
  }
  if ($persist) {
    tbn_stream_saved_upsert($path, $name, $in, $out, true);
  }
  $live = tbn_stream_list_live();
  $hit = null;
  foreach ($live as $row) {
    if ($row['path'] === $path && $row['name'] === $name) {
      $hit = $row;
      break;
    }
  }
  return ['ok' => true, 'stream' => $hit, 'in_hopid' => tbn_stream_cfgfs_read($nameDir . '/in_hopid'), 'out_hopid' => tbn_stream_cfgfs_read($nameDir . '/out_hopid')];
}

function tbn_stream_destroy($path, $name, $persist = true) {
  $path = trim((string)$path);
  $name = trim((string)$name);
  if (!tbn_stream_path_ok($path) || !tbn_stream_name_ok($name)) {
    return ['ok' => false, 'error' => 'invalid path or name'];
  }
  $root = tbn_stream_configfs_root();
  $nameDir = $root . '/' . $path . '/' . $name;
  $pathDir = $root . '/' . $path;
  if (is_dir($nameDir) && !@rmdir($nameDir)) {
    $err = error_get_last();
    return ['ok' => false, 'error' => 'rmdir stream failed: ' . ($err['message'] ?? $name)];
  }
  if (is_dir($pathDir)) {
    $left = @scandir($pathDir) ?: [];
    $left = array_values(array_diff($left, ['.', '..']));
    if (!$left) {
      @rmdir($pathDir);
    }
  }
  if ($persist) {
    tbn_stream_saved_remove($path, $name);
  }
  return ['ok' => true];
}

/**
 * Recreate enabled saved streams (boot / Apply / hotplug).
 */
function tbn_stream_apply($force_module = false) {
  $cfg = function_exists('tbn_load_cfg') ? tbn_load_cfg() : [];
  $want = ($cfg['enable_usb4stream'] ?? 'no') === 'yes' || $force_module;
  $saved = tbn_stream_load_saved();
  $enabled = [];
  foreach ($saved as $row) {
    if (!empty($row['enabled'])) {
      $enabled[] = $row;
    }
  }
  if (!$want && !$enabled) {
    return ['ok' => true, 'skipped' => true, 'reason' => 'USB4STREAM disabled and no saved streams'];
  }
  if ($want || $enabled) {
    $load = tbn_stream_load_module();
    if (empty($load['ok'])) {
      return $load + ['applied' => []];
    }
  }
  $applied = [];
  $errors = [];
  foreach ($enabled as $row) {
    $r = tbn_stream_create($row['path'], $row['name'], $row['in_hopid'], $row['out_hopid'], false);
    if (!empty($r['ok'])) {
      $applied[] = $row['path'] . '/' . $row['name'];
    } else {
      $errors[] = $row['path'] . '/' . $row['name'] . ': ' . ($r['error'] ?? 'failed');
    }
  }
  return [
    'ok' => $errors ? false : true,
    'applied' => $applied,
    'errors' => $errors,
  ];
}

function tbn_usb4stream_status() {
  $mods = function_exists('tbn_modules_loaded') ? tbn_modules_loaded() : [];
  $available = tbn_usb4stream_module_available();
  $loaded = !empty($mods['thunderbolt_stream']);
  $devs = [];
  foreach (@glob('/dev/tbstream*') ?: [] as $p) {
    $devs[] = basename($p);
  }
  sort($devs);
  tbn_stream_ensure_configfs_mount();
  $configfs_root = function_exists('tbn_stream_configfs_live_root')
    ? tbn_stream_configfs_live_root()
    : '';
  $configfs = ($configfs_root !== '');
  $kver = trim((string)@shell_exec('uname -r 2>/dev/null'));
  if ($kver === '') {
    $kver = php_uname('r');
  }
  $live = tbn_stream_list_live();
  $saved = tbn_stream_load_saved();
  $note = '';
  if (!$available) {
    $note = 'No thunderbolt_stream in this kernel'
      . ($kver !== '' ? ' (' . $kver . ')' : '')
      . '. USB4STREAM needs a kernel that ships the module (mainline ~7.2+). '
      . 'Unraid version numbers do not imply it. thunderbolt_net (IP/tbn) still works. '
      . 'Stream tab settings are kept; create runs when the module is present.';
  } elseif (!$loaded) {
    $note = 'Module available but not loaded'
      . ($kver !== '' ? ' on ' . $kver : '')
      . '. Enable USB4STREAM under Settings and Apply, or Create on the Stream tab.';
  } elseif (!$live && !$devs) {
    $note = 'Module loaded; no streams yet. Create a stream on the Stream tab (peer path + name). Receive side first.';
  } else {
    $note = 'USB4STREAM live: ' . implode(', ', $devs ?: array_column($live, 'device'));
  }
  return [
    'available' => $available,
    'loaded' => $loaded,
    'devices' => $devs,
    'configfs' => $configfs,
    'configfs_root' => $configfs_root,
    'kernel' => $kver,
    'note' => $note,
    'live' => $live,
    'saved' => $saved,
    'services' => tbn_stream_sysfs_services(),
  ];
}

function tbn_stream_copy_file_ok($path, $for_write) {
  $path = (string)$path;
  if ($path === '' || strpos($path, "\0") !== false) {
    return ['ok' => false, 'error' => 'empty path'];
  }
  if ($path[0] !== '/') {
    return ['ok' => false, 'error' => 'path must be absolute'];
  }
  $allow = ['/mnt/', '/tmp/'];
  $ok_prefix = false;
  foreach ($allow as $pfx) {
    if (strpos($path, $pfx) === 0) {
      $ok_prefix = true;
      break;
    }
  }
  if (!$ok_prefix) {
    return ['ok' => false, 'error' => 'file path must be under /mnt or /tmp'];
  }
  if ($for_write) {
    $dir = dirname($path);
    $realDir = realpath($dir);
    if ($realDir === false) {
      return ['ok' => false, 'error' => 'destination directory does not exist'];
    }
    $ok_dir = false;
    foreach ($allow as $pfx) {
      if (strpos($realDir . '/', $pfx) === 0 || $realDir . '/' === $pfx) {
        $ok_dir = true;
        break;
      }
    }
    if (!$ok_dir) {
      return ['ok' => false, 'error' => 'destination directory not under /mnt or /tmp'];
    }
    return ['ok' => true, 'path' => $realDir . '/' . basename($path)];
  }
  $real = realpath($path);
  if ($real === false || !is_file($real) || !is_readable($real)) {
    return ['ok' => false, 'error' => 'source file not found'];
  }
  return ['ok' => true, 'path' => $real];
}

function tbn_stream_copy_raw_ok($path) {
  $path = (string)$path;
  if (!preg_match('#^/dev/(nvme[0-9]+n[0-9]+(p[0-9]+)?|sd[a-z]+[0-9]*|vd[a-z]+[0-9]*|md[0-9]+p?[0-9]*|nvd[0-9]+p?[0-9]*)$#', $path)) {
    return ['ok' => false, 'error' => 'raw device not allowed'];
  }
  if (!file_exists($path)) {
    return ['ok' => false, 'error' => 'raw device not found'];
  }
  return ['ok' => true, 'path' => $path];
}

/**
 * dd between /dev/tbstreamN and a file or (opt-in) raw block device.
 *
 * @return array{ok:bool,error?:string,cmd?:string}
 */
function tbn_stream_copy_cmd($direction, $dev, $file, $raw = false, $overwrite = false) {
  $direction = strtolower(trim((string)$direction));
  if ($direction !== 'send' && $direction !== 'receive') {
    return ['ok' => false, 'error' => 'direction must be send or receive'];
  }
  $dev = (string)$dev;
  if (strpos($dev, '/dev/') !== 0) {
    $dev = '/dev/' . ltrim($dev, '/');
  }
  if (!tbn_stream_dev_ok($dev)) {
    return ['ok' => false, 'error' => 'device must be /dev/tbstreamN'];
  }
  if (!file_exists($dev)) {
    return ['ok' => false, 'error' => $dev . ' is not present — create the stream first'];
  }
  if ($raw) {
    $chk = tbn_stream_copy_raw_ok($file);
  } else {
    $chk = tbn_stream_copy_file_ok($file, $direction === 'receive');
  }
  if (empty($chk['ok'])) {
    return $chk;
  }
  $file = $chk['path'];
  if ($direction === 'receive' && !$raw && is_file($file) && !$overwrite) {
    return ['ok' => false, 'error' => 'destination exists (set overwrite)'];
  }
  if ($direction === 'send') {
    $if = $file;
    $of = $dev;
  } else {
    $if = $dev;
    $of = $file;
  }
  $cmd = 'dd if=' . escapeshellarg($if) . ' of=' . escapeshellarg($of) . ' bs=256k status=progress';
  return ['ok' => true, 'cmd' => $cmd, 'if' => $if, 'of' => $of];
}
