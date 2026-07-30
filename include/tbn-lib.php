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
    // Space-separated warning keys the user chose to hide globally (e.g. vfio:0000:11:00.0)
    'ignore_warnings' => '',
  ];
  $cfg = [];
  if (function_exists('parse_plugin_cfg')) {
    $parsed = parse_plugin_cfg(tbn_plugin_name());
    if (is_array($parsed)) {
      $cfg = $parsed;
    }
  } elseif (is_readable(tbn_cfg_path())) {
    // CLI / no Helpers: parse .cfg key="value" lines ourselves
    foreach (file(tbn_cfg_path(), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
      $line = trim($line);
      if ($line === '' || $line[0] === ';' || $line[0] === '#') {
        continue;
      }
      if (preg_match('/^([A-Za-z0-9_]+)="([^"]*)"/', $line, $m)) {
        $cfg[$m[1]] = $m[2];
      } elseif (preg_match('/^([A-Za-z0-9_]+)=(.*)$/', $line, $m)) {
        $cfg[$m[1]] = trim($m[2], " \t\"'");
      }
    }
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
    if (!preg_match('/^(thunderbolt\d+|bond-tb|br-tb)$/', $if)) {
      continue;
    }
    $base = $net . '/' . $if;
    $master = tbn_iface_master($if);
    $out[] = [
      'iface' => $if,
      'operstate' => tbn_sysfs_str($base . '/operstate'),
      'carrier' => tbn_sysfs_str($base . '/carrier'),
      'address' => tbn_sysfs_str($base . '/address'),
      'mtu' => tbn_sysfs_str($base . '/mtu'),
      'addrs' => tbn_iface_addrs($if),
      'master' => $master,
      'masters' => tbn_iface_membership_labels($if, $master),
    ];
  }
  return $out;
}

/** Bond/bridge master name if enslaved, else ''. */
function tbn_iface_master($if) {
  $m = '/sys/class/net/' . $if . '/master';
  if (!is_link($m) && !is_dir($m)) {
    return '';
  }
  $real = @realpath($m);
  return $real ? basename($real) : '';
}

/** Human labels for bond/bridge membership. */
function tbn_iface_membership_labels($if, $master = null) {
  if ($master === null) {
    $master = tbn_iface_master($if);
  }
  $labels = [];
  if ($master !== '') {
    $type = 'upper';
    if (is_dir('/sys/class/net/' . $master . '/bonding')) {
      $type = 'bond';
    } elseif (is_dir('/sys/class/net/' . $master . '/bridge')) {
      $type = 'bridge';
    }
    $labels[] = $type . ':' . $master;
  }
  // Also list if this iface IS a bond/bridge that has members
  if (is_dir('/sys/class/net/' . $if . '/bonding')) {
    $members = [];
    foreach (@scandir('/sys/class/net/' . $if . '/lower_*') ?: [] as $_) {
      // lower_* are not via scandir of bonding; use glob
    }
    foreach (glob('/sys/class/net/' . $if . '/lower_*') ?: [] as $low) {
      $members[] = basename(@realpath($low) ?: $low);
    }
    if ($members) {
      $labels[] = 'bond-members:' . implode(',', $members);
    }
  }
  return $labels;
}

/**
 * Per-link summary: local side + far (peer) side from Thunderbolt fabric sysfs.
 * Peer hostname comes from TB topology (device_name), not LLDP.
 */
