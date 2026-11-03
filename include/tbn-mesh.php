<?php
/**
 * Thunderbolt Net — multi-host fabric link reports (mesh).
 * Local sysfs = this host. Peer Unraid TBN export = their local view.
 * Green = agree (incl. crossed TX/RX). Orange = unverified. Red = disagree.
 */
if (!function_exists('tbn_cfg_dir')) {
  require_once __DIR__ . '/tbn-lib.php';
}

function tbn_mesh_schema_version() { return 2; }

/** TCP port for mesh JSON beacon (php -S; bypasses Unraid auth_request 302). NBD uses 10808. */
function tbn_mesh_beacon_port() { return 10807; }

function tbn_mesh_beacon_pidfile() {
  return '/var/run/thunderboltnet-mesh-beacon.pid';
}

function tbn_mesh_beacon_logfile() {
  // Access lines are discarded (php -S logs every request; /var/log is a small tmpfs).
  return '/dev/null';
}

/** Legacy beacon log from older versions (removed on next beacon start). */
function tbn_mesh_beacon_legacy_log() {
  return '/var/log/thunderboltnet-mesh-beacon.log';
}

/**
 * List DHCP servers this host is running on Thunderbolt underlays (for peer conflict awareness).
 *
 * @return array<int,array{iface:string,network:string,host:string,pool_start:string,pool_end:string}>
 */
function tbn_mesh_dhcp_servers_snapshot(?array $cfg = null) {
  $out = [];
  if (!function_exists('tbn_load_iface_cfg') || !function_exists('tbn_dhcp_server_plan')) {
    return $out;
  }
  foreach (['thunderbolt0', 'thunderbolt1', 'thunderbolt2', 'thunderbolt3'] as $if) {
    if (!is_dir('/sys/class/net/' . $if) && !is_file(tbn_iface_cfg_path($if))) {
      continue;
    }
    $icfg = tbn_load_iface_cfg($if);
    if (($icfg['USE_DHCP'] ?? '') !== 'server') {
      continue;
    }
    $plan = tbn_dhcp_server_plan($if, $icfg);
    $out[] = [
      'iface' => $if,
      'network' => $plan['network'] ?? '',
      'host' => $plan['ip'] ?? '',
      'pool_start' => $plan['pool_start'] ?? '',
      'pool_end' => $plan['pool_end'] ?? '',
    ];
  }
  return $out;
}

function tbn_mesh_beacon_piddir() {
  return '/var/run/thunderboltnet-mesh-beacon';
}

function tbn_mesh_kill_pid($pid) {
  $pid = (int)$pid;
  if ($pid <= 1 || !@file_exists('/proc/' . $pid)) {
    return;
  }
  // Only our beacon: a stale pidfile must not kill a reused PID.
  $cmd = (string)@file_get_contents('/proc/' . $pid . '/cmdline');
  if (strpos($cmd, 'tbn-mesh-beacon-server.php') === false) {
    return;
  }
  if (function_exists('posix_kill')) {
    @posix_kill($pid, 15);
    usleep(120000);
    if (@file_exists('/proc/' . $pid)) {
      @posix_kill($pid, 9);
    }
  } else {
    @exec('kill -TERM ' . $pid . ' 2>/dev/null');
    usleep(120000);
    @exec('kill -KILL ' . $pid . ' 2>/dev/null');
  }
}

/**
 * Private IPv4s the mesh beacon may bind (Thunderbolt + optional mesh_eth_ifaces).
 * Never br0 / wlan / docker — that was the 0.0.0.0 leak onto the management LAN.
 */
