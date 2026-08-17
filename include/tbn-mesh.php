<?php
/**
 * Thunderbolt Net — multi-host fabric link reports (mesh).
 * Local sysfs = this host. Peer Unraid TBN export = their local view.
 * Green = agree (incl. crossed TX/RX). Orange = unverified. Red = disagree.
 */
if (!function_exists('tbn_cfg_dir')) {
  require_once __DIR__ . '/tbn-lib.php';
}

function tbn_mesh_schema_version() { return 1; }

function tbn_mesh_host_id_path() { return tbn_cfg_dir() . '/mesh_host_id'; }
function tbn_mesh_cache_dir() { return tbn_cfg_dir() . '/mesh-cache'; }

function tbn_mesh_host_id() {
  $path = tbn_mesh_host_id_path();
  if (is_readable($path)) {
    $id = trim((string)@file_get_contents($path));
    if ($id !== '' && preg_match('/^[a-f0-9-]{8,64}$/i', $id)) return $id;
  }
  $id = is_readable('/etc/machine-id') ? trim((string)@file_get_contents('/etc/machine-id')) : '';
  if ($id === '') $id = bin2hex(random_bytes(16));
  if (strlen($id) === 32 && strpos($id, '-') === false) {
    $id = substr($id,0,8).'-'.substr($id,8,4).'-'.substr($id,12,4).'-'.substr($id,16,4).'-'.substr($id,20,12);
  }
  @mkdir(tbn_cfg_dir(), 0755, true);
  @file_put_contents($path, $id . "\n");
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

function tbn_mesh_enabled(array $cfg = null) {
  if ($cfg === null) $cfg = tbn_load_cfg();
  return (($cfg['mesh_report'] ?? 'no') === 'yes') && trim((string)($cfg['mesh_token'] ?? '')) !== '';
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

function tbn_mesh_snapshot(array $links = null, array $cfg = null) {
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
    'links' => $out_links,
    'openfabric' => $of,
  ];
}

function tbn_mesh_validate_pair(array $local_link, array $peer_link = null, $stale = false) {
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
  if ($status === 'red') return 'Disagree — check cable/hosts';
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

function tbn_mesh_peer_targets(array $cfg = null, array $links = null) {
  if ($cfg === null) $cfg = tbn_load_cfg();
  if ($links === null) $links = tbn_link_summaries();
  $ips = [];
  foreach (preg_split('/[\s,;]+/', trim((string)($cfg['mesh_peer_ips'] ?? ''))) as $ip) {
    $ip = trim($ip);
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) $ips[$ip] = 'manual';
  }
  foreach ($links as $L) {
    $if = $L['iface'] ?? '';
    if ($if === '' || strpos($if, 'thunderbolt') !== 0) continue;
    $lines = [];
    @exec('ip neigh show dev ' . escapeshellarg($if) . ' 2>/dev/null', $lines);
    foreach ($lines as $line) {
      if (preg_match('/^([0-9.]+)\s+/', $line, $m) && tbn_mesh_is_private_ip($m[1])) {
        $ips[$m[1]] = 'neigh';
      }
    }
  }
  $out = [];
  foreach ($ips as $ip => $src) {
    $out[] = [
      'ip' => $ip,
      'url' => 'http://' . $ip . '/plugins/ThunderboltNet/include/tbn-mesh-export.php',
      'source' => $src,
    ];
  }
  return $out;
}

function tbn_mesh_fetch_peer($url, $token, $timeout = 4) {
  $token = (string)$token;
  $ctx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'timeout' => $timeout,
      'header' => "X-Tbn-Mesh-Token: $token\r\nUser-Agent: ThunderboltNet-Mesh/1.0\r\n",
      'ignore_errors' => true,
    ],
  ]);
  $body = @file_get_contents($url, false, $ctx);
  $code = 0;
  if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
    $code = (int)$m[1];
  }
  if (!is_string($body) || $body === '') {
    return ['ok' => false, 'code' => $code, 'error' => 'empty', 'data' => null];
  }
  $data = json_decode($body, true);
  if (!is_array($data) || empty($data['host_id'])) {
    return ['ok' => false, 'code' => $code, 'error' => 'bad_json', 'data' => null];
  }
  return ['ok' => true, 'code' => $code, 'error' => '', 'data' => $data];
}

