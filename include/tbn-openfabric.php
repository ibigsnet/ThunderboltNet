<?php
/**
 * Thunderbolt Net — OpenFabric / FRR helpers.
 *
 * Stage 1–2: detect FRR, metric policy, conf generate, apply when FRR present.
 * Config keys live in ThunderboltNet.cfg; snippets under configdir/frr/.
 *
 * Absolute paths only where Unraid .page eval needs them — this file is required
 * from tbn-lib / apply / update via full plugin path.
 */

if (!function_exists('tbn_cfg_dir')) {
  require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';
}

/** Marker block for plugin-managed FRR config fragments. */
function tbn_of_marker_begin() {
  return '! BEGIN ThunderboltNet OpenFabric';
}

function tbn_of_marker_end() {
  return '! END ThunderboltNet OpenFabric';
}

function tbn_of_flash_dir() {
  return tbn_cfg_dir() . '/frr';
}

function tbn_of_generated_conf_path() {
  return tbn_of_flash_dir() . '/thunderboltnet-openfabric.conf';
}

function tbn_of_preview_path() {
  return tbn_of_flash_dir() . '/openfabric.preview.conf';
}

/**
 * Whether global OpenFabric is enabled in cfg (intent). Runtime needs FRR.
 */
function tbn_of_enabled(array $cfg = null) {
  if ($cfg === null) {
    $cfg = tbn_load_cfg();
  }
  return ($cfg['openfabric_enable'] ?? 'yes') === 'yes';
}

/**
 * Detect FRR / fabricd / vtysh on this host.
 *
 * @return array{
 *   present:bool,vtysh:string,fabricd:string,version:string,
 *   fabricd_enabled:bool,running:bool,note:string
 * }
 */
/**
 * Optional companion plugin UnraidFRR (package install) — never required.
 */
function tbn_of_unraidfrr_companion() {
  $out = [
    'plugin_dir' => is_dir('/usr/local/emhttp/plugins/UnraidFRR'),
    'marker' => null,
    'install_url' => 'https://raw.githubusercontent.com/ibigsnet/UnraidFRR/main/unraidfrr.plg',
    'project' => 'https://github.com/ibigsnet/UnraidFRR',
  ];
  $marker = '/boot/config/plugins/UnraidFRR/companion.json';
  if (is_readable($marker)) {
    $j = json_decode((string)@file_get_contents($marker), true);
    if (is_array($j)) {
      $out['marker'] = $j;
    }
  }
  return $out;
}