function tbn_mesh_listen_addrs(?array $cfg = null) {
  if ($cfg === null) {
    $cfg = tbn_load_cfg();
  }
  $ifaces = function_exists('tbn_list_tb_iface_names') ? tbn_list_tb_iface_names() : [];
  foreach (preg_split('/[\s,;]+/', trim((string)($cfg['mesh_eth_ifaces'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) as $if) {
    $if = preg_replace('/[^A-Za-z0-9_.-]/', '', $if);
    if ($if === '' || preg_match('/^(br|bond|wlan|docker|veth|virbr|wg|tun|tap|lo)/', $if)) {
      continue;
    }
    $ifaces[] = $if;
  }
  $ips = [];
  foreach (array_unique($ifaces) as $if) {
    if (!is_dir('/sys/class/net/' . $if)) {
      continue;
    }
    if (!function_exists('tbn_iface_addrs')) {
      continue;
    }
    foreach (tbn_iface_addrs($if) as $raw) {
      $ip = explode('/', (string)$raw)[0];
      if (tbn_mesh_is_private_ip($ip)) {
        $ips[$ip] = true;
      }
    }
  }
  return array_keys($ips);
}

/** Start mesh beacon when link-check export is enabled; stop when off. */
function tbn_mesh_beacon_ensure(?array $cfg = null) {
  if ($cfg === null) {
    $cfg = tbn_load_cfg();
  }
  if (!tbn_mesh_enabled($cfg)) {
    return tbn_mesh_beacon_stop();
  }
  $addrs = tbn_mesh_listen_addrs($cfg);
  if (!$addrs) {
    tbn_mesh_beacon_stop();
    return ['ok' => true, 'running' => false, 'reason' => 'no thunderbolt IP yet', 'port' => tbn_mesh_beacon_port()];
  }
  $router = '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-mesh-beacon-server.php';
  if (!is_file($router)) {
    $router = dirname(__FILE__) . '/tbn-mesh-beacon-server.php';
  }
  if (!is_file($router)) {
    return ['ok' => false, 'error' => 'beacon server missing'];
  }
  $php = trim((string)@shell_exec('command -v php 2>/dev/null'));
  if ($php === '' || !is_executable($php)) {
    $php = '/usr/bin/php';
  }
  if (!is_executable($php)) {
    return ['ok' => false, 'error' => 'php not found'];
  }
  @mkdir('/var/run', 0755, true);
  $dir = tbn_mesh_beacon_piddir();
  @mkdir($dir, 0755, true);
  $legacy = tbn_mesh_beacon_pidfile();
  if (is_file($legacy)) {
    tbn_mesh_kill_pid((int)@file_get_contents($legacy));
    @unlink($legacy);
  }
  $port = tbn_mesh_beacon_port();
  $log = tbn_mesh_beacon_logfile();
  if (is_file(tbn_mesh_beacon_legacy_log())) {
    @unlink(tbn_mesh_beacon_legacy_log());
  }
  $want = array_fill_keys($addrs, true);
  $alive = [];
  foreach (glob($dir . '/*.pid') ?: [] as $pf) {
    $ip = basename($pf, '.pid');
    $pid = (int)@file_get_contents($pf);
    if (!isset($want[$ip]) || $pid <= 1 || !@file_exists('/proc/' . $pid)) {
      tbn_mesh_kill_pid($pid);
      @unlink($pf);
      continue;
    }
    $alive[$ip] = $pid;
  }
  foreach ($addrs as $ip) {
    if (isset($alive[$ip])) {
      continue;
    }
    $pf = $dir . '/' . $ip . '.pid';
    // The beacon only answers GET: no request bodies, uploads or upload progress
    // (also closes the session upload-progress PHP bug on unpatched Unraid builds).
    $cmd = 'setsid nohup ' . escapeshellarg($php) . ' -d enable_post_data_reading=0 -d file_uploads=0 -d session.upload_progress.enabled=0 -S ' . escapeshellarg($ip . ':' . (int)$port)
      . ' ' . escapeshellarg($router)
      . ' >>' . escapeshellarg($log) . ' 2>&1 & echo $! >' . escapeshellarg($pf);
    exec($cmd);
    usleep(150000);
    $pid = is_file($pf) ? (int)@file_get_contents($pf) : 0;
    if ($pid > 0 && @file_exists('/proc/' . $pid)) {
      $alive[$ip] = $pid;
    }
  }
  $n = count($alive);
  return [
    'ok' => $n > 0,
    'running' => $n > 0,
    'listen' => array_keys($alive),
    'port' => $port,
  ];
}

function tbn_mesh_beacon_stop() {
  $legacy = tbn_mesh_beacon_pidfile();
  if (is_file($legacy)) {
    tbn_mesh_kill_pid((int)@file_get_contents($legacy));
    @unlink($legacy);
  }
  $dir = tbn_mesh_beacon_piddir();
  foreach (glob($dir . '/*.pid') ?: [] as $pf) {
    tbn_mesh_kill_pid((int)@file_get_contents($pf));
    @unlink($pf);
  }
  return ['ok' => true, 'running' => false];
}

function tbn_mesh_host_id_path() { return tbn_cfg_dir() . '/mesh_host_id'; }
/** Peer snapshots, last poll and link-check results: RAM only (polled every minute). */
function tbn_mesh_cache_dir() {
  $d = tbn_runtime_path('mesh-cache');
  if (!is_dir($d)) {
    @mkdir($d, 0700, true);
  }
  return $d;
}

function tbn_mesh_last_poll_path() {
  return tbn_runtime_path('mesh_last_poll.json');
}

function tbn_mesh_validation_path() {
  return tbn_runtime_path('mesh_validation.json');
}

/** Largest peer snapshot accepted (bytes). */
function tbn_mesh_max_body() {
  return 262144;
}

/** Peer snapshot files kept in the cache (newest first). */
function tbn_mesh_cache_keep() {
  return 16;
}

/**
 * Older versions kept the cache and last poll on flash; drop them once.
 */
function tbn_mesh_cleanup_flash_legacy() {
  $old = tbn_cfg_dir() . '/mesh-cache';
  if (is_dir($old)) {
    foreach (glob($old . '/*.json') ?: [] as $f) {
      @unlink($f);
    }
    @rmdir($old);
  }
  if (is_file(tbn_cfg_dir() . '/mesh_last_poll.json')) {
    @unlink(tbn_cfg_dir() . '/mesh_last_poll.json');
  }
}

/**
 * Keep at most $keep cache files and none older than $max_age seconds.
 * The far end picks host_id (the filename), so the cache must stay bounded.
 *
 * @return string[] removed paths
 */
function tbn_mesh_prune_cache($dir, $keep = 16, $max_age = 86400, $now = null) {
  $now = $now ?? time();
  $files = [];
  foreach (glob($dir . '/*.json') ?: [] as $f) {
    $files[$f] = (int)@filemtime($f);
  }
  arsort($files, SORT_NUMERIC);
  $removed = [];
  $i = 0;
  foreach ($files as $f => $mt) {
    $i++;
    if ($i > $keep || ($now - $mt) > $max_age) {
      @unlink($f);
      $removed[] = $f;
    }
  }
  return $removed;
}

/** @return array<string,array> peer_key => validation */
function tbn_mesh_validation_load() {
  return tbn_read_json_file(tbn_mesh_validation_path());
}

function tbn_mesh_validation_save(array $map) {
  return tbn_write_json_file(tbn_mesh_validation_path(), array_slice($map, -64, null, true));
}

/** Add RAM link-check results to a peers.json array for display. */
function tbn_mesh_overlay_validation(array $peers) {
  $val = tbn_mesh_validation_load();
  foreach ($peers as $k => $p) {
    if (is_array($p) && isset($val[$k]) && is_array($val[$k])) {
      $peers[$k]['mesh_validation'] = $val[$k];
    }
  }
  return $peers;
}

/* ---- Schedule: Unraid plugin cron (flash .cron + update_cron) ---- */

// update_cron reads /boot/config/plugins/<plg name>/*.cron: lowercase
// thunderboltnet/ (case matters on a ZFS boot device; same folder on FAT).
function tbn_mesh_cron_path() {
  return dirname(tbn_cfg_dir()) . '/thunderboltnet/thunderboltnet.cron';
}

function tbn_mesh_cron_legacy_path() {
  return tbn_cfg_dir() . '/thunderboltnet.cron';
}

function tbn_mesh_cron_body() {
  return "# ThunderboltNet peer link check (generated; removed when the check is off)\n"
    . "* * * * * /bin/bash /usr/local/emhttp/plugins/ThunderboltNet/scripts/tbn-mesh-cron >/dev/null 2>&1\n";
}

/**
 * Install the cron line while the link check is enabled (report=yes and a token),
 * remove it otherwise. Flash is written only when the state changes; update_cron
 * runs only then. The cron script itself is a cheap no-op without a link or peer IPs.
 *
 * @return bool true when the cron file changed
 */
function tbn_mesh_cron_sync(?array $cfg = null) {
  if ($cfg === null) {
    $cfg = tbn_load_cfg();
  }
  $path = tbn_mesh_cron_path();
  $want = tbn_mesh_enabled($cfg);
  $changed = false;
  if ($want) {
    $body = tbn_mesh_cron_body();
    if (!is_file($path) || (string)@file_get_contents($path) !== $body) {
      @mkdir(dirname($path), 0755, true);
      $changed = tbn_write_file_atomic($path, $body);
    }
  } elseif (is_file($path)) {
    $changed = @unlink($path);
  }
  $legacy = tbn_mesh_cron_legacy_path();
  if (is_file($legacy) && !(is_file($path) && @fileinode($legacy) === @fileinode($path))) {
    @unlink($legacy);
    $changed = true;
  }
  if ($changed) {
    $uc = tbn_env_path('TBN_UPDATE_CRON', '/usr/local/sbin/update_cron');
    if (is_file($uc)) {
      @exec(escapeshellarg($uc) . ' >/dev/null 2>&1');
    }
  }
  return $changed;
}

function tbn_mesh_host_id() {
  $path = tbn_mesh_host_id_path();
  // Derive from machine-id + hostname. Cloned Unraid USB sticks often share
  // /etc/machine-id; hostname usually differs between hosts. A copied
  // ThunderboltNet/mesh_host_id file alone also used to cause self_host_id rejects.
  $mid = is_readable('/etc/machine-id') ? trim((string)@file_get_contents('/etc/machine-id')) : '';
  $host = gethostname() ?: (php_uname('n') ?: '');
  // Unraid NAME= from ident.cfg is more stable than transient hostname
  if (is_readable('/boot/config/ident.cfg')) {
    $ident = (string)@file_get_contents('/boot/config/ident.cfg');
    if (preg_match('/^NAME=\"([^\"]+)\"/m', $ident, $m) && trim($m[1]) !== '') {
      $host = trim($m[1]);
    }
  }
  $seed = strtolower($mid) . "\0" . strtolower($host);
  if ($mid !== '' || $host !== '') {
    $hex = md5($seed);
    $id = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
      . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    @mkdir(tbn_cfg_dir(), 0755, true);
    $prev = is_readable($path) ? trim((string)@file_get_contents($path)) : '';
    if ($prev !== $id) {
      tbn_write_file_atomic($path, $id . "\n");
    }
    return $id;
  }
  if (is_readable($path)) {
    $id = trim((string)@file_get_contents($path));
    if ($id !== '' && preg_match('/^[a-f0-9-]{8,64}$/i', $id)) {
      return $id;
    }
  }
  $hex = bin2hex(random_bytes(16));
  $id = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
    . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
  @mkdir(tbn_cfg_dir(), 0755, true);
  tbn_write_file_atomic($path, $id . "\n");
  return $id;
}

function tbn_mesh_plugin_version() {
  foreach (['/var/log/plugins/thunderboltnet.plg','/boot/config/plugins/thunderboltnet.plg'] as $plg) {
    if (!is_readable($plg)) continue;
    $t = (string)@file_get_contents($plg);
    if (preg_match('/ENTITY version "([^"]+)"/', $t, $m)) return $m[1];
  }
  return 'dev';
}

function tbn_mesh_cfg_defaults() {
  return [
    'mesh_report' => 'yes',
    'mesh_token' => '',
    'mesh_poll_secs' => '60',
    'mesh_stale_secs' => '300',
    'mesh_holdoff_secs' => '120',
    'mesh_private_only' => 'yes',
    'mesh_eth_ifaces' => '',
    'mesh_peer_ips' => '',
  ];
}

function tbn_mesh_enabled(?array $cfg = null) {
  if ($cfg === null) $cfg = tbn_load_cfg();
  return (($cfg['mesh_report'] ?? 'no') === 'yes') && trim((string)($cfg['mesh_token'] ?? '')) !== '';
}

/**
 * Local Thunderbolt controller UUID(s) (sysfs …/N-0/unique_id).
 * Used to match a peer's link that points back at us (peer_unique_id).
 *
 * @return string[]
 */
function tbn_mesh_local_fabric_uuids() {
  $ids = [];
  foreach (glob('/sys/bus/thunderbolt/devices/*-0/unique_id') ?: [] as $p) {
    $id = trim((string)@file_get_contents($p));
    if ($id !== '' && preg_match('/^[a-f0-9-]{8,64}$/i', $id)) {
      $ids[strtolower($id)] = strtolower($id);
    }
  }
  return array_values($ids);
}

function tbn_mesh_speed_to_mbps($raw) {
  if (function_exists('tbn_of_speed_to_mbps')) {
    $v = tbn_of_speed_to_mbps($raw);
    if ($v > 0) return (int)$v;
  }
  $s = trim((string)$raw);
  if ($s === '') return 0;
  if (preg_match('/([\d.]+)\s*Gb/i', $s, $m)) return (int)round((float)$m[1] * 1000);
  if (preg_match('/([\d.]+)\s*Mb/i', $s, $m)) return (int)round((float)$m[1]);
  if (preg_match('/^([\d.]+)$/', $s, $m)) {
    $n = (float)$m[1];
    return (int)round($n > 500 ? $n : $n * 1000);
  }
  return 0;
}

function tbn_mesh_lanes_int($raw) {
  if ($raw === '' || $raw === null) return 0;
  return preg_match('/(\d+)/', (string)$raw, $m) ? (int)$m[1] : 0;
}

function tbn_mesh_fmt_mbps($mbps) {
  $mbps = (int)$mbps;
  if ($mbps >= 1000) {
    $g = $mbps / 1000.0;
    return (abs($g - round($g)) < 0.05 ? (string)(int)round($g) : number_format($g, 1)) . 'G';
  }
  return $mbps . 'M';
}

function tbn_mesh_snapshot(?array $links = null, ?array $cfg = null) {
  if ($cfg === null) $cfg = tbn_load_cfg();
  if ($links === null) $links = tbn_link_summaries();
  $out_links = [];
  foreach ($links as $L) {
    $rem = $L['remote'] ?? [];
    $out_links[] = [
      'media' => 'thunderbolt',
      'peer_key' => function_exists('tbn_peer_key_from_link') ? tbn_peer_key_from_link($L) : ($L['iface'] ?? ''),
      'peer_unique_id' => $rem['unique_id'] ?? '',
      'peer_name' => $rem['peer_name'] ?? '',
      'local_iface' => $L['iface'] ?? '',
      'local_label' => $L['label'] ?? '',
      'local_addrs' => $L['local']['addrs'] ?? [],
      'tx_speed_raw' => $rem['tx_speed'] ?? '',
      'rx_speed_raw' => $rem['rx_speed'] ?? '',
      'tx_mbps' => tbn_mesh_speed_to_mbps($rem['tx_speed'] ?? ''),
      'rx_mbps' => tbn_mesh_speed_to_mbps($rem['rx_speed'] ?? ''),
      'tx_lanes' => tbn_mesh_lanes_int($rem['tx_lanes'] ?? ''),
      'rx_lanes' => tbn_mesh_lanes_int($rem['rx_lanes'] ?? ''),
      'operstate' => $L['local']['operstate'] ?? '',
      'negotiated_mbps' => 0,
      'max_mbps' => 0,
      'duplex' => '',
    ];
  }
  $eth = trim((string)($cfg['mesh_eth_ifaces'] ?? ''));
  if ($eth !== '') {
    foreach (preg_split('/[\s,]+/', $eth) as $if) {
      $if = trim($if);
      if ($if === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $if)) continue;
      if (strpos($if, 'thunderbolt') === 0) continue;
      if (preg_match('/^(br|wlan|wlp|tailscale|docker|veth|virbr)/', $if)) continue;
      $base = '/sys/class/net/' . $if;
      if (!is_dir($base)) continue;
      $neg = 0; $max = 0; $duplex = '';
      $lines = [];
      @exec('ethtool ' . escapeshellarg($if) . ' 2>/dev/null', $lines);
      foreach ($lines as $line) {
        if (preg_match('/Speed:\s*(\d+)/i', $line, $m)) $neg = (int)$m[1];
        if (preg_match('/Duplex:\s*(\S+)/i', $line, $m)) $duplex = strtolower($m[1]);
      }
      $modes = [];
      @exec('ethtool ' . escapeshellarg($if) . ' 2>/dev/null', $modes);
      foreach ($modes as $line) {
        if (preg_match_all('/(\d+)base/i', $line, $mm)) {
          foreach ($mm[1] as $spd) $max = max($max, (int)$spd);
        }
      }
      if ($max <= 0) $max = $neg;
      $out_links[] = [
        'media' => 'ethernet',
        'peer_key' => 'eth:' . $if,
        'peer_unique_id' => '',
        'peer_name' => '',
        'local_iface' => $if,
        'local_label' => $if,
        'local_addrs' => function_exists('tbn_iface_addrs') ? tbn_iface_addrs($if) : [],
        'tx_speed_raw' => $neg > 0 ? ($neg . 'Mb/s') : '',
        'rx_speed_raw' => $neg > 0 ? ($neg . 'Mb/s') : '',
        'tx_mbps' => $neg,
        'rx_mbps' => $neg,
        'tx_lanes' => 0,
        'rx_lanes' => 0,
        'operstate' => function_exists('tbn_sysfs_str') ? tbn_sysfs_str($base . '/operstate') : '',
        'negotiated_mbps' => $neg,
        'max_mbps' => $max,
        'duplex' => $duplex,
      ];
    }
  }
  $of = ['present' => false, 'iface_metrics' => []];
  if (function_exists('tbn_of_frr_detect')) {
    $det = tbn_of_frr_detect();
    $of['present'] = !empty($det['present']);
  }
  if (function_exists('tbn_of_participate_ifaces')) {
    foreach (tbn_of_participate_ifaces($cfg) as $row) {
      $of['iface_metrics'][] = [
        'if' => $row['if'] ?? '',
        'metric' => (int)($row['metric'] ?? 0),
        'mode' => $row['mode'] ?? '',
        'participate' => $row['participate'] ?? '',
      ];
    }
  }
  return [
    'schema' => tbn_mesh_schema_version(),
    'plugin' => 'ThunderboltNet',
    'plugin_version' => tbn_mesh_plugin_version(),
    'host_id' => tbn_mesh_host_id(),
    'hostname' => gethostname() ?: '',
    'generated_at' => date('c'),
    'beacon_port' => tbn_mesh_beacon_port(),
    'fabric_uuids' => tbn_mesh_local_fabric_uuids(),
    'links' => $out_links,
    'openfabric' => $of,
    'dhcp_servers' => tbn_mesh_dhcp_servers_snapshot($cfg),
  ];
}

function tbn_mesh_validate_pair(array $local_link, ?array $peer_link = null, $stale = false) {
  $local = [
    'tx_mbps' => (int)($local_link['tx_mbps'] ?? 0),
    'rx_mbps' => (int)($local_link['rx_mbps'] ?? 0),
    'tx_lanes' => (int)($local_link['tx_lanes'] ?? 0),
    'rx_lanes' => (int)($local_link['rx_lanes'] ?? 0),
    'max_mbps' => (int)($local_link['max_mbps'] ?? 0),
    'negotiated_mbps' => (int)($local_link['negotiated_mbps'] ?? 0),
    'media' => $local_link['media'] ?? 'thunderbolt',
  ];
  if ($peer_link === null || $stale) {
    return [
      'status' => 'orange',
      'reason' => $stale ? 'peer_stale' : 'local_only',
      'local' => $local,
      'peer' => null,
      'info' => '',
    ];
  }
  $peer = [
    'tx_mbps' => (int)($peer_link['tx_mbps'] ?? 0),
    'rx_mbps' => (int)($peer_link['rx_mbps'] ?? 0),
    'tx_lanes' => (int)($peer_link['tx_lanes'] ?? 0),
    'rx_lanes' => (int)($peer_link['rx_lanes'] ?? 0),
    'max_mbps' => (int)($peer_link['max_mbps'] ?? 0),
    'negotiated_mbps' => (int)($peer_link['negotiated_mbps'] ?? 0),
  ];
  $agree = function ($a, $b) {
    if ($a <= 0 && $b <= 0) return true;
    if ($a <= 0 || $b <= 0) return false;
    $tol = max(2000, (int)round(max($a, $b) * 0.15));
    return abs($a - $b) <= $tol;
  };
  $tx_ok = $agree($local['tx_mbps'], $peer['rx_mbps']);
  $rx_ok = $agree($local['rx_mbps'], $peer['tx_mbps']);
  if (($local_link['media'] ?? '') === 'ethernet') {
    $n1 = $local['negotiated_mbps'] ?: $local['tx_mbps'];
    $n2 = $peer['negotiated_mbps'] ?: $peer['tx_mbps'];
    $tx_ok = $rx_ok = $agree($n1, $n2);
  }
  $info = '';
  if ($tx_ok && $rx_ok) {
    $reason = 'agree';
    if ($local['tx_mbps'] > 0 && $local['rx_mbps'] > 0
      && abs($local['tx_mbps'] - $local['rx_mbps']) > max(2000, $local['tx_mbps'] * 0.15)) {
      $reason = 'agree_asymmetric';
    }
    $neg = $local['negotiated_mbps'] ?: max($local['tx_mbps'], $local['rx_mbps']);
    $cap = max($local['max_mbps'], $peer['max_mbps']);
    if ($cap > 0 && $neg > 0 && $neg < $cap * 0.85) {
      $info = 'Negotiated ' . tbn_mesh_fmt_mbps($neg)
        . ' (path limited by the slowest link partner; capability up to '
        . tbn_mesh_fmt_mbps($cap) . ' — normal).';
    }
    return ['status' => 'green', 'reason' => $reason, 'local' => $local, 'peer' => $peer, 'info' => $info];
  }
  return ['status' => 'red', 'reason' => 'disagree_speed', 'local' => $local, 'peer' => $peer, 'info' => ''];
}

function tbn_mesh_status_label($status, $reason = '') {
  if ($status === 'green') {
    return $reason === 'agree_asymmetric' ? 'Validated (asymmetric OK)' : 'Validated (both ends agree)';
  }
  if ($status === 'red') return 'Disagree — the two hosts report different rates';
  if ($reason === 'peer_stale') return 'Unverified (peer report stale)';
  if ($reason === 'probing') return 'Probing…';
  return 'Unverified (local only)';
}

function tbn_mesh_apply_holdoff(array $prev, array $new_result, $holdoff_secs = 120) {
  $now = time();
  $holdoff_secs = max(30, (int)$holdoff_secs);
  $new_status = $new_result['status'] ?? 'orange';
  $confirmed = $prev['status'] ?? '';
  $pending = $prev['pending_status'] ?? '';
  $pending_since = strtotime($prev['pending_since'] ?? '') ?: 0;

  if ($confirmed === '' || empty($prev['checked_at'])) {
    $new_result['pending_status'] = '';
    $new_result['pending_since'] = '';
    $new_result['checked_at'] = date('c', $now);
    if ($new_status === 'red' || $new_status === 'green') {
      // first non-orange still needs hold-off for red flips from green later
    }
    return $new_result;
  }
  if ($new_status === $confirmed) {
    $new_result['pending_status'] = '';
    $new_result['pending_since'] = '';
    $new_result['checked_at'] = date('c', $now);
    return $new_result;
  }
  if ($pending === $new_status && $pending_since > 0 && ($now - $pending_since) >= $holdoff_secs) {
    $new_result['pending_status'] = '';
    $new_result['pending_since'] = '';
    $new_result['checked_at'] = date('c', $now);
    return $new_result;
  }
  $out = $prev;
  $out['pending_status'] = $new_status;
  $out['pending_since'] = ($pending === $new_status && $pending_since)
    ? date('c', $pending_since) : date('c', $now);
  $out['reason_pending'] = $new_result['reason'] ?? '';
  $out['local'] = $new_result['local'] ?? ($out['local'] ?? []);
  $out['peer'] = array_key_exists('peer', $new_result) ? $new_result['peer'] : ($out['peer'] ?? null);
  $out['info'] = $new_result['info'] ?? '';
  return $out;
}

function tbn_mesh_is_private_ip($ip) {
  $ip = trim((string)$ip);
  if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;
  $long = ip2long($ip);
  if ($long === false) return false;
  foreach ([['10.0.0.0','10.255.255.255'],['172.16.0.0','172.31.255.255'],['192.168.0.0','192.168.255.255'],['169.254.0.0','169.254.255.255']] as $r) {
    if ($long >= ip2long($r[0]) && $long <= ip2long($r[1])) return true;
  }
  return false;
}

/**
 * IPv4 addresses on this host (any iface) — used to avoid polling ourselves.
 *
 * @return array<string,true>
 */
function tbn_mesh_local_ipv4_set() {
  static $cache = null;
  if (is_array($cache)) {
    return $cache;
  }
  $cache = [];
  $lines = [];
  @exec('ip -4 -o addr show scope global 2>/dev/null', $lines);
  foreach ($lines as $line) {
    if (preg_match('/\binet\s+(\d+\.\d+\.\d+\.\d+)/', $line, $m)) {
      $cache[$m[1]] = true;
    }
  }
  // Always exclude loopback
  $cache['127.0.0.1'] = true;
  return $cache;
}

/** True when $ip is inside $cidr (a.b.c.d/nn). */
function tbn_mesh_ip_in_cidr($ip, $cidr) {
  if (!preg_match('#^(\d{1,3}(?:\.\d{1,3}){3})/(\d{1,2})$#', (string)$cidr, $m)) {
    return false;
  }
  $il = ip2long((string)$ip);
  $nl = ip2long($m[1]);
  $p = (int)$m[2];
  if ($il === false || $nl === false || $p < 1 || $p > 32) {
    return false;
  }
  $mask = (-1 << (32 - $p)) & 0xffffffff;
  return ($il & $mask) === ($nl & $mask);
}

/**
 * Poll targets from `ip -4 neigh show dev X` lines: only resolved neighbours
 * inside this link's own subnet(s). The far end can plant neighbour entries for
 * any address; outside the link subnet the poll (and token) would leave the cable.
 *
 * @param string[] $lines
 * @param string[] $iface_cidrs e.g. ["10.255.0.1/24"]
 * @param array<string,true> $local_ips
 * @return string[]
 */
function tbn_mesh_neigh_targets(array $lines, array $iface_cidrs, array $local_ips) {
  $out = [];
  foreach ($lines as $line) {
    if (!preg_match('/^(\d{1,3}(?:\.\d{1,3}){3})\s/', (string)$line, $m)) {
      continue;
    }
    if (preg_match('/\b(FAILED|INCOMPLETE|NOARP)\b/', (string)$line)) {
      continue;
    }
    $ip = $m[1];
    if (!empty($local_ips[$ip]) || !tbn_mesh_is_private_ip($ip)) {
      continue;
    }
    foreach ($iface_cidrs as $c) {
      if (tbn_mesh_ip_in_cidr($ip, $c)) {
        $out[$ip] = $ip;
        break;
      }
    }
  }
  return array_values($out);
}

function tbn_mesh_peer_targets(?array $cfg = null, ?array $links = null) {
  if ($cfg === null) $cfg = tbn_load_cfg();
  if ($links === null) $links = tbn_link_summaries();
  $local_ips = tbn_mesh_local_ipv4_set();
  $ips = [];
  foreach (preg_split('/[\s,;]+/', trim((string)($cfg['mesh_peer_ips'] ?? ''))) as $ip) {
    $ip = trim($ip);
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && empty($local_ips[$ip])) {
      $ips[$ip] = 'manual';
    }
  }
  foreach ($links as $L) {
    $if = $L['iface'] ?? '';
    if ($if === '' || strpos($if, 'thunderbolt') !== 0) continue;
    $lines = [];
    @exec('ip -4 neigh show dev ' . escapeshellarg($if) . ' 2>/dev/null', $lines);
    $cidrs = function_exists('tbn_iface_addrs') ? tbn_iface_addrs($if) : [];
    foreach (tbn_mesh_neigh_targets($lines, $cidrs, $local_ips) as $ip) {
      if (!isset($ips[$ip])) {
        $ips[$ip] = 'neigh';
      }
    }
  }
  $port = tbn_mesh_beacon_port();
  $out = [];
  foreach ($ips as $ip => $src) {
    $out[] = [
      'ip' => $ip,
      'url' => 'http://' . $ip . ':' . $port . '/',
      'source' => $src,
    ];
  }
  return $out;
}

/**
 * HTTP context for a peer poll: no redirects (a peer could bounce the request,
 * token header included, to any host), short timeout, header-safe token.
 */
function tbn_mesh_http_options($token, $timeout = 4) {
  $token = preg_replace('/[^\x21-\x7e]/', '', (string)$token);
  return [
    'http' => [
      'method' => 'GET',
      'timeout' => (float)$timeout,
      'follow_location' => 0,
      'max_redirects' => 0,
      'header' => "X-Tbn-Mesh-Token: {$token}\r\nUser-Agent: ThunderboltNet-Mesh/1.1\r\nConnection: close\r\n",
      'ignore_errors' => true,
      'protocol_version' => 1.0,
    ],
  ];
}

/**
 * Decode a peer snapshot body (size-capped). Pure; used by the poller and tests.
 *
 * @return array{ok:bool,error:string,data:?array}
 */
function tbn_mesh_decode_body($body, $code, $max = null) {
  $max = $max ?? tbn_mesh_max_body();
  if (!is_string($body) || $body === '') {
    return ['ok' => false, 'error' => 'empty', 'data' => null];
  }
  if (strlen($body) > $max) {
    return ['ok' => false, 'error' => 'too_large', 'data' => null];
  }
  if ((int)$code !== 200) {
    return ['ok' => false, 'error' => 'http_' . (int)$code, 'data' => null];
  }
  $data = json_decode($body, true, 32);
  if (!is_array($data) || empty($data['host_id']) || !is_string($data['host_id'])) {
    return ['ok' => false, 'error' => 'bad_json', 'data' => null];
  }
  // Shape the untrusted payload before anything iterates it
  $data['hostname'] = substr(is_string($data['hostname'] ?? null) ? $data['hostname'] : '', 0, 255);
  $data['generated_at'] = is_string($data['generated_at'] ?? null) ? substr($data['generated_at'], 0, 40) : '';
  $data['fabric_uuids'] = array_values(array_filter(
    is_array($data['fabric_uuids'] ?? null) ? $data['fabric_uuids'] : [],
    'is_string'
  ));
  $links = [];
  foreach (is_array($data['links'] ?? null) ? $data['links'] : [] as $L) {
    if (is_array($L)) {
      foreach ($L as $k => $v) {
        if (!is_scalar($v) && !(is_array($v) && $k === 'local_addrs')) {
          unset($L[$k]);
        }
      }
      $links[] = $L;
    }
  }
  $data['links'] = array_slice($links, 0, 64);
  return ['ok' => true, 'error' => '', 'data' => $data];
}

function tbn_mesh_fetch_peer($url, $token, $timeout = 4) {
  $ctx = stream_context_create(tbn_mesh_http_options($token, $timeout));
  $fp = @fopen($url, 'rb', false, $ctx);
  if (!$fp) {
    return ['ok' => false, 'code' => 0, 'error' => 'connect', 'data' => null];
  }
  stream_set_timeout($fp, (int)ceil($timeout));
  $max = tbn_mesh_max_body();
  $body = (string)@stream_get_contents($fp, $max + 1);
  $meta = @stream_get_meta_data($fp);
  fclose($fp);
  $code = 0;
  foreach ((array)($meta['wrapper_data'] ?? []) as $h) {
    if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string)$h, $m)) {
      $code = (int)$m[1];
    }
  }
  $r = tbn_mesh_decode_body($body, $code, $max);
  $r['code'] = $code;
  return $r;
}

