<?php
/**
 * Thunderbolt underlay DHCP server (dnsmasq) — come-and-go clients.
 * Unraid = .1; pool = .2–.254 on 10.255.N.0/24. Never bind eth0/br0.
 */

if (!function_exists('tbn_forum_support_url')) {
  function tbn_forum_support_url() {
    return 'https://forums.unraid.net/topic/200065-plugin-thunderbolt-net-host-to-host-networking-over-thunderbolt-345-and-usb44v2/';
  }
}

function tbn_dhcp_bin() {
  foreach (['/usr/sbin/dnsmasq', '/usr/bin/dnsmasq'] as $p) {
    if (is_executable($p)) {
      return $p;
    }
  }
  return '';
}

function tbn_dhcp_runtime_dir() {
  return '/var/lib/thunderboltnet/dhcp';
}

function tbn_dhcp_flash_dir() {
  return tbn_cfg_dir() . '/dhcp';
}

function tbn_dhcp_safe_if($if) {
  $if = preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$if);
  if ($if === '') {
    return '';
  }
  // Only Thunderbolt underlays (and our bond/bridge names)
  if (preg_match('/^thunderbolt\d+$/', $if)) {
    return $if;
  }
  if (preg_match('/^bond-tb\d*$/', $if) || preg_match('/^br-tb\d*$/', $if)) {
    return $if;
  }
  return '';
}

/** Netdev that should run DHCP when cfg says server (master if bonded/bridged). */
function tbn_dhcp_netdev_for_cfg($if, array $cfg) {
  $if = tbn_dhcp_safe_if($if);
  if ($if === '') {
    return '';
  }
  if (($cfg['BRIDGING'] ?? 'no') === 'yes') {
    $br = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($cfg['BR_NAME'] ?? 'br-tb0'));
    if ($br !== '' && is_dir('/sys/class/net/' . $br)) {
      return $br;
    }
  }
  if (($cfg['BONDING'] ?? 'no') === 'yes') {
    $bond = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($cfg['BOND_NAME'] ?? 'bond-tb0'));
    if ($bond !== '' && is_dir('/sys/class/net/' . $bond)) {
      return $bond;
    }
  }
  $master = function_exists('tbn_iface_master') ? tbn_iface_master($if) : '';
  if ($master !== '' && tbn_dhcp_safe_if($master) !== '') {
    return $master;
  }
  return $if;
}

/**
 * Server plan for iface: Unraid .1, pool .2–.254, /24.
 *
 * @return array{ip:string,prefix:int,network:string,pool_start:string,pool_end:string,mask:string,ula_prefix?:string}
 */
function tbn_dhcp_server_plan($if) {
  $n = function_exists('tbn_iface_index') ? tbn_iface_index($if) : 0;
  $base = '10.255.' . (int)$n;
  // Unique ULA-ish /64 per link for RA (fd70:7462:6eXX::/64 — "tbn" nibbles-ish)
  $ula = sprintf('fd70:7462:6e%02x::', (int)$n & 0xff);
  return [
    'ip' => $base . '.1',
    'prefix' => 24,
    'network' => $base . '.0/24',
    'pool_start' => $base . '.2',
    'pool_end' => $base . '.254',
    'mask' => '255.255.255.0',
    'ula_prefix' => $ula,
  ];
}

function tbn_dhcp_pid_path($netdev) {
  return '/var/run/thunderboltnet-dnsmasq-' . $netdev . '.pid';
}

function tbn_dhcp_conf_path($netdev) {
  return tbn_dhcp_flash_dir() . '/' . $netdev . '.conf';
}

function tbn_dhcp_lease_path($netdev) {
  return tbn_dhcp_runtime_dir() . '/' . $netdev . '.leases';
}

/**
 * Probe whether we can safely host DHCP on this underlay.
 *
 * @return array{status:string,messages:array,plan:array,netdev:string}
 *   status: ok|warn|block
 */