function tbn_link_summaries() {
  $include = tbn_read_include_interfaces();
  $include_map = array_fill_keys($include, true);
  $out = [];

  foreach (tbn_list_tb_iface_names() as $if) {
    $base = '/sys/class/net/' . $if;
    $master = tbn_iface_master($if);
    $parentdev = '';
    $lines = [];
    @exec('ip -d link show dev ' . escapeshellarg($if) . ' 2>/dev/null', $lines);
    foreach ($lines as $line) {
      if (preg_match('/parentdev\s+(\S+)/', $line, $m)) {
        $parentdev = $m[1];
        break;
      }
    }
    // Resolve service node and peer host from sysfs
    $svc_path = '';
    $peer_path = '';
    if ($parentdev !== '') {
      // parentdev is like 0-1.0 under domain
      $cand = '/sys/bus/thunderbolt/devices/' . $parentdev;
      if (is_dir($cand) || is_link($cand)) {
        $svc_path = @realpath($cand) ?: $cand;
        $peer_path = dirname($svc_path);
      }
    }
    if ($svc_path === '') {
      $devlink = @realpath($base . '/device');
      if ($devlink) {
        $svc_path = $devlink;
        $peer_path = dirname($devlink);
      }
    }

    $local_host = tbn_sysfs_str('/sys/bus/thunderbolt/devices/0-0/device_name');
    if ($local_host === '') {
      $local_host = gethostname() ?: '';
    }

    $entry = [
      'iface' => $if,
      'label' => tbn_label_for_iface($if),
      'local' => [
        'hostname' => gethostname() ?: '',
        'controller_name' => $local_host,
        'mac' => tbn_sysfs_str($base . '/address'),
        'operstate' => tbn_sysfs_str($base . '/operstate'),
        'carrier' => tbn_sysfs_str($base . '/carrier'),
        'mtu' => tbn_sysfs_str($base . '/mtu'),
        'addrs' => tbn_iface_addrs($if),
        'master' => $master,
        'membership' => tbn_iface_membership_labels($if, $master),
        'listening' => !empty($include_map[$if]) || ($master !== '' && !empty($include_map[$master])),
      ],
      'remote' => [
        'hostname' => '',
        'vendor' => '',
        'unique_id' => '',
        'rx_speed' => '',
        'tx_speed' => '',
        'rx_lanes' => '',
        'tx_lanes' => '',
        'service' => $parentdev,
      ],
      'lldp' => tbn_lldp_neighbor($if),
    ];

    if ($peer_path && is_dir($peer_path)) {
      $entry['remote']['hostname'] = tbn_sysfs_str($peer_path . '/device_name');
      $entry['remote']['vendor'] = tbn_sysfs_str($peer_path . '/vendor_name');
      $entry['remote']['unique_id'] = tbn_sysfs_str($peer_path . '/unique_id');
      $entry['remote']['rx_speed'] = tbn_sysfs_str($peer_path . '/rx_speed');
      $entry['remote']['tx_speed'] = tbn_sysfs_str($peer_path . '/tx_speed');
      $entry['remote']['rx_lanes'] = tbn_sysfs_str($peer_path . '/rx_lanes');
      $entry['remote']['tx_lanes'] = tbn_sysfs_str($peer_path . '/tx_lanes');
    }

    $out[] = $entry;
  }
  return $out;
}

/**
 * Optional LLDP neighbor if lldpcli/lldpctl is installed (usually not on Unraid).
 * Thunderbolt fabric already exposes peer name/speed via sysfs; LLDP is extra.
 */