function tbn_mesh_poll_all(?array $cfg = null, $lock_wait = 0) {
  if ($cfg === null) $cfg = tbn_load_cfg();
  $r = tbn_locked(function () use ($cfg) {
    return tbn_mesh_poll_all_unlocked($cfg);
  }, $lock_wait, true);
  if ($r === null) {
    return ['enabled' => tbn_mesh_enabled($cfg), 'polled' => 0, 'ok' => 0, 'edges' => [], 'hosts' => [], 'error' => 'busy'];
  }
  return $r;
}

function tbn_mesh_poll_all_unlocked(array $cfg) {
  $result = ['enabled' => tbn_mesh_enabled($cfg), 'polled' => 0, 'ok' => 0, 'edges' => [], 'hosts' => [], 'error' => ''];
  if (!$result['enabled']) {
    $result['error'] = 'mesh_report off or token empty';
    tbn_mesh_beacon_stop();
    return $result;
  }
  // Ensure local beacon is up so peers can fetch us without Unraid login
  $beacon = tbn_mesh_beacon_ensure($cfg);
  $result['beacon'] = $beacon;
  $token = trim((string)$cfg['mesh_token']);
  $stale_secs = max(60, (int)($cfg['mesh_stale_secs'] ?? 300));
  $holdoff = max(30, (int)($cfg['mesh_holdoff_secs'] ?? 120));
  $links = tbn_link_summaries();
  $local_snap = tbn_mesh_snapshot($links, $cfg);
  $targets = tbn_mesh_peer_targets($cfg, $links);
  $cache_dir = tbn_mesh_cache_dir();
  $peers = tbn_load_peers_memory();
  $valmap = tbn_mesh_validation_load();
  $forgotten = function_exists('tbn_load_forgotten_peers') ? tbn_load_forgotten_peers() : [];
  $now = time();
  $our_host = $local_snap['hostname'] ?? '';
  $our_id = $local_snap['host_id'] ?? tbn_mesh_host_id();

  foreach (array_slice($targets, 0, 16) as $t) {
    $result['polled']++;
    $fetch = tbn_mesh_fetch_peer($t['url'], $token);
    if (!$fetch['ok']) {
      $result['edges'][] = ['ip' => $t['ip'], 'ok' => false, 'error' => $fetch['error'], 'code' => $fetch['code']];
      continue;
    }
    $data = $fetch['data'];
    $hid = trim((string)($data['host_id'] ?? ''));
    // Never treat our own export as a peer (self-poll / dual-IP confusion)
    if ($hid !== '' && $our_id !== '' && strcasecmp($hid, $our_id) === 0) {
      $result['edges'][] = ['ip' => $t['ip'], 'ok' => false, 'error' => 'self_host_id', 'code' => 0];
      continue;
    }
    $remote_host = trim((string)($data['hostname'] ?? ''));
    if ($remote_host !== '' && $our_host !== '' && strcasecmp($remote_host, $our_host) === 0) {
      $result['edges'][] = ['ip' => $t['ip'], 'ok' => false, 'error' => 'self_hostname', 'code' => 0];
      continue;
    }
    $result['ok']++;
    $safe = preg_replace('/[^a-f0-9-]/i', '', $hid);
    if ($safe === '') {
      $safe = 'peer-' . md5($t['ip']);
    }
    $data['_fetched_at'] = date('c');
    $data['_fetch_ip'] = $t['ip'];
    $data['_source'] = $t['source'];
    $safe = substr($safe, 0, 64);
    tbn_write_json_file($cache_dir . '/' . $safe . '.json', $data);
    $result['hosts'][] = ['host_id' => $hid, 'hostname' => $data['hostname'] ?? '', 'ip' => $t['ip'], 'links' => count($data['links'] ?? [])];

    $our_fabrics_lc = array_map('strtolower', tbn_mesh_local_fabric_uuids());
    $remote_fabrics_lc = array_map('strtolower', $data['fabric_uuids'] ?? []);
    // Their link(s) that point at our controller(s)
    $back_links = [];
    foreach ($data['links'] ?? [] as $pl) {
      if (($pl['media'] ?? '') !== 'thunderbolt') {
        continue;
      }
      $pl_uid = strtolower(trim((string)($pl['peer_unique_id'] ?? '')));
      if ($pl_uid !== '' && in_array($pl_uid, $our_fabrics_lc, true)) {
        $back_links[] = $pl;
      }
    }
    foreach ($local_snap['links'] as $ll) {
      if (($ll['media'] ?? '') !== 'thunderbolt') continue;
      $pname = trim((string)($ll['peer_name'] ?? ''));
      $puid = strtolower(trim((string)($ll['peer_unique_id'] ?? '')));
      $match = null;
      // Pair only if this local link is the path to the host we just fetched
      $ll_is_remote = false;
      if ($pname !== '' && $remote_host !== '' && strcasecmp($pname, $remote_host) === 0) {
        $ll_is_remote = true;
      }
      if ($puid !== '' && $remote_fabrics_lc && in_array($puid, $remote_fabrics_lc, true)) {
        $ll_is_remote = true;
      }
      if ($ll_is_remote && $back_links) {
        $match = $back_links[0];
      }
      // Name cross-check fallback
      if ($match === null && $pname !== '' && $remote_host !== '' && strcasecmp($pname, $remote_host) === 0) {
        foreach ($data['links'] ?? [] as $pl) {
          if (($pl['media'] ?? '') !== 'thunderbolt') continue;
          $pl_name = trim((string)($pl['peer_name'] ?? ''));
          if ($our_host !== '' && ($pl_name === '' || strcasecmp($pl_name, $our_host) === 0)) {
            $match = $pl;
            break;
          }
        }
      }
      // Single TB link each side + different mesh host_id
      if ($match === null) {
        $loc_tb = array_values(array_filter($local_snap['links'], function ($x) { return ($x['media'] ?? '') === 'thunderbolt'; }));
        $rem_tb = array_values(array_filter($data['links'] ?? [], function ($x) { return ($x['media'] ?? '') === 'thunderbolt'; }));
        if (count($loc_tb) === 1 && count($rem_tb) === 1
            && ($ll['local_iface'] ?? '') === ($loc_tb[0]['local_iface'] ?? '')
            && $hid !== '' && $our_id !== '' && strcasecmp($hid, $our_id) !== 0) {
          $match = $rem_tb[0];
        }
      }
      if ($match === null) continue;
      $gen = strtotime($data['generated_at'] ?? '') ?: $now;
      $stale = ($now - $gen) > $stale_secs;
      $raw = tbn_mesh_validate_pair($ll, $match, $stale);
      $pkey = $ll['peer_key'] ?? '';
      if ($pkey === '') continue;
      if (function_exists('tbn_sanitize_peer_key')) {
        $pkey = tbn_sanitize_peer_key($pkey);
        if ($pkey === '') continue;
      }
      if (isset($forgotten[$pkey])) continue;
      $prev = $valmap[$pkey] ?? [];
      $val = tbn_mesh_apply_holdoff($prev, $raw, $holdoff);
      $val['peer_host_id'] = $hid;
      $val['peer_hostname'] = $data['hostname'] ?? '';
      $val['fetch_ip'] = $t['ip'];
      $val['fetched_at'] = date('c');
      $valmap[$pkey] = $val;
      $result['edges'][] = [
        'peer_key' => $pkey,
        'status' => $val['status'] ?? 'orange',
        'reason' => $val['reason'] ?? '',
        'info' => $val['info'] ?? '',
        'peer_hostname' => $data['hostname'] ?? '',
        'ok' => true,
      ];
    }
  }

  foreach ($local_snap['links'] as $ll) {
    $pkey = $ll['peer_key'] ?? '';
    if ($pkey === '' || !isset($peers[$pkey])) continue;
    if (empty($valmap[$pkey])) {
      $valmap[$pkey] = [
        'status' => 'orange',
        'reason' => 'local_only',
        'checked_at' => date('c'),
        'local' => ['tx_mbps' => $ll['tx_mbps'] ?? 0, 'rx_mbps' => $ll['rx_mbps'] ?? 0],
        'peer' => null,
        'info' => '',
      ];
    }
  }
  tbn_mesh_validation_save($valmap);
  tbn_mesh_prune_cache($cache_dir, tbn_mesh_cache_keep(), max(3600, $stale_secs * 4));
  tbn_write_json_file(tbn_mesh_last_poll_path(), ['at' => date('c'), 'result' => $result]);
  return $result;
}