function tbn_dhcp_server_safe($if, array $cfg = []) {
  $messages = [];
  $status = 'ok';
  $plan = tbn_dhcp_server_plan($if);
  $netdev = tbn_dhcp_netdev_for_cfg($if, $cfg);
  if ($netdev === '') {
    return ['status' => 'block', 'messages' => ['Invalid underlay interface.'], 'plan' => $plan, 'netdev' => ''];
  }
  if (tbn_dhcp_bin() === '') {
    return [
      'status' => 'block',
      'messages' => ['dnsmasq not found on this Unraid (required for DHCP server).'],
      'plan' => $plan,
      'netdev' => $netdev,
    ];
  }
  if (!is_dir('/sys/class/net/' . $netdev)) {
    $messages[] = "Netdev {$netdev} is not up yet — server will start when the link appears.";
    $status = 'warn';
  }

  $host_ip = $plan['ip'];
  // Local collision: .1 already on a *different* iface
  $lines = [];
  @exec('ip -4 -o addr show scope global 2>/dev/null', $lines);
  foreach ($lines as $line) {
    if (!preg_match('/^\d+:\s+(\S+)\s+inet\s+(\d+\.\d+\.\d+\.\d+)/', $line, $m)) {
      continue;
    }
    $lif = $m[1];
    $lip = $m[2];
    if ($lip === $host_ip && $lif !== $netdev && strpos($lif, $netdev . '@') !== 0) {
      $messages[] = "Address {$host_ip} is already on {$lif}. Free it or pick another plan before hosting DHCP.";
      $status = 'block';
    }
  }

  // Neighbour claiming our .1 (someone else is .1)
  if (is_dir('/sys/class/net/' . $netdev)) {
    $neigh = [];
    @exec('ip neigh show dev ' . escapeshellarg($netdev) . ' 2>/dev/null', $neigh);
    foreach ($neigh as $line) {
      if (!preg_match('/^([0-9.]+)\s+.*\s+(REACHABLE|STALE|DELAY|PROBE|PERMANENT)/', $line, $m)) {
        continue;
      }
      if ($m[1] === $host_ip) {
        $messages[] = "Another host already answers ARP for {$host_ip} on {$netdev}. Preferred server address is occupied.";
        $status = 'block';
      }
    }
  }

  // Same-IP seed collision: we are .2 and peer neigh also looks like our schema peer missing
  // Detect: local address is .2 on this subnet while FAILED/incomplete .1 (classic dual-Unraid seed)
  $local_on_link = '';
  foreach ($lines as $line) {
    if (preg_match('/^\d+:\s+' . preg_quote($netdev, '/') . '(?:@\S+)?\s+inet\s+(\d+\.\d+\.\d+\.\d+)/', $line, $m)) {
      $local_on_link = $m[1];
      break;
    }
  }
  if ($local_on_link !== '' && preg_match('/^10\.255\.\d+\.2$/', $local_on_link)) {
    $messages[] = 'This host is still on the plugin static seed (.2). DHCP server mode will move it to .1 on Apply.';
    if ($status === 'ok') {
      $status = 'warn';
    }
  }

  if (!$messages) {
    $messages[] = "Ready: host {$plan['ip']}/{$plan['prefix']} · pool {$plan['pool_start']}–{$plan['pool_end']} on {$netdev}.";
  }
  return ['status' => $status, 'messages' => $messages, 'plan' => $plan, 'netdev' => $netdev];
}

/**
 * Underlay addressing collision hints (Static or server) for UI banners.
 *
 * @return string[] human messages
 */