function tbn_lldp_neighbor($if) {
  $out = ['available' => false, 'summary' => '', 'raw' => ''];
  $bins = ['/usr/sbin/lldpctl', '/usr/bin/lldpctl', '/usr/sbin/lldpcli', '/usr/bin/lldpcli'];
  $bin = '';
  foreach ($bins as $b) {
    if (is_executable($b)) {
      $bin = $b;
      break;
    }
  }
  if ($bin === '') {
    $out['summary'] = 'LLDP tools not installed';
    return $out;
  }
  $out['available'] = true;
  $lines = [];
  if (strpos($bin, 'lldpcli') !== false) {
    @exec(escapeshellcmd($bin) . ' show neighbors ports ' . escapeshellarg($if) . ' 2>/dev/null', $lines);
  } else {
    @exec(escapeshellcmd($bin) . ' ' . escapeshellarg($if) . ' 2>/dev/null', $lines);
  }
  $text = trim(implode("\n", $lines));
  $out['raw'] = $text;
  if ($text === '' || stripos($text, 'unknown') !== false && strlen($text) < 40) {
    $out['summary'] = 'No LLDP neighbor on this port';
  } else {
    // Keep short
    $out['summary'] = preg_replace('/\s+/', ' ', substr($text, 0, 200));
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
 * Probe for Thunderbolt / USB4 host controller hardware (not peer cable).
 * Returns keys: has_hardware, sysfs_bus, domain0, pci_lines, modules, reason.
 */
function tbn_hardware_probe() {
  $sysfs = is_dir('/sys/bus/thunderbolt/devices');
  $domain0 = is_dir('/sys/bus/thunderbolt/devices/domain0')
    || is_link('/sys/bus/thunderbolt/devices/domain0');
  $pci = [];
  $lines = [];
  @exec('lspci -nn 2>/dev/null', $lines);
  foreach ($lines as $line) {
    if (preg_match('/thunderbolt|USB4|Maple Ridge|NHI|JHL|Alpine Ridge|Titan Ridge|Ice Lake.*Thunderbolt/i', $line)) {
      $pci[] = $line;
    }
  }
  // Also match known Intel TB NHI device IDs if description is sparse
  if (!$pci) {
    foreach ($lines as $line) {
      if (preg_match('/\[8086:(1137|15eb|15ec|15ef|15f0|9a1b|9a1d|a0b5|a71e)\]/i', $line)) {
        $pci[] = $line;
      }
    }
  }
  $mods = tbn_modules_loaded();
  $has = $domain0 || count($pci) > 0;
  $reason = '';
  if (!$has) {
    if (!$sysfs && !$mods['thunderbolt']) {
      $reason = 'no_controller';
    } elseif (!$sysfs) {
      $reason = 'no_sysfs';
    } else {
      $reason = 'no_domain';
    }
  }
  return [
    'has_hardware' => $has,
    'sysfs_bus' => $sysfs,
    'domain0' => $domain0,
    'pci_lines' => $pci,
    'modules' => $mods,
    'reason' => $reason,
  ];
}

/**
 * Plain-text diagnostics for GitHub issues / support (no secrets).
 */
function tbn_diagnostics_text() {
  $probe = tbn_hardware_probe();
  $ver = 'unknown';
  foreach (['/tmp/plugins/thunderboltnet.plg', '/boot/config/plugins/thunderboltnet.plg'] as $plg) {
    if (is_file($plg) && preg_match('/ENTITY version "([^"]+)"/', (string)@file_get_contents($plg), $m)) {
      $ver = $m[1];
      break;
    }
  }
  $unraid = '';
  if (is_readable('/etc/unraid-version')) {
    $ini = @parse_ini_file('/etc/unraid-version');
    $unraid = is_array($ini) && isset($ini['version']) ? $ini['version'] : '';
  }
  $out = [];
  $out[] = '=== ThunderboltNet diagnostics ===';
  $out[] = 'plugin_version: ' . $ver;
  $out[] = 'hostname: ' . (gethostname() ?: '');
  $out[] = 'unraid: ' . $unraid;
  $out[] = 'time: ' . date('c');
  $out[] = 'has_hardware: ' . ($probe['has_hardware'] ? 'yes' : 'no');
  $out[] = 'sysfs_bus: ' . ($probe['sysfs_bus'] ? 'yes' : 'no');
  $out[] = 'domain0: ' . ($probe['domain0'] ? 'yes' : 'no');
  $out[] = 'module_thunderbolt: ' . (!empty($probe['modules']['thunderbolt']) ? 'loaded' : 'not loaded');
  $out[] = 'module_thunderbolt_net: ' . (!empty($probe['modules']['thunderbolt_net']) ? 'loaded' : 'not loaded');
  $out[] = 'domain_security: ' . (tbn_domain_security() ?: '(none)');
  $out[] = '--- lspci (thunderbolt/USB4 matches) ---';
  if ($probe['pci_lines']) {
    foreach ($probe['pci_lines'] as $l) {
      $out[] = $l;
    }
  } else {
    $out[] = '(no matching PCI lines)';
  }
  $out[] = '--- /sys/bus/thunderbolt/devices ---';
  if ($probe['sysfs_bus']) {
    foreach (@scandir('/sys/bus/thunderbolt/devices') ?: [] as $n) {
      if ($n === '.' || $n === '..') {
        continue;
      }
      $out[] = $n;
    }
  } else {
    $out[] = '(bus not present)';
  }
  $out[] = '--- netdevs ---';
  $nets = tbn_list_netdevs();
  if ($nets) {
    foreach ($nets as $n) {
      $out[] = $n['iface'] . ' state=' . $n['operstate'] . ' carrier=' . $n['carrier']
        . ' addrs=' . ($n['addrs'] ? implode(',', $n['addrs']) : '-');
    }
  } else {
    $out[] = '(none)';
  }
  $out[] = '=== end ===';
  return implode("\n", $out) . "\n";
}

/**
 * Full status blob for UI / JSON.
 */
function tbn_status() {
  $cfg = tbn_load_cfg();
  $probe = tbn_hardware_probe();
  return [
    'hostname' => gethostname() ?: '',
    'time' => date('c'),
    'security' => tbn_domain_security(),
    'modules' => tbn_modules_loaded(),
    'hardware' => $probe,
    'has_hardware' => !empty($probe['has_hardware']),
    'local_controller' => tbn_sysfs_str('/sys/bus/thunderbolt/devices/0-0/device_name'),
    'devices' => tbn_list_tb_devices(),
    'netdevs' => tbn_list_netdevs(),
    'links' => tbn_link_summaries(),
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
 * Bring configured TB ifaces administratively up (helps carrier / ping).
 */
function tbn_bring_up_ifaces() {
  $cfg = tbn_load_cfg();
  $up = [];
  foreach ([$cfg['iface_primary'], $cfg['iface_secondary']] as $if) {
    if ($if === '' || !is_dir('/sys/class/net/' . $if)) {
      continue;
    }
    $ife = escapeshellarg($if);
    @exec("ip link set {$ife} up 2>/dev/null");
    $up[] = $if;
  }
  return $up;
}

/**
 * Apply static IPv4 to primary iface (CLI; not full Unraid network.cfg rewrite).
 * Global legacy static IP path. Default route only if explicitly enabled.
 * thunderbolt* is hotplug — not network.cfg eth blocks.
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
  // Prefer replace so re-Apply is idempotent
  @exec("ip addr replace {$target} dev {$ife} 2>/dev/null", $o, $rc);
  if ($rc !== 0) {
    @exec("ip addr flush dev {$ife} 2>/dev/null");
    @exec("ip addr add {$target} dev {$ife} 2>/dev/null", $o, $rc);
  }
  // Legacy global: never_default=yes means do not install default (same as DEFAULT_ROUTE=no)
  $allow_default = (($cfg['default_route'] ?? '') === 'yes')
    || (($cfg['never_default'] ?? 'yes') === 'no');
  if ($cfg['ip_gateway'] !== '' && $allow_default) {
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

/* ------------------------------------------------------------------ */
/*  Multi-iface tabs (tbn0 → thunderbolt0), IOMMU, page sync           */
/* ------------------------------------------------------------------ */

function tbn_plugin_root() {
  return '/usr/local/emhttp/plugins/ThunderboltNet';
}

function tbn_require_lib() {
  // Absolute path only — Unraid evaluates .page files with a cwd where __DIR__ is wrong.
  require_once tbn_plugin_root() . '/include/tbn-lib.php';
}

/** Kernel names: thunderbolt0, thunderbolt1, ... sorted by number. */
function tbn_list_tb_iface_names() {
  $names = [];
  foreach (@scandir('/sys/class/net') ?: [] as $if) {
    if (preg_match('/^thunderbolt(\d+)$/', $if, $m)) {
      $names[(int)$m[1]] = $if;
    }
  }
  ksort($names, SORT_NUMERIC);
  return array_values($names);
}

/** Display label tbn0 for thunderbolt0. */
function tbn_label_for_iface($if) {
  if (preg_match('/^thunderbolt(\d+)$/', $if, $m)) {
    return 'tbn' . $m[1];
  }
  return $if;
}

function tbn_iface_cfg_dir() {
  return tbn_cfg_dir() . '/ifaces';
}

function tbn_iface_cfg_path($if) {
  $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $if);
  return tbn_iface_cfg_dir() . '/' . $safe . '.cfg';
}

function tbn_iface_defaults($if = 'thunderbolt0') {
  $n = 0;
  if (preg_match('/(\d+)$/', $if, $m)) {
    $n = (int)$m[1];
  }
  // .2 on first Unraid TB iface is conventional; peer often .1
  $last = 2 + $n;
  return [
    'DESCRIPTION' => '',
    'ENABLE' => 'yes',
    'BONDING' => 'no',
    'BONDING_MODE' => 'balance-rr',
    'BOND_NAME' => 'bond-tb',
    'BRIDGING' => 'no',
    'BR_NAME' => 'br-tb',
    'PROTOCOL' => 'ipv4',
    'USE_DHCP' => 'no',
    'IPADDR' => '10.255.1.' . $last,
    'NETMASK' => '24',
    'GATEWAY' => '',
    // Install a system default route via this iface? Default no (peer-local only).
    'DEFAULT_ROUTE' => 'no',
    'MTU' => '',
    'USE_MTU' => 'no',
    'INCLUDE_LISTENING' => 'no',
  ];
}

function tbn_parse_cfg_file($path) {
  $cfg = [];
  if (!is_readable($path)) {
    return $cfg;
  }
  foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === ';' || $line[0] === '#') {
      continue;
    }
    if (preg_match('/^([A-Za-z0-9_]+)="([^"]*)"/', $line, $m)) {
      $cfg[$m[1]] = $m[2];
    } elseif (preg_match('/^([A-Za-z0-9_]+)=(.*)$/', $line, $m)) {
      $cfg[$m[1]] = trim($m[2], " \t\"'");
    }
  }
  return $cfg;
}

function tbn_load_iface_cfg($if) {
  $raw = tbn_parse_cfg_file(tbn_iface_cfg_path($if));
  // Migrate old NEVER_DEFAULT=yes|no → DEFAULT_ROUTE=no|yes (invert double-negative)
  if (!isset($raw['DEFAULT_ROUTE']) && isset($raw['NEVER_DEFAULT'])) {
    $raw['DEFAULT_ROUTE'] = (($raw['NEVER_DEFAULT'] ?? 'yes') === 'yes') ? 'no' : 'yes';
    unset($raw['NEVER_DEFAULT']);
  }
  return array_merge(tbn_iface_defaults($if), $raw);
}

function tbn_write_iface_cfg($if, array $cfg) {
  $dir = tbn_iface_cfg_dir();
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  $defaults = tbn_iface_defaults($if);
  $merged = array_merge($defaults, $cfg);
  $lines = ['; ThunderboltNet iface ' . $if, ''];
  foreach ($defaults as $k => $_) {
    $v = isset($merged[$k]) ? $merged[$k] : '';
    $lines[] = $k . '="' . str_replace(['\\', '"'], ['\\\\', '\\"'], (string)$v) . '"';
  }
  return @file_put_contents(tbn_iface_cfg_path($if), implode("\n", $lines) . "\n") !== false;
}

function tbn_reset_iface_cfg($if) {
  return tbn_write_iface_cfg($if, tbn_iface_defaults($if));
}

/**
 * PCI / IOMMU / VFIO info for Thunderbolt controllers (informational).
 */
function tbn_list_pci_iommu() {
  $out = [];
  $lines = [];
  @exec('lspci -Dnn 2>/dev/null', $lines);
  $want = [];
  foreach ($lines as $line) {
    if (preg_match('/thunderbolt|USB4|Maple Ridge|NHI|Alpine Ridge|Titan Ridge|JHL/i', $line)
      || preg_match('/\[8086:(1136|1137|1138|15eb|15ec|15ef|15f0|9a1b|9a1d|a0b5|a71e)\]/i', $line)) {
      if (preg_match('/^([0-9a-f:.]+)\s+(.+)$/i', $line, $m)) {
        $want[$m[1]] = $m[2];
      }
    }
  }
  // Also walk domain0 parent PCI
  $dom = @realpath('/sys/bus/thunderbolt/devices/domain0');
  if ($dom) {
    $p = $dom;
    for ($i = 0; $i < 12 && $p && $p !== '/'; $i++, $p = dirname($p)) {
      if (preg_match('#/([0-9a-f]{4}:[0-9a-f]{2}:[0-9a-f]{2}\.[0-9a-f])$#i', $p, $m)) {
        $bdf = $m[1];
        if (!isset($want[$bdf])) {
          $want[$bdf] = '';
        }
      }
    }
  }
  $vfio_bound = [];
  if (is_readable('/boot/config/vfio-pci.cfg')) {
    $raw = (string)@file_get_contents('/boot/config/vfio-pci.cfg');
    if (preg_match_all('/[0-9a-f]{4}:[0-9a-f]{2}:[0-9a-f]{2}\.[0-9a-f]/i', $raw, $mm)) {
      foreach ($mm[0] as $b) {
        $vfio_bound[strtolower($b)] = true;
      }
    }
  }
  foreach ($want as $bdf => $desc) {
    $sys = '/sys/bus/pci/devices/' . $bdf;
    if (!is_dir($sys) && is_dir('/sys/bus/pci/devices/0000:' . preg_replace('/^0000:/', '', $bdf))) {
      $bdf = '0000:' . preg_replace('/^0000:/', '', $bdf);
      $sys = '/sys/bus/pci/devices/' . $bdf;
    }
    $driver = '';
    if (is_link($sys . '/driver')) {
      $driver = basename((string)@readlink($sys . '/driver'));
    }
    $group = '';
    if (is_link($sys . '/iommu_group')) {
      $group = basename((string)@readlink($sys . '/iommu_group'));
    }
    $in_vfio_cfg = !empty($vfio_bound[strtolower($bdf)]);
    $vfio_active = ($driver === 'vfio-pci') || $in_vfio_cfg;
    if ($desc === '' && is_readable($sys . '/vendor')) {
      $desc = tbn_sysfs_str($sys . '/vendor') . ':' . tbn_sysfs_str($sys . '/device');
    }
    $out[] = [
      'bdf' => $bdf,
      'description' => $desc,
      'driver' => $driver ?: '(none)',
      'iommu_group' => $group !== '' ? $group : 'n/a',
      'vfio' => $vfio_active ? 'yes' : 'no',
      'vfio_boot_cfg' => $in_vfio_cfg ? 'yes' : 'no',
    ];
  }
  usort($out, function ($a, $b) {
    return strcmp($a['bdf'], $b['bdf']);
  });
  return $out;
}

/**
 * Generate Network Settings tabs: Thunderbolt tbn0, tbn1, ... after wlan (:1000).
 * Overview stays at :1100; ifaces start at :1110.
 */
function tbn_sync_iface_pages() {
  $root = tbn_plugin_root();
  if (!is_dir($root)) {
    return;
  }
  $live = tbn_list_tb_iface_names();
  $keep = [];
  foreach ($live as $if) {
    if (!preg_match('/^thunderbolt(\d+)$/', $if, $m)) {
      continue;
    }
    $n = (int)$m[1];
    $keep[$n] = true;
    $menu = 1110 + $n;
    $label = 'tbn' . $n;
    $page = $root . '/Tbn' . $n . '.page';
    // Markdown=false: Unraid runs page text through Markdown by default, which breaks <?php.
    $body = <<<PAGE
Menu="NetworkSettings:{$menu}"
Title="Thunderbolt {$label}"
Tag="sitemap"
Markdown="false"
Cond="file_exists('/sys/class/net/{$if}')"
---
<?php
\$tbn_if = '{$if}';
\$tbn_label = '{$label}';
require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-iface-page.php';

PAGE;
    @file_put_contents($page, $body);
  }
  foreach (glob($root . '/Tbn*.page') ?: [] as $f) {
    if (preg_match('/Tbn(\d+)\.page$/', $f, $m) && empty($keep[(int)$m[1]])) {
      @unlink($f);
    }
  }
}

/**
 * Apply one iface cfg to the live system (not Unraid network.cfg).
 */
function tbn_apply_iface($if) {
  $cfg = tbn_load_iface_cfg($if);
  if (!is_dir('/sys/class/net/' . $if)) {
    return ['ok' => false, 'error' => "interface {$if} not present"];
  }
  $ife = escapeshellarg($if);
  if (($cfg['ENABLE'] ?? 'yes') === 'yes') {
    @exec("ip link set {$ife} up 2>/dev/null");
  } else {
    @exec("ip link set {$ife} down 2>/dev/null");
    return ['ok' => true, 'iface' => $if, 'enabled' => false];
  }
  if (($cfg['USE_MTU'] ?? 'no') === 'yes' && ($cfg['MTU'] ?? '') !== '') {
    $mtu = (int)$cfg['MTU'];
    if ($mtu >= 68 && $mtu <= 9198) {
      @exec("ip link set {$ife} mtu {$mtu} 2>/dev/null");
    }
  }
  $proto = $cfg['PROTOCOL'] ?? 'ipv4';
  if ($proto === 'ipv4' || $proto === 'ipv4+ipv6') {
    if (($cfg['USE_DHCP'] ?? 'no') === 'yes') {
      // Best-effort DHCP; many Unraid systems lack a long-lived dhclient for TB
      @exec("dhcpcd -n {$ife} 2>/dev/null || dhclient -1 {$ife} 2>/dev/null || true");
    } else {
      $ip = $cfg['IPADDR'] ?? '';
      $cidr = preg_replace('/\D/', '', $cfg['NETMASK'] ?? '24');
      if ($cidr === '') {
        $cidr = '24';
      }
      // Accept classic netmask in NETMASK field
      if (strpos($cfg['NETMASK'] ?? '', '.') !== false) {
        $cidr = (string)tbn_mask_to_prefix($cfg['NETMASK']);
      }
      if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $target = escapeshellarg($ip . '/' . $cidr);
        @exec("ip addr replace {$target} dev {$ife} 2>/dev/null", $o, $rc);
        if ($rc !== 0) {
          @exec("ip addr flush dev {$ife} 2>/dev/null");
          @exec("ip addr add {$target} dev {$ife} 2>/dev/null");
        }
        // DEFAULT_ROUTE=yes + gateway → install system default via this iface (uncommon for TB)
        if (($cfg['GATEWAY'] ?? '') !== '' && ($cfg['DEFAULT_ROUTE'] ?? 'no') === 'yes') {
          $gw = escapeshellarg($cfg['GATEWAY']);
          @exec("ip route replace default via {$gw} dev {$ife} 2>/dev/null");
        }
      }
    }
  }
  // Listening include for this iface only
  $current = tbn_read_include_interfaces();
  if (($cfg['INCLUDE_LISTENING'] ?? 'no') === 'yes') {
    if (!in_array($if, $current, true)) {
      $current[] = $if;
      tbn_write_include_interfaces($current);
    }
  } else {
    $current = array_values(array_filter($current, function ($x) use ($if) {
      return $x !== $if;
    }));
    tbn_write_include_interfaces($current);
  }
  // Bonding (simplified TB-only bond)
  if (($cfg['BONDING'] ?? 'no') === 'yes') {
    tbn_apply_simple_bond($cfg, $if);
  }
  return ['ok' => true, 'iface' => $if, 'cfg' => $cfg, 'netdevs' => tbn_list_netdevs()];
}