function tbn_mesh_maybe_poll(?array $cfg = null, $force = false) {
  if ($cfg === null) $cfg = tbn_load_cfg();
  if (!tbn_mesh_enabled($cfg)) return null;
  $interval = max(30, (int)($cfg['mesh_poll_secs'] ?? 60));
  $path = tbn_mesh_last_poll_path();
  if (!$force && is_readable($path)) {
    $j = json_decode((string)@file_get_contents($path), true);
    $at = strtotime($j['at'] ?? '') ?: 0;
    if ($at > 0 && (time() - $at) < $interval) return $j['result'] ?? null;
  }
  return tbn_mesh_poll_all($cfg);
}

function tbn_mesh_cached_hosts() {
  $dir = tbn_mesh_cache_dir();
  $out = [];
  if (!is_dir($dir)) return $out;
  foreach (glob($dir . '/*.json') ?: [] as $f) {
    $j = json_decode((string)@file_get_contents($f), true);
    if (!is_array($j) || empty($j['host_id'])) continue;
    $out[] = $j;
  }
  return $out;
}

function tbn_mesh_ensure_token(array &$cfg) {
  if (($cfg['mesh_report'] ?? 'no') !== 'yes') return;
  if (trim((string)($cfg['mesh_token'] ?? '')) !== '') return;
  $cfg['mesh_token'] = bin2hex(random_bytes(16));
}

