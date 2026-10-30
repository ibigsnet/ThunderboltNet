<?php
/**
 * Thunderbolt Net — per-link NAT / share host uplink to TB underlay peers.
 *
 * Opposite of GATEWAY/DEFAULT_ROUTE (those make *this host* use TB as uplink).
 * NAT shares Unraid's LAN/WAN iface (br0/eth0/wlan0/…) with peers on this tbn prefix.
 *
 * Persistence: NAT_ENABLE / NAT_UPLINK in ifaces/thunderboltN.cfg on flash.
 * Rules are reinstalled by tbn_apply_iface() on UI Apply, array-start, and udev reapply.
 * Marked iptables comments allow idempotent remove without touching Docker/libvirt NAT.
 */

if (!function_exists('tbn_nat_comment_tag')) {

/**
 * iptables comment tag unique per Thunderbolt iface.
 */
function tbn_nat_comment_tag($if) {
  $if = preg_replace('/[^a-zA-Z0-9._-]/', '', (string)$if);
  return 'ThunderboltNet-NAT-' . ($if !== '' ? $if : 'unknown');
}

/**
 * Normalize NAT_ENABLE.
 */
function tbn_nat_enabled(array $cfg) {
  return strtolower(trim((string)($cfg['NAT_ENABLE'] ?? 'no'))) === 'yes';
}

/**
 * Normalize uplink choice (auto or iface name).
 */
function tbn_nat_normalize_uplink($raw) {
  $u = strtolower(trim((string)$raw));
  if ($u === '' || $u === 'auto') {
    return 'auto';
  }
  // Allow common Unraid / Linux names
  if (!preg_match('/^[a-z][a-z0-9._-]{0,15}$/', $u)) {
    return 'auto';
  }
  // Never NAT out a Thunderbolt underlay into itself
  if (preg_match('/^thunderbolt\d+$/', $u) || preg_match('/^bond-tb\d+$/', $u) || preg_match('/^br-tb\d+$/', $u)) {
    return 'auto';
  }
  return $u;
}

/**
 * Prefix length from dotted mask or integer string.
 */
function tbn_nat_prefix_len(array $cfg) {
  $nm = trim((string)($cfg['NETMASK'] ?? '24'));
  if ($nm !== '' && ctype_digit($nm)) {
    $p = (int)$nm;
    return ($p >= 0 && $p <= 32) ? $p : 24;
  }
  if (function_exists('tbn_mask_to_prefix')) {
    return (int)tbn_mask_to_prefix($nm);
  }
  $long = @ip2long($nm);
  if ($long === false) {
    return 24;
  }
  return substr_count(decbin($long), '1');
}

/**
 * Underlay CIDR for this iface cfg (e.g. 10.255.1.0/24), or '' if unknown.
 */
function tbn_nat_underlay_cidr(array $cfg) {
  $ip = trim((string)($cfg['IPADDR'] ?? ''));
  if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
    return '';
  }
  $pfx = tbn_nat_prefix_len($cfg);
  $long = ip2long($ip);
  if ($long === false) {
    return '';
  }
  $mask = $pfx === 0 ? 0 : (-1 << (32 - $pfx));
  $net = long2ip($long & $mask);
  return $net . '/' . $pfx;
}

/**
 * Suggested peer IPv4 on this underlay (.1 if host is .2, else .2 if host is .1, else host^1 in host byte).
 */
function tbn_nat_peer_hint(array $cfg) {
  $ip = trim((string)($cfg['IPADDR'] ?? ''));
  if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
    return '';
  }
  $parts = explode('.', $ip);
  if (count($parts) !== 4) {
    return '';
  }
  $host = (int)$parts[3];
  if ($host === 1) {
    $parts[3] = '2';
  } elseif ($host === 2) {
    $parts[3] = '1';
  } else {
    $parts[3] = (string)max(1, min(254, $host === 254 ? 253 : $host + 1));
  }
  return implode('.', $parts);
}

/**
 * First global IPv4 on a netdev (lightweight — no external WAN lookup).
 */
function tbn_nat_iface_ipv4($if) {
  $if = preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$if);
  if ($if === '' || !is_dir('/sys/class/net/' . $if)) {
    return '';
  }
  $lines = [];
  @exec('ip -4 -o addr show dev ' . escapeshellarg($if) . ' scope global 2>/dev/null', $lines);
  foreach ($lines as $line) {
    if (preg_match('/\binet\s+(\d{1,3}(?:\.\d{1,3}){3})/', $line, $m)) {
      return $m[1];
    }
  }
  return '';
}

/**
 * Compact path line for the tbn form lead (same row as the muted hint).
 *
 * NAT off:  tbn1 · 10.255.0.1/24 · peer ~10.255.0.2 · NAT off
 * NAT on:   tbn1 · 10.255.0.0/24 → wlan0 (uplink) → internet
 */