function tbn_mask_to_prefix($mask) {
  $long = ip2long($mask);
  if ($long === false) {
    return 24;
  }
  return substr_count(decbin($long), '1');
}

/**
 * Simple balance-rr (etc.) bond for TB members only — not full Unraid bond0.
 */
function tbn_apply_simple_bond(array $cfg, $primary_if) {
  $bond = $cfg['BOND_NAME'] ?? 'bond-tb';
  if ($bond === '' || !preg_match('/^[A-Za-z0-9_.-]+$/', $bond)) {
    return;
  }
  $mode = $cfg['BONDING_MODE'] ?? 'balance-rr';
  $modes = [
    'balance-rr' => 0, '0' => 0,
    'active-backup' => 1, '1' => 1,
    'balance-xor' => 2, '2' => 2,
    'broadcast' => 3, '3' => 3,
    '802.3ad' => 4, '4' => 4,
    'balance-tlb' => 5, '5' => 5,
    'balance-alb' => 6, '6' => 6,
  ];
  $mi = isset($modes[$mode]) ? $modes[$mode] : 0;
  if (!is_dir('/sys/class/net/' . $bond)) {
    @exec('ip link add ' . escapeshellarg($bond) . ' type bond mode ' . (int)$mi . ' 2>/dev/null');
  }
  $members = tbn_list_tb_iface_names();
  foreach ($members as $m) {
    @exec('ip link set ' . escapeshellarg($m) . ' down 2>/dev/null');
    @exec('ip link set ' . escapeshellarg($m) . ' master ' . escapeshellarg($bond) . ' 2>/dev/null');
  }
  @exec('ip link set ' . escapeshellarg($bond) . ' up 2>/dev/null');
}