function tbn_underlay_collision_hints($if = null) {
  $hints = [];
  $ifs = [];
  if ($if) {
    $ifs[] = tbn_dhcp_safe_if($if);
  } else {
    foreach (glob('/sys/class/net/thunderbolt*') ?: [] as $p) {
      $ifs[] = basename($p);
    }
  }
  $ifs = array_values(array_filter($ifs));
  foreach ($ifs as $dev) {
    $addrs = [];
    @exec('ip -4 -o addr show dev ' . escapeshellarg($dev) . ' scope global 2>/dev/null', $addrs);
    $local = '';
    foreach ($addrs as $line) {
      if (preg_match('/inet\s+(\d+\.\d+\.\d+\.\d+)/', $line, $m)) {
        $local = $m[1];
        break;
      }
    }
    if ($local === '') {
      continue;
    }
    $neigh = [];
    @exec('ip neigh show dev ' . escapeshellarg($dev) . ' 2>/dev/null', $neigh);
    foreach ($neigh as $line) {
      // Same IP, foreign MAC
      if (preg_match('/^' . preg_quote($local, '/') . '\s+lladdr\s+([0-9a-f:]+)/i', $line, $m)) {
        $hints[] = "{$dev}: address {$local} also seen with MAC {$m[1]} (duplicate IP / both ends using the same seed). Set one host to .1 or enable DHCP server.";
      }
    }
    // Both-plugin-seed heuristic: we are .2 and .1 is FAILED (nothing there) while link is up
    if (preg_match('/^10\.255\.\d+\.2$/', $local)) {
      $carrier = @file_get_contents('/sys/class/net/' . $dev . '/carrier');
      $failed1 = false;
      foreach ($neigh as $line) {
        if (preg_match('/^10\.255\.\d+\.1\s+.*FAILED/', $line)) {
          $failed1 = true;
        }
      }
      if (trim((string)$carrier) === '1') {
        $hints[] = "{$dev}: live link while using seed {$local}. If the peer Unraid also uses .2, Peer link check cannot reach their export — use Unraid .1 / peer .2, or DHCP server on one side.";
      }
    }
  }
  return array_values(array_unique($hints));
}

function tbn_dhcp_write_conf($netdev, array $plan, $v4 = true, $v6 = false) {
  $flash = tbn_dhcp_flash_dir();
  $run = tbn_dhcp_runtime_dir();
  @mkdir($flash, 0755, true);
  @mkdir($run, 0755, true);
  $lease = tbn_dhcp_lease_path($netdev);
  $pid = tbn_dhcp_pid_path($netdev);
  $lines = [
    '# ThunderboltNet DHCP — generated; do not edit while server mode is on',
    '# Underlay only: ' . $netdev,
    'port=0',
    'interface=' . $netdev,
    'bind-interfaces',
    'except-interface=lo',
    'dhcp-authoritative',
    'dhcp-leasefile=' . $lease,
    'pid-file=' . $pid,
    'user=nobody',
    'bogus-priv',
  ];
  // Exclude common LAN bridges if present
  foreach (['eth0', 'br0', 'wlan0', 'virbr0', 'docker0'] as $ex) {
    if (is_dir('/sys/class/net/' . $ex) && $ex !== $netdev) {
      $lines[] = 'except-interface=' . $ex;
    }
  }
  if ($v4) {
    $lines[] = 'dhcp-range=' . $plan['pool_start'] . ',' . $plan['pool_end'] . ',' . $plan['mask'] . ',12h';
    // No router/DNS by default — P2P file share, not a WAN
    $lines[] = 'dhcp-option=option:router';
    $lines[] = 'dhcp-option=option:dns-server';
  }
  if ($v6) {
    $ula = rtrim($plan['ula_prefix'], ':') . ':';
    $lines[] = 'enable-ra';
    $lines[] = 'dhcp-range=' . $ula . '2,' . $ula . 'ffff,constructor:' . $netdev . ',ra-stateless,ra-names,64,12h';
  }
  $body = implode("\n", $lines) . "\n";
  $conf = tbn_dhcp_conf_path($netdev);
  @file_put_contents($conf, $body);
  return is_file($conf) ? $conf : '';
}

function tbn_dhcp_server_stop($netdev) {
  $netdev = tbn_dhcp_safe_if($netdev);
  if ($netdev === '') {
    return;
  }
  $pidf = tbn_dhcp_pid_path($netdev);
  if (is_readable($pidf)) {
    $pid = (int)trim((string)@file_get_contents($pidf));
    if ($pid > 1) {
      if (function_exists('posix_kill')) {
        @posix_kill($pid, defined('SIGTERM') ? SIGTERM : 15);
        usleep(200000);
        if (@posix_kill($pid, 0)) {
          @posix_kill($pid, defined('SIGKILL') ? SIGKILL : 9);
        }
      } else {
        @exec('kill -TERM ' . (int)$pid . ' 2>/dev/null');
        usleep(200000);
        @exec('kill -KILL ' . (int)$pid . ' 2>/dev/null');
      }
    }
    @unlink($pidf);
  }
  // Fallback: match our conf path
  $conf = tbn_dhcp_conf_path($netdev);
  if ($conf !== '' && is_file($conf)) {
    @exec('pkill -f ' . escapeshellarg('dnsmasq.*' . basename($conf)) . ' 2>/dev/null || true');
  }
}