function tbn_nat_schema_line($if, array $cfg) {
  $label = preg_match('/^thunderbolt(\d+)$/', (string)$if, $m) ? ('tbn' . $m[1]) : (string)$if;
  $host = trim((string)($cfg['IPADDR'] ?? ''));
  $pfx = function_exists('tbn_nat_prefix_len') ? tbn_nat_prefix_len($cfg) : 24;
  $cidr = tbn_nat_underlay_cidr($cfg);
  $peer = tbn_nat_peer_hint($cfg);
  $host_cidr = '';
  if ($host !== '' && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    $host_cidr = $host . '/' . $pfx;
  } elseif ($cidr !== '') {
    $host_cidr = $cidr;
  }

  if (tbn_nat_enabled($cfg)) {
    $up = tbn_nat_resolve_uplink($cfg['NAT_UPLINK'] ?? 'auto');
    $up_ip = $up !== '' ? tbn_nat_iface_ipv4($up) : '';
    $under = $cidr !== '' ? $cidr : ($host_cidr !== '' ? $host_cidr : 'underlay');
    $mid = $up !== ''
      ? ($up . ($up_ip !== '' ? ' (' . $up_ip . ')' : ''))
      : 'uplink?';
    return $label . ' · ' . $under . ' → ' . $mid . ' → internet';
  }

  $bits = [$label];
  if ($host_cidr !== '') {
    $bits[] = $host_cidr;
  }
  if ($peer !== '') {
    // Short peer hint: ~.2 when same /24 prefix as host
    $short = $peer;
    if ($host !== '' && preg_match('/^(\d+\.\d+\.\d+)\.(\d+)$/', $host, $hm)
        && preg_match('/^(\d+\.\d+\.\d+)\.(\d+)$/', $peer, $pm)
        && $hm[1] === $pm[1]) {
      $short = '~.' . $pm[2];
    } else {
      $short = '~' . $peer;
    }
    $bits[] = 'peer ' . $short;
  }
  $bits[] = 'NAT off';
  return implode(' · ', $bits);
}

/**
 * Candidate uplink ifaces for the dropdown (auto + live non-TB).
 *
 * @return string[] values for <option>
 */
function tbn_nat_uplink_choices() {
  $out = ['auto'];
  $prefer = ['br0', 'bond0', 'eth0', 'wlan0', 'wg0'];
  foreach ($prefer as $n) {
    if (is_dir('/sys/class/net/' . $n) && !in_array($n, $out, true)) {
      $out[] = $n;
    }
  }
  $skip = '/^(lo|docker|veth|virbr|br-tb|bond-tb|thunderbolt|tun|tap|wg-quick)/';
  foreach (scandir('/sys/class/net') ?: [] as $n) {
    if ($n === '.' || $n === '..') {
      continue;
    }
    if (preg_match($skip, $n)) {
      continue;
    }
    if (preg_match('/^thunderbolt\d+$/', $n) || preg_match('/^bond-tb\d+$/', $n)) {
      continue;
    }
    if (!in_array($n, $out, true)) {
      $out[] = $n;
    }
  }
  return $out;
}

/**
 * Resolve uplink iface name, or '' if none.
 */
function tbn_nat_resolve_uplink($choice = 'auto') {
  $choice = tbn_nat_normalize_uplink($choice);
  if ($choice !== 'auto') {
    if (is_dir('/sys/class/net/' . $choice)) {
      return $choice;
    }
    return '';
  }
  // Prefer br0 when it owns (or could own) default; else iface of current default route.
  $def = tbn_nat_default_route_dev();
  if ($def !== '' && is_dir('/sys/class/net/' . $def)) {
    // If default is wlan0 but br0 exists and is UP with an address, still honor default
    // (lab may use Wi‑Fi as WAN). auto = follow default route.
    return $def;
  }
  foreach (['br0', 'bond0', 'eth0', 'wlan0'] as $n) {
    if (!is_dir('/sys/class/net/' . $n)) {
      continue;
    }
    $oper = @file_get_contents('/sys/class/net/' . $n . '/operstate');
    if (is_string($oper) && trim($oper) === 'up') {
      return $n;
    }
  }
  return '';
}

/**
 * Device of the current IPv4 default route, or ''.
 */
function tbn_nat_default_route_dev() {
  $lines = [];
  @exec('ip -4 route show default 2>/dev/null', $lines);
  foreach ($lines as $line) {
    if (preg_match('/\bdev\s+(\S+)/', $line, $m)) {
      return $m[1];
    }
  }
  return '';
}