function tbn_mesh_poll_all(array $cfg = null) {
  if ($cfg === null) $cfg = tbn_load_cfg();
  $result = ['enabled' => tbn_mesh_enabled($cfg), 'polled' => 0, 'ok' => 0, 'edges' => [], 'hosts' => [], 'error' => ''];
  if (!$result['enabled']) {
    $result['error'] = 'mesh_report off or token empty';
    return $result;
  }
  $token = trim((string)$cfg['mesh_token']);
  $stale_secs = max(60, (int)($cfg['mesh_stale_secs'] ?? 300));
  $holdoff = max(30, (int)($cfg['mesh_holdoff_secs'] ?? 120));
  $links = tbn_link_summaries();
  $local_snap = tbn_mesh_snapshot($links, $cfg);
  $targets = tbn_mesh_peer_targets($cfg, $links);
  @mkdir(tbn_mesh_cache_dir(), 0755, true);
  $peers = tbn_load_peers_memory();
  $now = time();
  $our_host = $local_snap['hostname'] ?? '';

  foreach (array_slice($targets, 0, 16) as $t) {
    $result['polled']++;
    $fetch = tbn_mesh_fetch_peer($t['url'], $token);
    if (!$fetch['ok']) {
      $result['edges'][] = ['ip' => $t['ip'], 'ok' => false, 'error' => $fetch['error'], 'code' => $fetch['code']];
      continue;
    }
    $result['ok']++;
    $data = $fetch['data'];
    $hid = $data['host_id'];
    $safe = preg_replace('/[^a-f0-9-]/i', '', $hid);
    $data['_fetched_at'] = date('c');
    $data['_fetch_ip'] = $t['ip'];
    $data['_source'] = $t['source'];
    @file_put_contents(tbn_mesh_cache_dir() . '/' . $safe . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    $result['hosts'][] = ['host_id' => $hid, 'hostname' => $data['hostname'] ?? '', 'ip' => $t['ip'], 'links' => count($data['links'] ?? [])];

    foreach ($local_snap['links'] as $ll) {
      if (($ll['media'] ?? '') !== 'thunderbolt') continue;
      $pname = trim((string)($ll['peer_name'] ?? ''));
      $match = null;
      foreach ($data['links'] ?? [] as $pl) {
        if (($pl['media'] ?? '') !== 'thunderbolt') continue;
        $pl_name = trim((string)($pl['peer_name'] ?? ''));
        if ($pname !== '' && strcasecmp($pname, $data['hostname'] ?? '') === 0) {
          if ($our_host !== '' && strcasecmp($pl_name, $our_host) === 0) {
            $match = $pl;
            break;
          }
        }
      }
      if ($match === null) {
        $loc_tb = array_values(array_filter($local_snap['links'], function ($x) { return ($x['media'] ?? '') === 'thunderbolt'; }));
        $rem_tb = array_values(array_filter($data['links'] ?? [], function ($x) { return ($x['media'] ?? '') === 'thunderbolt'; }));
        if (count($loc_tb) === 1 && count($rem_tb) === 1 && ($ll['local_iface'] ?? '') === ($loc_tb[0]['local_iface'] ?? '')) {
          $match = $rem_tb[0];
        }
      }
      if ($match === null) continue;
      $gen = strtotime($data['generated_at'] ?? '') ?: $now;
      $stale = ($now - $gen) > $stale_secs;
      $raw = tbn_mesh_validate_pair($ll, $match, $stale);
      $pkey = $ll['peer_key'] ?? '';
      if ($pkey === '') continue;
      $prev = $peers[$pkey]['mesh_validation'] ?? [];
      $val = tbn_mesh_apply_holdoff($prev, $raw, $holdoff);
      $val['peer_host_id'] = $hid;
      $val['peer_hostname'] = $data['hostname'] ?? '';
      $val['fetch_ip'] = $t['ip'];
      if (!isset($peers[$pkey])) {
        $peers[$pkey] = ['peer_name' => $ll['peer_name'] ?? '', 'unique_id' => $ll['peer_unique_id'] ?? '', 'online' => true];
      }
      $peers[$pkey]['mesh_validation'] = $val;
      $peers[$pkey]['mesh_last_fetch'] = date('c');
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
    if (empty($peers[$pkey]['mesh_validation'])) {
      $peers[$pkey]['mesh_validation'] = [
        'status' => 'orange',
        'reason' => 'local_only',
        'checked_at' => date('c'),
        'local' => ['tx_mbps' => $ll['tx_mbps'] ?? 0, 'rx_mbps' => $ll['rx_mbps'] ?? 0],
        'peer' => null,
        'info' => '',
      ];
    }
  }
  tbn_save_peers_memory($peers);
  @file_put_contents(tbn_cfg_dir() . '/mesh_last_poll.json', json_encode(['at' => date('c'), 'result' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
  return $result;
}

function tbn_mesh_maybe_poll(array $cfg = null, $force = false) {
  if ($cfg === null) $cfg = tbn_load_cfg();
  if (!tbn_mesh_enabled($cfg)) return null;
  $interval = max(30, (int)($cfg['mesh_poll_secs'] ?? 60));
  $path = tbn_cfg_dir() . '/mesh_last_poll.json';
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
    . '<span class="tbn-mesh-pill tbn-mesh-green">Green</span> Validated — local and peer Unraid plugin reports agree '
    . '(including valid asymmetric TX/RX). '
    . '<span class="tbn-mesh-pill tbn-mesh-orange">Orange</span> Unverified — local only or peer silent/stale; '
    . '<strong>not</strong> a degraded link. '
    . '<span class="tbn-mesh-pill tbn-mesh-red">Red</span> Disagree — both plugins report different speeds; troubleshoot. '
    . '<span class="tbn-mesh-pill tbn-mesh-info">Info</span> Speeds agree but path is below max port capability (slowest partner).'
    . '</div>';
}

function tbn_mesh_badge_html(array $val = null, $enabled = null) {
  if ($enabled === null && function_exists('tbn_load_cfg')) {
    $enabled = tbn_mesh_enabled(tbn_load_cfg());
  }
  if ($enabled === false) {
    return '<span class="tbn-muted" title="Peer link check is off (Thunderbolt → Settings)">—</span>';
  }
  if (!$val || empty($val['status'])) {
    return '<span class="tbn-mesh-pill tbn-mesh-orange" title="Waiting for the other Unraid plugin to report rates">Checking…</span>';
  }
  $st = $val['status'];
  $label = tbn_mesh_status_label($st, $val['reason'] ?? '');
  // Shorter table labels
  if ($st === 'green') {
    $label = ($val['reason'] ?? '') === 'agree_asymmetric' ? 'Match (asymmetric)' : 'Match';
  } elseif ($st === 'red') {
    $label = 'Mismatch';
  } elseif ($st === 'orange') {
    $label = (($val['reason'] ?? '') === 'peer_stale') ? 'Stale' : 'Checking…';
  }
  $cls = 'tbn-mesh-orange';
  if ($st === 'green') $cls = 'tbn-mesh-green';
  elseif ($st === 'red') $cls = 'tbn-mesh-red';
  $title = htmlspecialchars(tbn_mesh_status_label($st, $val['reason'] ?? '') . (!empty($val['info']) ? ' — ' . $val['info'] : ''), ENT_QUOTES);
  $html = '<span class="tbn-mesh-pill ' . $cls . '" title="' . $title . '">' . htmlspecialchars($label) . '</span>';
  if (!empty($val['info'])) {
    $html .= '<div class="tbn-mesh-info-note">' . htmlspecialchars($val['info']) . '</div>';
  }
  return $html;
}

function tbn_mesh_reports_panel_html(array $cfg = null) {
  if ($cfg === null) $cfg = tbn_load_cfg();
  $enabled = tbn_mesh_enabled($cfg);
  $hosts = tbn_mesh_cached_hosts();
  $poll = null;
  if (is_readable(tbn_cfg_dir() . '/mesh_last_poll.json')) {
    $j = json_decode((string)@file_get_contents(tbn_cfg_dir() . '/mesh_last_poll.json'), true);
    $poll = $j;
  }
  $html = '<div class="tbn-section tbn-mesh-panel">';
  $html .= '<h3>Peer link check <span class="tbn-muted">(Unraid plugin rate compare)</span></h3>';
  $html .= '<p class="tbn-note">Peer rows come from <strong>other Unraid hosts on your network</strong> that run this plugin '
    . 'and share a token — not from local sysfs alone, and not from the internet or plugin developers. '
    . 'Orange = unverified, not a bad cable.</p>';
  $html .= tbn_mesh_legend_html();
  if (!$enabled) {
    $html .= '<p class="tbn-muted">Fabric reports are <strong>off</strong> on this host. '
      . 'Enable on each Unraid peer: '
      . '<a href="/Settings/NetworkSettings" onclick="return tbnGotoFabricReportsSettings(event)">'
      . 'Network Settings → Thunderbolt → Settings → Show Fabric reports → Enable fabric reports'
      . '</a>'
      . ' (same shared token). Optional: list private eth ifaces / peer IPs for non-Thunderbolt fabric paths.</p>';
    $html .= '</div>';
    return $html;
  }
  $at = $poll['at'] ?? '';
  $html .= '<p class="tbn-muted">Last poll: ' . htmlspecialchars($at !== '' ? $at : 'never')
    . ' · Hosts cached: ' . count($hosts) . '</p>';
  if (!$hosts) {
    $html .= '<p class="tbn-muted">No peer reports yet. On each peer Unraid: export on, same token, and a reachable private IP '
      . '(Thunderbolt tbn IP and/or eth fabric — add Mesh peer IPs if needed).</p>';
    $html .= '</div>';
    return $html;
  }
  $html .= '<table class="tbn-table tbn-wide tbn-mesh-hosts"><thead><tr>'
    . '<th>Peer host</th><th>Source IP</th><th>Age</th><th>Their links (as they see them)</th></tr></thead><tbody>';
  $now = time();
  foreach ($hosts as $h) {
    $age = '';
    $ts = strtotime($h['_fetched_at'] ?? $h['generated_at'] ?? '') ?: 0;
    if ($ts) $age = max(0, $now - $ts) . 's';
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