function tbn_dhcp_server_stop_all() {
  foreach (glob(tbn_dhcp_flash_dir() . '/*.conf') ?: [] as $conf) {
    $base = basename($conf, '.conf');
    tbn_dhcp_server_stop($base);
  }
  foreach (glob('/var/run/thunderboltnet-dnsmasq-*.pid') ?: [] as $pidf) {
    if (preg_match('/thunderboltnet-dnsmasq-(.+)\.pid$/', $pidf, $m)) {
      tbn_dhcp_server_stop($m[1]);
    }
  }
}

/**
 * Start/restart DHCP server for underlay. Returns ['ok'=>bool,'error'=>string,'safe'=>array]
 */
function tbn_dhcp_server_apply($if, array $cfg) {
  $v4 = (($cfg['USE_DHCP'] ?? '') === 'server');
  $v6 = (($cfg['USE_DHCP6'] ?? '') === 'server');
  if (!$v4 && !$v6) {
    $netdev = tbn_dhcp_netdev_for_cfg($if, $cfg);
    if ($netdev !== '') {
      tbn_dhcp_server_stop($netdev);
    }
    return ['ok' => true, 'error' => '', 'safe' => null];
  }
  $safe = tbn_dhcp_server_safe($if, $cfg);
  $netdev = $safe['netdev'];
  if ($safe['status'] === 'block' || $netdev === '') {
    return [
      'ok' => false,
      'error' => implode(' ', $safe['messages']),
      'safe' => $safe,
    ];
  }
  if (!is_dir('/sys/class/net/' . $netdev)) {
    return ['ok' => false, 'error' => "Netdev {$netdev} missing", 'safe' => $safe];
  }
  $plan = $safe['plan'];
  // Ensure host L3 is .1 before dnsmasq (caller usually set this; belt-and-suspenders)
  if ($v4) {
    $ife = escapeshellarg($netdev);
    @exec("ip -4 addr flush dev {$ife} 2>/dev/null");
    @exec('ip -4 addr add ' . escapeshellarg($plan['ip'] . '/' . $plan['prefix']) . " dev {$ife} 2>/dev/null");
  }
  if ($v6) {
    $ula_ip = rtrim($plan['ula_prefix'], ':') . ':1';
    $ife = escapeshellarg($netdev);
    @exec('ip -6 addr add ' . escapeshellarg($ula_ip . '/64') . " dev {$ife} 2>/dev/null");
  }
  tbn_dhcp_server_stop($netdev);
  $conf = tbn_dhcp_write_conf($netdev, $plan, $v4, $v6);
  if ($conf === '') {
    return ['ok' => false, 'error' => 'Could not write dnsmasq conf', 'safe' => $safe];
  }
  $bin = tbn_dhcp_bin();
  $cmd = escapeshellarg($bin) . ' --conf-file=' . escapeshellarg($conf);
  $out = [];
  $rc = 0;
  @exec($cmd . ' 2>&1', $out, $rc);
  if ($rc !== 0) {
    return [
      'ok' => false,
      'error' => 'dnsmasq failed: ' . implode(' ', $out),
      'safe' => $safe,
    ];
  }
  return ['ok' => true, 'error' => '', 'safe' => $safe, 'netdev' => $netdev, 'conf' => $conf];
}

/** HTML notice for iface page / peers. */
function tbn_dhcp_forum_help_html() {
  $u = htmlspecialchars(tbn_forum_support_url(), ENT_QUOTES);
  return ' If this blocks you, post diagnostics on the '
    . '<a href="' . $u . '" target="_blank" rel="noopener">Unraid forum thread</a>.';
}