function tbn_of_frr_detect() {
  $out = [
    'present' => false,
    'vtysh' => '',
    'fabricd' => '',
    'version' => '',
    'fabricd_enabled' => false,
    'running' => false,
    'note' => 'FRR not found on this Unraid host',
    'unraidfrr' => tbn_of_unraidfrr_companion(),
  ];

  $vtysh = '';
  foreach (['/usr/bin/vtysh', '/usr/sbin/vtysh', '/bin/vtysh'] as $p) {
    if (is_executable($p)) {
      $vtysh = $p;
      break;
    }
  }
  if ($vtysh === '') {
    $which = trim((string)@shell_exec('command -v vtysh 2>/dev/null'));
    if ($which !== '' && is_executable($which)) {
      $vtysh = $which;
    }
  }
  $out['vtysh'] = $vtysh;

  $fabricd = '';
  foreach (['/usr/lib/frr/fabricd', '/usr/sbin/fabricd', '/usr/bin/fabricd'] as $p) {
    if (is_executable($p)) {
      $fabricd = $p;
      break;
    }
  }
  $out['fabricd'] = $fabricd;

  if ($vtysh === '' && $fabricd === '') {
    return $out;
  }

  $out['present'] = true;
  $ver = '';
  if ($vtysh !== '') {
    $ver = trim((string)@shell_exec(escapeshellarg($vtysh) . ' -v 2>/dev/null | head -1'));
  }
  $out['version'] = $ver !== '' ? $ver : 'FRR present (version unknown)';

  // daemons file may list fabricd=yes
  foreach (['/etc/frr/daemons', '/boot/config/plugins/ThunderboltNet/frr/daemons'] as $dae) {
    if (!is_readable($dae)) {
      continue;
    }
    $raw = (string)@file_get_contents($dae);
    if (preg_match('/^\s*fabricd\s*=\s*yes\s*$/mi', $raw)) {
      $out['fabricd_enabled'] = true;
      break;
    }
  }

  if ($vtysh !== '') {
    $adj = (string)@shell_exec(escapeshellarg($vtysh) . ' -c "show openfabric summary" 2>/dev/null');
    if ($adj !== '' && stripos($adj, 'unknown command') === false && stripos($adj, 'failed') === false) {
      $out['running'] = true;
    }
  }
  // process check fallback
  if (!$out['running']) {
    $ps = (string)@shell_exec('pgrep -a fabricd 2>/dev/null');
    $out['running'] = trim($ps) !== '';
  }

  $out['note'] = $out['running']
    ? 'FRR OpenFabric appears running'
    : ($out['fabricd_enabled']
      ? 'FRR present; fabricd enabled in daemons but not confirmed running'
      : 'FRR present; enable fabricd and apply plugin config to start OpenFabric');

  // Enrich note when companion plugin can supply packages
  if (!$out['present']) {
    $c = $out['unraidfrr'];
    if (!empty($c['plugin_dir'])) {
      $out['note'] = 'UnraidFRR installed but FRR binaries not live yet — add packages under /boot/config/plugins/UnraidFRR/packages/ and Apply there';
    } else {
      $out['note'] = 'FRR not found — install companion plugin UnraidFRR for packaged FRR, or provide vtysh/fabricd yourself';
    }
  }

  return $out;
}

/**
 * Stable auto router-id in 10.254.0.0/16 from machine identity (not a public IP promise).
 */
function tbn_of_auto_router_id() {
  $seed = '';
  foreach (['/etc/machine-id', '/var/lib/dbus/machine-id'] as $f) {
    if (is_readable($f)) {
      $seed = trim((string)@file_get_contents($f));
      if ($seed !== '') {
        break;
      }
    }
  }
  if ($seed === '') {
    $seed = (string)gethostname() . '|' . php_uname('n');
  }
  $h = md5($seed);
  $a = hexdec(substr($h, 0, 2));
  $b = hexdec(substr($h, 2, 2));
  // avoid .0 and .255 in last octet-ish
  $a = $a % 254 + 1;
  $b = $b % 254 + 1;
  return '10.254.' . $a . '.' . $b;
}