function tbn_mesh_legend_html() {
  return '<div class="tbn-mesh-legend" role="note">'
    . '<span class="tbn-mesh-pill tbn-mesh-green">Match</span> Same trained rates. '
    . '<span class="tbn-mesh-pill tbn-mesh-orange">Unverified</span> Peer silent / token mismatch / not Unraid — not a bad cable. '
    . '<span class="tbn-mesh-pill tbn-mesh-orange">Waiting…</span> First poll pending. '
    . '<span class="tbn-mesh-pill tbn-mesh-red">Mismatch</span> Different speeds. '
    . '<span class="tbn-muted">Blue row = online path; green/red = check result. Token: Settings → Peer link check.</span>'
    . '</div>';
}

function tbn_mesh_badge_html(?array $val = null, $enabled = null) {
  if ($enabled === null && function_exists('tbn_load_cfg')) {
    $enabled = tbn_mesh_enabled(tbn_load_cfg());
  }
  if ($enabled === false) {
    // Do not show stale green/orange from an old poll when check is off or token missing
    return '<span class="tbn-muted" title="Link check off or shared token unset (Thunderbolt → Settings → Peer link check). Not FRR.">—</span>';
  }
  if (!$val || empty($val['status'])) {
    return '<span class="tbn-mesh-pill tbn-mesh-orange" title="Waiting for first peer plugin report (same mesh token on both Unraid hosts)">Waiting…</span>';
  }
  // Stale self-match artifacts
  if (($val['reason'] ?? '') === 'self' || ($val['error'] ?? '') === 'self_host_id') {
    return '<span class="tbn-muted" title="Ignored self report">—</span>';
  }
  $st = $val['status'];
  $reason = (string)($val['reason'] ?? '');
  $label = tbn_mesh_status_label($st, $reason);
  // Shorter table labels
  if ($st === 'green') {
    $label = ($reason === 'agree_asymmetric') ? 'Match (asymmetric)' : 'Match';
  } elseif ($st === 'red') {
    $label = 'Mismatch';
  } elseif ($st === 'orange') {
    if ($reason === 'peer_stale') {
      $label = 'Stale';
    } elseif ($reason === 'local_only') {
      $label = 'Unverified';
    } else {
      $label = 'Unverified';
    }
  }
  $cls = 'tbn-mesh-orange';
  if ($st === 'green') $cls = 'tbn-mesh-green';
  elseif ($st === 'red') $cls = 'tbn-mesh-red';
  $title = htmlspecialchars(tbn_mesh_status_label($st, $reason) . (!empty($val['info']) ? ' — ' . $val['info'] : ''), ENT_QUOTES);
  $html = '<span class="tbn-mesh-pill ' . $cls . '" title="' . $title . '">' . htmlspecialchars($label) . '</span>';
  if (!empty($val['info'])) {
    $html .= '<div class="tbn-mesh-info-note">' . htmlspecialchars($val['info']) . '</div>';
  }
  return $html;
}