/**
 * True when an `iptables -S` line carries exactly this comment tag
 * (ThunderboltNet-NAT-thunderbolt1 must not match ...-thunderbolt10).
 */
function tbn_nat_line_has_tag($line, $tag) {
  $q = preg_quote((string)$tag, '/');
  return (bool)preg_match('/--comment\s+(?:"' . $q . '"|' . $q . ')(?:\s|$)/', (string)$line);
}

/** Split an `iptables -S` rule line into argv (handles "quoted" tokens). */
function tbn_nat_split_rule($line) {
  $out = [];
  foreach (str_getcsv(trim((string)$line), ' ', '"', '\\') as $t) {
    if ($t !== '' && $t !== null) {
      $out[] = $t;
    }
  }
  return $out;
}

/**
 * Delete argv lists (without the iptables binary) for every rule tagged $tag in
 * `iptables -t $table -S` output. Pure; no DNS lookups (uses -S, not -L).
 *
 * @param string[] $lines
 * @return array<int,string[]>
 */
function tbn_nat_delete_argvs($table, array $lines, $tag) {
  $out = [];
  foreach ($lines as $line) {
    if (strpos((string)$line, '-A ') !== 0 || !tbn_nat_line_has_tag($line, $tag)) {
      continue;
    }
    $argv = tbn_nat_split_rule($line);
    $argv[0] = '-D';
    $out[] = array_merge(['-t', $table], $argv);
  }
  return $out;
}

function tbn_nat_iptables($argv, &$out = null, &$rc = null) {
  $out = [];
  $rc = 0;
  @exec('iptables -w 5 ' . implode(' ', array_map('escapeshellarg', $argv)) . ' 2>&1', $out, $rc);
  return $rc === 0;
}

/**
 * Remove all iptables rules tagged for this iface (nat + filter).
 */
function tbn_nat_clear($if) {
  $tag = tbn_nat_comment_tag($if);
  foreach (['nat' => 'POSTROUTING', 'filter' => 'FORWARD'] as $table => $chain) {
    $lines = [];
    @exec('iptables -w 5 -t ' . escapeshellarg($table) . ' -S ' . escapeshellarg($chain) . ' 2>/dev/null', $lines);
    foreach (tbn_nat_delete_argvs($table, $lines, $tag) as $argv) {
      tbn_nat_iptables($argv);
    }
  }
  if (function_exists('tbn_fwd_release')) {
    tbn_fwd_release('nat:' . $if);
  }
  return true;
}

/** Private IPv4 nameservers from resolv.conf (kept reachable in internet-only NAT). */
function tbn_nat_private_dns($resolv = '/etc/resolv.conf') {
  $out = [];
  foreach (@file($resolv, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    if (preg_match('/^\s*nameserver\s+(\d{1,3}(?:\.\d{1,3}){3})\s*$/', $line, $m)
        && filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        && !filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
      $out[$m[1]] = $m[1];
    }
  }
  return array_values($out);
}

/** Destinations an internet-only peer may not reach through the uplink. */
function tbn_nat_internet_only_blocks() {
  return ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '169.254.0.0/16', '100.64.0.0/10'];
}

/**
 * Rules for one NAT link, in insert order. Each entry: argv after `iptables`.
 * scope lan: the peer prefix may reach anything the uplink reaches.
 * scope internet: private / CGNAT / link-local destinations are dropped,
 * except DNS to the host's private nameservers.
 *
 * @return array<int,string[]>
 */
function tbn_nat_rule_argvs($if, $prefix, $uplink, $scope = 'lan', array $dns = []) {
  $tag = tbn_nat_comment_tag($if);
  $c = ['-m', 'comment', '--comment', $tag];
  $rules = [];
  $rules[] = array_merge(['-t', 'nat', '-A', 'POSTROUTING', '-s', $prefix, '-o', $uplink], $c, ['-j', 'MASQUERADE']);
  if ($scope === 'internet') {
    foreach ($dns as $ip) {
      foreach (['udp', 'tcp'] as $proto) {
        $rules[] = array_merge(['-t', 'filter', '-A', 'FORWARD', '-i', $if, '-o', $uplink, '-s', $prefix,
          '-d', $ip . '/32', '-p', $proto, '--dport', '53'], $c, ['-j', 'ACCEPT']);
      }
    }
    foreach (tbn_nat_internet_only_blocks() as $net) {
      $rules[] = array_merge(['-t', 'filter', '-A', 'FORWARD', '-i', $if, '-o', $uplink, '-d', $net], $c, ['-j', 'DROP']);
    }
  }
  $rules[] = array_merge(['-t', 'filter', '-A', 'FORWARD', '-i', $if, '-o', $uplink, '-s', $prefix], $c, ['-j', 'ACCEPT']);
  $rules[] = array_merge(['-t', 'filter', '-A', 'FORWARD', '-i', $uplink, '-o', $if, '-d', $prefix,
    '-m', 'conntrack', '--ctstate', 'RELATED,ESTABLISHED'], $c, ['-j', 'ACCEPT']);
  return $rules;
}