function tbn_of_router_id(array $cfg = null) {
  if ($cfg === null) {
    $cfg = tbn_load_cfg();
  }
  $rid = trim((string)($cfg['openfabric_router_id'] ?? ''));
  if ($rid !== '' && filter_var($rid, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    return $rid;
  }
  return tbn_of_auto_router_id();
}

/**
 * Build IS-IS style NET 49.0001.xxxx.xxxx.xxxx.00 from IPv4 router-id.
 */
function tbn_of_net_from_router_id($rid, array $cfg = null) {
  if ($cfg === null) {
    $cfg = tbn_load_cfg();
  }
  $override = trim((string)($cfg['openfabric_net'] ?? ''));
  if ($override !== '' && preg_match('/^[0-9a-fA-F.]+$/', $override)) {
    return $override;
  }
  $parts = array_map('intval', explode('.', (string)$rid));
  if (count($parts) !== 4) {
    $parts = [10, 254, 0, 1];
  }
  // 49.0001 + 6-byte system id from first 3 octets doubled nibble-style
  $sys = sprintf(
    '%02x%02x.%02x%02x.%02x%02x',
    $parts[0] & 0xff,
    $parts[1] & 0xff,
    $parts[2] & 0xff,
    $parts[3] & 0xff,
    ($parts[0] ^ $parts[2]) & 0xff,
    ($parts[1] ^ $parts[3]) & 0xff
  );
  return '49.0001.' . $sys . '.00';
}

/**
 * Parse a trained speed string like "20.00 Gb/s" or "20000" to Mbps.
 */
function tbn_of_speed_to_mbps($raw) {
  $s = trim((string)$raw);
  if ($s === '' || $s === '—' || $s === '-') {
    return 0;
  }
  if (preg_match('/([0-9]+(?:\.[0-9]+)?)\s*Gb/i', $s, $m)) {
    return (int)round((float)$m[1] * 1000);
  }
  if (preg_match('/([0-9]+(?:\.[0-9]+)?)\s*Mb/i', $s, $m)) {
    return (int)round((float)$m[1]);
  }
  if (preg_match('/^([0-9]+)$/', $s, $m)) {
    $n = (int)$m[1];
    // sysfs sometimes in Mb/s already, or raw large numbers
    return $n > 100000 ? (int)round($n / 1000) : $n;
  }
  return 0;
}

/**
 * Auto metric from bandwidth (reference / mbps), min 1.
 */
function tbn_of_metric_from_mbps($mbps, $reference_mbps = 20000) {
  $ref = (int)$reference_mbps;
  if ($ref < 1) {
    $ref = 20000;
  }
  $bw = (int)$mbps;
  if ($bw < 1) {
    // unknown — prefer not to look "best"
    return 100;
  }
  $m = (int)round($ref / $bw);
  return $m < 1 ? 1 : $m;
}

/**
 * Resolve metric for a live link summary entry + iface cfg.
 */
function tbn_of_link_metric(array $link, array $icfg, array $gcfg) {
  $mode = strtolower((string)($icfg['OPENFABRIC_METRIC_MODE'] ?? 'auto'));
  if ($mode === 'manual') {
    $man = trim((string)($icfg['OPENFABRIC_METRIC'] ?? ''));
    if ($man !== '' && ctype_digit($man)) {
      $v = (int)$man;
      return $v < 1 ? 1 : $v;
    }
  }
  $ref = (int)($gcfg['openfabric_metric_reference_mbps'] ?? 20000);
  $rx = tbn_of_speed_to_mbps($link['remote']['rx_speed'] ?? ($link['rx_speed'] ?? ''));
  $tx = tbn_of_speed_to_mbps($link['remote']['tx_speed'] ?? ($link['tx_speed'] ?? ''));
  $mbps = 0;
  if ($rx > 0 && $tx > 0) {
    $mbps = min($rx, $tx);
  } else {
    $mbps = max($rx, $tx);
  }
  return tbn_of_metric_from_mbps($mbps, $ref);
}

/**
 * List TB ifaces that should participate in OpenFabric.
 *
 * @return list<array{if:string,mode:string,metric:int,participate:string}>
 */
function tbn_of_participate_ifaces(array $gcfg = null) {
  if ($gcfg === null) {
    $gcfg = tbn_load_cfg();
  }
  $links = function_exists('tbn_link_summaries') ? tbn_link_summaries() : [];
  $by_if = [];
  foreach ($links as $L) {
    $if = $L['iface'] ?? ($L['if'] ?? '');
    if ($if !== '') {
      $by_if[$if] = $L;
    }
  }

  $out = [];
  $names = function_exists('tbn_list_tb_iface_names') ? tbn_list_tb_iface_names() : array_keys($by_if);
  if (!$names) {
    // fall back to sysfs thunderbolt*
    foreach (@scandir('/sys/class/net') ?: [] as $n) {
      if (preg_match('/^thunderbolt\d+$/', $n)) {
        $names[] = $n;
      }
    }
  }

  foreach ($names as $if) {
    if (!preg_match('/^(thunderbolt\d+|bond-tb\d+)$/', $if)) {
      continue;
    }
    $icfg = function_exists('tbn_load_iface_cfg') ? tbn_load_iface_cfg($if) : [];
    if (!is_array($icfg)) {
      $icfg = [];
    }
    $part = strtolower((string)($icfg['OPENFABRIC_PARTICIPATE'] ?? 'yes'));
    if ($part === '' || $part === 'default') {
      $part = 'yes';
    }
    if ($part === 'no') {
      continue;
    }
    $link = $by_if[$if] ?? ['iface' => $if, 'remote' => []];
    $metric = tbn_of_link_metric($link, $icfg, $gcfg);
    $out[] = [
      'if' => $if,
      'mode' => $part === 'passive' ? 'passive' : 'active',
      'metric' => $metric,
      'participate' => $part,
    ];
  }
  return $out;
}

/**
 * Generate OpenFabric conf body (without writing).
 */
function tbn_of_generate_conf(array $gcfg = null) {
  if ($gcfg === null) {
    $gcfg = tbn_load_cfg();
  }
  $rid = tbn_of_router_id($gcfg);
  $net = tbn_of_net_from_router_id($rid, $gcfg);
  $area = trim((string)($gcfg['openfabric_area'] ?? '1'));
  if ($area === '') {
    $area = '1';
  }
  $v6 = ($gcfg['openfabric_ipv6'] ?? 'yes') === 'yes';
  $ifaces = tbn_of_participate_ifaces($gcfg);

  $lines = [];
  $lines[] = tbn_of_marker_begin();
  $lines[] = '! Generated by Thunderbolt Net — do not edit by hand inside markers';
  $lines[] = '! Router-id (loopback): ' . $rid;
  $lines[] = '! Metric policy: reference_mbps=' . (int)($gcfg['openfabric_metric_reference_mbps'] ?? 20000);
  $lines[] = '!';
  $lines[] = 'hostname ' . preg_replace('/[^A-Za-z0-9._-]/', '-', (string)(gethostname() ?: 'unraid'));
  $lines[] = '!';
  $lines[] = 'interface lo';
  $lines[] = ' ip address ' . $rid . '/32';
  $lines[] = ' ip router openfabric ' . $area;
  if ($v6) {
    $lines[] = ' ipv6 router openfabric ' . $area;
  }
  $lines[] = ' openfabric passive';
  $lines[] = 'exit';
  $lines[] = '!';

  foreach ($ifaces as $row) {
    $if = $row['if'];
    $lines[] = 'interface ' . $if;
    $lines[] = ' ip router openfabric ' . $area;
    if ($v6) {
      $lines[] = ' ipv6 router openfabric ' . $area;
    }
    $lines[] = ' openfabric metric ' . (int)$row['metric'];
    if ($row['mode'] === 'passive') {
      $lines[] = ' openfabric passive';
    }
    // slightly aggressive hellos help hot-plug lab fabrics; still FRR-safe
    $lines[] = ' openfabric hello-interval 3';
    $lines[] = ' openfabric hello-multiplier 10';
    $lines[] = 'exit';
    $lines[] = '!';
  }

  $lines[] = 'router openfabric ' . $area;
  $lines[] = ' net ' . $net;
  $lines[] = 'exit';
  $lines[] = '!';
  $lines[] = tbn_of_marker_end();
  $lines[] = '';

  return implode("\n", $lines);
}

/**
 * Write preview + generated conf to flash.
 */
function tbn_of_write_generated(array $gcfg = null) {
  if ($gcfg === null) {
    $gcfg = tbn_load_cfg();
  }
  $body = tbn_of_generate_conf($gcfg);
  $dir = tbn_of_flash_dir();
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  @file_put_contents(tbn_of_preview_path(), $body);
  @file_put_contents(tbn_of_generated_conf_path(), $body);
  return $body;
}

/**
 * Merge generated block into a target frr.conf (or write standalone include-style file).
 */
function tbn_of_merge_into_frr_conf($target = '/etc/frr/frr.conf') {
  $body = tbn_of_write_generated();
  $begin = tbn_of_marker_begin();
  $end = tbn_of_marker_end();

  $existing = '';
  if (is_readable($target)) {
    $existing = (string)@file_get_contents($target);
  }

  if ($existing !== '' && strpos($existing, $begin) !== false) {
    $pattern = '/' . preg_quote($begin, '/') . '.*?' . preg_quote($end, '/') . '\s*/s';
    $merged = preg_replace($pattern, $body . "\n", $existing, 1);
    if (!is_string($merged)) {
      $merged = $existing . "\n" . $body;
    }
  } else {
    $merged = rtrim($existing) . "\n\n" . $body;
  }

  $dir = dirname($target);
  if (!is_dir($dir)) {
    // Unraid may not have /etc/frr until package installed — still keep flash copy
    return [
      'ok' => false,
      'error' => 'FRR config directory missing: ' . $dir,
      'flash' => tbn_of_generated_conf_path(),
    ];
  }

  $ok = @file_put_contents($target, $merged) !== false;
  return [
    'ok' => $ok,
    'target' => $target,
    'flash' => tbn_of_generated_conf_path(),
  ];
}

/**
 * Best-effort: enable fabricd in /etc/frr/daemons and reload FRR.
 */
function tbn_of_enable_fabricd_daemon() {
  $dae = '/etc/frr/daemons';
  if (!is_readable($dae) || !is_writable($dae)) {
    return ['ok' => false, 'error' => 'cannot update ' . $dae];
  }
  $raw = (string)file_get_contents($dae);
  $new = preg_replace('/^\s*fabricd\s*=\s*no\s*$/mi', 'fabricd=yes', $raw, 1, $count);
  if ($count === 0 && !preg_match('/^\s*fabricd\s*=/mi', $raw)) {
    $new = rtrim($raw) . "\nfabricd=yes\n";
  }
  if ($new !== null && $new !== $raw) {
    @file_put_contents($dae, $new);
  }
  return ['ok' => true];
}

/**
 * Apply OpenFabric: generate conf; if FRR present, merge + reload.
 *
 * @return array<string,mixed>
 */
function tbn_of_apply() {
  $cfg = tbn_load_cfg();
  $result = [
    'ok' => false,
    'enabled' => tbn_of_enabled($cfg),
    'frr' => tbn_of_frr_detect(),
    'router_id' => tbn_of_router_id($cfg),
    'net' => '',
    'ifaces' => [],
    'actions' => [],
  ];
  $result['net'] = tbn_of_net_from_router_id($result['router_id'], $cfg);
  $result['ifaces'] = tbn_of_participate_ifaces($cfg);

  $body = tbn_of_write_generated($cfg);
  $result['actions'][] = 'wrote ' . tbn_of_generated_conf_path();
  $result['preview_bytes'] = strlen($body);

  if (!tbn_of_enabled($cfg)) {
    $result['ok'] = true;
    $result['actions'][] = 'openfabric_enable=no — conf generated for preview only; fabric not started';
    return $result;
  }

  // Ensure lo has router-id /32 (harmless; useful even before FRR package lands)
  $rid = $result['router_id'];
  @exec('ip addr add ' . escapeshellarg($rid . '/32') . ' dev lo 2>/dev/null');
  $result['actions'][] = 'ensured ' . $rid . '/32 on lo';

  $frr = $result['frr'];
  if (empty($frr['present'])) {
    $result['ok'] = true; // degrade gracefully — no sysctl / frr changes without FRR
    $result['actions'][] = 'FRR not installed — static underlay only; conf preview on flash; install FRR for multi-hop';
    $result['degraded'] = true;
    return $result;
  }

  // Forwarding only when FRR is actually present (multi-hop fabric). Do not touch default route.
  @exec('sysctl -w net.ipv4.ip_forward=1 2>/dev/null');
  if (($cfg['openfabric_ipv6'] ?? 'yes') === 'yes') {
    @exec('sysctl -w net.ipv6.conf.all.forwarding=1 2>/dev/null');
  }
  $result['actions'][] = 'enabled IP forwarding (sysctl)';

  $merge = tbn_of_merge_into_frr_conf('/etc/frr/frr.conf');
  $result['merge'] = $merge;
  $result['actions'][] = !empty($merge['ok']) ? 'merged into /etc/frr/frr.conf' : ('merge skipped: ' . ($merge['error'] ?? 'unknown'));

  tbn_of_enable_fabricd_daemon();
  $result['actions'][] = 'fabricd=yes attempted in daemons';

  // Reload / restart FRR
  $reloaded = false;
  foreach ([
    'systemctl reload frr',
    'systemctl restart frr',
    '/usr/lib/frr/frrinit.sh reload',
    '/usr/lib/frr/frrinit.sh restart',
    'service frr reload',
    'service frr restart',
  ] as $cmd) {
    $rc = 1;
    @exec($cmd . ' 2>/dev/null', $o, $rc);
    if ($rc === 0) {
      $result['actions'][] = $cmd;
      $reloaded = true;
      break;
    }
  }
  if (!$reloaded && !empty($frr['vtysh'])) {
    @exec(escapeshellarg($frr['vtysh']) . ' -c "write memory" 2>/dev/null');
    $result['actions'][] = 'vtysh write memory (best effort)';
  }

  $result['frr'] = tbn_of_frr_detect();
  $result['ok'] = true;
  return $result;
}

/**
 * Status blob for UI / JSON.
 */
function tbn_of_status() {
  $cfg = tbn_load_cfg();
  $frr = tbn_of_frr_detect();
  $rid = tbn_of_router_id($cfg);
  $enabled = tbn_of_enabled($cfg);
  $ifaces = tbn_of_participate_ifaces($cfg);

  $mode = 'static-only';
  if ($enabled && !empty($frr['running'])) {
    $mode = 'openfabric-running';
  } elseif ($enabled && !empty($frr['present'])) {
    $mode = 'openfabric-ready';
  } elseif ($enabled) {
    $mode = 'openfabric-want-frr';
  }

  $neighbors = '';
  $routes = '';
  if (!empty($frr['vtysh']) && !empty($frr['running'])) {
    $neighbors = (string)@shell_exec(escapeshellarg($frr['vtysh']) . ' -c "show openfabric neighbor" 2>/dev/null');
    $routes = (string)@shell_exec(escapeshellarg($frr['vtysh']) . ' -c "show openfabric route" 2>/dev/null');
  }

  return [
    'enabled' => $enabled,
    'mode' => $mode,
    'frr' => $frr,
    'router_id' => $rid,
    'net' => tbn_of_net_from_router_id($rid, $cfg),
    'area' => $cfg['openfabric_area'] ?? '1',
    'metric_reference_mbps' => (int)($cfg['openfabric_metric_reference_mbps'] ?? 20000),
    'ifaces' => $ifaces,
    'preview_path' => tbn_of_preview_path(),
    'generated_path' => tbn_of_generated_conf_path(),
    'neighbors_text' => $neighbors,
    'routes_text' => $routes,
    'docs' => 'docs/routing-openfabric.md',
  ];
}

/**
 * Compact HTML panel for overview page.
 */
function tbn_of_status_html() {
  $st = tbn_of_status();
  $mode = $st['mode'];
  $label = [
    'openfabric-running' => 'OpenFabric running',
    'openfabric-ready' => 'FRR present — apply to start / reload',
    'openfabric-want-frr' => 'OpenFabric on — FRR not installed (static underlay)',
    'static-only' => 'OpenFabric off — static underlay only',
  ][$mode] ?? $mode;

  $html = '<table class="tbn-table tbn-summary">';
  $html .= '<tr><td>Routing mode</td><td><strong>' . htmlspecialchars($label) . '</strong></td></tr>';
  $html .= '<tr><td>FRR</td><td>' . htmlspecialchars($st['frr']['note'] ?? '') .
    ($st['frr']['version'] !== '' ? ' · <code>' . htmlspecialchars($st['frr']['version']) . '</code>' : '') .
    '</td></tr>';
  $uf = $st['frr']['unraidfrr'] ?? tbn_of_unraidfrr_companion();
  $install = $uf['install_url'] ?? 'https://raw.githubusercontent.com/ibigsnet/UnraidFRR/main/unraidfrr.plg';
  $project = $uf['project'] ?? 'https://github.com/ibigsnet/UnraidFRR';
  $html .= '<tr><td>UnraidFRR companion</td><td>';
  if (!empty($st['frr']['present'])) {
    if (!empty($uf['plugin_dir'])) {
      $html .= 'Installed · FRR live · <a href="/Settings/NetworkSettings" onclick="return tbnGotoNetTab(\'Fabric Routing\', event)">Network Settings → Fabric Routing</a>';
    } else {
      $html .= 'FRR present (packages not via UnraidFRR) · optional UI at '
        . '<a href="' . htmlspecialchars($project) . '" target="_blank" rel="noopener">UnraidFRR</a>';
    }
  } elseif (!empty($uf['plugin_dir'])) {
    $html .= '<strong>Plugin installed but FRR not live yet</strong> — open '
      . '<a href="/Settings/NetworkSettings" onclick="return tbnGotoNetTab(\'Fabric Routing\', event)">Network Settings → Fabric Routing</a> → packages → Apply. '
      . '<a href="#tbn-companion-frr" class="tbn-jump-frr">↑ Companion card</a>';
  } else {
    $html .= '<strong>Not installed</strong> — optional. Only for rings / multi-hop / mixed Proxmox fabrics. '
      . '<a href="#tbn-companion-frr" class="tbn-jump-frr">↑ Companion card (install path)</a> · '
      . '<a href="' . htmlspecialchars($install) . '" target="_blank" rel="noopener">raw .plg</a> · '
      . '<a href="' . htmlspecialchars($project) . '" target="_blank" rel="noopener">GitHub</a>';
  }
  $html .= '</td></tr>';
  $html .= '<tr><td>Roles</td><td class="tbn-muted">'
    . '<strong>UnraidFRR</strong> = FRR packages/daemons · '
    . '<strong>Thunderbolt Net</strong> = tbn underlay + OpenFabric policy/metrics · '
    . 'see docs/usb4stream.md for kernel stream path'
    . '</td></tr>';
  $html .= '<tr><td>Router ID (lo)</td><td><code>' . htmlspecialchars($st['router_id']) . '</code></td></tr>';
  $html .= '<tr><td>NET</td><td><code>' . htmlspecialchars($st['net']) . '</code></td></tr>';
  $html .= '<tr><td>Metric reference</td><td>' . (int)$st['metric_reference_mbps'] . ' Mb/s (auto cost = ref / trained)</td></tr>';

  if (!empty($st['ifaces'])) {
    $rows = [];
    foreach ($st['ifaces'] as $row) {
      $rows[] = htmlspecialchars($row['if']) . ' metric ' . (int)$row['metric'] .
        ($row['mode'] === 'passive' ? ' passive' : '');
    }
    $html .= '<tr><td>Participating ifaces</td><td><code>' . implode('</code>, <code>', $rows) . '</code></td></tr>';
  } else {
    $html .= '<tr><td>Participating ifaces</td><td class="tbn-muted">None live / all participate=no</td></tr>';
  }

  $html .= '<tr><td>Generated conf</td><td><code>' . htmlspecialchars($st['generated_path']) . '</code> (on flash after Apply)</td></tr>';
  $html .= '</table>';
  return $html;
}