function tbn_plugin_version() {
  foreach (['/tmp/plugins/thunderboltnet.plg', '/boot/config/plugins/thunderboltnet.plg'] as $plg) {
    if (is_file($plg) && preg_match('/ENTITY version "([^"]+)"/', (string)@file_get_contents($plg), $m)) {
      return $m[1];
    }
  }
  return 'unknown';
}

/** Parsed list of globally ignored warning keys. */
function tbn_ignored_warnings(?array $cfg = null) {
  if ($cfg === null) {
    $cfg = tbn_load_cfg();
  }
  $raw = trim((string)($cfg['ignore_warnings'] ?? ''));
  if ($raw === '') {
    return [];
  }
  $parts = preg_split('/\s+/', $raw);
  return array_values(array_filter($parts, function ($p) {
    return $p !== '';
  }));
}

function tbn_is_warning_ignored($key, ?array $cfg = null) {
  return in_array($key, tbn_ignored_warnings($cfg), true);
}

/**
 * Persist ignore for one warning key in ThunderboltNet.cfg (global).
 */
function tbn_ignore_warning($key) {
  $key = trim((string)$key);
  if ($key === '' || !preg_match('/^[A-Za-z0-9:._-]+$/', $key)) {
    return false;
  }
  $cfg = tbn_load_cfg();
  $list = tbn_ignored_warnings($cfg);
  if (!in_array($key, $list, true)) {
    $list[] = $key;
  }
  $cfg['ignore_warnings'] = implode(' ', $list);
  return tbn_write_global_cfg($cfg);
}