/**
 * Older versions forced net.ipv4.ip_forward=1 and left sysctl files behind.
 * Forwarding is now per interface (tbn_fwd_*); remove the leftovers.
 */
function tbn_nat_cleanup_legacy_sysctl() {
  foreach ([tbn_cfg_dir() . '/sysctl-nat.conf', '/etc/sysctl.d/99-thunderboltnet-nat.conf'] as $f) {
    if (is_file($f)) {
      @unlink($f);
    }
  }
}

/**
 * Apply or clear NAT for one Thunderbolt iface according to cfg.
 *
 * @return array{ok:bool,enabled?:bool,uplink?:string,prefix?:string,error?:string}
 */
function tbn_nat_apply($if, array $cfg) {
  $if = (string)$if;
  // Always clear our previous tags first (idempotent)
  tbn_nat_clear($if);
  tbn_nat_cleanup_legacy_sysctl();

  if (($cfg['ENABLE'] ?? 'yes') !== 'yes' || !tbn_nat_enabled($cfg)) {
    return ['ok' => true, 'enabled' => false, 'iface' => $if];
  }

  $prefix = tbn_nat_underlay_cidr($cfg);
  if ($prefix === '') {
    return ['ok' => false, 'enabled' => false, 'error' => 'NAT needs a static IPv4 address/mask on this link'];
  }

  $uplink = tbn_nat_resolve_uplink($cfg['NAT_UPLINK'] ?? 'auto');
  if ($uplink === '') {
    return ['ok' => false, 'enabled' => false, 'error' => 'NAT uplink iface not found (set Uplink or bring br0/eth0/wlan0 up)'];
  }
  if ($uplink === $if || preg_match('/^thunderbolt\d+$/', $uplink)) {
    return ['ok' => false, 'enabled' => false, 'error' => 'NAT uplink cannot be a Thunderbolt underlay'];
  }

  $scope = (($cfg['NAT_SCOPE'] ?? 'lan') === 'internet') ? 'internet' : 'lan';
  $dns = $scope === 'internet' ? tbn_nat_private_dns() : [];
  foreach (tbn_nat_rule_argvs($if, $prefix, $uplink, $scope, $dns) as $i => $argv) {
    $out = [];
    $rc = 0;
    if (!tbn_nat_iptables($argv, $out, $rc) && $i === 0) {
      return [
        'ok' => false,
        'enabled' => false,
        'error' => 'iptables MASQUERADE failed: ' . trim(implode(' ', $out)),
      ];
    }
  }

  // Forwarding only on the two interfaces involved (not net.ipv4.ip_forward)
  if (function_exists('tbn_fwd_claim')) {
    tbn_fwd_claim('nat:' . $if, [$if, $uplink], []);
  }

  return [
    'ok' => true,
    'enabled' => true,
    'iface' => $if,
    'uplink' => $uplink,
    'prefix' => $prefix,
    'scope' => $scope,
  ];
}

/**
 * Status blob for Info / schema (does not change rules).
 */
function tbn_nat_status($if, ?array $cfg = null) {
  if ($cfg === null && function_exists('tbn_load_iface_cfg')) {
    $cfg = tbn_load_iface_cfg($if);
  }
  if (!is_array($cfg)) {
    $cfg = [];
  }
  $want = tbn_nat_enabled($cfg);
  $prefix = tbn_nat_underlay_cidr($cfg);
  $uplink_cfg = tbn_nat_normalize_uplink($cfg['NAT_UPLINK'] ?? 'auto');
  $uplink = $want ? tbn_nat_resolve_uplink($uplink_cfg) : '';
  $tag = tbn_nat_comment_tag($if);
  $live = false;
  $lines = [];
  @exec('iptables -w 5 -t nat -S POSTROUTING 2>/dev/null', $lines);
  foreach ($lines as $line) {
    if (tbn_nat_line_has_tag($line, $tag)) {
      $live = true;
      break;
    }
  }
  return [
    'want' => $want,
    'live' => $live,
    'uplink_cfg' => $uplink_cfg,
    'uplink' => $uplink,
    'prefix' => $prefix,
    'schema' => tbn_nat_schema_line($if, $cfg),
    'scope' => (($cfg['NAT_SCOPE'] ?? 'lan') === 'internet') ? 'internet' : 'lan',
    'forwarding' => trim((string)@file_get_contents('/proc/sys/net/ipv4/conf/' . preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$if) . '/forwarding')) === '1',
  ];
}

} // function_exists guard
