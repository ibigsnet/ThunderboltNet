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

// DHCP server helpers (dnsmasq on tbn underlays)
if (is_file(__DIR__ . '/tbn-dhcp.php')) {
  require_once __DIR__ . '/tbn-dhcp.php';
}
// Per-link NAT / share host uplink
if (is_file(__DIR__ . '/tbn-nat.php')) {
  require_once __DIR__ . '/tbn-nat.php';
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
    'tbn_defaults' => '',
    'load_modules' => 'yes',
    'e2e_flow_control' => 'no',
    // USB4STREAM (thunderbolt-stream): raw host↔host path, separate from thunderbolt_net
    // Default no until the *running* kernel ships thunderbolt_stream (mainline ~7.2+;
    // not “Unraid 7.2”). Plugin detects and enables gracefully.
    'enable_usb4stream' => 'no',
    // Default IPv4 plan for new/Reset iface tabs: small-lan | p2p | custom
    'address_plan' => 'small-lan',
    'include_listening' => 'no',
    // Legacy global IP path (tbn tabs own addressing; leave manage_ip=no)
    'manage_ip' => 'no',
    'ip_addr' => '10.255.0.2',
    'ip_cidr' => '24',
    'ip_gateway' => '',
    'never_default' => 'yes',
    'iface_primary' => 'thunderbolt0',
    'iface_secondary' => 'thunderbolt1',
    'bond_enable' => 'no',
    'bond_name' => 'bond-tb0',
    'bond_mode' => 'active-backup',
    // Space-separated warning keys the user chose to hide globally (e.g. vfio:0000:11:00.0)
    'ignore_warnings' => '',
    // OpenFabric / FRR — multi-hop only (default off = static tbn underlay)
    'openfabric_enable' => 'no',
    'openfabric_user_off' => 'no',
    'openfabric_auto_install_frr' => 'yes',
    'openfabric_ipv6' => 'yes',
    'openfabric_area' => '1',
    'openfabric_router_id' => '',
    'openfabric_net' => '',
    'openfabric_metric_reference_mbps' => '20000',
    'mesh_report' => 'yes',
    'mesh_token' => '',
    'mesh_poll_secs' => '60',
    'mesh_stale_secs' => '300',
    'mesh_holdoff_secs' => '120',
    'mesh_private_only' => 'yes',
    'mesh_eth_ifaces' => '',
    'mesh_peer_ips' => '',
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
 * Kernel netdevs matching thunderbolt* (and optional bond-tbN / br-tb).
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
    if (!preg_match('/^(thunderbolt\d+|bond-tb(?:\d+)?|br-tb(?:\d+)?)$/', $if)) {
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
      'addrs6' => tbn_iface_addrs6($if),
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

/**
 * Live Linux bridges on this host (for “join existing bridge”).
 * Prefer Unraid-style names (br0, br0.10, br1, …); still include other bridges
 * except common disposable ones (docker0, virbr*).
 *
 * @return string[]
 */
function tbn_list_system_bridges() {
  $out = [];
  foreach (glob('/sys/class/net/*/bridge') ?: [] as $p) {
    $br = basename(dirname($p));
    if ($br === '' || $br === 'docker0' || preg_match('/^virbr/', $br)) {
      continue;
    }
    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $br)) {
      continue;
    }
    $out[] = $br;
  }
  $out = array_values(array_unique($out));
  usort($out, function ($a, $b) {
    // br0, br0.10, br1… before odd names
    $sa = preg_match('/^br/', $a) ? 0 : 1;
    $sb = preg_match('/^br/', $b) ? 0 : 1;
    if ($sa !== $sb) {
      return $sa - $sb;
    }
    return strnatcasecmp($a, $b);
  });
  return $out;
}

/** True if name is an existing bridge device. */
function tbn_is_bridge_netdev($name) {
  $name = (string)$name;
  return $name !== '' && is_dir('/sys/class/net/' . $name . '/bridge');
}

/**
 * Sanitize BR_NAME from cfg — must be an existing bridge when joining.
 *
 * @return string bridge name or ''
 */
function tbn_bridge_join_target(array $cfg) {
  if (($cfg['BRIDGING'] ?? 'no') !== 'yes') {
    return '';
  }
  $br = preg_replace('/[^A-Za-z0-9_.-]/', '', (string)($cfg['BR_NAME'] ?? ''));
  if ($br === '' || !tbn_is_bridge_netdev($br)) {
    return '';
  }
  // Never invent br-tb* here — join existing Unraid (or admin-created) bridges only.
  return $br;
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
 * Peer hostname comes from Thunderbolt topology (device_name), not LLDP.
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

    // Local 0-0 is the Thunderbolt host router/controller (board product + OEM).
    // Remote device_name is usually the peer OS hostname on Linux; vendor_name is
    // often the software stack ("Linux"), not a chassis OEM like "ASUS".
    $ctrl_product = tbn_sysfs_str('/sys/bus/thunderbolt/devices/0-0/device_name');
    $ctrl_mfg = tbn_sysfs_str('/sys/bus/thunderbolt/devices/0-0/vendor_name');

    $entry = [
      'iface' => $if,
      'label' => tbn_label_for_iface($if),
      'local' => [
        'os_hostname' => gethostname() ?: '',
        'product' => $ctrl_product,
        'manufacturer' => $ctrl_mfg,
        'mac' => tbn_sysfs_str($base . '/address'),
        'operstate' => tbn_sysfs_str($base . '/operstate'),
        'carrier' => tbn_sysfs_str($base . '/carrier'),
        'mtu' => tbn_sysfs_str($base . '/mtu'),
        'addrs' => tbn_iface_addrs($if),
        'addrs6' => tbn_iface_addrs6($if),
        'master' => $master,
        'membership' => tbn_iface_membership_labels($if, $master),
        'listening' => !empty($include_map[$if]) || ($master !== '' && !empty($include_map[$master])),
      ],
      'remote' => [
        'peer_name' => '',
        'stack' => '',
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
      $entry['remote']['peer_name'] = tbn_sysfs_str($peer_path . '/device_name');
      $entry['remote']['stack'] = tbn_sysfs_str($peer_path . '/vendor_name');
      $entry['remote']['unique_id'] = tbn_sysfs_str($peer_path . '/unique_id');
      $entry['remote']['rx_speed'] = tbn_sysfs_str($peer_path . '/rx_speed');
      $entry['remote']['tx_speed'] = tbn_sysfs_str($peer_path . '/tx_speed');
      $entry['remote']['rx_lanes'] = tbn_sysfs_str($peer_path . '/rx_lanes');
      $entry['remote']['tx_lanes'] = tbn_sysfs_str($peer_path . '/tx_lanes');
    }

    // Traffic heuristic + safe-to-unplug hint (refined over future visits via sample file)
    $entry['activity'] = tbn_iface_activity($if);
    $out[] = $entry;
  }
  return $out;
}

/**
 * Sysfs counter snapshot for traffic rate estimates between page loads.
 */
function tbn_iface_stats_path($if) {
  $safe = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$if);
  return '/var/tmp/tbn-stats-' . $safe . '.json';
}

function tbn_read_iface_counters($if) {
  $base = '/sys/class/net/' . $if . '/statistics';
  $rx = @file_get_contents($base . '/rx_bytes');
  $tx = @file_get_contents($base . '/tx_bytes');
  return [
    'rx_bytes' => is_numeric(trim((string)$rx)) ? (float)trim($rx) : 0.0,
    'tx_bytes' => is_numeric(trim((string)$tx)) ? (float)trim($tx) : 0.0,
    't' => microtime(true),
  ];
}

/**
 * Best-effort activity for “safe to unplug” guidance.
 *
 * Uses a sample file with last counters + last computed result. Multiple calls in one
 * page load (or rapid polls) must not clobber the baseline with dt≈0.
 *
 * Returns: level, label, safe_unplug, note, bps
 */
function tbn_iface_activity($if) {
  $path = tbn_iface_stats_path($if);
  $store = [];
  if (is_readable($path)) {
    $j = @json_decode((string)@file_get_contents($path), true);
    if (is_array($j)) {
      $store = $j;
    }
  }

  $last_result = (isset($store['result']) && is_array($store['result'])) ? $store['result'] : null;

  if (!is_dir('/sys/class/net/' . $if)) {
    $r = [
      'level' => 'down',
      'label' => 'Not present',
      'safe_unplug' => 'yes',
      'note' => 'Interface gone — cable already removed or peer left.',
      'bps' => 0.0,
    ];
    @file_put_contents($path, json_encode(['result' => $r, 't' => microtime(true)]));
    return $r;
  }

  $oper = tbn_sysfs_str('/sys/class/net/' . $if . '/operstate');
  $carrier = tbn_sysfs_str('/sys/class/net/' . $if . '/carrier');
  if ($oper === 'down' || $carrier === '0') {
    $r = [
      'level' => 'down',
      'label' => 'Link down',
      'safe_unplug' => 'yes',
      'note' => 'No carrier — fine to unplug.',
      'bps' => 0.0,
    ];
    @file_put_contents($path, json_encode([
      'result' => $r,
      't' => microtime(true),
      'rx_bytes' => 0,
      'tx_bytes' => 0,
    ]));
    return $r;
  }

  $now = tbn_read_iface_counters($if);
  $min_dt = 2.0; // seconds between usable samples

  if (!isset($store['t'], $store['rx_bytes'], $store['tx_bytes'])) {
    // First baseline only — keep prior result if any
    $store['t'] = $now['t'];
    $store['rx_bytes'] = $now['rx_bytes'];
    $store['tx_bytes'] = $now['tx_bytes'];
    $r = [
      'level' => 'unknown',
      'label' => 'Measuring…',
      'safe_unplug' => 'unknown',
      'note' => 'Collecting traffic samples (updates automatically every few seconds).',
      'bps' => null,
    ];
    $store['result'] = $r;
    @file_put_contents($path, json_encode($store));
    return $r;
  }

  $dt = $now['t'] - (float)$store['t'];

  // Too soon: do not overwrite baseline; return last good result or measuring
  if ($dt < $min_dt) {
    if ($last_result !== null && ($last_result['level'] ?? '') !== 'unknown') {
      return $last_result;
    }
    return [
      'level' => 'unknown',
      'label' => 'Measuring…',
      'safe_unplug' => 'unknown',
      'note' => 'Collecting traffic samples (updates automatically every few seconds).',
      'bps' => null,
    ];
  }

  // Stale baseline (>10 min): re-baseline, keep last result for display if useful
  if ($dt > 600) {
    $store['t'] = $now['t'];
    $store['rx_bytes'] = $now['rx_bytes'];
    $store['tx_bytes'] = $now['tx_bytes'];
    $r = [
      'level' => 'unknown',
      'label' => 'Measuring…',
      'safe_unplug' => 'unknown',
      'note' => 'Sample baseline refreshed; next update in a few seconds.',
      'bps' => null,
    ];
    if ($last_result !== null && in_array($last_result['level'] ?? '', ['idle', 'light', 'busy'], true)) {
      // keep showing last known until next interval
      $store['result'] = $last_result;
      @file_put_contents($path, json_encode($store));
      return $last_result;
    }
    $store['result'] = $r;
    @file_put_contents($path, json_encode($store));
    return $r;
  }

  $drx = max(0.0, $now['rx_bytes'] - (float)$store['rx_bytes']);
  $dtx = max(0.0, $now['tx_bytes'] - (float)$store['tx_bytes']);
  $bps = ($drx + $dtx) / $dt;

  if ($bps < 50 * 1024) {
    $r = [
      'level' => 'idle',
      'label' => 'Idle',
      'safe_unplug' => 'yes',
      'note' => 'Little or no recent traffic — generally OK to unplug after finishing copies.',
      'bps' => $bps,
    ];
  } elseif ($bps < 2 * 1024 * 1024) {
    $r = [
      'level' => 'light',
      'label' => 'Light traffic',
      'safe_unplug' => 'unknown',
      'note' => 'Some traffic still flowing — prefer waiting until transfers finish.',
      'bps' => $bps,
    ];
  } else {
    $r = [
      'level' => 'busy',
      'label' => 'Busy',
      'safe_unplug' => 'no',
      'note' => 'Sustained traffic — do not unplug yet.',
      'bps' => $bps,
    ];
  }

  // Advance baseline to now for next interval
  $store['t'] = $now['t'];
  $store['rx_bytes'] = $now['rx_bytes'];
  $store['tx_bytes'] = $now['tx_bytes'];
  $store['result'] = $r;
  @file_put_contents($path, json_encode($store));
  return $r;
}

function tbn_format_bps($bps) {
  if ($bps === null || $bps < 0) {
    return '—';
  }
  if ($bps < 1024) {
    return round($bps) . ' B/s';
  }
  if ($bps < 1024 * 1024) {
    return round($bps / 1024, 1) . ' KiB/s';
  }
  return round($bps / (1024 * 1024), 2) . ' MiB/s';
}

/**
 * Remembered peers (plug-and-play groundwork). JSON on flash under plugin config dir.
 * Future: re-apply last IPs, show health history, notify on reconnect.
 */
function tbn_peers_memory_path() {
  return tbn_cfg_dir() . '/peers.json';
}

function tbn_load_peers_memory() {
  $path = tbn_peers_memory_path();
  if (!is_readable($path)) {
    return [];
  }
  $j = @json_decode((string)@file_get_contents($path), true);
  return is_array($j) ? $j : [];
}

