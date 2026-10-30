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
function tbn_of_enabled(?array $cfg = null) {
  if ($cfg === null) {
    $cfg = tbn_load_cfg();
  }
  return ($cfg['openfabric_enable'] ?? 'no') === 'yes';
}

/**
 * When FRR packages become available, turn OpenFabric on automatically (plug-and-play).
 * If the user later sets Enable=No, we set openfabric_user_off=yes and do not re-force.
 * Call from status / apply paths (idempotent).
 *
 * @return array updated cfg
 */
function tbn_of_maybe_auto_enable_from_frr(?array $cfg = null) {
  if ($cfg === null) {
    $cfg = tbn_load_cfg();
  }
  if (!function_exists('tbn_of_frr_detect')) {
    return $cfg;
  }
  $frr = tbn_of_frr_detect();
  if (empty($frr['present'])) {
    return $cfg;
  }
  // User explicitly turned OpenFabric off while FRR was available
  if (($cfg['openfabric_user_off'] ?? 'no') === 'yes') {
    return $cfg;
  }
  if (($cfg['openfabric_enable'] ?? 'no') === 'yes') {
    return $cfg;
  }
  $cfg['openfabric_enable'] = 'yes';
  $cfg['openfabric_user_off'] = 'no';
  if (function_exists('tbn_write_global_cfg')) {
    @tbn_write_global_cfg($cfg);
  }
  return $cfg;
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
 * Optional companion plugin FabricRouting (package install) — never required.
 */
function tbn_of_fabricrouting_companion() {
  // FabricRouting (current) or legacy UnraidFRR paths after rename
  $dir = is_dir('/usr/local/emhttp/plugins/FabricRouting')
    || is_dir('/usr/local/emhttp/plugins/UnraidFRR')
    || is_dir('/boot/config/plugins/FabricRouting')
    || is_dir('/boot/config/plugins/UnraidFRR');
  $out = [
    'plugin_dir' => $dir,
    'marker' => null,
    'install_url' => 'https://raw.githubusercontent.com/ibigsnet/FabricRouting/main/fabricrouting.plg',
    'project' => 'https://github.com/ibigsnet/FabricRouting',
  ];
  foreach ([
    '/boot/config/plugins/FabricRouting/companion.json',
    '/boot/config/plugins/UnraidFRR/companion.json',
  ] as $marker) {
    if (!is_readable($marker)) {
      continue;
    }
    $j = json_decode((string)@file_get_contents($marker), true);
    if (is_array($j)) {
      $out['marker'] = $j;
      break;
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
    'fabricrouting' => tbn_of_fabricrouting_companion(),
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
    $c = $out['fabricrouting'];
    if (!empty($c['plugin_dir'])) {
      $out['note'] = 'FabricRouting installed but FRR binaries not live yet — add packages under /boot/config/plugins/FabricRouting/packages/ and Apply there';
    } else {
      $out['note'] = 'FRR not found — install companion plugin FabricRouting for packaged FRR, or provide vtysh/fabricd yourself';
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

function tbn_of_router_id(?array $cfg = null) {
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
function tbn_of_net_from_router_id($rid, ?array $cfg = null) {
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
 * List Thunderbolt interfaces that should participate in OpenFabric.
 *
 * @return list<array{if:string,mode:string,metric:int,participate:string}>
 */
function tbn_of_participate_ifaces(?array $gcfg = null) {
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
function tbn_of_generate_conf(?array $gcfg = null) {
  if ($gcfg === null) {
    $gcfg = tbn_load_cfg();
  }
  $rid = tbn_of_router_id($gcfg);
  $net = tbn_of_net_from_router_id($rid, $gcfg);
  $area = preg_replace('/[^A-Za-z0-9_-]/', '', trim((string)($gcfg['openfabric_area'] ?? '1')));
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
 * Preview on flash (always, write-on-change). Contract with FabricRouting:
 * the fragment thunderboltnet-openfabric.conf exists ONLY while OpenFabric is on.
 */
function tbn_of_write_generated(?array $gcfg = null) {
  if ($gcfg === null) {
    $gcfg = tbn_load_cfg();
  }
  $body = tbn_of_generate_conf($gcfg);
  tbn_write_file_atomic(tbn_of_preview_path(), $body);
  return $body;
}

/** @return bool true when the fragment changed on flash */
function tbn_of_sync_fragment($enabled, $body) {
  $path = tbn_of_generated_conf_path();
  if ($enabled) {
    $same = is_file($path) && (string)@file_get_contents($path) === $body;
    if ($same) {
      return false;
    }
    return tbn_write_file_atomic($path, $body);
  }
  if (is_file($path)) {
    return @unlink($path);
  }
  return false;
}

/** FabricRouting's reload hook: rebuilds frr.conf from its baseline + our fragment. */
function tbn_of_hook_path() {
  return tbn_env_path('TBN_FRR_HOOK', '/usr/local/emhttp/plugins/FabricRouting/scripts/frr-reload');
}

/**
 * Ordered commands to make FRR pick up a change.
 * Hook present: the hook only (it owns frr.conf, daemons, reload and its lock).
 * No hook (FRR from elsewhere): frrinit.sh reload, then restart, then other
 * init styles. Never "vtysh write memory" (it would save FRR's running config
 * over the user's frr.conf).
 *
 * @return array<int,array{kind:string,cmd:string}>
 */
function tbn_of_reload_plan($hook_exists, $hook_path = null) {
  if ($hook_exists) {
    $hook = $hook_path ?? tbn_of_hook_path();
    $run = is_executable($hook) ? escapeshellarg($hook) : ('/bin/bash ' . escapeshellarg($hook));
    return [['kind' => 'hook', 'cmd' => 'timeout 90 ' . $run]];
  }
  $plan = [];
  foreach ([
    '/usr/sbin/frrinit.sh reload',
    '/usr/sbin/frrinit.sh restart',
    '/usr/lib/frr/frrinit.sh reload',
    '/usr/lib/frr/frrinit.sh restart',
    '/etc/rc.d/rc.frr reload',
    '/etc/rc.d/rc.frr restart',
  ] as $c) {
    $plan[] = ['kind' => 'fallback', 'cmd' => 'timeout 90 ' . $c];
  }
  return $plan;
}

/** Run the plan until one command succeeds. @return string|null command that worked */
function tbn_of_run_reload(array $plan) {
  foreach ($plan as $step) {
    $o = [];
    $rc = 1;
    @exec($step['cmd'] . ' >/dev/null 2>&1', $o, $rc);
    if ($rc === 0) {
      return $step['cmd'];
    }
  }
  return null;
}

/**
 * Pure: frr.conf text with our marker block replaced by $body ('' removes it).
 */
function tbn_of_merge_text($existing, $body) {
  $begin = tbn_of_marker_begin();
  $end = tbn_of_marker_end();
  $existing = (string)$existing;
  $pattern = '/' . preg_quote($begin, '/') . '.*?' . preg_quote($end, '/') . '\s*/s';
  $has = strpos($existing, $begin) !== false;
  if ($body === '') {
    return $has ? rtrim((string)preg_replace($pattern, '', $existing)) . "\n" : $existing;
  }
  if ($has) {
    $merged = preg_replace($pattern, $body . "\n", $existing, 1);
    return is_string($merged) ? $merged : ($existing . "\n" . $body);
  }
  return rtrim($existing) . "\n\n" . $body;
}

/**
 * Fallback only (no FabricRouting hook): merge or remove our block in frr.conf.
 */
function tbn_of_merge_into_frr_conf($body, $target = '/etc/frr/frr.conf') {
  $dir = dirname($target);
  if (!is_dir($dir)) {
    return ['ok' => false, 'error' => 'FRR config directory missing: ' . $dir, 'changed' => false];
  }
  $existing = is_readable($target) ? (string)@file_get_contents($target) : '';
  $merged = tbn_of_merge_text($existing, (string)$body);
  if ($merged === $existing) {
    return ['ok' => true, 'target' => $target, 'changed' => false];
  }
  return ['ok' => tbn_write_file_atomic($target, $merged), 'target' => $target, 'changed' => true];
}

/**
 * Fallback only: fabricd=yes in /etc/frr/daemons.
 */
function tbn_of_enable_fabricd_daemon() {
  $dae = '/etc/frr/daemons';
  if (!is_readable($dae) || !is_writable($dae)) {
    return ['ok' => false, 'error' => 'cannot update ' . $dae, 'changed' => false];
  }
  $raw = (string)file_get_contents($dae);
  $new = preg_replace('/^\s*fabricd\s*=\s*no\s*$/mi', 'fabricd=yes', $raw, 1, $count);
  if ($count === 0 && !preg_match('/^\s*fabricd\s*=/mi', $raw)) {
    $new = rtrim($raw) . "\nfabricd=yes\n";
  }
  if ($new !== null && $new !== $raw) {
    tbn_write_file_atomic($dae, $new);
    return ['ok' => true, 'changed' => true];
  }
  return ['ok' => true, 'changed' => false];
}

/** Router-id /32 on lo that we added (RAM state), so disable/uninstall can remove it. */
function tbn_of_lo_state_path() {
  return tbn_runtime_path('openfabric-lo.json');
}

function tbn_of_lo_set($rid) {
  $st = tbn_read_json_file(tbn_of_lo_state_path());
  $old = (string)($st['addr'] ?? '');
  $want = $rid !== '' ? $rid . '/32' : '';
  if ($old !== '' && $old !== $want) {
    @exec('ip addr del ' . escapeshellarg($old) . ' dev lo 2>/dev/null');
  }
  if ($want !== '') {
    @exec('ip addr add ' . escapeshellarg($want) . ' dev lo 2>/dev/null');
  }
  tbn_write_json_file(tbn_of_lo_state_path(), ['addr' => $want]);
}

/**
 * Apply OpenFabric: preview always; fragment + FRR only while enabled; when
 * disabled, remove fragment, lo /32, forwarding and (fallback) our frr.conf block.
 *
 * @return array<string,mixed>
 */
// $force_reload: boot / array start (call the FabricRouting hook even when the fragment is unchanged)
function tbn_of_apply($force_reload = false) {
  return tbn_locked(function () use ($force_reload) {
    return tbn_of_apply_unlocked($force_reload);
  });
}

function tbn_of_apply_unlocked($force_reload = false) {
  $cfg = tbn_load_cfg();
  $enabled = tbn_of_enabled($cfg);
  $result = [
    'ok' => false,
    'enabled' => $enabled,
    'frr' => tbn_of_frr_detect(),
    'router_id' => tbn_of_router_id($cfg),
    'net' => '',
    'ifaces' => [],
    'actions' => [],
  ];
  $result['net'] = tbn_of_net_from_router_id($result['router_id'], $cfg);
  $result['ifaces'] = tbn_of_participate_ifaces($cfg);

  $body = tbn_of_write_generated($cfg);
  $result['preview_bytes'] = strlen($body);
  $changed = tbn_of_sync_fragment($enabled, $body);
  $result['actions'][] = $enabled
    ? ('fragment ' . ($changed ? 'written' : 'unchanged') . ': ' . tbn_of_generated_conf_path())
    : ('fragment ' . ($changed ? 'removed' : 'absent') . ' (OpenFabric off)');

  $hook = tbn_of_hook_path();
  $hook_exists = is_file($hook);
  $frr = $result['frr'];

  if (!$enabled) {
    tbn_of_lo_set('');
    if (function_exists('tbn_fwd_release')) {
      tbn_fwd_release('of');
    }
    if ($hook_exists) {
      if ($changed) {
        $ran = tbn_of_run_reload(tbn_of_reload_plan(true, $hook));
        $result['actions'][] = $ran !== null ? 'FabricRouting reload hook' : 'FabricRouting reload hook failed';
      }
    } elseif (!empty($frr['present'])) {
      $m = tbn_of_merge_into_frr_conf('');
      if (!empty($m['changed'])) {
        $ran = tbn_of_run_reload(tbn_of_reload_plan(false));
        $result['actions'][] = 'removed block from /etc/frr/frr.conf; ' . ($ran ?? 'reload failed');
      }
    }
    $result['ok'] = true;
    return $result;
  }

  // Router-id /32 on lo (harmless; useful even before FRR package lands)
  $rid = $result['router_id'];
  tbn_of_lo_set($rid);
  $result['actions'][] = 'ensured ' . $rid . '/32 on lo';

  if (empty($frr['present']) && !$hook_exists) {
    $result['ok'] = true; // degrade gracefully — fragment stays on flash for later
    $result['actions'][] = 'FRR not installed — static underlay only; install FRR for multi-hop';
    $result['degraded'] = true;
    return $result;
  }

  // Transit forwarding only on the participating Thunderbolt links
  $v4 = [];
  foreach ($result['ifaces'] as $row) {
    if (($row['mode'] ?? '') !== 'passive') {
      $v4[] = $row['if'];
    }
  }
  $v6 = (($cfg['openfabric_ipv6'] ?? 'yes') === 'yes') ? $v4 : [];
  if (function_exists('tbn_fwd_claim')) {
    tbn_fwd_claim('of', $v4, $v6);
    $result['actions'][] = 'forwarding on ' . ($v4 ? implode(', ', $v4) : 'no links') . ' (per interface)';
    if ($v6 && !is_file(tbn_fwd_sysctl_path(6, $v6[0], 'force_forwarding'))
        && trim((string)@file_get_contents(tbn_env_path('TBN_PROC_SYS', '/proc/sys') . '/net/ipv6/conf/all/forwarding')) !== '1') {
      $result['actions'][] = 'IPv6 transit through this host needs a kernel with per-interface force_forwarding (IPv6 to this host works)';
    }
  }

  if ($hook_exists) {
    // FabricRouting owns /etc/frr/frr.conf + daemons + reload
    if ($changed || $force_reload) {
      $ran = tbn_of_run_reload(tbn_of_reload_plan(true, $hook));
      $result['actions'][] = $ran !== null ? 'FabricRouting reload hook' : 'FabricRouting reload hook failed';
    }
  } else {
    $merge = tbn_of_merge_into_frr_conf($body);
    $result['merge'] = $merge;
    $dae = tbn_of_enable_fabricd_daemon();
    if (!empty($merge['changed']) || !empty($dae['changed'])) {
      $ran = tbn_of_run_reload(tbn_of_reload_plan(false));
      $result['actions'][] = 'merged into /etc/frr/frr.conf; ' . ($ran ?? 'FRR reload failed');
    }
  }

  $result['frr'] = tbn_of_frr_detect();
  $result['ok'] = true;
  return $result;
}

/**
 * Uninstall / full off: remove the fragment, lo /32, forwarding; let FabricRouting
 * rebuild (hook) or strip our block (fallback).
 */
function tbn_of_teardown() {
  $had = is_file(tbn_of_generated_conf_path());
  tbn_of_sync_fragment(false, '');
  tbn_of_lo_set('');
  if (function_exists('tbn_fwd_release')) {
    tbn_fwd_release('of');
  }
  $hook = tbn_of_hook_path();
  if (is_file($hook)) {
    if ($had) {
      tbn_of_run_reload(tbn_of_reload_plan(true, $hook));
    }
  } elseif (is_file('/etc/frr/frr.conf')) {
    $m = tbn_of_merge_into_frr_conf('');
    if (!empty($m['changed'])) {
      tbn_of_run_reload(tbn_of_reload_plan(false));
    }
  }
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

  // Without FRR packages: always static underlay for status (no scary “want FRR” fault line).
  // Multi-hop CTA lives on the companion card / short Settings note instead.
  $mode = 'static-only';
  if (!empty($frr['present'])) {
    if ($enabled && !empty($frr['running'])) {
      $mode = 'openfabric-running';
    } elseif ($enabled) {
      $mode = 'openfabric-ready';
    }
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
    'openfabric-ready' => 'FRR ready — OpenFabric can run',
    'openfabric-want-frr' => 'Static underlay',
    'static-only' => 'Static underlay',
  ][$mode] ?? $mode;

  $html = '<table class="tbn-table tbn-summary">';
  $html .= '<tr><td>Routing mode</td><td><strong>' . htmlspecialchars($label) . '</strong></td></tr>';
  $html .= '<tr><td>FRR</td><td>' . htmlspecialchars($st['frr']['note'] ?? '') .
    ($st['frr']['version'] !== '' ? ' · <code>' . htmlspecialchars($st['frr']['version']) . '</code>' : '') .
    '</td></tr>';
  $uf = $st['frr']['fabricrouting'] ?? tbn_of_fabricrouting_companion();
  $install = $uf['install_url'] ?? 'https://raw.githubusercontent.com/ibigsnet/FabricRouting/main/fabricrouting.plg';
  $project = $uf['project'] ?? 'https://github.com/ibigsnet/FabricRouting';
  $html .= '<tr><td>FabricRouting companion</td><td>';
  if (!empty($st['frr']['present'])) {
    if (!empty($uf['plugin_dir'])) {
      $html .= 'Installed · FRR live · <a href="/Settings/NetworkSettings" onclick="return ibigsGotoNetTab(\'Fabric Routing\', event)">Network Settings → Fabric Routing</a>';
    } else {
      $html .= 'FRR present (packages not via Fabric Routing) · optional UI at '
        . '<a href="' . htmlspecialchars($project) . '" target="_blank" rel="noopener">FabricRouting</a>';
    }
  } elseif (!empty($uf['plugin_dir'])) {
    $html .= '<strong>Plugin installed but FRR not live yet</strong> — open '
      . '<a href="/Settings/NetworkSettings" onclick="return ibigsGotoNetTab(\'Fabric Routing\', event)">Network Settings → Fabric Routing</a> → packages → Apply. '
      . '<a href="#tbn-companion-frr" class="tbn-jump-frr">↑ Companion card</a>';
  } else {
    $html .= '<strong>Not installed</strong> — optional. Only for rings / multi-hop / mixed Proxmox fabrics. '
      . '<a href="#tbn-companion-frr" class="tbn-jump-frr">↑ Companion card (install path)</a> · '
      . '<a href="' . htmlspecialchars($install) . '" target="_blank" rel="noopener">raw .plg</a> · '
      . '<a href="' . htmlspecialchars($project) . '" target="_blank" rel="noopener">GitHub</a>';
  }
  $html .= '</td></tr>';
  $html .= '<tr><td>Roles</td><td class="tbn-muted">'
    . '<strong>FabricRouting</strong> = FRR packages/daemons · '
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

  $html .= '<tr><td>Generated conf</td><td><code>' . htmlspecialchars($st['generated_path']) . '</code> (on flash while OpenFabric is on)</td></tr>';
  $html .= '</table>';
  return $html;
}