/**
 * Write full global plugin cfg (known keys only).
 */
function tbn_write_global_cfg(array $cfg) {
  $defaults = [
    'tbn_defaults' => '',
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
    'ignore_warnings' => '',
  ];
  $merged = array_merge($defaults, $cfg);
  $dir = tbn_cfg_dir();
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  $lines = ['; ThunderboltNet global settings', ''];
  foreach ($defaults as $k => $_) {
    $v = isset($merged[$k]) ? (string)$merged[$k] : '';
    $lines[] = $k . '="' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"';
  }
  return @file_put_contents(tbn_cfg_path(), implode("\n", $lines) . "\n") !== false;
}

/**
 * Active PCI warnings (currently VFIO on TB-related devices).
 * Each: key, bdf, message, severity.
 */
function tbn_pci_warnings(array $pci, ?array $cfg = null) {
  if ($cfg === null) {
    $cfg = tbn_load_cfg();
  }
  $out = [];
  foreach ($pci as $p) {
    $bdf = $p['bdf'] ?? '';
    if ($bdf === '') {
      continue;
    }
    $vfio = ($p['vfio'] ?? 'no') === 'yes' || ($p['driver'] ?? '') === 'vfio-pci'
      || ($p['vfio_boot_cfg'] ?? 'no') === 'yes';
    if (!$vfio) {
      continue;
    }
    $key = 'vfio:' . $bdf;
    if (tbn_is_warning_ignored($key, $cfg)) {
      continue;
    }
    $drv = $p['driver'] ?? '(none)';
    $desc = $p['description'] ?? '';
    $out[] = [
      'key' => $key,
      'bdf' => $bdf,
      'severity' => 'warning',
      'message' => "PCI {$bdf} is VFIO-bound or listed in vfio-pci.cfg (driver: {$drv})"
        . ($desc !== '' ? " — {$desc}" : '')
        . '. The host may not be able to use this Thunderbolt function until it is unbound from VFIO.',
    ];
  }
  return $out;
}