function tbn_save_peers_memory(array $peers) {
  $dir = tbn_cfg_dir();
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  // Cap history
  if (count($peers) > 32) {
    uasort($peers, function ($a, $b) {
      return strcmp($b['last_seen'] ?? '', $a['last_seen'] ?? '');
    });
    $peers = array_slice($peers, 0, 32, true);
  }
  return @file_put_contents(
    tbn_peers_memory_path(),
    json_encode($peers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
  ) !== false;
}

/**
 * Upsert live links into peers.json (by fabric unique_id or iface fallback).
 */
/**
 * Stable key for peers.json (fabric UUID preferred).
 *
 * Hotplug race: unique_id can be empty for a moment after netdev recreate. Prefer an
 * existing fabric-UUID peer already bound to this iface over inventing iface:thunderboltN
 * (which left a permanent Offline “—” row next to the real peer after reconnect).
 *
 * @param array|null $existing_peers peers.json map (optional)
 */
function tbn_peer_key_from_link(array $L, ?array $existing_peers = null) {
  $uid = trim((string)($L['remote']['unique_id'] ?? ''));
  if ($uid !== '') {
    return $uid;
  }
  $if = trim((string)($L['iface'] ?? ''));
  if ($existing_peers !== null && $if !== '') {
    $hits = [];
    foreach ($existing_peers as $k => $p) {
      if (!is_string($k) || strpos($k, 'iface:') === 0) {
        continue;
      }
      if (($p['last_iface'] ?? '') === $if) {
        $hits[] = $k;
      }
    }
    if (count($hits) === 1) {
      return $hits[0];
    }
  }
  return 'iface:' . ($if !== '' ? $if : 'unknown');
}

/**
 * Drop iface:* fallback rows that duplicate a fabric-UUID peer on the same iface.
 * Merges listening Yes / empty name onto the UUID row before delete.
 */
function tbn_peers_dedupe_iface_fallbacks(array $peers) {
  foreach (array_keys($peers) as $k) {
    if (!is_string($k) || strpos($k, 'iface:') !== 0) {
      continue;
    }
    $if = trim((string)($peers[$k]['last_iface'] ?? ''));
    if ($if === '' && preg_match('/^iface:(thunderbolt\d+)$/', $k, $m)) {
      $if = $m[1];
    }
    if ($if === '') {
      continue;
    }
    $uuid_key = null;
    foreach ($peers as $k2 => $p2) {
      if (!is_string($k2) || $k2 === $k || strpos($k2, 'iface:') === 0) {
        continue;
      }
      if (($p2['last_iface'] ?? '') === $if) {
        $uuid_key = $k2;
        break;
      }
    }
    if ($uuid_key === null) {
      continue;
    }
    $ghost = $peers[$k];
    if (($peers[$uuid_key]['include_listening'] ?? 'no') !== 'yes'
        && ($ghost['include_listening'] ?? 'no') === 'yes') {
      $peers[$uuid_key]['include_listening'] = 'yes';
    }
    if (trim((string)($peers[$uuid_key]['peer_name'] ?? '')) === ''
        && trim((string)($ghost['peer_name'] ?? '')) !== '') {
      $peers[$uuid_key]['peer_name'] = $ghost['peer_name'];
    }
    if (trim((string)($peers[$uuid_key]['stack'] ?? '')) === ''
        && trim((string)($ghost['stack'] ?? '')) !== '') {
      $peers[$uuid_key]['stack'] = $ghost['stack'];
    }
    unset($peers[$k]);
  }
  return $peers;
}

/**
 * Ask Unraid to rebuild service listen sockets (best-effort).
 */
function tbn_refresh_unraid_services() {
  $script = '/usr/local/emhttp/webGui/scripts/update_services';
  if (is_executable($script)) {
    @exec(escapeshellarg($script) . ' >/dev/null 2>&1 &');
  }
}

/**
 * Set INCLUDE_LISTENING for one iface + network-extra include_interfaces.
 * $enable: 'yes'|'no'|bool
 */
function tbn_set_listening_for_iface($if, $enable) {
  if (!preg_match('/^thunderbolt\d+$/', (string)$if)) {
    return false;
  }
  $enable = ($enable === true || $enable === 'yes' || $enable === 1 || $enable === '1') ? 'yes' : 'no';
  $cfg = tbn_load_iface_cfg($if);
  $cfg['INCLUDE_LISTENING'] = $enable;
  tbn_write_iface_cfg($if, $cfg);

  $current = tbn_read_include_interfaces();
  if ($enable === 'yes') {
    if (!in_array($if, $current, true)) {
      $current[] = $if;
    }
  } else {
    $current = array_values(array_filter($current, function ($x) use ($if) {
      return $x !== $if;
    }));
  }
  tbn_write_include_interfaces($current);
  return true;
}

/**
 * Persist preferred listening for a remembered peer (and optional live iface).
 */
function tbn_set_peer_listening_pref($peer_key, $enable, $if = '') {
  $enable = ($enable === true || $enable === 'yes' || $enable === 1 || $enable === '1') ? 'yes' : 'no';
  $peer_key = trim((string)$peer_key);
  if ($peer_key === '') {
    return false;
  }
  $peers = tbn_load_peers_memory();
  if (!isset($peers[$peer_key])) {
    $peers[$peer_key] = [
      'unique_id' => (strpos($peer_key, 'iface:') === 0) ? '' : $peer_key,
      'peer_name' => '',
      'include_listening' => $enable,
      'seen_count' => 0,
      'online' => false,
    ];
  } else {
    $peers[$peer_key]['include_listening'] = $enable;
  }
  tbn_save_peers_memory($peers);
  if ($if !== '' && preg_match('/^thunderbolt\d+$/', $if)) {
    tbn_set_listening_for_iface($if, $enable);
    tbn_refresh_unraid_services();
  }
  return true;
}

/**
 * Security hardening: all Thunderbolt interfaces + all remembered peers → listening No.
 * Strips thunderbolt* / bond-tb* / br-tb* from network-extra include list.
 */
function tbn_listening_harden_all() {
  $peers = tbn_load_peers_memory();
  foreach ($peers as $k => $_) {
    $peers[$k]['include_listening'] = 'no';
  }
  tbn_save_peers_memory($peers);

  foreach (tbn_list_tb_iface_names() as $if) {
    tbn_set_listening_for_iface($if, 'no');
  }

  $current = tbn_read_include_interfaces();
  $current = array_values(array_filter($current, function ($x) {
    return !preg_match('/^(thunderbolt\d+|bond-tb(?:\d+)?|br-tb(?:\d+)?)$/', (string)$x);
  }));
  tbn_write_include_interfaces($current);
  tbn_refresh_unraid_services();
  return true;
}

/**
 * When a remembered peer comes online with include_listening=yes|no, align live iface + network-extra.
 */
function tbn_reconcile_listening_from_memory(array $links) {
  $peers = tbn_load_peers_memory();
  $changed = false;
  foreach ($links as $L) {
    $key = tbn_peer_key_from_link($L, $peers);
    if (!isset($peers[$key]['include_listening'])) {
      continue;
    }
    $pref = (($peers[$key]['include_listening'] ?? 'no') === 'yes') ? 'yes' : 'no';
    $if = $L['iface'] ?? '';
    if ($if === '' || !preg_match('/^thunderbolt\d+$/', $if)) {
      continue;
    }
    $cfg = tbn_load_iface_cfg($if);
    $cur = (($cfg['INCLUDE_LISTENING'] ?? 'no') === 'yes') ? 'yes' : 'no';
    $in_list = in_array($if, tbn_read_include_interfaces(), true);
    $want_in = ($pref === 'yes');
    if ($cur !== $pref || $in_list !== $want_in) {
      tbn_set_listening_for_iface($if, $pref);
      $changed = true;
    }
  }
  if ($changed) {
    tbn_refresh_unraid_services();
  }
  return $changed;
}

function tbn_remember_live_peers(array $links) {
  $peers = tbn_load_peers_memory();
  $now = date('c');
  foreach ($links as $L) {
    $rem = $L['remote'] ?? [];
    $uid = trim((string)($rem['unique_id'] ?? ''));
    $if = $L['iface'] ?? '';
    // Absorb iface: fallback for this netdev before key resolve / upsert
    if ($uid !== '' && $if !== '' && preg_match('/^thunderbolt\d+$/', $if)) {
      $fk = 'iface:' . $if;
      if (isset($peers[$fk])) {
        if (!isset($peers[$uid])) {
          $peers[$uid] = $peers[$fk];
          $peers[$uid]['unique_id'] = $uid;
        } else {
          if (($peers[$uid]['include_listening'] ?? 'no') !== 'yes'
              && ($peers[$fk]['include_listening'] ?? 'no') === 'yes') {
            $peers[$uid]['include_listening'] = 'yes';
          }
        }
        unset($peers[$fk]);
      }
    }
    $key = tbn_peer_key_from_link($L, $peers);
    $prev = $peers[$key] ?? [];
    $iface_listen = 'no';
    if ($if !== '' && preg_match('/^thunderbolt\d+$/', $if)) {
      $ic = tbn_load_iface_cfg($if);
      $iface_listen = (($ic['INCLUDE_LISTENING'] ?? 'no') === 'yes') ? 'yes' : 'no';
    }
    // Prefer saved peer preference; else current iface cfg; default no
    $listen = $prev['include_listening'] ?? $iface_listen;
    if ($listen !== 'yes') {
      $listen = 'no';
    }
    $name = trim((string)($rem['peer_name'] ?? ''));
    if ($name === '') {
      $name = (string)($prev['peer_name'] ?? '');
    }
    $stack = trim((string)($rem['stack'] ?? ''));
    if ($stack === '') {
      $stack = (string)($prev['stack'] ?? '');
    }
    $peers[$key] = array_merge($prev, [
      'unique_id' => $uid !== '' ? $uid : (string)($prev['unique_id'] ?? ''),
      'peer_name' => $name,
      'stack' => $stack,
      'last_iface' => $if,
      'last_label' => $L['label'] ?? '',
      'last_rx_speed' => $rem['rx_speed'] ?? '',
      'last_tx_speed' => $rem['tx_speed'] ?? '',
      'last_rx_lanes' => $rem['rx_lanes'] ?? '',
      'last_tx_lanes' => $rem['tx_lanes'] ?? '',
      'last_local_addrs' => $L['local']['addrs'] ?? [],
      'include_listening' => $listen,
      'last_seen' => $now,
      'seen_count' => (int)($prev['seen_count'] ?? 0) + 1,
      'online' => true,
    ]);
  }
  // Drop any remaining iface: ghosts that share an iface with a UUID peer
  $peers = tbn_peers_dedupe_iface_fallbacks($peers);
  // Mark others offline (still remembered)
  $live_keys = [];
  foreach ($links as $L) {
    $live_keys[] = tbn_peer_key_from_link($L, $peers);
  }
  foreach ($peers as $k => $p) {
    $peers[$k]['online'] = in_array($k, $live_keys, true);
  }
  // Seed peer L3 plan from last live addrs when missing (migrate iface-only setups)
  foreach ($peers as $k => $p) {
    if (tbn_peer_plan_is_usable($p['plan'] ?? null)) {
      continue;
    }
    $seed = tbn_peer_plan_from_addrs($p['last_local_addrs'] ?? []);
    if ($seed !== null) {
      $peers[$k]['plan'] = $seed;
    }
  }
  tbn_save_peers_memory($peers);
  return $peers;
}

/**
 * Whether a peer L3 plan is complete enough to auto-apply on the live path.
 */
function tbn_peer_plan_is_usable($plan) {
  if (!is_array($plan)) {
    return false;
  }
  if (($plan['auto'] ?? 'yes') === 'no') {
    return false;
  }
  if (($plan['USE_DHCP'] ?? 'no') === 'yes') {
    return true;
  }
  $ip = trim((string)($plan['IPADDR'] ?? ''));
  return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

/**
 * Build a peer plan from iface cfg (tbn Apply capture).
 * Includes NAT so “share uplink” follows this peer UUID across tbn renumber.
 */
function tbn_peer_plan_from_iface_cfg(array $cfg) {
  $dhcp = (string)($cfg['USE_DHCP'] ?? 'no');
  if (!in_array($dhcp, ['no', 'yes', 'server'], true)) {
    $dhcp = 'no';
  }
  return [
    'auto' => 'yes',
    'USE_DHCP' => $dhcp,
    'IPADDR' => (string)($cfg['IPADDR'] ?? ''),
    'NETMASK' => (string)($cfg['NETMASK'] ?? '24'),
    'GATEWAY' => (string)($cfg['GATEWAY'] ?? ''),
    'DEFAULT_ROUTE' => (($cfg['DEFAULT_ROUTE'] ?? 'no') === 'yes') ? 'yes' : 'no',
    'MTU_MODE' => (string)($cfg['MTU_MODE'] ?? 'default'),
    'MTU' => (string)($cfg['MTU'] ?? '1500'),
    'PROTOCOL' => (string)($cfg['PROTOCOL'] ?? 'ipv4'),
    'NAT_ENABLE' => (($cfg['NAT_ENABLE'] ?? 'no') === 'yes') ? 'yes' : 'no',
    'NAT_UPLINK' => function_exists('tbn_nat_normalize_uplink')
      ? tbn_nat_normalize_uplink($cfg['NAT_UPLINK'] ?? 'auto')
      : (string)($cfg['NAT_UPLINK'] ?? 'auto'),
  ];
}

/**
 * Seed plan from last_local_addrs like ["10.255.0.2/24"] (first IPv4 only).
 */
function tbn_peer_plan_from_addrs($addrs) {
  if (!is_array($addrs)) {
    return null;
  }
  foreach ($addrs as $a) {
    $a = trim((string)$a);
    if (preg_match('#^(\d{1,3}(?:\.\d{1,3}){3})/(\d{1,2})$#', $a, $m)) {
      if (filter_var($m[1], FILTER_VALIDATE_IP) === false) {
        continue;
      }
      $pfx = (int)$m[2];
      if ($pfx < 0 || $pfx > 32) {
        $pfx = 24;
      }
      return [
        'auto' => 'yes',
        'USE_DHCP' => 'no',
        'IPADDR' => $m[1],
        'NETMASK' => (string)$pfx,
        'GATEWAY' => '',
        'DEFAULT_ROUTE' => 'no',
        'MTU_MODE' => 'default',
        'MTU' => '1500',
        'PROTOCOL' => 'ipv4',
      ];
    }
  }
  return null;
}

/**
 * Merge peer plan + listening into an iface cfg for apply on $if.
 *
 * L3/MTU/NAT come from the Saved peer UUID plan when present so a different
 * device landing on the same thunderboltN does not inherit the previous peer’s
 * NAT. Path-slot cfg still holds bonding/bridging/DHCP-pool/OpenFabric editor
 * state for that tab.
 */
function tbn_iface_cfg_from_peer(array $peer, $if) {
  $cfg = tbn_iface_defaults($if);
  $prior = [];
  if (function_exists('tbn_iface_cfg_path') && is_file(tbn_iface_cfg_path($if))) {
    $prior = tbn_load_iface_cfg($if);
  }
  // Path-slot UI policy (not peer identity)
  foreach ([
    'DESCRIPTION',
    'BONDING', 'BONDING_MODE', 'BOND_NAME', 'BOND_MEMBERS',
    'BRIDGING', 'BR_NAME',
    'VLAN_ENABLE', 'VLAN_LIST',
    'DHCP_POOL_START', 'DHCP_POOL_END',
    'ADDRESS_PLAN',
    'OPENFABRIC_PARTICIPATE', 'OPENFABRIC_METRIC_MODE', 'OPENFABRIC_METRIC',
  ] as $k) {
    if (array_key_exists($k, $prior)) {
      $cfg[$k] = $prior[$k];
    }
  }
  $plan = is_array($peer['plan'] ?? null) ? $peer['plan'] : [];
  foreach (['USE_DHCP', 'IPADDR', 'NETMASK', 'GATEWAY', 'DEFAULT_ROUTE', 'MTU_MODE', 'MTU', 'PROTOCOL'] as $k) {
    if (isset($plan[$k]) && (string)$plan[$k] !== '') {
      $cfg[$k] = $plan[$k];
    }
  }
  // NAT follows the peer plan when stamped (Apply while linked). Legacy plans
  // without NAT keys default to No so another device on this tbnN is safe.
  if (array_key_exists('NAT_ENABLE', $plan)) {
    $cfg['NAT_ENABLE'] = (($plan['NAT_ENABLE'] ?? 'no') === 'yes') ? 'yes' : 'no';
    $cfg['NAT_UPLINK'] = function_exists('tbn_nat_normalize_uplink')
      ? tbn_nat_normalize_uplink($plan['NAT_UPLINK'] ?? 'auto')
      : (string)($plan['NAT_UPLINK'] ?? 'auto');
  } else {
    $cfg['NAT_ENABLE'] = 'no';
    $cfg['NAT_UPLINK'] = 'auto';
  }
  if (($cfg['USE_DHCP'] ?? '') === 'server') {
    if (trim((string)($cfg['DHCP_POOL_START'] ?? '')) === '' && trim((string)($prior['DHCP_POOL_START'] ?? '')) !== '') {
      $cfg['DHCP_POOL_START'] = $prior['DHCP_POOL_START'];
    }
    if (trim((string)($cfg['DHCP_POOL_END'] ?? '')) === '' && trim((string)($prior['DHCP_POOL_END'] ?? '')) !== '') {
      $cfg['DHCP_POOL_END'] = $prior['DHCP_POOL_END'];
    }
  }
  $cfg['INCLUDE_LISTENING'] = (($peer['include_listening'] ?? 'no') === 'yes') ? 'yes' : 'no';
  $cfg['ENABLE'] = 'yes';
  return tbn_iface_reconcile_nat_bridge($cfg);
}

/**
 * First IPv4 found in a display string (live addrs or plan label).
 */
function tbn_first_ipv4($s) {
  if (preg_match('/\b(\d{1,3}(?:\.\d{1,3}){3})\b/', (string)$s, $m)) {
    return $m[1];
  }
  return '';
}

/**
 * Carrier present on a thunderbolt* netdev (1 = datapath trained at L2).
 */
function tbn_iface_carrier_up($if) {
  $if = preg_replace('/[^A-Za-z0-9._-]/', '', (string)$if);
  if ($if === '' || !is_dir('/sys/class/net/' . $if)) {
    return false;
  }
  return tbn_sysfs_str('/sys/class/net/' . $if . '/carrier') === '1';
}

/**
 * Best-effort peer IPv4 on the same /24 as local (lab convention .1 ↔ .2).
 * Prefers an explicit candidate list when provided.
 *
 * @param string $local_cidr e.g. 10.255.0.1/24
 * @param array<int,string> $candidates
 */
function tbn_guess_peer_ipv4($local_cidr, array $candidates = []) {
  foreach ($candidates as $c) {
    $ip = tbn_first_ipv4($c);
    if ($ip !== '') {
      return $ip;
    }
  }
  $local = tbn_first_ipv4($local_cidr);
  if ($local === '' || !preg_match('/^(\d+\.\d+\.\d+)\.(\d+)$/', $local, $m)) {
    return '';
  }
  $base = $m[1];
  $host = (int)$m[2];
  if ($host === 1) {
    return $base . '.2';
  }
  if ($host === 2) {
    return $base . '.1';
  }
  return '';
}

/**
 * Quick underlay reachability (1-probe ping bound to iface).
 * Used to surface “Online but no reply” when sysfs looks healthy.
 */
function tbn_underlay_ping_ok($if, $peer_ip, $timeout_sec = 1) {
  $if = preg_replace('/[^A-Za-z0-9._-]/', '', (string)$if);
  $peer_ip = tbn_first_ipv4($peer_ip);
  if ($if === '' || $peer_ip === '' || !is_dir('/sys/class/net/' . $if)) {
    return false;
  }
  $t = max(1, min(3, (int)$timeout_sec));
  $cmd = 'ping -c1 -W' . $t . ' -I ' . escapeshellarg($if) . ' ' . escapeshellarg($peer_ip) . ' >/dev/null 2>&1';
  $rc = 1;
  @exec($cmd, $o, $rc);
  return $rc === 0;
}

/**
 * Path health for Peers UI: carrier + optional ping.
 *
 * @return array{ok:bool,label:string,detail:string}
 */
function tbn_path_underlay_health($if, $local_addrs = '', array $peer = []) {
  $if = (string)$if;
  if ($if === '' || !is_dir('/sys/class/net/' . $if)) {
    return ['ok' => false, 'label' => 'No iface', 'detail' => 'Path netdev missing'];
  }
  if (!tbn_iface_carrier_up($if)) {
    return ['ok' => false, 'label' => 'No carrier', 'detail' => 'Link trained in TB topology but netdev has no carrier'];
  }
  $cands = [];
  if (!empty($peer['last_remote_addrs']) && is_array($peer['last_remote_addrs'])) {
    $cands = $peer['last_remote_addrs'];
  }
  $peer_ip = tbn_guess_peer_ipv4($local_addrs, $cands);
  if ($peer_ip === '') {
    return ['ok' => true, 'label' => '', 'detail' => 'Carrier up'];
  }
  if (tbn_underlay_ping_ok($if, $peer_ip, 1)) {
    return ['ok' => true, 'label' => '', 'detail' => 'Ping ' . $peer_ip . ' ok'];
  }
  return [
    'ok' => false,
    'label' => 'No reply',
    'detail' => 'Carrier up but no ping reply from ' . $peer_ip . ' — check peer e2e/cable',
  ];
}

/**
 * Human one-liner for Saved column (Peers table). No jargon suffix —
 * the column header already says Saved.
 */
function tbn_peer_plan_label(array $peer) {
  $plan = $peer['plan'] ?? null;
  if (!tbn_peer_plan_is_usable($plan)) {
    return '—';
  }
  if (($plan['auto'] ?? 'yes') === 'no') {
    return 'auto off';
  }
  if (($plan['USE_DHCP'] ?? 'no') === 'yes') {
    return 'DHCP';
  }
  $ip = trim((string)($plan['IPADDR'] ?? ''));
  $mask = trim((string)($plan['NETMASK'] ?? '24'));
  if ($ip === '') {
    return '—';
  }
  return $ip . '/' . $mask;
}

/**
 * Save L3 plan onto a remembered peer (UUID or iface: key).
 */
function tbn_save_peer_plan($key, array $plan, $listen = null) {
  $key = trim((string)$key);
  if ($key === '') {
    return false;
  }
  $peers = tbn_load_peers_memory();
  if (!isset($peers[$key])) {
    $peers[$key] = [
      'unique_id' => (strpos($key, 'iface:') === 0) ? '' : $key,
      'peer_name' => '',
      'seen_count' => 0,
      'online' => false,
      'include_listening' => 'no',
    ];
  }
  $cur = is_array($peers[$key]['plan'] ?? null) ? $peers[$key]['plan'] : [];
  $peers[$key]['plan'] = array_merge($cur, $plan);
  if ($listen !== null) {
    $peers[$key]['include_listening'] = ($listen === 'yes' || $listen === true) ? 'yes' : 'no';
  }
  tbn_save_peers_memory($peers);
  return true;
}

/**
 * After tbn Apply: if a peer is live on $if, store that path's L3 as the peer plan.
 * Survives renumber (tbn0↔tbn1) because plan is UUID-keyed.
 */
function tbn_capture_peer_plan_from_iface($if) {
  if (!preg_match('/^thunderbolt\d+$/', (string)$if)) {
    return ['ok' => false, 'error' => 'bad iface'];
  }
  $links = tbn_link_summaries();
  $peers = tbn_load_peers_memory();
  foreach ($links as $L) {
    if (($L['iface'] ?? '') !== $if) {
      continue;
    }
    $key = tbn_peer_key_from_link($L, $peers);
    $cfg = tbn_load_iface_cfg($if);
    $plan = tbn_peer_plan_from_iface_cfg($cfg);
    $listen = (($cfg['INCLUDE_LISTENING'] ?? 'no') === 'yes') ? 'yes' : 'no';
    // Ensure peer row exists
    tbn_remember_live_peers($links);
    tbn_save_peer_plan($key, $plan, $listen);
    return ['ok' => true, 'key' => $key, 'plan' => $plan];
  }
  return ['ok' => false, 'error' => 'no live peer on iface'];
}

/**
 * Apply remembered peer plan onto a live netdev (write iface cfg + apply).
 */
function tbn_apply_peer_plan_to_iface($key, $if) {
  $key = trim((string)$key);
  if ($key === '' || !preg_match('/^thunderbolt\d+$/', (string)$if)) {
    return ['ok' => false, 'applied' => false, 'error' => 'bad args'];
  }
  if (!is_dir('/sys/class/net/' . $if)) {
    return ['ok' => false, 'applied' => false, 'error' => 'iface missing'];
  }
  $peers = tbn_load_peers_memory();
  if (!isset($peers[$key])) {
    return ['ok' => false, 'applied' => false, 'error' => 'unknown peer'];
  }
  $peer = $peers[$key];
  if (!tbn_peer_plan_is_usable($peer['plan'] ?? null)) {
    return ['ok' => true, 'applied' => false, 'reason' => 'no_plan'];
  }
  $cfg = tbn_iface_cfg_from_peer($peer, $if);
  tbn_write_iface_cfg($if, $cfg);
  $r = tbn_apply_iface($if, ['skip_peer_capture' => true]);
  return [
    'ok' => !empty($r['ok']),
    'applied' => true,
    'iface' => $if,
    'key' => $key,
    'result' => $r,
  ];
}

/**
 * Remove peers from Known peers (peers.json only). Does not wipe ifaces/*.cfg.
 *
 * @param string[] $keys
 * @return int number removed
 */
function tbn_forget_peers(array $keys) {
  $peers = tbn_load_peers_memory();
  $n = 0;
  foreach ($keys as $k) {
    $k = trim((string)$k);
    if ($k !== '' && isset($peers[$k])) {
      unset($peers[$k]);
      $n++;
    }
  }
  if ($n > 0) {
    tbn_save_peers_memory($peers);
  }
  return $n;
}

/**
 * HTML snippet for activity / safe-unplug (Settings pages).
 */
function tbn_activity_html(array $act) {
  $level = htmlspecialchars($act['level'] ?? 'unknown');
  $label = htmlspecialchars($act['label'] ?? 'Unknown');
  $safe = $act['safe_unplug'] ?? 'unknown';
  $note = htmlspecialchars($act['note'] ?? '');
  $rate = tbn_format_bps($act['bps'] ?? null);
  $safe_label = [
    'yes' => 'OK to unplug',
    'no' => 'Keep connected',
    'unknown' => 'Measuring…',
  ];
  // Don't show confusing "Unplug: check first" while still sampling
  if (($act['level'] ?? '') === 'unknown' || ($act['level'] ?? '') === 'down') {
    $sl = ($act['level'] ?? '') === 'down'
      ? 'OK to unplug'
      : 'Measuring…';
  } else {
    $sl = $safe_label[$safe] ?? $safe_label['unknown'];
  }
  $sl = htmlspecialchars($sl);
  $html = '<span class="tbn-badge tbn-badge-act-' . $level . '">' . $label . '</span> ';
  $html .= '<span class="tbn-badge tbn-badge-safe-' . htmlspecialchars($safe) . '">' . $sl . '</span>';
  if ($rate !== '—' && $act['bps'] !== null) {
    $html .= ' <span class="tbn-muted">~' . htmlspecialchars($rate) . '</span>';
  }
  if ($note !== '' && ($act['level'] ?? '') !== 'unknown') {
    // Keep Measuring notes short; hide long notes during measure to reduce noise
    $html .= '<p class="tbn-hint tbn-activity-note">' . $note . '</p>';
  } elseif ($note !== '' && ($act['level'] ?? '') === 'unknown') {
    $html .= '<p class="tbn-hint tbn-activity-note tbn-muted">' . $note . '</p>';
  }
  return $html;
}

/**
 * Normalize rate strings for UI.
 * Use SI **Gb/s** (capital G = giga, lowercase b = bit — not GB/s bytes).
 *
 * @param mixed $raw  "20.0 Gb/s", 20, "20G", null
 * @return string e.g. "20 Gb/s" or ""
 */
function tbn_format_gbps($raw) {
  if ($raw === null || $raw === '') {
    return '';
  }
  if (is_numeric($raw)) {
    $n = (float)$raw;
    // Some paths use Mbps integers (e.g. 20000); sysfs strings are usually "20.00 Gb/s"
    if ($n > 500) {
      $n = $n / 1000.0;
    }
  } elseif (preg_match('/([\d.]+)/', (string)$raw, $m)) {
    $n = (float)$m[1];
  } else {
    return trim((string)$raw);
  }
  if ($n <= 0) {
    return '';
  }
  // Drop trailing .0 for whole numbers
  if (abs($n - round($n)) < 0.05) {
    return (string)(int)round($n) . ' Gb/s';
  }
  return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.') . ' Gb/s';
}

/** Compact max line: "~40 Gb/s · 2-lane" */
function tbn_format_max_line($gbps, $lanes = 2) {
  $g = tbn_format_gbps($gbps);
  if ($g === '') {
    return '';
  }
  // Prefer "~40 Gb/s" style
  $g = '~' . preg_replace('/^~/', '', $g);
  $lanes = (int)$lanes;
  if ($lanes > 0) {
    return $g . ' · ' . $lanes . '-lane';
  }
  return $g;
}

/**
 * Parse a rate string to float Gb/s (bits), or 0 if unknown.
 *
 * @param mixed $raw  "20.0 Gb/s", 20, "20G", null
 */
function tbn_parse_gbps($raw) {
  if ($raw === null || $raw === '') {
    return 0.0;
  }
  if (is_numeric($raw)) {
    $n = (float)$raw;
    // Heuristic: values like 20000 are Mbps from some paths
    if ($n > 500) {
      $n = $n / 1000.0;
    }
    return $n > 0 ? $n : 0.0;
  }
  if (preg_match('/([\d.]+)/', (string)$raw, $m)) {
    $n = (float)$m[1];
    return $n > 0 ? $n : 0.0;
  }
  return 0.0;
}

/**
 * Format trained link rates for Known peers / quality tables.
 *
 * Perspective is this host: RX = from peer, TX = to peer (sysfs on peer path).
 * Equal rates → "20 Gb/s full-duplex · using 1 of 2 lanes".
 * Asymmetric → "TX 40 Gb/s (to peer) · RX 20 Gb/s (from peer) · …".
 *
 * @param mixed $rx   last_rx_speed / remote rx_speed
 * @param mixed $tx   last_tx_speed / remote tx_speed
 * @param array $opts keys: rx_lanes, tx_lanes, show_lanes (bool, default true), max_lanes (int|null)
 * @return string empty if neither side known
 */
function tbn_format_link_rate($rx, $tx, array $opts = []) {
  $rx_s = tbn_format_gbps($rx);
  $tx_s = tbn_format_gbps($tx);
  $rx_n = tbn_parse_gbps($rx);
  $tx_n = tbn_parse_gbps($tx);

  $show_lanes = !array_key_exists('show_lanes', $opts) || !empty($opts['show_lanes']);
  $rx_lanes = isset($opts['rx_lanes']) ? trim((string)$opts['rx_lanes']) : '';
  $tx_lanes = isset($opts['tx_lanes']) ? trim((string)$opts['tx_lanes']) : '';
  $max_lanes = isset($opts['max_lanes']) ? (int)$opts['max_lanes'] : 0;
  if ($max_lanes <= 0 && function_exists('tbn_controller_capability')) {
    $cap = tbn_controller_capability();
    $max_lanes = (int)($cap['max_lanes'] ?? 0);
  }
  $lanes_suffix = '';
  if ($show_lanes) {
    $used = 0;
    if ($rx_lanes !== '' && $tx_lanes !== '' && preg_match('/^\d+$/', $rx_lanes) && preg_match('/^\d+$/', $tx_lanes)) {
      // Trained path: report the active lane count (usually RX==TX); take max if unequal
      $used = max((int)$rx_lanes, (int)$tx_lanes);
    } elseif ($rx_lanes !== '' && preg_match('/^\d+$/', $rx_lanes)) {
      $used = (int)$rx_lanes;
    } elseif ($tx_lanes !== '' && preg_match('/^\d+$/', $tx_lanes)) {
      $used = (int)$tx_lanes;
    }
    if ($used > 0 && $max_lanes > 0) {
      $lanes_suffix = ' · using ' . $used . ' of ' . $max_lanes . ' lanes';
    } elseif ($used > 0) {
      $lanes_suffix = ' · using ' . $used . ' lane' . ($used === 1 ? '' : 's');
    }
  }

  if ($rx_s === '' && $tx_s === '') {
    return '';
  }
  if ($rx_s === '') {
    return $tx_s . ' TX (to peer)' . $lanes_suffix;
  }
  if ($tx_s === '') {
    return $rx_s . ' RX (from peer)' . $lanes_suffix;
  }

  // Equal within ~0.05 Gb/s → full-duplex wording
  if ($rx_n > 0 && $tx_n > 0 && abs($rx_n - $tx_n) < 0.05) {
    return $rx_s . ' full-duplex' . $lanes_suffix;
  }

  // Asymmetric (e.g. Thunderbolt 5 unequal modes): TX first (out to peer), then RX
  return 'TX ' . $tx_s . ' (to peer) · RX ' . $rx_s . ' (from peer)' . $lanes_suffix;
}

/**
 * Compact lanes cell: trained "using 1 of 2" vs host max only.
 *
 * LOCAL compare column should say host capability; REMOTE says trained path.
 *
 * @param string|int $rx_lanes
 * @param string|int $tx_lanes
 * @param int        $max_lanes host class ceiling (0 = unknown)
 * @param string     $side 'local' | 'remote'
 */
function tbn_format_lanes_cell($rx_lanes, $tx_lanes, $max_lanes = 0, $side = 'remote') {
  $max_lanes = (int)$max_lanes;
  $rx = trim((string)$rx_lanes);
  $tx = trim((string)$tx_lanes);
  if ($side === 'local') {
    if ($max_lanes > 0) {
      return 'Host capable of ' . $max_lanes . ' lane' . ($max_lanes === 1 ? '' : 's')
        . ' (class max — not this hop’s train)';
    }
    return '—';
  }
  $used = 0;
  if ($rx !== '' && $tx !== '' && ctype_digit($rx) && ctype_digit($tx)) {
    $used = max((int)$rx, (int)$tx);
  } elseif ($rx !== '' && ctype_digit($rx)) {
    $used = (int)$rx;
  } elseif ($tx !== '' && ctype_digit($tx)) {
    $used = (int)$tx;
  }
  if ($used <= 0) {
    return '—';
  }
  if ($max_lanes > 0) {
    $s = 'Using ' . $used . ' of ' . $max_lanes . ' lanes (trained on this cable)';
    if ($used < $max_lanes) {
      $s .= ' — peer/cable/Linux often trains below host max';
    }
    return $s;
  }
  return 'Using ' . $used . ' lane' . ($used === 1 ? '' : 's') . ' (trained)';
}

/**
 * Install helper for companion plugins (Fabric Routing / NBD Export) on Status.
 *
 * CA deep-search is not stable across AppFeed versions — link Apps home + exact search text.
 * Manual path: copyable raw .plg + Plugins → Install Plugin.
 *
 * @param string $display_name e.g. Fabric Routing
 * @param string $plg_url      raw GitHub .plg (stable preferred for CA users)
 * @param string $ca_search    text to search in Apps
 */
function tbn_companion_install_html($display_name, $plg_url, $ca_search = '') {
  $name = htmlspecialchars((string)$display_name);
  $url = htmlspecialchars((string)$plg_url);
  $q = trim((string)$ca_search);
  if ($q === '') {
    $q = (string)$display_name;
  }
  $q_h = htmlspecialchars($q);
  $ca = is_dir('/usr/local/emhttp/plugins/community.applications')
    || is_file('/boot/config/plugins/community.applications.plg');
  $html = '<div class="tbn-install-box">';
  $html .= '<span class="tbn-install-label">Install <strong>' . $name . '</strong>:</span> ';
  if ($ca) {
    $html .= '<a class="tbn-btn-link" href="/Apps" title="Community Applications — search for ' . $q_h . '">'
      . '<strong>from CA</strong></a>';
    $html .= ' <span class="tbn-muted">(Apps → search <code>' . $q_h . '</code>)</span>';
  } else {
    $html .= '<a class="tbn-btn-link" href="/Apps" title="Open Apps (prompts to install Community Applications if missing)">'
      . '<strong>from CA</strong></a>';
    $html .= ' <span class="tbn-muted">(enable Community Applications if Apps is empty)</span>';
  }
  $html .= ' <span class="tbn-muted">·</span> ';
  $html .= '<button type="button" class="tbn-btn-link tbn-plg-toggle" data-tbn-plg-panel>'
    . 'manual .plg</button>';
  $html .= '<div class="tbn-plg-panel tbn-hidden" hidden>';
  $html .= '<p class="tbn-muted" style="margin:0.4rem 0 0.25rem">Plugins → Install Plugin → paste URL:</p>';
  $html .= '<div class="tbn-plg-row">';
  $html .= '<input type="text" class="tbn-plg-url" readonly value="' . $url . '" '
    . 'onclick="this.select()" title="Select and copy">';
  $html .= '<button type="button" class="tbn-btn-small" data-tbn-copy-plg>Copy</button>';
  $html .= '<a class="tbn-btn-link" href="/Plugins">Open Plugins</a>';
  $html .= '</div></div></div>';
  return $html;
}

/**
 * Soft-detect NBD Export plugin (no hard require).
 * Returns path info if installed; used for services hints.
 */
function tbn_nbdexport_present() {
  return is_dir('/usr/local/emhttp/plugins/NBDExport')
    || is_dir('/boot/config/plugins/NBDExport')
    || is_dir('/usr/local/emhttp/plugins/NbdExport')
    || is_dir('/boot/config/plugins/NbdExport');
}

/**
 * Local host controller capability (from 0-0 sysfs) — max potential, not trained path.
 */
function tbn_controller_capability() {
  $gen = tbn_sysfs_str('/sys/bus/thunderbolt/devices/0-0/generation');
  $usb4 = '';
  $uevent = @file_get_contents('/sys/bus/thunderbolt/devices/0-0/uevent');
  if (is_string($uevent) && preg_match('/USB4_VERSION=(\S+)/', $uevent, $m)) {
    $usb4 = $m[1];
  }
  $product = tbn_sysfs_str('/sys/bus/thunderbolt/devices/0-0/device_name');
  $mfg = tbn_sysfs_str('/sys/bus/thunderbolt/devices/0-0/vendor_name');

  $gen_i = ($gen !== '' && ctype_digit($gen)) ? (int)$gen : 0;
  // Marketing-class ceilings when path + peer + cable allow (not a cable EEPROM read).
  $max_gbps = 40;
  $max_lanes = 2;
  $class = 'Thunderbolt/USB4 host';
  if ($gen_i >= 5 || ($usb4 !== '' && version_compare($usb4, '2.0', '>='))) {
    $max_gbps = 80;
    $max_lanes = 2;
    $class = $gen_i >= 5 ? 'Thunderbolt 5-class' : 'USB4 v2-class';
  } elseif ($gen_i >= 4 || $usb4 !== '') {
    $max_gbps = 40;
    $max_lanes = 2;
    $class = $gen_i >= 4 ? 'Thunderbolt 4-class' : 'USB4-class';
  } elseif ($gen_i >= 3) {
    $max_gbps = 40;
    $max_lanes = 2;
    $class = 'Thunderbolt 3-class';
  } elseif ($gen_i === 2) {
    $max_gbps = 20;
    $max_lanes = 2;
    $class = 'TB2-class';
  } elseif ($gen_i === 1) {
    $max_gbps = 10;
    $max_lanes = 2;
    $class = 'TB1-class';
  }

  $ctrl = 'Thunderbolt host';
  if ($gen !== '') {
    $ctrl = 'Thunderbolt Gen ' . $gen;
  }
  if ($usb4 !== '') {
    $ctrl .= ' / USB4 ' . $usb4;
  }

  $max_short = tbn_format_max_line($max_gbps, $max_lanes);

  return [
    'gen' => $gen,
    'usb4' => $usb4,
    'class' => $class,
    'label' => $ctrl,
    'max_gbps' => $max_gbps,
    'max_lanes' => $max_lanes,
    'max_short' => $max_short,
    'can_dual' => ($gen_i === 0 || $gen_i >= 3 || $usb4 !== ''),
    'detail' => $ctrl . '; max class ' . $max_short . ' when path allows',
    'product' => $product,
    'mfg' => $mfg,
  ];
}

/**
 * Human speed label from USB root-hub sysfs speed (Mbps integer string).
 * Always use Gb/s (bits), never GB/s (bytes).
 */
function tbn_usb_speed_label($speed_mbps) {
  $s = (float)$speed_mbps;
  if ($s >= 20000) {
    return ['short' => '20 Gb/s', 'label' => 'USB 3.2 SuperSpeed 20 Gb/s', 'mbps' => 20000];
  }
  if ($s >= 10000) {
    return ['short' => '10 Gb/s', 'label' => 'USB 3.1/3.2 SuperSpeed 10 Gb/s', 'mbps' => 10000];
  }
  if ($s >= 5000) {
    return ['short' => '5 Gb/s', 'label' => 'USB 3 SuperSpeed 5 Gb/s', 'mbps' => 5000];
  }
  if ($s >= 480) {
    return ['short' => '480 Mb/s', 'label' => 'USB 2.0 High Speed', 'mbps' => 480];
  }
  if ($s > 0) {
    return ['short' => (string)(int)$s . ' Mb/s', 'label' => (int)$s . ' Mb/s', 'mbps' => (int)$s];
  }
  return ['short' => '?', 'label' => 'unknown', 'mbps' => 0];
}

/**
 * PCI BDF parent of a sysfs path (e.g. usb root hub).
 */
function tbn_sysfs_pci_bdf($path) {
  $real = @realpath($path);
  if (!$real) {
    return '';
  }
  if (preg_match_all('/([0-9a-f]{4}:[0-9a-f]{2}:[0-9a-f]{2}\.[0-9a-f])/i', $real, $m)) {
    return end($m[1]);
  }
  return '';
}

/**
 * Whether a PCI USB controller looks Thunderbolt/USB4-family (Maple Ridge, etc.).
 */
function tbn_pci_is_tb_usb($bdf) {
  if ($bdf === '' || !is_dir('/sys/bus/pci/devices/' . $bdf)) {
    return false;
  }
  $vendor = tbn_sysfs_str('/sys/bus/pci/devices/' . $bdf . '/vendor');
  $device = tbn_sysfs_str('/sys/bus/pci/devices/' . $bdf . '/device');
  // Intel Maple Ridge Thunderbolt 4 USB: 8086:1138; NHI 1137 — also match common USB4 xHCI IDs loosely via path
  if (strtolower($vendor) === '0x8086' && in_array(strtolower($device), ['0x1138', '0x1137', '0x15eb', '0x15ec', '0x15ef', '0x15f0'], true)) {
    return true;
  }
  // Path contains thunderbolt domain bridge chain often has 0f:00 / maple — check driver of sibling NHI
  $real = @realpath('/sys/bus/pci/devices/' . $bdf);
  if ($real && (strpos($real, 'thunderbolt') !== false || preg_match('#/0f:00\.0/#', $real))) {
    return true;
  }
  // lspci-style: device name from modalias
  $mod = @file_get_contents('/sys/bus/pci/devices/' . $bdf . '/modalias');
  if (is_string($mod) && stripos($mod, 'thunderbolt') !== false) {
    return true;
  }
  return false;
}

/**
 * Brief local port inventory for Link quality LOCAL column.
 *
 * Includes:
 *  - Thunderbolt host max class (from controller)
 *  - Active Thunderbolt peer ports (trained path)
 *  - High-speed USB root hubs (5G/10G/20G) with port counts — Thunderbolt-icon USB-C often appears here
 *    even when they are not full dual-lane 40G Thunderbolt networking paths
 *
 * @return list of rows: kind, title, detail, ports (int|null), speed_short, attached
 */
function tbn_list_local_ports(array $cap = null) {
  if ($cap === null) {
    $cap = tbn_controller_capability();
  }
  $rows = [];

  // 1) Thunderbolt host controller (network fabric ceiling)
  $rows[] = [
    'kind' => 'tb-host',
    'title' => 'Thunderbolt/USB4 host',
    'detail' => ($cap['label'] ?? 'Thunderbolt host') . ' · max ' . ($cap['max_short'] ?? '?'),
    'ports' => null,
    'speed_short' => $cap['max_short'] ?? '',
    'attached' => null,
  ];

  // 2) Active Thunderbolt peer ports — host max vs trained (what this plugin cares about)
  $tb_ports = 0;
  $host_max = $cap['max_short'] ?? tbn_format_max_line($cap['max_gbps'] ?? 40, $cap['max_lanes'] ?? 2);
  foreach (@scandir('/sys/bus/thunderbolt/devices') ?: [] as $id) {
    if ($id === '.' || $id === '..' || $id === 'domain0' || $id === '0-0') {
      continue;
    }
    // Direct children of host: 0-1, 0-3 (not services 0-1.0)
    if (!preg_match('/^\d+-\d+$/', $id)) {
      continue;
    }
    $base = '/sys/bus/thunderbolt/devices/' . $id;
    $rx = tbn_sysfs_str($base . '/rx_speed');
    $rl = tbn_sysfs_str($base . '/rx_lanes');
    $name = tbn_sysfs_str($base . '/device_name');
    if ($rx === '' && $name === '') {
      continue;
    }
    $tb_ports++;
    $rx_n = 0.0;
    if (preg_match('/([\d.]+)/', $rx, $m)) {
      $rx_n = (float)$m[1];
    }
    $rl_i = ($rl !== '' && ctype_digit($rl)) ? (int)$rl : 0;
    $trained = tbn_format_gbps($rx);
    if ($rl_i > 0) {
      $trained .= ($trained !== '' ? ' · ' : '') . $rl_i . '-lane';
    }
    $max_gbps = (float)($cap['max_gbps'] ?? 40);
    $below = ($rx_n > 0 && $max_gbps > 0 && $rx_n < ($max_gbps * 0.75))
      || ($rl_i === 1 && !empty($cap['can_dual']));
    $rows[] = [
      'kind' => 'tb-link',
      'title' => 'Port ' . $id,
      'peer' => $name,
      'max_line' => $host_max,
      'trained' => $trained,
      'below' => $below,
      'detail' => '',
      'ports' => 1,
      'speed_short' => $trained,
      'attached' => true,
    ];
  }

  // 3) USB SuperSpeed(+) root hubs — physical USB-C / Type-A SS banks
  //    Pair HS+SS of same controller later if needed; list SS roots only to avoid 480M clutter.
  $usb_roots = [];
  foreach (@scandir('/sys/bus/usb/devices') ?: [] as $bn) {
    if (!preg_match('/^usb(\d+)$/', $bn, $m)) {
      continue;
    }
    $hub = '/sys/bus/usb/devices/' . $bn;
    $speed = tbn_sysfs_str($hub . '/speed');
    $mbps = (float)$speed;
    if ($mbps < 5000) {
      continue; // skip pure USB2 roots (companion of SS hubs)
    }
    $maxchild = (int)tbn_sysfs_str($hub . '/maxchild');
    $bdf = tbn_sysfs_pci_bdf($hub);
    $sl = tbn_usb_speed_label($speed);
    $is_tb = tbn_pci_is_tb_usb($bdf);
    // Count attached vs empty via port state files
    $attached = 0;
    $empty = 0;
    foreach (glob($hub . '/*-0:1.0/usb*-port*') ?: [] as $pd) {
      $st = strtolower(tbn_sysfs_str($pd . '/state'));
      if ($st === '' || $st === 'not attached') {
        $empty++;
      } else {
        $attached++;
      }
    }
    $nports = $maxchild > 0 ? $maxchild : 0;
    $usb_roots[] = [
      'kind' => $is_tb ? 'usb-tb' : 'usb-ss',
      'title' => $is_tb ? 'USB-C on Thunderbolt controller' : 'USB SuperSpeed',
      'detail' => $sl['short']
        . ($nports > 0 ? ' · ' . $nports . ' port' . ($nports === 1 ? '' : 's') : '')
        . ($attached > 0 ? ' · ' . $attached . ' in use' : '')
        . ' · ' . $bn,
      'ports' => $nports > 0 ? $nports : null,
      'speed_short' => $sl['short'],
      'attached' => $attached,
      'mbps' => $sl['mbps'],
      'is_tb' => $is_tb,
      'bus' => $bn,
    ];
  }
  // Sort: Thunderbolt-USB first, then by speed desc, then bus name
  usort($usb_roots, function ($a, $b) {
    if (!empty($a['is_tb']) !== !empty($b['is_tb'])) {
      return !empty($a['is_tb']) ? -1 : 1;
    }
    $sa = (int)($a['mbps'] ?? 0);
    $sb = (int)($b['mbps'] ?? 0);
    if ($sa !== $sb) {
      return $sb - $sa;
    }
    return strcmp($a['bus'] ?? '', $b['bus'] ?? '');
  });
  foreach ($usb_roots as $r) {
    unset($r['mbps'], $r['is_tb'], $r['bus']);
    $rows[] = $r;
  }

  // Aggregate note if we only saw Thunderbolt links but no empty-port inventory from USB
  if ($tb_ports === 0 && count($usb_roots) === 0) {
    $rows[] = [
      'kind' => 'note',
      'title' => 'Ports',
      'detail' => 'No SuperSpeed USB roots or live Thunderbolt peers visible in sysfs yet',
      'ports' => null,
      'speed_short' => '',
      'attached' => null,
    ];
  }

  return $rows;
}

/**
 * HTML for LOCAL column — host max + Thunderbolt links (boxed) + optional USB SuperSpeed list.
 * Rates always use Gb/s (bits). Compact: wrap text; keep column width bounded via CSS.
 */
function tbn_controller_capability_html(array $cap = null) {
  if ($cap === null) {
    $cap = tbn_controller_capability();
  }
  if (empty($cap['max_short'])) {
    return '<span class="tbn-muted">Controller capability unknown</span>';
  }

  $html = '<div class="tbn-cap-block">';
  $html .= '<strong class="tbn-cap-max">' . htmlspecialchars('Max ' . $cap['max_short']) . '</strong>';
  $html .= '<p class="tbn-cap-meta">' . htmlspecialchars($cap['label'] ?? '') . '</p>';
  if (!empty($cap['class'])) {
    $html .= '<p class="tbn-cap-class tbn-muted">'
      . htmlspecialchars($cap['class'] . ' host — controller class ceiling, not a promise of dual-lane host-net')
      . '</p>';
  }
  if (!empty($cap['product'])) {
    $html .= '<p class="tbn-cap-product tbn-muted">'
      . htmlspecialchars($cap['product'])
      . '</p>';
  }

  $ports = tbn_list_local_ports($cap);
  $tb = [];
  $usb = [];
  foreach ($ports as $p) {
    $k = $p['kind'] ?? '';
    if ($k === 'tb-host') {
      continue;
    }
    if ($k === 'tb-link') {
      $tb[] = $p;
    } elseif ($k === 'usb-tb' || $k === 'usb-ss') {
      $usb[] = $p;
    }
  }

  // Thunderbolt fabric ports — primary (this plugin)
  $html .= '<div class="tbn-port-box tbn-port-box-tb">';
  $html .= '<div class="tbn-port-box-hd">Thunderbolt links</div>';
  if (!$tb) {
    $html .= '<p class="tbn-muted tbn-port-empty">No live Thunderbolt peer — plug a host cable to see trained rate vs Max.</p>';
  } else {
    $html .= '<ul class="tbn-port-list tbn-port-list-tb">';
    foreach ($tb as $p) {
      $below = !empty($p['below']);
      $cls = $below ? 'tbn-port-tb-link tbn-port-below' : 'tbn-port-tb-link tbn-port-ok';
      $html .= '<li class="' . $cls . '">';
      $html .= '<div class="tbn-port-line1">';
      $html .= '<span class="tbn-port-title">' . htmlspecialchars($p['title'] ?? '') . '</span>';
      if (!empty($p['peer'])) {
        $html .= ' <span class="tbn-port-peer">' . htmlspecialchars($p['peer']) . '</span>';
      }
      $html .= '</div>';
      $html .= '<div class="tbn-port-line2 tbn-muted">Host max '
        . htmlspecialchars($p['max_line'] ?? $cap['max_short'])
        . '</div>';
      $html .= '<div class="tbn-port-line3">';
      $html .= '<span class="tbn-port-spd' . ($below ? ' tbn-port-spd-warn' : ' tbn-port-spd-ok') . '">'
        . htmlspecialchars($p['trained'] !== '' ? $p['trained'] : '—')
        . '</span>';
      $html .= ' <span class="tbn-muted">' . ($below ? 'trained (below controller max)' : 'trained') . '</span>';
      $html .= '</div>';
      if ($below) {
        $html .= '<div class="tbn-port-hint tbn-muted">Single-lane is common for Thunderbolt host-to-host under Linux — not a failed install.</div>';
      }
      $html .= '</li>';
    }
    $html .= '</ul>';
  }
  $html .= '</div>';

  // Other USB SuperSpeed banks — secondary, collapsed by default
  if ($usb) {
    $n = count($usb);
    $html .= '<details class="tbn-port-box tbn-port-box-usb">';
    $html .= '<summary>Other USB SuperSpeed (' . (int)$n . ' bank'
      . ($n === 1 ? '' : 's') . ')</summary>';
    $html .= '<p class="tbn-port-usb-note tbn-muted">Type-C may show a Thunderbolt icon but these are USB data paths, not full Thunderbolt host networking.</p>';
    $html .= '<ul class="tbn-port-list tbn-port-list-usb">';
    foreach ($usb as $p) {
      $html .= '<li class="tbn-port-' . htmlspecialchars($p['kind'] ?? 'usb-ss') . '">';
      $html .= '<span class="tbn-port-spd tbn-port-spd-usb">'
        . htmlspecialchars($p['speed_short'] ?? '')
        . '</span> ';
      $html .= '<span class="tbn-port-detail">'
        . htmlspecialchars($p['detail'] ?? $p['title'] ?? '')
        . '</span>';
      $html .= '</li>';
    }
    $html .= '</ul></details>';
  }

  $html .= '</div>';
  return $html;
}

/**
 * Assess trained link vs local controller capability (cable / training hint).
 *
 * Returns:
 *   level      ok|warn|info|unknown
 *   label      short badge text
 *   note       plain explanation (capability vs trained path)
 *   likely     most probable limit (when known) — not a hard cable EEPROM read
 *   suggestion what to try next
 *   less_likely other possible causes
 *   detail     compact title/tooltip string
 *   controller capability array (max potential)
 *
 * Linux does not expose a reliable cable SKU. Trained 20G×1-lane on a dual-capable
 * host is common for Thunderbolt host-to-host under firmware ICM — treat as normal, not
 * a broken install. Cable is only one of several factors.
 */
function tbn_link_quality(array $remote, array $status = []) {
  $cap = tbn_controller_capability();
  $empty = [
    'level' => 'unknown',
    'label' => 'No link',
    // lead = short complete sentence for table cells (never mid-word "…")
    'lead' => '',
    'note' => '',
    'likely' => '',
    'suggestion' => '',
    'less_likely' => '',
    'detail' => '',
    'controller' => $cap,
  ];

  $ctrl = $cap['label'];
  $ctrl_can_dual = !empty($cap['can_dual']);
  $max_short = $cap['max_short'];

  $rx = $remote['rx_speed'] ?? '';
  $tx = $remote['tx_speed'] ?? '';
  $rl = isset($remote['rx_lanes']) ? (int)$remote['rx_lanes'] : 0;
  $tl = isset($remote['tx_lanes']) ? (int)$remote['tx_lanes'] : 0;
  $gbps = 0.0;
  if (preg_match('/([\d.]+)\s*Gb/i', $rx, $m)) {
    $gbps = (float)$m[1];
  }
  // Symmetric low rate both ways strengthens “path/cable” over one-sided host bug
  $tx_gbps = 0.0;
  if (preg_match('/([\d.]+)\s*Gb/i', $tx, $m)) {
    $tx_gbps = (float)$m[1];
  }
  $symmetric_20 = ($gbps > 0 && $gbps <= 20.5 && ($tx_gbps <= 0 || $tx_gbps <= 20.5));

  if ($rx === '' && $tx === '' && $rl === 0) {
    $empty['detail'] = "Controller: {$ctrl}; max {$max_short}";
    return $empty;
  }

  $rx_fmt = tbn_format_gbps($rx);
  $tx_fmt = tbn_format_gbps($tx);
  $trained = $rx_fmt;
  if ($rl > 0) {
    $trained .= ($trained !== '' ? ' · ' : '') . $rl . '-lane';
  }
  if ($tx_fmt !== '' && $tx_fmt !== $rx_fmt) {
    $trained .= ' (TX ' . $tx_fmt . ($tl > 0 ? ' · ' . $tl . '-lane' : '') . ')';
  }
  if ($trained === '') {
    $trained = 'linked';
  }
  $badge_trained = $rx_fmt !== ''
    ? ($rx_fmt . ($rl > 0 ? ' · ' . $rl . '-lane' : ''))
    : 'Linked';

  // Strong signal: capable controller, single-lane ~20 Gb/s both directions
  if ($ctrl_can_dual && $rl === 1 && $symmetric_20) {
    return [
      'level' => 'warn',
      'label' => $badge_trained,
      'status' => 'Single-lane',
      'lead' => 'Trained ' . $trained . ' (controller max ' . $max_short . ').',
      'note' => 'Host class is ' . $max_short . '. This path trained at ' . $trained
        . '. TCP/SMB in the ~10–15 Gbit/s range is normal for 1-lane host-net.',
      'likely' => 'Common on Thunderbolt 4/USB4 host-to-host under Linux (firmware ICM). Not a failed plugin install.',
      'suggestion' => 'Prefer one cable per peer path; set MTU 9000 on both ends for bulk. Dual-cable bonding needs two live netdevs (roadmap for same-peer multi-path).',
      'less_likely' => 'Cable/port can still matter for some pairs, but a short certified Thunderbolt 4 cable often stays 1-lane too.',
      'detail' => 'Max ' . $max_short . '; trained ' . $trained . '; single-lane host-net common',
      'controller' => $cap,
      'trained' => $trained,
      'max_short' => $max_short,
    ];
  }

  if ($rl >= 2 && $gbps >= 30) {
    return [
      'level' => 'ok',
      'label' => 'Near max',
      'status' => 'Healthy',
      'lead' => 'Dual-lane high rate — looks healthy for this host.',
      'note' => 'Trained ' . $trained . ' · host max ' . $max_short . '.',
      'likely' => '',
      'suggestion' => '',
      'less_likely' => '',
      'detail' => 'Max ' . $max_short . '; trained ' . $trained,
      'controller' => $cap,
      'trained' => $trained,
      'max_short' => $max_short,
    ];
  }

  if ($ctrl_can_dual && $rl >= 2 && $gbps > 0 && $gbps < 30) {
    return [
      'level' => 'info',
      'label' => $badge_trained,
      'status' => 'Dual-lane',
      'lead' => 'Trained ' . $trained . ' · controller max ' . $max_short . '.',
      'note' => 'Two lanes trained; peak rate still below controller class ceiling.',
      'likely' => 'Path/peer/cable can still limit signaling rate under the dual-lane width.',
      'suggestion' => 'MTU 9000 both ends helps CPU at high PPS; it does not raise trained Gb/s.',
      'less_likely' => '',
      'detail' => 'Max ' . $max_short . '; trained ' . $trained,
      'controller' => $cap,
      'trained' => $trained,
      'max_short' => $max_short,
    ];
  }

  if ($gbps > 0) {
    return [
      'level' => 'info',
      'label' => $badge_trained,
      'status' => 'Linked',
      'lead' => 'Trained ' . $trained . ' · host max ' . $max_short . '.',
      'note' => '',
      'likely' => '',
      'suggestion' => '',
      'less_likely' => '',
      'detail' => 'Max ' . $max_short . '; trained ' . $trained,
      'controller' => $cap,
      'trained' => $trained,
      'max_short' => $max_short,
    ];
  }

  return [
    'level' => 'info',
    'label' => 'Linked',
    'status' => 'Linked',
    'lead' => 'Host max ' . $max_short . '.',
    'note' => '',
    'likely' => '',
    'suggestion' => '',
    'less_likely' => '',
    'detail' => 'Max ' . $max_short . ' (' . $ctrl . ')',
    'controller' => $cap,
    'trained' => '',
    'max_short' => $max_short,
  ];
}

/**
 * HTML for REMOTE link-quality cell — matches LOCAL styling language.
 * Status badge + trained rate + host max; details collapsed.
 */
function tbn_link_quality_html(array $q, $compact = true) {
  $level = htmlspecialchars($q['level'] ?? 'info');
  $label = htmlspecialchars($q['label'] ?? '');
  $status = htmlspecialchars($q['status'] ?? '');
  $detail = htmlspecialchars($q['detail'] ?? '');
  $max = htmlspecialchars($q['max_short'] ?? (($q['controller']['max_short'] ?? '') ?: ''));
  $trained = htmlspecialchars($q['trained'] ?? $q['label'] ?? '');

  $html = '<div class="tbn-q-remote tbn-q-' . $level . '">';
  if ($status !== '') {
    $html .= '<span class="tbn-badge tbn-badge-' . $level . '" title="' . $detail . '">'
      . $status . '</span> ';
  }
  $html .= '<span class="tbn-badge tbn-badge-rate tbn-badge-' . $level . '" title="' . $detail . '">'
    . $label . '</span>';

  if ($max !== '' && ($q['level'] ?? '') !== 'unknown') {
    $html .= '<p class="tbn-q-maxline tbn-muted">Host max ' . $max . '</p>';
  }

  $likely = trim((string)($q['likely'] ?? ''));
  $note = trim((string)($q['note'] ?? ''));
  $suggestion = trim((string)($q['suggestion'] ?? ''));
  $less = trim((string)($q['less_likely'] ?? ''));
  $lead = trim((string)($q['lead'] ?? ''));
  if ($lead === '') {
    $lead = $likely !== '' ? $likely : $note;
  }

  if ($lead !== '') {
    $html .= '<p class="tbn-quality-lead">' . htmlspecialchars($lead) . '</p>';
  }

  $has_body = ($likely !== '' || $note !== '' || $suggestion !== '' || $less !== '');
  if ($has_body && $compact) {
    $html .= '<details class="tbn-quality-details">';
    $html .= '<summary>Why &amp; what to try</summary>';
    $html .= '<div class="tbn-quality-advice">';
    if ($note !== '' && $note !== $lead) {
      $html .= '<p class="tbn-quality-note">' . htmlspecialchars($note) . '</p>';
    }
    if ($likely !== '') {
      $html .= '<p class="tbn-quality-likely"><strong>Likely:</strong> '
        . htmlspecialchars($likely) . '</p>';
    }
    if ($suggestion !== '') {
      $html .= '<p class="tbn-quality-suggest"><strong>Try:</strong> '
        . htmlspecialchars($suggestion) . '</p>';
    }
    if ($less !== '') {
      $html .= '<p class="tbn-quality-less tbn-muted">' . htmlspecialchars($less) . '</p>';
    }
    $html .= '</div></details>';
  } elseif ($has_body && !$compact) {
    $html .= '<div class="tbn-quality-advice">';
    if ($note !== '') {
      $html .= '<p class="tbn-quality-note">' . htmlspecialchars($note) . '</p>';
    }
    if ($likely !== '') {
      $html .= '<p class="tbn-quality-likely"><strong>Likely:</strong> '
        . htmlspecialchars($likely) . '</p>';
    }
    if ($suggestion !== '') {
      $html .= '<p class="tbn-quality-suggest"><strong>Try:</strong> '
        . htmlspecialchars($suggestion) . '</p>';
    }
    if ($less !== '') {
      $html .= '<p class="tbn-quality-less tbn-muted">' . htmlspecialchars($less) . '</p>';
    }
    $html .= '</div>';
  }

  $html .= '</div>';
  return $html;
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

/**
 * Echo IPv4 netmask <select> (must live in tbn-lib — iface page is require'd per tab).
 */
function tbn_render_netmask_select($name, $nm_dotted, $masks) {
  echo '<select name="' . htmlspecialchars($name) . '" class="narrow">';
  foreach ($masks as $mask => $pref) {
    if (function_exists('mk_option')) {
      echo mk_option($nm_dotted, $mask, $pref);
    } else {
      $sel = ((string)$nm_dotted === (string)$mask) ? ' selected' : '';
      echo '<option value="' . htmlspecialchars($mask) . '"' . $sel . '>'
        . htmlspecialchars($pref) . "</option>\n";
    }
  }
  echo '</select>';
}

/** Global (and optional link-local) IPv6 addresses on iface. */
function tbn_iface_addrs6($if, $include_ll = false) {
  $addrs = [];
  $cmd = 'ip -6 -o addr show dev ' . escapeshellarg($if) . ' 2>/dev/null';
  $lines = [];
  @exec($cmd, $lines);
  foreach ($lines as $line) {
    if (preg_match('/inet6\s+(\S+)/', $line, $m)) {
      $a = $m[1];
      if (!$include_ll && strpos($a, 'fe80:') === 0) {
        continue;
      }
      $addrs[] = $a;
    }
  }
  return $addrs;
}

/**
 * Parse BOND_MEMBERS cfg: space-separated thunderboltN list.
 * Empty = all live thunderbolt* (legacy behavior).
 */
function tbn_parse_bond_members($raw, $fallback_all = true) {
  $raw = trim((string)$raw);
  $all = tbn_list_tb_iface_names();
  if ($raw === '') {
    return $fallback_all ? $all : [];
  }
  $want = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
  $out = [];
  foreach ($want as $m) {
    if (preg_match('/^thunderbolt\d+$/', $m) && in_array($m, $all, true)) {
      $out[] = $m;
    }
  }
  return array_values(array_unique($out));
}

function tbn_domain_security() {
  return tbn_sysfs_str('/sys/bus/thunderbolt/devices/domain0/security');
}

function tbn_modules_loaded() {
  $txt = @file_get_contents('/proc/modules');
  if ($txt === false) {
    return [
      'thunderbolt' => false,
      'thunderbolt_net' => false,
      'thunderbolt_stream' => false,
    ];
  }
  // /proc/modules uses underscores; modprobe may accept thunderbolt-stream
  $stream = (strpos($txt, 'thunderbolt_stream ') !== false
    || preg_match('/^thunderbolt_stream\s/m', $txt)
    || strpos($txt, 'thunderbolt-stream ') !== false);
  return [
    'thunderbolt' => (strpos($txt, 'thunderbolt ') !== false || preg_match('/^thunderbolt\s/m', $txt)),
    'thunderbolt_net' => (strpos($txt, 'thunderbolt_net ') !== false || preg_match('/^thunderbolt_net\s/m', $txt)),
    'thunderbolt_stream' => $stream,
  ];
}

/**
 * Whether the running kernel can load USB4STREAM (module present in tree).
 * Module name: thunderbolt_stream / thunderbolt-stream
 * (mainline Linux kernel ~7.2+; not Unraid product 7.2.x).
 */
function tbn_usb4stream_module_available() {
  static $cached = null;
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
  $cached = ($rc2 === 0 && $out);
  return $cached;
}

/**
 * USB4STREAM status for UI / JSON (raw host↔host stream, not IP).
 *
 * Returns: available, loaded, devices[], configfs, note
 */
function tbn_usb4stream_status() {
  $mods = tbn_modules_loaded();
  $available = tbn_usb4stream_module_available();
  $loaded = !empty($mods['thunderbolt_stream']);
  $devs = [];
  foreach (@glob('/dev/tbstream*') ?: [] as $p) {
    $devs[] = basename($p);
  }
  sort($devs);
  $configfs = is_dir('/sys/kernel/config/thunderbolt/stream')
    || is_dir('/sys/kernel/config/usb4stream');
  $kver = trim((string)@shell_exec('uname -r 2>/dev/null'));
  if ($kver === '') {
    $kver = php_uname('r');
  }
  $note = '';
  if (!$available) {
    // Mainline landed stream in Linux kernel ~7.2 — NOT Unraid product 7.2.x
    $note = 'No thunderbolt_stream in this kernel'
      . ($kver !== '' ? ' (' . $kver . ')' : '')
      . '. USB4STREAM needs a kernel build that ships the module (mainline ~7.2+); '
      . 'Unraid version numbers do not imply it. thunderbolt_net (IP/tbn) still works.';
  } elseif (!$loaded) {
    $note = 'Module available but not loaded'
      . ($kver !== '' ? ' on ' . $kver : '')
      . '. Enable USB4STREAM under Advanced and Apply, or modprobe thunderbolt-stream.';
  } elseif (!$devs) {
    $note = 'Module loaded; no /dev/tbstream* yet — configure stream via configfs when a peer is up (see docs/usb4stream.md).';
  } else {
    $note = 'USB4STREAM devices present: ' . implode(', ', $devs);
  }
  return [
    'available' => $available,
    'loaded' => $loaded,
    'devices' => $devs,
    'configfs' => $configfs,
    'kernel' => $kver,
    'note' => $note,
  ];
}

/**
 * Probe for Thunderbolt-family host controller hardware (Thunderbolt 3/4/5, USB4 host router — not peer cable).
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
    if (preg_match('/thunderbolt|USB4|Maple Ridge|Barlow Ridge|NHI|JHL|Alpine Ridge|Titan Ridge|Ice Lake.*Thunderbolt/i', $line)) {
      $pci[] = $line;
    }
  }
  // Also match known Intel Thunderbolt NHI device IDs if description is sparse
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
  $stream = tbn_usb4stream_status();
  $out[] = 'usb4stream_available: ' . (!empty($stream['available']) ? 'yes' : 'no');
  $out[] = 'module_thunderbolt_stream: ' . (!empty($stream['loaded']) ? 'loaded' : 'not loaded');
  $out[] = 'usb4stream_devices: ' . (implode(' ', $stream['devices'] ?? []) ?: '(none)');
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
  // FRR installed → OpenFabric on (unless user explicitly turned it off)
  if (function_exists('tbn_of_maybe_auto_enable_from_frr')) {
    $cfg = tbn_of_maybe_auto_enable_from_frr($cfg);
  }
  // Peer link check defaults on: ensure a token exists and is saved once
  if (function_exists('tbn_mesh_ensure_token') && function_exists('tbn_write_global_cfg')) {
    $before = trim((string)($cfg['mesh_token'] ?? ''));
    tbn_mesh_ensure_token($cfg);
    if (($cfg['mesh_report'] ?? 'yes') === 'yes' && $before === '' && trim((string)($cfg['mesh_token'] ?? '')) !== '') {
      @tbn_write_global_cfg($cfg);
    }
  }
  $probe = tbn_hardware_probe();
  $links = tbn_link_summaries();
  // Plug-and-play: persist last-seen peers; re-apply remembered listening prefs
  $peers = tbn_remember_live_peers($links);
  tbn_reconcile_listening_from_memory($links);
  // Refresh summaries after reconcile so "listening" flags match network-extra
  $links = tbn_link_summaries();
  $peers = tbn_load_peers_memory();
  $mesh = null;
  $mesh_file = __DIR__ . '/tbn-mesh.php';
  if (is_file($mesh_file)) {
    require_once $mesh_file;
    if (function_exists('tbn_mesh_maybe_poll')) {
      // Best-effort poll when due (non-blocking enough for UI load)
      @tbn_mesh_maybe_poll($cfg, false);
      $peers = tbn_load_peers_memory();
    }
    $mesh = [
      'enabled' => function_exists('tbn_mesh_enabled') ? tbn_mesh_enabled($cfg) : false,
      'host_id' => function_exists('tbn_mesh_host_id') ? tbn_mesh_host_id() : '',
      'hosts' => function_exists('tbn_mesh_cached_hosts') ? tbn_mesh_cached_hosts() : [],
    ];
  }
  return [
    'hostname' => gethostname() ?: '',
    'time' => date('c'),
    'security' => tbn_domain_security(),
    'modules' => tbn_modules_loaded(),
    'usb4stream' => tbn_usb4stream_status(),
    'hardware' => $probe,
    'has_hardware' => !empty($probe['has_hardware']),
    'local_controller' => tbn_sysfs_str('/sys/bus/thunderbolt/devices/0-0/device_name'),
    'local_manufacturer' => tbn_sysfs_str('/sys/bus/thunderbolt/devices/0-0/vendor_name'),
    'devices' => tbn_list_tb_devices(),
    'netdevs' => tbn_list_netdevs(),
    'links' => $links,
    'peers_memory' => $peers,
    'include_interfaces' => tbn_read_include_interfaces(),
    'cfg' => $cfg,
    'openfabric' => function_exists('tbn_of_status') ? tbn_of_status() : tbn_of_status_lazy(),
    'mesh' => $mesh,
  ];
}

/**
 * Lazy-load OpenFabric helpers (keeps tbn-lib usable if of file missing mid-upgrade).
 */
function tbn_of_status_lazy() {
  $of = '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-openfabric.php';
  if (is_file($of)) {
    require_once $of;
    if (function_exists('tbn_of_status')) {
      return tbn_of_status();
    }
  }
  return [
    'enabled' => true,
    'mode' => 'openfabric-want-frr',
    'frr' => ['present' => false, 'note' => 'OpenFabric module not loaded'],
    'router_id' => '',
    'net' => '',
    'ifaces' => [],
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
  $extra_lines = [];
  if (is_readable($path)) {
    $raw = (string)file_get_contents($path);
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
      $trim = trim($line);
      if ($trim === '' || $trim[0] === '#' || $trim[0] === ';') {
        // Keep comments only if they are not ours (preserve unknown file structure lightly)
        if ($trim !== '') {
          $extra_lines[] = $line;
        }
        continue;
      }
      if (preg_match('/^include_interfaces=/', $trim)) {
        continue;
      }
      if (preg_match('/^exclude_interfaces="([^"]*)"/', $trim, $m)) {
        $exclude = $m[1];
        continue;
      }
      if (preg_match('/^exclude_interfaces=/', $trim)) {
        continue;
      }
      // Preserve any future/other keys other plugins may add
      $extra_lines[] = $line;
    }
  }
  $dir = dirname($path);
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  $body = 'include_interfaces="' . implode(' ', $ifaces) . "\"\n";
  $body .= 'exclude_interfaces="' . $exclude . "\"\n";
  foreach ($extra_lines as $el) {
    $body .= rtrim($el) . "\n";
  }
  return @file_put_contents($path, $body) !== false;
}

/**
 * Ensure/remove Thunderbolt interfaces from include list based on cfg + live netdevs.
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
 * Desired thunderbolt_net e2e= bit from plugin cfg (product default: off).
 */
function tbn_desired_e2e_bit(array $cfg = null) {
  if ($cfg === null) {
    $cfg = tbn_load_cfg();
  }
  return (($cfg['e2e_flow_control'] ?? 'no') === 'yes') ? '1' : '0';
}

/**
 * Persist e2e for next boot on Unraid flash AND live /etc.
 * /etc alone is RAM — reboot lost options and kernel defaulted e2e=1 (flaky cross-host).
 *
 * @return string e2e bit written ('0'|'1')
 */
function tbn_persist_e2e_modprobe(array $cfg = null) {
  $e2e = tbn_desired_e2e_bit($cfg);
  $line = "options thunderbolt_net e2e={$e2e}\n";
  foreach (['/boot/config/modprobe.d', '/etc/modprobe.d'] as $dir) {
    if (!is_dir($dir)) {
      @mkdir($dir, 0755, true);
    }
    @file_put_contents($dir . '/thunderbolt_net.conf', $line);
  }
  return $e2e;
}

/**
 * Live e2e module parameter (Y/N/1/0) or '' if module not loaded.
 */
function tbn_live_e2e_bit() {
  $p = '/sys/module/thunderbolt_net/parameters/e2e';
  if (!is_readable($p)) {
    return '';
  }
  $v = strtoupper(trim((string)@file_get_contents($p)));
  if ($v === 'Y' || $v === '1') {
    return '1';
  }
  if ($v === 'N' || $v === '0') {
    return '0';
  }
  return '';
}

/**
 * Ensure thunderbolt_net is loaded with the configured e2e bit.
 * If already loaded with the wrong bit, reload the module (disrupts TB netdevs briefly).
 *
 * @return array{ok:bool,e2e:string,reloaded:bool,error?:string}
 */
function tbn_ensure_e2e_param(array $cfg = null) {
  $want = tbn_persist_e2e_modprobe($cfg);
  $have = tbn_live_e2e_bit();
  if ($have === $want) {
    return ['ok' => true, 'e2e' => $want, 'reloaded' => false];
  }
  // Not loaded yet — first modprobe picks up flash options + explicit arg
  if ($have === '') {
    @exec('modprobe thunderbolt 2>/dev/null');
    @exec('modprobe thunderbolt_net e2e=' . escapeshellarg($want) . ' 2>/dev/null');
    $have = tbn_live_e2e_bit();
    return [
      'ok' => ($have === $want || $have === ''),
      'e2e' => $have !== '' ? $have : $want,
      'reloaded' => false,
    ];
  }
  // Wrong live bit — reload (only thunderbolt_net; never unbind NHI)
  foreach (tbn_list_tb_iface_names() as $if) {
    @exec('ip link set ' . escapeshellarg($if) . ' down 2>/dev/null');
  }
  @exec('rmmod thunderbolt_net 2>/dev/null', $o, $rc);
  usleep(300000);
  @exec('modprobe thunderbolt_net e2e=' . escapeshellarg($want) . ' 2>/dev/null', $o2, $rc2);
  $have = tbn_live_e2e_bit();
  if ($have !== $want) {
    return [
      'ok' => false,
      'e2e' => $have !== '' ? $have : '?',
      'reloaded' => true,
      'error' => 'thunderbolt_net e2e still ' . ($have !== '' ? $have : 'unset') . ' after reload (want ' . $want . ')',
    ];
  }
  return ['ok' => true, 'e2e' => $want, 'reloaded' => true];
}

/**
 * Load kernel modules (no NHI unbind — that can wedge Maple Ridge).
 */
function tbn_load_modules() {
  $cfg = tbn_load_cfg();
  tbn_persist_e2e_modprobe($cfg);
  @exec('modprobe thunderbolt 2>/dev/null');
  // Prefer ensure (reload if wrong) over a no-op second modprobe that cannot change e2e
  tbn_ensure_e2e_param($cfg);
  // USB4STREAM — optional raw path; never fails the net stack if missing
  if (($cfg['enable_usb4stream'] ?? 'no') === 'yes' && tbn_usb4stream_module_available()) {
    @exec('modprobe thunderbolt_stream 2>/dev/null');
    @exec('modprobe thunderbolt-stream 2>/dev/null');
  }
  return tbn_modules_loaded();
}

/**
 * Bring configured Thunderbolt interfaces administratively up (helps carrier / ping).
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
  // Flush first — replace leaves other subnets/routes stacked on the same iface
  tbn_iface_flush_l3($if, 4);
  @exec("ip -4 addr add {$target} dev {$ife} 2>/dev/null", $o, $rc);
  // Legacy global: never_default=yes means do not install default (same as DEFAULT_ROUTE=no)
  $allow_default = (($cfg['default_route'] ?? '') === 'yes')
    || (($cfg['never_default'] ?? 'yes') === 'no');
  if ($cfg['ip_gateway'] !== '' && $allow_default) {
    $gw = escapeshellarg($cfg['ip_gateway']);
    @exec("ip route replace default via {$gw} dev {$ife} 2>/dev/null");
  } else {
    tbn_iface_drop_default_routes($if, 4);
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

/**
 * Numeric index from thunderboltN (used for unique default subnets per link).
 */
function tbn_iface_index($if) {
  if (preg_match('/(\d+)$/', (string)$if, $m)) {
    return (int)$m[1];
  }
  return 0;
}

/**
 * Supported IPv4 address plans (per-link and global default).
 * small-lan: /24, room for VMs/aliases — product default.
 * p2p: /30, two usable hosts — pure host↔host pipe.
 * custom: keep whatever IP/mask the user sets.
 */
function tbn_address_plans() {
  return [
    'small-lan' => [
      'label' => 'Small LAN (/24)',
      'prefix' => 24,
      'mask' => '255.255.255.0',
    ],
    'p2p' => [
      'label' => 'Point-to-point (/30)',
      'prefix' => 30,
      'mask' => '255.255.255.252',
    ],
    'custom' => [
      'label' => 'Custom',
      'prefix' => null,
      'mask' => null,
    ],
  ];
}

function tbn_normalize_address_plan($plan) {
  $plan = strtolower(trim((string)$plan));
  $plans = tbn_address_plans();
  return isset($plans[$plan]) ? $plan : 'small-lan';
}

/**
 * Suggested static IPv4 for a plan + iface (Unraid = .2, peer often .1).
 * Each thunderboltN gets third-octet N so dual peers do not share one /24.
 */
function tbn_suggest_address($if, $plan = 'small-lan') {
  $plan = tbn_normalize_address_plan($plan);
  $n = tbn_iface_index($if);
  $base = '10.255.' . $n;
  if ($plan === 'p2p') {
    return [
      'IPADDR' => $base . '.2',
      'NETMASK' => '255.255.255.252',
      'prefix' => 30,
      'peer_hint' => $base . '.1',
      'network' => $base . '.0/30',
    ];
  }
  // small-lan (and custom suggestions when resetting)
  return [
    'IPADDR' => $base . '.2',
    'NETMASK' => '255.255.255.0',
    'prefix' => 24,
    'peer_hint' => $base . '.1',
    'network' => $base . '.0/24',
  ];
}

function tbn_iface_defaults($if = 'thunderbolt0') {
  $g = tbn_load_cfg();
  $plan = tbn_normalize_address_plan($g['address_plan'] ?? 'small-lan');
  if ($plan === 'custom') {
    // Custom global default still needs a starting suggestion for empty forms.
    $plan = 'small-lan';
  }
  $sug = tbn_suggest_address($if, $plan);
  return [
    'DESCRIPTION' => '',
    'ENABLE' => 'yes',
    'BONDING' => 'no',
    'BONDING_MODE' => 'active-backup',
    'BOND_NAME' => 'bond-tb0',
    // Space-separated thunderboltN members; empty = all live Thunderbolt interfaces
    'BOND_MEMBERS' => '',
    'BRIDGING' => 'no',
    'BR_NAME' => 'br-tb0',
    // VLANs on this Thunderbolt interface (subinterfaces if.VID) — eth-like subset
    'VLAN_ENABLE' => 'no',
    'VLAN_LIST' => '',
    'PROTOCOL' => 'ipv4',
    'USE_DHCP' => 'no',
    // Address plan: small-lan | p2p | custom — see docs/addressing.md
    'ADDRESS_PLAN' => tbn_normalize_address_plan($g['address_plan'] ?? 'small-lan'),
    'IPADDR' => $sug['IPADDR'],
    'NETMASK' => (string)$sug['prefix'],
    'GATEWAY' => '',
    // Install a system default route via this iface? Default no (peer-local only).
    'DEFAULT_ROUTE' => 'no',
    // DHCP server pool (used when USE_DHCP=server); empty = derive from IPADDR/NETMASK
    'DHCP_POOL_START' => '',
    'DHCP_POOL_END' => '',
    // Share Unraid uplink (br0/eth0/wlan0/…) with peers on this underlay — opposite of DEFAULT_ROUTE.
    'NAT_ENABLE' => 'no',
    'NAT_UPLINK' => 'auto',
    // IPv6 (shown when PROTOCOL is ipv6 or ipv4+ipv6)
    'USE_DHCP6' => 'no',
    'IPADDR6' => '',
    'NETMASK6' => '64',
    'GATEWAY6' => '',
    'DEFAULT_ROUTE6' => 'no',
    // MTU_MODE: default (kernel 1500) | 9000 (optional jumbo, both ends) | custom
    // Product default 1500 for first-plug compatibility; 9000 only when both ends set it.
    'MTU_MODE' => 'default',
    'MTU' => '9000',
    'USE_MTU' => 'no', // kept in sync with MTU_MODE for older logic
    'INCLUDE_LISTENING' => 'no',
    // OpenFabric: yes | no | passive (default participate when global on)
    'OPENFABRIC_PARTICIPATE' => 'yes',
    'OPENFABRIC_METRIC_MODE' => 'auto',
    'OPENFABRIC_METRIC' => '',
  ];
}

/**
 * Driver/kernel MTU limits for a netdev (from `ip -d link`).
 * thunderbolt_net commonly reports maxmtu ~65522 — jumbo is fine.
 *
 * @return array{min:int,max:int}
 */
function tbn_iface_mtu_limits($if) {
  $min = 68;
  $max = 65522;
  if ($if === '' || !is_dir('/sys/class/net/' . $if)) {
    return ['min' => $min, 'max' => $max];
  }
  $out = [];
  @exec('ip -d link show dev ' . escapeshellarg($if) . ' 2>/dev/null', $out);
  $blob = implode(' ', $out);
  if (preg_match('/\bminmtu\s+(\d+)/', $blob, $m)) {
    $min = max(68, (int)$m[1]);
  }
  if (preg_match('/\bmaxmtu\s+(\d+)/', $blob, $m)) {
    $max = max($min, (int)$m[1]);
  }
  return ['min' => $min, 'max' => $max];
}

/**
 * Normalize MTU_MODE + USE_MTU/MTU (legacy) → mode string.
 */
function tbn_normalize_mtu_mode(array $cfg) {
  // eth0-style checkbox wins when present (form posts USE_MTU=yes|no)
  $use = strtolower(trim((string)($cfg['USE_MTU'] ?? '')));
  if ($use === 'no' || $use === '0') {
    return 'default';
  }
  if ($use === 'yes' || $use === '1') {
    $v = (int)($cfg['MTU'] ?? 0);
    if ($v === 9000) {
      return '9000';
    }
    if ($v >= 68) {
      return 'custom';
    }
    return 'default';
  }
  $mode = strtolower(trim((string)($cfg['MTU_MODE'] ?? '')));
  if (in_array($mode, ['default', '9000', 'custom'], true)) {
    return $mode;
  }
  return 'default';
}

/**
 * Desired MTU integer, or null to leave kernel default (typically 1500).
 */
function tbn_desired_mtu(array $cfg) {
  $mode = tbn_normalize_mtu_mode($cfg);
  if ($mode === 'default') {
    return null;
  }
  if ($mode === '9000') {
    return 9000;
  }
  $v = (int)($cfg['MTU'] ?? 0);
  if ($v < 68) {
    return null;
  }
  return $v;
}

/**
 * Apply MTU to a live netdev. Clamps to driver min/max.
 * $mtu null → set 1500 (explicit kernel default) so re-Apply undoes jumbo.
 */
function tbn_apply_mtu($if, $mtu) {
  if ($if === '' || !is_dir('/sys/class/net/' . $if)) {
    return false;
  }
  $lim = tbn_iface_mtu_limits($if);
  if ($mtu === null) {
    $mtu = 1500;
  }
  $mtu = (int)$mtu;
  if ($mtu < $lim['min']) {
    $mtu = $lim['min'];
  }
  if ($mtu > $lim['max']) {
    $mtu = $lim['max'];
  }
  // Cap UI-ish jumbo at 9198 for "normal" ethernet tools unless driver allows more;
  // still honor higher if user chose custom within maxmtu (Thunderbolt path supports large frames).
  @exec('ip link set dev ' . escapeshellarg($if) . ' mtu ' . (int)$mtu . ' 2>/dev/null', $o, $rc);
  return $rc === 0;
}

/**
 * Live MTU label for overview: "1500 (kernel default)" vs "9000".
 */
function tbn_format_mtu_live($mtu, $configured_mode = '') {
  $mtu = trim((string)$mtu);
  if ($mtu === '') {
    return '—';
  }
  $n = (int)$mtu;
  if ($n === 1500 && ($configured_mode === '' || $configured_mode === 'default')) {
    return '1500 (kernel default)';
  }
  if ($n === 9000) {
    return '9000 (jumbo)';
  }
  return (string)$n;
}

/**
 * Public docs on GitHub (same pattern as StorageGuard DOCS.md — not the Plugins blurb).
 */
function tbn_github_repo() {
  return 'https://github.com/ibigsnet/ThunderboltNet';
}

/**
 * Human-readable docs URL (blob/main). $path is repo-relative, e.g. DOCS.md or docs/addressing.md
 */

/**
 * Visible note: offline recovery file on flash (Safe Mode / no plugins).
 * Shown in WebUI while healthy so the path is learned before an emergency.
 */
function tbn_offline_recovery_note_html() {
  $path = '/boot/config/plugins/ThunderboltNet/ThunderboltNet-RECOVERY.txt';
  $on_flash = is_readable($path);
  $status = $on_flash
    ? 'Present on this flash (also left after uninstall).'
    : 'Will be written to flash on next successful plugin install/update.';
  return '<div class="tbn-offline-recovery" role="note">'
    . '<strong>Safe Mode / no plugins — Thunderbolt offline help</strong>'
    . '<p class="tbn-muted">If you boot Unraid <em>without plugins</em> and still need host Thunderbolt networking, '
    . 'the plugin UI will not load. Use the plain-text sheet on the USB flash '
    . '(keyword <strong>Thunderbolt</strong>):</p>'
    . '<pre class="tbn-offline-cmd">find /boot -iname \'*Thunderbolt*\' 2&gt;/dev/null' . "\n"
    . 'cat /boot/config/plugins/ThunderboltNet/ThunderboltNet-RECOVERY.txt</pre>'
    . '<p class="tbn-muted">' . htmlspecialchars($status) . ' '
    . 'Optional: <code>man -l</code> that path if <code>man</code> exists. '
    . 'This is documentation only — it does not auto-configure the link. '
    . tbn_docs_more_html('docs/safe-mode-recovery.md', 'Full Safe Mode guide ↗')
    . '</p></div>';
}

function tbn_docs_url($path = 'DOCS.md') {
  $path = ltrim((string)$path, '/');
  if ($path === '') {
    $path = 'DOCS.md';
  }
  return tbn_github_repo() . '/blob/main/' . $path;
}

/**
 * Compact docs nav for Settings pages (overview + tbnN).
 * $active: overview | iface | addressing | speeds | requirements | topology | troubleshoot
 */
function tbn_docs_bar_html($active = 'overview') {
  $links = [
    'guide' => ['DOCS.md', 'Docs home'],
    'drivers' => ['docs/driver-options.md', 'Driver options'],
    'peers' => ['docs/peer-scenarios.md', 'Peer scenarios'],
    'addressing' => ['docs/addressing.md', 'Addressing'],
    'nat' => ['docs/nat-share-uplink.md', 'NAT / share uplink'],
    'mtu' => ['docs/mtu-and-throughput.md', 'MTU & throughput'],
    'speeds' => ['docs/standards-and-speeds.md', 'Standards & speeds'],
    'ports' => ['docs/port-icons.md', 'Port icons'],
    'requirements' => ['docs/requirements.md', 'Requirements'],
    'topology' => ['docs/links-and-topology.md', 'Links & topology'],
    'routing' => ['docs/routing-openfabric.md', 'OpenFabric / FRR'],
    'troubleshoot' => ['docs/troubleshooting.md', 'Troubleshooting'],
    'safemode' => ['docs/safe-mode-recovery.md', 'Safe Mode / offline'],
  ];
  $parts = [];
  foreach ($links as $key => $pair) {
    list($path, $label) = $pair;
    $cls = ($key === $active || ($active === 'overview' && $key === 'guide') || ($active === 'iface' && $key === 'addressing'))
      ? ' class="tbn-docs-active"' : '';
    $parts[] = '<a href="' . htmlspecialchars(tbn_docs_url($path)) . '" target="_blank" rel="noopener"'
      . $cls . '>' . htmlspecialchars($label) . '</a>';
  }
  return '<nav class="tbn-docs-bar" aria-label="Thunderbolt Net documentation">'
    . '<span class="tbn-docs-label">Documentation</span> '
    . implode(' <span class="tbn-docs-sep">·</span> ', $parts)
    . '</nav>';
}

/**
 * Short inline “more in docs” anchor for hints / helpers.
 */
/**
 * Docs link for help footers. $label is plain text (use "&" not "&amp;") — htmlspecialchars
 * runs here; pre-encoded entities double-escape and show as literal "&amp;" in the WebUI.
 */
function tbn_docs_more_html($path, $label = 'More in docs') {
  return '<a class="tbn-docs-more" href="' . htmlspecialchars(tbn_docs_url($path))
    . '" target="_blank" rel="noopener">' . htmlspecialchars($label) . '</a>';
}

/**
 * Footer line used inside blockquote.inline_help — always opens GitHub docs in a new tab.
 */
function tbn_help_docs_footer($path, $label = 'Full guide') {
  return '<br><br>' . tbn_docs_more_html($path, $label . ' ↗');
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
  // Legacy short names (pre bond-tb0 / br-tb0) → numbered defaults
  if (isset($raw['BOND_NAME']) && ($raw['BOND_NAME'] === 'bond-tb' || $raw['BOND_NAME'] === '')) {
    $raw['BOND_NAME'] = 'bond-tb0';
  }
  if (isset($raw['BR_NAME']) && ($raw['BR_NAME'] === 'br-tb' || $raw['BR_NAME'] === '')) {
    $raw['BR_NAME'] = 'br-tb0';
  }
  // Derive MTU_MODE from legacy USE_MTU/MTU when mode missing
  if (!isset($raw['MTU_MODE']) || $raw['MTU_MODE'] === '') {
    $raw['MTU_MODE'] = tbn_normalize_mtu_mode($raw);
  }
  $merged = array_merge(tbn_iface_defaults($if), $raw);
  // Keep USE_MTU in sync for any old readers
  $mode = tbn_normalize_mtu_mode($merged);
  $merged['MTU_MODE'] = $mode;
  $merged['USE_MTU'] = ($mode === 'default') ? 'no' : 'yes';
  if ($mode === '9000') {
    $merged['MTU'] = '9000';
  }
  return $merged;
}

function tbn_write_iface_cfg($if, array $cfg) {
  $dir = tbn_iface_cfg_dir();
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  $defaults = tbn_iface_defaults($if);
  $merged = array_merge($defaults, $cfg);
  $keys = array_keys($defaults);
  foreach (array_keys($merged) as $k) {
    // Persist dynamic per-VLAN keys (VLAN_10_IPADDR, …)
    if (preg_match('/^VLAN_\d+_/', $k) && !in_array($k, $keys, true)) {
      $keys[] = $k;
    }
  }
  $lines = ['; ThunderboltNet iface ' . $if, ''];
  foreach ($keys as $k) {
    $v = isset($merged[$k]) ? $merged[$k] : '';
    if (is_array($v)) {
      $v = implode(' ', $v);
    }
    $lines[] = $k . '="' . str_replace(['\\', '"'], ['\\\\', '\\"'], (string)$v) . '"';
  }
  return @file_put_contents(tbn_iface_cfg_path($if), implode("\n", $lines) . "\n") !== false;
}

/**
 * Stop DHCP clients on a netdev (before static apply).
 * Without this, dhcpcd started for USE_DHCP=yes keeps running after Static
 * is selected and re-adds 169.254.x.x alongside the static address.
 * Credit: Vinney (Unraid forums) — root cause on Thunderbolt Net support thread.
 *
 * $family: 4, 6, or 0 for both.
 */
function tbn_iface_stop_dhcp_clients($if, $family = 0) {
  if ($if === '' || !is_dir('/sys/class/net/' . $if)) {
    return;
  }
  $ife = escapeshellarg($if);
  $do4 = ($family === 0 || $family === 4);
  $do6 = ($family === 0 || $family === 6);
  if ($do4) {
    @exec("dhcpcd -k {$ife} 2>/dev/null || true");
    @exec("dhclient -r {$ife} 2>/dev/null || true");
  }
  if ($do6) {
    @exec("dhcpcd -6 -k {$ife} 2>/dev/null || true");
    @exec("dhclient -6 -r {$ife} 2>/dev/null || true");
  }
}

/**
 * Drop L3 state on an iface so re-Apply is clean.
 * Kernel connected routes follow addresses; also flush residual routes on this dev
 * (stale defaults / stacked subnets from earlier applies that used "addr replace").
 *
 * $family: 4, 6, or 0 for both.
 */
function tbn_iface_flush_l3($if, $family = 0) {
  if ($if === '' || !is_dir('/sys/class/net/' . $if)) {
    return;
  }
  $ife = escapeshellarg($if);
  $do4 = ($family === 0 || $family === 4);
  $do6 = ($family === 0 || $family === 6);
  if ($do4) {
    // Global unicast only — keep link-local/peer quirks out of scope issues
    @exec("ip -4 addr flush dev {$ife} scope global 2>/dev/null");
    // Any leftover IPv4 routes bound to this device (connected should already be gone)
    @exec("ip -4 route flush dev {$ife} 2>/dev/null");
  }
  if ($do6) {
    @exec("ip -6 addr flush dev {$ife} scope global 2>/dev/null");
    @exec("ip -6 route flush dev {$ife} 2>/dev/null");
  }
}

/**
 * Remove default route(s) that egress via this iface (when DEFAULT_ROUTE=no).
 */
function tbn_iface_drop_default_routes($if, $family = 0) {
  if ($if === '' || !is_dir('/sys/class/net/' . $if)) {
    return;
  }
  $do4 = ($family === 0 || $family === 4);
  $do6 = ($family === 0 || $family === 6);
  if ($do4) {
    $lines = [];
    @exec('ip -4 route show default 2>/dev/null', $lines);
    foreach ($lines as $line) {
      if (preg_match('/\bdev\s+' . preg_quote($if, '/') . '\b/', $line)) {
        @exec('ip -4 route del ' . $line . ' 2>/dev/null');
      }
    }
  }
  if ($do6) {
    $lines = [];
    @exec('ip -6 route show default 2>/dev/null', $lines);
    foreach ($lines as $line) {
      if (preg_match('/\bdev\s+' . preg_quote($if, '/') . '\b/', $line)) {
        @exec('ip -6 route del ' . $line . ' 2>/dev/null');
      }
    }
  }
}

/**
 * Apply IPv4/IPv6 from a flat key prefix ('' for parent, or 'VLAN_10_' for VLAN).
 */
function tbn_apply_ip_block($dev, array $cfg, $prefix = '') {
  if (!is_dir('/sys/class/net/' . $dev)) {
    return;
  }
  $ife = escapeshellarg($dev);
  $proto = $cfg[$prefix . 'PROTOCOL'] ?? ($prefix === '' ? ($cfg['PROTOCOL'] ?? 'ipv4') : 'ipv4');
  // VLAN blocks only store assignment keys; inherit protocol from parent when empty
  if ($prefix !== '' && !isset($cfg[$prefix . 'PROTOCOL'])) {
    $proto = $cfg['PROTOCOL'] ?? 'ipv4';
  }
  $do4 = ($proto === 'ipv4' || $proto === 'ipv4+ipv6');
  $do6 = ($proto === 'ipv6' || $proto === 'ipv4+ipv6');
  // VLAN rows may only have USE_DHCP / IP fields without PROTOCOL — treat as dual-capable
  if ($prefix !== '' && !isset($cfg[$prefix . 'PROTOCOL'])) {
    $do4 = true;
    $do6 = (($cfg[$prefix . 'IPADDR6'] ?? '') !== '' || ($cfg[$prefix . 'USE_DHCP6'] ?? 'no') === 'yes');
  }

  // Protocol-only modes: drop the other family so old dual-stack leftovers vanish
  if ($prefix === '') {
    if (!$do4) {
      tbn_iface_flush_l3($dev, 4);
    }
    if (!$do6) {
      tbn_iface_flush_l3($dev, 6);
    }
  }

  if ($do4) {
    $use = $cfg[$prefix . 'USE_DHCP'] ?? ($prefix === '' ? ($cfg['USE_DHCP'] ?? 'no') : 'no');
    if ($use === 'yes') {
      // DHCP client will install its own addresses/routes — clear static leftovers first
      if ($prefix === '' && function_exists('tbn_dhcp_server_stop')) {
        tbn_dhcp_server_stop($dev);
      }
      tbn_iface_stop_dhcp_clients($dev, 4);
      tbn_iface_flush_l3($dev, 4);
      // -G: no default route from underlay. --nohook resolv.conf: never wipe LAN DNS
      // (empty dhcp dns-server options from a misconfigured server used to blank /etc/resolv.conf).
      @exec("dhcpcd -n -G --nohook resolv.conf {$ife} 2>/dev/null || dhcpcd -n -G {$ife} 2>/dev/null || dhclient -1 {$ife} 2>/dev/null || true");
    } elseif ($use === 'server' && $prefix === '') {
      // DHCP server: host = .1, dnsmasq serves .2–.254 (see tbn-dhcp.php)
      tbn_iface_stop_dhcp_clients($dev, 4);
      $plan = function_exists('tbn_dhcp_server_plan') ? tbn_dhcp_server_plan($dev, $cfg) : null;
      $ip = $plan ? $plan['ip'] : trim((string)($cfg['IPADDR'] ?? ''));
      $cidr = $plan ? (string)$plan['prefix'] : '24';
      tbn_iface_flush_l3($dev, 4);
      if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        @exec('ip -4 addr add ' . escapeshellarg($ip . '/' . $cidr) . " dev {$ife} 2>/dev/null");
      }
      tbn_iface_drop_default_routes($dev, 4);
      // dnsmasq start deferred to tbn_apply_iface after VLAN/bond settle
    } else {
      if ($prefix === '' && function_exists('tbn_dhcp_server_stop')) {
        tbn_dhcp_server_stop($dev);
      }
      $ip = trim((string)($cfg[$prefix . 'IPADDR'] ?? ''));
      $nm = $cfg[$prefix . 'NETMASK'] ?? ($prefix === '' ? ($cfg['NETMASK'] ?? '24') : '24');
      $cidr = preg_replace('/\D/', '', (string)$nm);
      if ($cidr === '') {
        $cidr = '24';
      }
      if (strpos((string)$nm, '.') !== false) {
        $cidr = (string)tbn_mask_to_prefix($nm);
      }
      // Kill any prior dhcpcd/dhclient so 169.254.x.x does not reappear next to static.
      tbn_iface_stop_dhcp_clients($dev, 4);
      // Always clear first: "ip addr replace" only swaps ONE address; stacked
      // 10.255.0.0/24 + 10.255.1.0/24 both stay as kernel connected routes.
      tbn_iface_flush_l3($dev, 4);
      if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $target = escapeshellarg($ip . '/' . $cidr);
        @exec("ip -4 addr add {$target} dev {$ife} 2>/dev/null");
        $gw = trim((string)($cfg[$prefix . 'GATEWAY'] ?? ''));
        $dr = $cfg[$prefix . 'DEFAULT_ROUTE'] ?? 'no';
        if ($gw !== '' && $dr === 'yes' && filter_var($gw, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
          @exec('ip -4 route replace default via ' . escapeshellarg($gw) . ' dev ' . $ife . ' 2>/dev/null');
        } else {
          tbn_iface_drop_default_routes($dev, 4);
        }
      }
    }
  }

  if ($do6) {
    $use6 = $cfg[$prefix . 'USE_DHCP6'] ?? 'no';
    if ($use6 === 'yes') {
      tbn_iface_stop_dhcp_clients($dev, 6);
      tbn_iface_flush_l3($dev, 6);
      @exec("dhcpcd -6 -n {$ife} 2>/dev/null || dhclient -6 -1 {$ife} 2>/dev/null || true");
    } elseif ($use6 === 'server' && $prefix === '') {
      tbn_iface_stop_dhcp_clients($dev, 6);
      $plan = function_exists('tbn_dhcp_server_plan') ? tbn_dhcp_server_plan($dev, $cfg) : null;
      if ($plan && !empty($plan['ula_prefix'])) {
        $ula_ip = rtrim($plan['ula_prefix'], ':') . ':1';
        tbn_iface_flush_l3($dev, 6);
        @exec('ip -6 addr add ' . escapeshellarg($ula_ip . '/64') . " dev {$ife} 2>/dev/null");
      }
      tbn_iface_drop_default_routes($dev, 6);
    } else {
      $ip6 = trim((string)($cfg[$prefix . 'IPADDR6'] ?? ''));
      $p6 = (int)preg_replace('/\D/', '', (string)($cfg[$prefix . 'NETMASK6'] ?? '64'));
      if ($p6 < 1 || $p6 > 128) {
        $p6 = 64;
      }
      tbn_iface_stop_dhcp_clients($dev, 6);
      tbn_iface_flush_l3($dev, 6);
      if ($ip6 !== '' && filter_var($ip6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $target6 = escapeshellarg($ip6 . '/' . $p6);
        @exec("ip -6 addr add {$target6} dev {$ife} 2>/dev/null");
        $gw6 = trim((string)($cfg[$prefix . 'GATEWAY6'] ?? ''));
        $dr6 = $cfg[$prefix . 'DEFAULT_ROUTE6'] ?? 'no';
        if ($gw6 !== '' && $dr6 === 'yes' && filter_var($gw6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
          @exec('ip -6 route replace default via ' . escapeshellarg($gw6) . ' dev ' . $ife . ' 2>/dev/null');
        } else {
          tbn_iface_drop_default_routes($dev, 6);
        }
      }
    }
  }
}

/**
 * Create/configure VLAN subinterfaces parent.VID (e.g. thunderbolt0.10).
 */
function tbn_apply_vlans($parent, array $cfg) {
  if (($cfg['VLAN_ENABLE'] ?? 'no') !== 'yes') {
    return;
  }
  if (!is_dir('/sys/class/net/' . $parent)) {
    return;
  }
  $list = preg_split('/[\s,]+/', trim((string)($cfg['VLAN_LIST'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
  foreach ($list as $vid) {
    if (!preg_match('/^\d+$/', $vid)) {
      continue;
    }
    $id = (int)$vid;
    if ($id < 1 || $id > 4094) {
      continue;
    }
    $vname = $parent . '.' . $id;
    if (!is_dir('/sys/class/net/' . $vname)) {
      @exec(
        'ip link add link ' . escapeshellarg($parent)
        . ' name ' . escapeshellarg($vname)
        . ' type vlan id ' . $id . ' 2>/dev/null'
      );
    }
    if (!is_dir('/sys/class/net/' . $vname)) {
      continue;
    }
    @exec('ip link set ' . escapeshellarg($vname) . ' up 2>/dev/null');
    tbn_apply_ip_block($vname, $cfg, 'VLAN_' . $id . '_');
  }
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
    if (preg_match('/thunderbolt|USB4|Maple Ridge|Barlow Ridge|NHI|Alpine Ridge|Titan Ridge|JHL/i', $line)
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
 * Generate top-level Network Settings tabs: tbn0, tbn1, … (NOT nested under Thunderbolt).
 *
 * Sort keys (see ThunderboltNet.page header):
 *   eth* ~100 · Wireless 1000 · tbnN 1010+N · Thunderbolt overview 1080 · Interface Extra · FRR :z
 * Keep tbnN as strip siblings (eth-like) with Tag=sitemap; overview uses fa-bolt.
 * Never nest Status/Peers as Unraid xmenu under Thunderbolt (breaks empty parent / Interface Extra).
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
    // After stock Wireless (1000); before Thunderbolt overview (1080)
    $menu = 1010 + $n;
    $label = 'tbn' . $n;
    $page = $root . '/Tbn' . $n . '.page';
    // Markdown=false: Unraid runs page text through Markdown by default, which breaks <?php.
    // Use require (not require_once): Network Settings evaluates every TbnN.page in one
    // request. require_once would run the shared template only for the first tab and leave
    // tbn1, tbn2, … completely blank.
    // Light shell only — full iface form loads via tbn-lazy-render.php when this
    // Network Settings tab is shown (keeps eth0 first paint free of TB work).
    $body = <<<PAGE
Menu="NetworkSettings:{$menu}"
Title="Thunderbolt {$label}"
Tag="sitemap"
Markdown="false"
Cond="file_exists('/sys/class/net/{$if}')"
---
<link rel="stylesheet" href="/plugins/ThunderboltNet/thunderboltnet.css">
<div class="tbn-wrap tbn-lazy-iface" data-tbn-lazy-iface="{$if}" data-tbn-label="{$label}">
  <p class="tbn-muted tbn-lazy-placeholder">Loading {$label}…</p>
</div>
<script src="/plugins/ThunderboltNet/thunderboltnet.js"></script>

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
 * Re-apply L3 to live thunderbolt* netdevs (array start + hotplug).
 *
 * Preference order per live iface:
 *  1) Peer plan for the remote fabric UUID currently on that path (survives tbn renumber)
 *  2) Saved ifaces/thunderboltN.cfg (path-slot cache, eth-like by name)
 *
 * @param string|null $if_filter When set (e.g. thunderbolt0), only that iface.
 * @return array<string,array> map iface => apply result
 */
function tbn_reapply_live_ifaces($if_filter = null) {
  $out = [];
  $names = tbn_list_tb_iface_names();
  if (is_string($if_filter) && $if_filter !== '') {
    $names = in_array($if_filter, $names, true) ? [$if_filter] : [];
  }
  $links = [];
  try {
    $links = tbn_link_summaries();
  } catch (Throwable $e) {
    $links = [];
  }
  $peers = tbn_load_peers_memory();
  $link_by_if = [];
  foreach ($links as $L) {
    $i = $L['iface'] ?? '';
    if ($i !== '') {
      $link_by_if[$i] = $L;
    }
  }
  foreach ($names as $if) {
    $applied = false;
    if (isset($link_by_if[$if])) {
      $key = tbn_peer_key_from_link($link_by_if[$if], $peers);
      $pr = tbn_apply_peer_plan_to_iface($key, $if);
      if (!empty($pr['applied'])) {
        $out[$if] = $pr;
        $applied = true;
      }
    }
    if (!$applied && is_file(tbn_iface_cfg_path($if))) {
      $out[$if] = tbn_apply_iface($if, ['skip_peer_capture' => true]);
    }
  }
  if (function_exists('tbn_sync_iface_pages')) {
    tbn_sync_iface_pages();
  }
  // Apply/reapply paths must remember peers even when nobody opened the Peers UI tab.
  try {
    tbn_remember_live_peers(tbn_link_summaries());
  } catch (Throwable $e) {
    // non-fatal
  }
  return $out;
}

/**
 * Install udev rule so thunderbolt* netdevs re-apply flash cfg on add (hotplug).
 * Rule lives under the plugin tree and is symlinked/copied into /etc/udev/rules.d.
 */
function tbn_install_net_udev() {
  $src = tbn_plugin_root() . '/udev/99-thunderboltnet-net.rules';
  $dst = '/etc/udev/rules.d/99-thunderboltnet-net.rules';
  if (!is_file($src)) {
    return false;
  }
  if (!is_dir('/etc/udev/rules.d')) {
    @mkdir('/etc/udev/rules.d', 0755, true);
  }
  $ok = @copy($src, $dst);
  if ($ok) {
    @exec('udevadm control --reload-rules 2>/dev/null || true');
  }
  return $ok;
}

function tbn_remove_net_udev() {
  $dst = '/etc/udev/rules.d/99-thunderboltnet-net.rules';
  if (is_file($dst)) {
    @unlink($dst);
    @exec('udevadm control --reload-rules 2>/dev/null || true');
  }
}

/**
 * Apply one iface cfg to the live system (not Unraid network.cfg).
 *
 * @param array $opts skip_peer_capture=true when applying from a peer plan (avoid recursion)
 */
/**
 * NAT (private TB underlay via Unraid uplink) and join-existing-bridge cannot both be Yes.
 * Prefer the explicit request: if both set, NAT wins when called after UI force; otherwise bridging
 * clears NAT (bridge member has no underlay IP to MASQUERADE).
 */
function tbn_iface_reconcile_nat_bridge(array $cfg) {
  $nat = strtolower(trim((string)($cfg['NAT_ENABLE'] ?? 'no'))) === 'yes';
  $br = strtolower(trim((string)($cfg['BRIDGING'] ?? 'no'))) === 'yes';
  if ($nat && $br) {
    // Keep NAT; drop bridging (peer-behind-Unraid model needs its own underlay IP).
    $cfg['BRIDGING'] = 'no';
  }
  return $cfg;
}

function tbn_apply_iface($if, array $opts = []) {
  $cfg = tbn_load_iface_cfg($if);
  $before_br = (string)($cfg['BRIDGING'] ?? 'no');
  $before_nat = (string)($cfg['NAT_ENABLE'] ?? 'no');
  $cfg = tbn_iface_reconcile_nat_bridge($cfg);
  if ((string)($cfg['BRIDGING'] ?? 'no') !== $before_br || (string)($cfg['NAT_ENABLE'] ?? 'no') !== $before_nat) {
    tbn_write_iface_cfg($if, $cfg);
  }
  if (!is_dir('/sys/class/net/' . $if)) {
    return ['ok' => false, 'error' => "interface {$if} not present"];
  }
  $ife = escapeshellarg($if);
  if (($cfg['ENABLE'] ?? 'yes') === 'yes') {
    @exec("ip link set {$ife} up 2>/dev/null");
  } else {
    // Disable: drop addresses + routes so nothing lingers in the main table
    if (function_exists('tbn_nat_clear')) {
      tbn_nat_clear($if);
    }
    if (function_exists('tbn_dhcp_server_stop')) {
      tbn_dhcp_server_stop($if);
      $netdev = function_exists('tbn_dhcp_netdev_for_cfg') ? tbn_dhcp_netdev_for_cfg($if, $cfg) : '';
      if ($netdev !== '' && $netdev !== $if) {
        tbn_dhcp_server_stop($netdev);
      }
    }
    tbn_iface_flush_l3($if, 0);
    @exec("ip link set {$ife} down 2>/dev/null");
    return ['ok' => true, 'iface' => $if, 'enabled' => false];
  }
  // Join / leave an existing Unraid (or admin) bridge before L3 — member has no own IP.
  // Mutual exclusion: bridging wins over bonding on this path.
  $want_br = tbn_bridge_join_target($cfg);
  if ($want_br !== '' && ($cfg['BONDING'] ?? 'no') === 'yes') {
    // Prefer bridge when both set; leave bonding cfg alone but do not create a bond.
  }
  if ($want_br !== '') {
    tbn_apply_bridge_join($if, $want_br);
  } else {
    // Bridging off (or invalid target): detach only if we were a *bridge* member
    $cur = tbn_iface_master($if);
    if ($cur !== '' && tbn_is_bridge_netdev($cur)) {
      @exec('ip link set ' . escapeshellarg($if) . ' nomaster 2>/dev/null');
    }
  }

  // If this iface is enslaved to a bond or bridge, do not fight the master (eth-like).
  $master = tbn_iface_master($if);
  $is_bond_slave = ($master !== '' && (
    preg_match('/^bond-tb/', $master) || is_dir('/sys/class/net/' . $master . '/bonding')
  ));
  $is_bridge_slave = ($master !== '' && tbn_is_bridge_netdev($master));
  $is_slave = $is_bond_slave || $is_bridge_slave;

  // MTU on the netdev that carries the IP (slave: master owns MTU)
  if (!$is_slave) {
    tbn_apply_mtu($if, tbn_desired_mtu($cfg));
  }

  if (!$is_slave) {
    tbn_apply_ip_block($if, $cfg, '');
    tbn_apply_vlans($if, $cfg);
  } elseif ($is_bridge_slave) {
    // Bridge owns addressing — ensure member has no leftover L3
    tbn_iface_flush_l3($if, 0);
  }

  // Listening include for this iface only (bridge members usually leave No — LAN already on br0)
  tbn_set_listening_for_iface($if, ($cfg['INCLUDE_LISTENING'] ?? 'no') === 'yes' ? 'yes' : 'no');

  // Bonding — experimental; only when form enables it and ≥2 live Thunderbolt members
  if (!$is_slave && $want_br === '' && ($cfg['BONDING'] ?? 'no') === 'yes') {
    $members = tbn_parse_bond_members($cfg['BOND_MEMBERS'] ?? '', true);
    if (!$members && $if !== '') {
      $members = [$if];
    }
    // Same-peer dual-cable almost never yields two netdevs; refuse 1-member bonds
    $live_tb = tbn_list_tb_iface_names();
    $members = array_values(array_filter($members, function ($m) use ($live_tb) {
      return in_array($m, $live_tb, true);
    }));
    if (count($members) >= 2) {
      $cfg['BOND_MEMBERS'] = implode(' ', $members);
      tbn_apply_simple_bond($cfg, $if);
      $bond = $cfg['BOND_NAME'] ?? 'bond-tb0';
      if ($bond !== '' && is_dir('/sys/class/net/' . $bond)) {
        tbn_apply_mtu($bond, tbn_desired_mtu($cfg));
      }
    }
    // else: leave bonding=yes in cfg but do not create a useless 1-slave bond
  }
  // DHCP server (after L3 / bond exists). Never on a bridge member — LAN DHCP is Unraid's job.
  $dhcp_res = null;
  if (!$is_slave && function_exists('tbn_dhcp_server_apply')
      && ((($cfg['USE_DHCP'] ?? '') === 'server') || (($cfg['USE_DHCP6'] ?? '') === 'server'))) {
    // Persist resolved server host/pool into cfg so flash matches live dnsmasq plan
    if (($cfg['USE_DHCP'] ?? '') === 'server' && function_exists('tbn_dhcp_server_plan')) {
      $p = tbn_dhcp_server_plan($if, $cfg);
      $cfg['IPADDR'] = $p['ip'];
      $cfg['NETMASK'] = (string)$p['prefix'];
      $cfg['DHCP_POOL_START'] = $p['pool_start'];
      $cfg['DHCP_POOL_END'] = $p['pool_end'];
      tbn_write_iface_cfg($if, $cfg);
    }
    $dhcp_res = tbn_dhcp_server_apply($if, $cfg);
  } elseif (!$is_slave && function_exists('tbn_dhcp_server_stop')) {
    $netdev = function_exists('tbn_dhcp_netdev_for_cfg') ? tbn_dhcp_netdev_for_cfg($if, $cfg) : $if;
    if ($netdev !== '') {
      tbn_dhcp_server_stop($netdev);
    }
  } elseif ($is_bridge_slave && function_exists('tbn_dhcp_server_stop')) {
    tbn_dhcp_server_stop($if);
  }

  // Remember peers on Apply so Peers list fills without requiring a separate UI load.
  try {
    tbn_remember_live_peers(tbn_link_summaries());
  } catch (Throwable $e) {
    // non-fatal
  }
  // Bind this path's L3 to the live peer UUID so renumber (tbn0↔tbn1) keeps the plan.
  if (empty($opts['skip_peer_capture'])) {
    try {
      tbn_capture_peer_plan_from_iface($if);
    } catch (Throwable $e) {
      // non-fatal — no peer on path is normal
    }
  }

  // Share host uplink with TB peers (NAT). Cleared when off; reapplied on boot/udev via this path.
  $nat_res = null;
  if (!$is_slave && function_exists('tbn_nat_apply')) {
    $nat_res = tbn_nat_apply($if, $cfg);
  } elseif ($is_slave && function_exists('tbn_nat_clear')) {
    tbn_nat_clear($if);
  }

  $out = ['ok' => true, 'iface' => $if, 'cfg' => $cfg, 'netdevs' => tbn_list_netdevs()];
  if (is_array($dhcp_res) && empty($dhcp_res['ok'])) {
    $out['ok'] = false;
    $out['error'] = $dhcp_res['error'] ?? 'DHCP server failed';
    $out['dhcp'] = $dhcp_res;
  } elseif (is_array($dhcp_res)) {
    $out['dhcp'] = $dhcp_res;
  }
  if (is_array($nat_res)) {
    $out['nat'] = $nat_res;
    if (empty($nat_res['ok']) && empty($out['error'])) {
      $out['ok'] = false;
      $out['error'] = $nat_res['error'] ?? 'NAT apply failed';
    }
  }
  return $out;
}

function tbn_mask_to_prefix($mask) {
  $long = ip2long($mask);
  if ($long === false) {
    return 24;
  }
  return substr_count(decbin($long), '1');
}

/**
 * Enslave Thunderbolt iface into an existing bridge (br0, br0.10, …).
 * Does not create or destroy the bridge — Unraid owns br0*.
 */
function tbn_apply_bridge_join($if, $bridge) {
  $if = (string)$if;
  $bridge = (string)$bridge;
  if ($if === '' || $bridge === '' || !is_dir('/sys/class/net/' . $if)) {
    return false;
  }
  if (!tbn_is_bridge_netdev($bridge)) {
    return false;
  }
  // Drop L3 on member before enslaving
  if (function_exists('tbn_iface_flush_l3')) {
    tbn_iface_flush_l3($if, 0);
  }
  $cur = tbn_iface_master($if);
  if ($cur !== '' && $cur !== $bridge) {
    @exec('ip link set ' . escapeshellarg($if) . ' nomaster 2>/dev/null');
  }
  @exec('ip link set ' . escapeshellarg($if) . ' up 2>/dev/null');
  @exec('ip link set ' . escapeshellarg($bridge) . ' up 2>/dev/null');
  if ($cur !== $bridge) {
    @exec('ip link set ' . escapeshellarg($if) . ' master ' . escapeshellarg($bridge) . ' 2>/dev/null');
  }
  return tbn_iface_master($if) === $bridge;
}

/**
 * Simple balance-rr (etc.) bond for Thunderbolt members only — not full Unraid bond0.
 */
function tbn_apply_simple_bond(array $cfg, $primary_if) {
  $bond = $cfg['BOND_NAME'] ?? 'bond-tb0';
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
  $members = tbn_parse_bond_members($cfg['BOND_MEMBERS'] ?? '', true);
  if (!$members && $primary_if !== '') {
    $members = [$primary_if];
  }
  // Release Thunderbolt interfaces not in this bond that still slave under it
  foreach (tbn_list_tb_iface_names() as $m) {
    $cur = tbn_iface_master($m);
    if ($cur === $bond && !in_array($m, $members, true)) {
      @exec('ip link set ' . escapeshellarg($m) . ' nomaster 2>/dev/null');
    }
  }
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

/**
 * Parse a stored timestamp to Unix seconds.
 * Accepts ISO-8601 (date('c')), common date strings, or numeric epoch.
 *
 * @param mixed $when
 * @return int|null
 */
function tbn_parse_when($when) {
  if ($when === null || $when === '' || $when === '—') {
    return null;
  }
  if (is_int($when) || is_float($when)) {
    $ts = (int)$when;
    return $ts > 0 ? $ts : null;
  }
  $s = trim((string)$when);
  if ($s === '') {
    return null;
  }
  if (preg_match('/^\d{9,12}$/', $s)) {
    $ts = (int)$s;
    return $ts > 0 ? $ts : null;
  }
  $ts = strtotime($s);
  if ($ts === false || $ts <= 0) {
    return null;
  }
  return $ts;
}

/**
 * Format a stored time for UI using Unraid Display / Date and Time prefs.
 *
 * Storage stays ISO-8601 with offset (date('c')) for machine sort/portability.
 * Display follows Settings → Date and Time (display date + 12/24h time), via
 * stock my_time() when available — same as the rest of Unraid.
 *
 * @param mixed $when ISO string, epoch, etc.
 * @return string Human-readable local time, or "—" / raw fallback
 */
function tbn_format_when($when) {
  $ts = tbn_parse_when($when);
  if ($ts === null) {
    $raw = is_scalar($when) ? trim((string)$when) : '';
    return ($raw === '' || $raw === '—') ? '—' : $raw;
  }

  // Stock Unraid helper (Settings → Date and Time → display date/time)
  if (function_exists('my_time')) {
    $out = my_time($ts);
    if (is_string($out) && $out !== '' && strtolower($out) !== 'unknown') {
      return $out;
    }
  }

  // Fallback: same formula as Helpers.php my_time() without requiring $display global
  $date_fmt = '%c';
  $time_fmt = '%R';
  if (isset($GLOBALS['display']) && is_array($GLOBALS['display'])) {
    $date_fmt = (string)($GLOBALS['display']['date'] ?? $date_fmt);
    $time_fmt = (string)($GLOBALS['display']['time'] ?? $time_fmt);
  } elseif (is_readable('/boot/config/plugins/dynamix/dynamix.cfg')) {
    $ini = @parse_ini_file('/boot/config/plugins/dynamix/dynamix.cfg', true);
    if (is_array($ini) && !empty($ini['display'])) {
      $date_fmt = (string)($ini['display']['date'] ?? $date_fmt);
      $time_fmt = (string)($ini['display']['time'] ?? $time_fmt);
    }
  }
  // my_date legacy map (Wrappers.php) — strftime-ish → PHP date()
  $legacy = [
    '%c' => 'D j M Y h:i A',
    '%A' => 'l',
    '%Y' => 'Y',
    '%B' => 'F',
    '%e' => 'j',
    '%d' => 'd',
    '%m' => 'm',
    '%I' => 'h',
    '%H' => 'H',
    '%M' => 'i',
    '%S' => 's',
    '%p' => 'A',
    '%R' => 'H:i',
    '%F' => 'Y-m-d',
    '%T' => 'H:i:s',
  ];
  $fmt = $date_fmt;
  if ($date_fmt !== '%c') {
    $fmt = $date_fmt . ', ' . $time_fmt;
  }
  return date(strtr($fmt, $legacy), $ts);
}

/**
 * HTML for a when-column: pretty local time, title=ISO for precision on hover.
 *
 * @param mixed $when
 * @return string Safe HTML (escaped text)
 */
function tbn_format_when_html($when) {
  $pretty = tbn_format_when($when);
  $raw = is_scalar($when) ? trim((string)$when) : '';
  if ($pretty === '—' || $raw === '' || $raw === '—') {
    return '—';
  }
  // Prefer true ISO in title when we have it; else re-export from epoch
  $title = $raw;
  if ($title === '' || $title === $pretty) {
    $ts = tbn_parse_when($when);
    $title = $ts ? date('c', $ts) : $pretty;
  }
  return '<span class="tbn-when" title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">'
    . htmlspecialchars($pretty, ENT_QUOTES, 'UTF-8')
    . '</span>';
}

/**
 * Format an age in seconds for compact UI (fabric Age column, etc.).
 *
 * Rules:
 *  - ≥ 1 day → rounded days only ("15d")
 *  - ≥ 1 hour → hours + minutes ("14h 57m")
 *  - ≥ 1 minute → minutes + seconds ("5m 12s")
 *  - else → seconds only ("42s")
 *
 * @param int|float $secs
 * @return string
 */
function tbn_format_age_seconds($secs) {
  $s = (int)max(0, round((float)$secs));
  if ($s >= 86400) {
    $days = (int)round($s / 86400);
    if ($days < 1) {
      $days = 1;
    }
    return $days . 'd';
  }
  if ($s >= 3600) {
    $h = intdiv($s, 3600);
    $m = intdiv($s % 3600, 60);
    return $h . 'h ' . $m . 'm';
  }
  if ($s >= 60) {
    $m = intdiv($s, 60);
    $r = $s % 60;
    return $m . 'm ' . $r . 's';
  }
  return $s . 's';
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
    'enable_usb4stream' => 'no',
    'address_plan' => 'small-lan',
    'include_listening' => 'no',
    'manage_ip' => 'no',
    'ip_addr' => '10.255.0.2',
    'ip_cidr' => '24',
    'ip_gateway' => '',
    'never_default' => 'yes',
    'iface_primary' => 'thunderbolt0',
    'iface_secondary' => 'thunderbolt1',
    'bond_enable' => 'no',
    'bond_name' => 'bond-tb0',
    'bond_mode' => 'active-backup',
    'ignore_warnings' => '',
    'openfabric_enable' => 'no',
    'openfabric_user_off' => 'no',
    'openfabric_auto_install_frr' => 'yes',
    'openfabric_ipv6' => 'yes',
    'openfabric_area' => '1',
    'openfabric_router_id' => '',
    'openfabric_net' => '',
    'openfabric_metric_reference_mbps' => '20000',
    'mesh_report' => 'yes',
    'mesh_token' => '',
    'mesh_poll_secs' => '60',
    'mesh_stale_secs' => '300',
    'mesh_holdoff_secs' => '120',
    'mesh_private_only' => 'yes',
    'mesh_eth_ifaces' => '',
    'mesh_peer_ips' => '',
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

// OpenFabric / FRR helpers (optional until install finishes)
$__tbn_of = '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-openfabric.php';
if (is_file($__tbn_of)) {
  require_once $__tbn_of;
}
// Dev tree / CLI when plugin path not installed yet
$__tbn_of_dev = dirname(__FILE__) . '/tbn-openfabric.php';
if (!function_exists('tbn_of_status') && is_file($__tbn_of_dev)) {
  require_once $__tbn_of_dev;
}

/**
 * Active PCI warnings (currently VFIO on Thunderbolt-related devices).
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
