<?php
/**
 * ThunderboltNet — shared helpers (sysfs discovery, network-extra, module load).
 * Develop in ~/projects/ThunderboltNet; ship via GitHub + plugin install only.
 */

if (!function_exists('tbn_plugin_name')) {
  function tbn_plugin_name() {
    return 'ThunderboltNet';
  }
}

function tbn_cfg_dir() {
  return '/boot/config/plugins/ThunderboltNet';
}

function tbn_cfg_path() {
  return tbn_cfg_dir() . '/ThunderboltNet.cfg';
}

function tbn_network_extra_path() {
  return '/boot/config/network-extra.cfg';
}

/**
 * Read plugin cfg with defaults.
 */
function tbn_load_cfg() {
  $defaults = [
    'load_modules' => 'yes',
    'e2e_flow_control' => 'no',
    'include_listening' => 'no',
    'manage_ip' => 'no',
    'ip_addr' => '10.255.1.2',
    'ip_cidr' => '24',
    'ip_gateway' => '',
    'never_default' => 'yes',
    'iface_primary' => 'thunderbolt0',
    'iface_secondary' => 'thunderbolt1',
    'bond_enable' => 'no',
    'bond_name' => 'bond-tb',
    'bond_mode' => 'balance-rr',
  ];
  $cfg = function_exists('parse_plugin_cfg')
    ? parse_plugin_cfg(tbn_plugin_name())
    : [];
  if (!is_array($cfg)) {
    $cfg = [];
  }
  return array_merge($defaults, $cfg);
}

/**
 * Sysfs: list thunderbolt bus devices (hosts, xdomain peers, services).
 */
function tbn_list_tb_devices() {
  $root = '/sys/bus/thunderbolt/devices';
  $out = [];
  if (!is_dir($root)) {
    return $out;
  }
  $names = @scandir($root);
  if (!$names) {
    return $out;
  }
  foreach ($names as $name) {
    if ($name === '.' || $name === '..') {
      continue;
    }
    $path = $root . '/' . $name;
    if (!is_dir($path) && !is_link($path)) {
      continue;
    }
    $real = realpath($path) ?: $path;
    $entry = [
      'id' => $name,
      'path' => $real,
      'device_name' => tbn_sysfs_str($real . '/device_name'),
      'vendor_name' => tbn_sysfs_str($real . '/vendor_name'),
      'unique_id' => tbn_sysfs_str($real . '/unique_id'),
      'authorized' => tbn_sysfs_str($real . '/authorized'),
      'rx_speed' => tbn_sysfs_str($real . '/rx_speed'),
      'tx_speed' => tbn_sysfs_str($real . '/tx_speed'),
      'rx_lanes' => tbn_sysfs_str($real . '/rx_lanes'),
      'tx_lanes' => tbn_sysfs_str($real . '/tx_lanes'),
      'generation' => tbn_sysfs_str($real . '/generation'),
      'security' => tbn_sysfs_str($real . '/security'),
      'netdevs' => [],
    ];
    $netdir = $real . '/net';
    if (is_dir($netdir)) {
      foreach (@scandir($netdir) ?: [] as $n) {
        if ($n === '.' || $n === '..') {
          continue;
        }
        $entry['netdevs'][] = $n;
      }
    }
    // service under xdomain often holds net/
    if (is_dir($real)) {
      foreach (@scandir($real) ?: [] as $child) {
        if ($child === '.' || $child === '..' || $child === 'net') {
          continue;
        }
        $cpath = $real . '/' . $child . '/net';
        if (is_dir($cpath)) {
          foreach (@scandir($cpath) ?: [] as $n) {
            if ($n === '.' || $n === '..') {
              continue;
            }
            if (!in_array($n, $entry['netdevs'], true)) {
              $entry['netdevs'][] = $n;
            }
          }
        }
      }
    }
    $out[] = $entry;
  }
  return $out;
}

function tbn_sysfs_str($path) {
  if (!is_readable($path)) {
    return '';
  }
  $s = @file_get_contents($path);
  return $s === false ? '' : trim($s);
}

/**
 * Kernel netdevs matching thunderbolt* (and optional bond-tb).
 */
function tbn_list_netdevs() {
  $out = [];
  $net = '/sys/class/net';
  if (!is_dir($net)) {
    return $out;
  }
  foreach (@scandir($net) ?: [] as $if) {
    if ($if === '.' || $if === '..') {
      continue;
    }
    if (!preg_match('/^(thunderbolt\d+|bond-tb)$/', $if)) {
      continue;
    }
    $base = $net . '/' . $if;
    $out[] = [
      'iface' => $if,
      'operstate' => tbn_sysfs_str($base . '/operstate'),
      'carrier' => tbn_sysfs_str($base . '/carrier'),
      'address' => tbn_sysfs_str($base . '/address'),
      'mtu' => tbn_sysfs_str($base . '/mtu'),
      'addrs' => tbn_iface_addrs($if),
    ];
  }
  return $out;
}

function tbn_iface_addrs($if) {
  $addrs = [];
  $cmd = 'ip -4 -o addr show dev ' . escapeshellarg($if) . ' 2>/dev/null';
  $lines = [];
  @exec($cmd, $lines);
  foreach ($lines as $line) {
    if (preg_match('/inet\s+(\S+)/', $line, $m)) {
      $addrs[] = $m[1];
    }
  }
  return $addrs;
}

function tbn_domain_security() {
  return tbn_sysfs_str('/sys/bus/thunderbolt/devices/domain0/security');
}