function tbn_mesh_reports_panel_html(?array $cfg = null) {
  if ($cfg === null) $cfg = tbn_load_cfg();
  $enabled = tbn_mesh_enabled($cfg);
  $hosts = tbn_mesh_cached_hosts();
  $poll = null;
  if (is_readable(tbn_mesh_last_poll_path())) {
    $j = json_decode((string)@file_get_contents(tbn_mesh_last_poll_path()), true);
    $poll = $j;
  }
  $html = '<div class="tbn-section tbn-mesh-panel">';
  $html .= '<h3>Peer link check</h3>';
  $html .= '<p class="tbn-note">Unraid↔Unraid trained-rate compare (shared token under Settings). '
    . '<strong>Not FRR.</strong> Orange / Unverified is not a bad cable.</p>';
  // Single legend for the Peers tab (callers must not also print tbn_mesh_legend_html).
  $html .= tbn_mesh_legend_html();
  if (!$enabled) {
    $html .= '<p class="tbn-muted">Link check is <strong>off</strong> or the token is empty on this host. '
      . '<a href="/Settings/NetworkSettings" onclick="return tbnGotoFabricReportsSettings(event)">'
      . 'Open Settings → enable fabric reports + set the same token on both Unraid hosts</a>.</p>';
    $html .= '</div>';
    return $html;
  }
  $at = $poll['at'] ?? '';
  $at_disp = ($at !== '' && function_exists('tbn_format_when_html'))
    ? tbn_format_when_html($at)
    : htmlspecialchars($at !== '' ? $at : 'never');
  $html .= '<p class="tbn-muted">Last poll: ' . $at_disp
    . ' · Peer reports cached: ' . count($hosts) . '</p>';
  if (!$hosts) {
    $html .= '<p class="tbn-muted">No peer reports yet. On each other Unraid: same token, Peer link check on, and a reachable '
      . 'Thunderbolt IP (or Mesh peer IPs). Export uses a small local beacon on TCP '
      . htmlspecialchars((string)tbn_mesh_beacon_port()) . ' (not the web UI login).</p>';
    $html .= '</div>';
    return $html;
  }
  $html .= '<table class="tbn-table tbn-wide tbn-mesh-hosts"><thead><tr>'
    . '<th>Peer host</th><th>Source IP</th><th>Age</th><th>Their links (as they see them)</th></tr></thead><tbody>';
  $now = time();
  foreach ($hosts as $h) {
    $age = '';
    $ts = strtotime($h['_fetched_at'] ?? $h['generated_at'] ?? '') ?: 0;
    if ($ts) {
      $age_secs = max(0, $now - $ts);
      $age = function_exists('tbn_format_age_seconds')
        ? tbn_format_age_seconds($age_secs)
        : ($age_secs . 's');
    }
    $rows = [];
    foreach ($h['links'] ?? [] as $L) {
      if (($L['media'] ?? '') === 'ethernet') {
        $rows[] = htmlspecialchars(($L['local_iface'] ?? '') . ' eth ' . tbn_mesh_fmt_mbps($L['negotiated_mbps'] ?? $L['tx_mbps'] ?? 0));
      } else {
        $rows[] = htmlspecialchars(
          ($L['local_label'] ?: $L['local_iface'] ?: '?')
          . ' → ' . ($L['peer_name'] ?: 'peer')
          . ' TX ' . tbn_mesh_fmt_mbps($L['tx_mbps'] ?? 0)
          . ' / RX ' . tbn_mesh_fmt_mbps($L['rx_mbps'] ?? 0)
        );
      }
    }
    $html .= '<tr class="tbn-mesh-peer-row"><td><strong>' . htmlspecialchars($h['hostname'] ?? $h['host_id']) . '</strong>'
      . '<br><span class="tbn-muted"><code>' . htmlspecialchars(substr($h['host_id'], 0, 13)) . '…</code></span></td>'
      . '<td><code>' . htmlspecialchars($h['_fetch_ip'] ?? '') . '</code></td>'
      . '<td>' . htmlspecialchars($age) . '</td>'
      . '<td class="tbn-muted">' . ($rows ? implode('<br>', $rows) : '—') . '</td></tr>';
  }
  $html .= '</tbody></table></div>';
  return $html;
}