function tbn_modules_loaded() {
  $txt = @file_get_contents('/proc/modules');
  if ($txt === false) {
    return ['thunderbolt' => false, 'thunderbolt_net' => false];
  }
  return [
    'thunderbolt' => (strpos($txt, 'thunderbolt ') !== false || preg_match('/^thunderbolt\s/m', $txt)),
    'thunderbolt_net' => (strpos($txt, 'thunderbolt_net ') !== false || preg_match('/^thunderbolt_net\s/m', $txt)),
  ];
}

/**
 * Full status blob for UI / JSON.
 */
function tbn_status() {
  $cfg = tbn_load_cfg();
  return [
    'hostname' => gethostname() ?: '',
    'time' => date('c'),
    'security' => tbn_domain_security(),
    'modules' => tbn_modules_loaded(),
    'devices' => tbn_list_tb_devices(),
    'netdevs' => tbn_list_netdevs(),
    'include_interfaces' => tbn_read_include_interfaces(),
    'cfg' => $cfg,
  ];
}

/**
 * Parse network-extra.cfg include_interfaces.
 */
function tbn_read_include_interfaces() {
  $path = tbn_network_extra_path();
  if (!is_readable($path)) {
    return [];
  }
  $lines = file($path, FILE_IGNORE_NEW_LINES);
  if ($lines === false) {
    return [];
  }
  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || $line[0] === ';') {
      continue;
    }
    if (preg_match('/^include_interfaces="([^"]*)"/', $line, $m)) {
      $parts = preg_split('/\s+/', trim($m[1]));
      return array_values(array_filter($parts, function ($p) {
        return $p !== '';
      }));
    }
  }
  return [];
}

/**
 * Write include_interfaces list (preserves exclude_interfaces if present).
 * Does not create thunderbolt devices — only Unraid listening list.
 */
function tbn_write_include_interfaces(array $ifaces) {
  $path = tbn_network_extra_path();
  $ifaces = array_values(array_unique(array_filter(array_map('trim', $ifaces))));
  $exclude = '';
  if (is_readable($path)) {
    $raw = file_get_contents($path);
    if (preg_match('/^exclude_interfaces="([^"]*)"/m', $raw, $m)) {
      $exclude = $m[1];
    }
  }
  $dir = dirname($path);
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  $body = 'include_interfaces="' . implode(' ', $ifaces) . "\"\n";
  $body .= 'exclude_interfaces="' . $exclude . "\"\n";
  return @file_put_contents($path, $body) !== false;
}

/**
 * Ensure/remove TB ifaces from include list based on cfg + live netdevs.
 */
function tbn_apply_include_listening($enable) {
  $cfg = tbn_load_cfg();
  $want = [];
  foreach ([$cfg['iface_primary'], $cfg['iface_secondary'], $cfg['bond_name']] as $if) {
    if ($if !== '') {
      $want[] = $if;
    }
  }
  $current = tbn_read_include_interfaces();
  if ($enable) {
    // Only include interfaces that exist now
    $live = [];
    foreach (@scandir('/sys/class/net') ?: [] as $n) {
      if ($n === '.' || $n === '..') {
        continue;
      }
      $live[$n] = true;
    }
    foreach ($want as $if) {
      if (!empty($live[$if]) && !in_array($if, $current, true)) {
        $current[] = $if;
      }
    }
  } else {
    $current = array_values(array_filter($current, function ($if) use ($want) {
      return !in_array($if, $want, true);
    }));
  }
  return tbn_write_include_interfaces($current);
}

/**
 * Load kernel modules (no NHI unbind — that can wedge Maple Ridge).
 */
function tbn_load_modules() {
  $cfg = tbn_load_cfg();
  $e2e = ($cfg['e2e_flow_control'] === 'yes') ? '1' : '0';
  @exec('modprobe thunderbolt 2>/dev/null');
  @exec('modprobe thunderbolt_net e2e=' . escapeshellarg($e2e) . ' 2>/dev/null');
  // persist hint for next boot (best-effort)
  @mkdir('/etc/modprobe.d', 0755, true);
  @file_put_contents(
    '/etc/modprobe.d/thunderbolt_net.conf',
    "options thunderbolt_net e2e={$e2e}\n"
  );
  return tbn_modules_loaded();
}

/**
 * Apply static IPv4 to primary iface (CLI; not full Unraid network.cfg rewrite).
 * Safe: never sets default route if never_default=yes.
 */
function tbn_apply_static_ip() {
  $cfg = tbn_load_cfg();
  if ($cfg['manage_ip'] !== 'yes') {
    return ['ok' => false, 'error' => 'manage_ip is no'];
  }
  $if = $cfg['iface_primary'];
  if ($if === '' || !is_dir('/sys/class/net/' . $if)) {
    return ['ok' => false, 'error' => "interface {$if} not present"];
  }
  $ip = $cfg['ip_addr'];
  $cidr = preg_replace('/\D/', '', $cfg['ip_cidr']);
  if ($cidr === '') {
    $cidr = '24';
  }
  if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    return ['ok' => false, 'error' => 'invalid ip_addr'];
  }
  $target = escapeshellarg($ip . '/' . $cidr);
  $ife = escapeshellarg($if);
  @exec("ip link set {$ife} up 2>/dev/null");
  @exec("ip addr flush dev {$ife} 2>/dev/null");
  @exec("ip addr add {$target} dev {$ife} 2>/dev/null", $o, $rc);
  if ($cfg['ip_gateway'] !== '' && $cfg['never_default'] !== 'yes') {
    $gw = escapeshellarg($cfg['ip_gateway']);
    @exec("ip route replace default via {$gw} dev {$ife} 2>/dev/null");
  }
  return [
    'ok' => $rc === 0,
    'iface' => $if,
    'addr' => $ip . '/' . $cidr,
    'netdevs' => tbn_list_netdevs(),
  ];
}
