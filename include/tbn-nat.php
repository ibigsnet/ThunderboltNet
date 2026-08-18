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
 * One-line address schema for UI (facts only).
 */
function tbn_nat_schema_line($if, array $cfg) {
  $cidr = tbn_nat_underlay_cidr($cfg);
  $plan = function_exists('tbn_normalize_address_plan')
    ? tbn_normalize_address_plan($cfg['ADDRESS_PLAN'] ?? 'small-lan')
    : (string)($cfg['ADDRESS_PLAN'] ?? 'small-lan');
  $host = trim((string)($cfg['IPADDR'] ?? ''));
  $peer = tbn_nat_peer_hint($cfg);
  $bits = [];
  if ($cidr !== '') {
    $bits[] = $cidr;
  }
  if ($plan !== '') {
    $bits[] = 'plan ' . $plan;
  }
  if ($host !== '') {
    $bits[] = 'this host ' . $host;
  }
  if ($peer !== '') {
    $bits[] = 'peer ~' . $peer;
  }
  if (tbn_nat_enabled($cfg)) {
    $up = tbn_nat_normalize_uplink($cfg['NAT_UPLINK'] ?? 'auto');
    $resolved = tbn_nat_resolve_uplink($up);
    $bits[] = 'NAT → ' . ($resolved !== '' ? $resolved : $up);
  } else {
    $bits[] = 'NAT off';
  }
  $label = preg_match('/^thunderbolt(\d+)$/', (string)$if, $m) ? ('tbn' . $m[1]) : (string)$if;
  return $label . ': ' . implode(' · ', $bits);
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
 * Remove all iptables rules tagged for this iface (nat + filter).
 */
function tbn_nat_clear($if) {
  $tag = tbn_nat_comment_tag($if);
  tbn_nat_delete_tagged_rules('nat', $tag);
  tbn_nat_delete_tagged_rules('filter', $tag);
  return true;
}

/**
 * Delete rules in a table that carry our comment tag (line-number delete).
 */
function tbn_nat_delete_tagged_rules($table, $tag) {
  $table = preg_replace('/[^a-z]/', '', (string)$table);
  if ($table === '' || $tag === '') {
    return;
  }
  $chains = ($table === 'nat') ? ['POSTROUTING'] : ['FORWARD'];
  foreach ($chains as $chain) {
    for ($guard = 0; $guard < 16; $guard++) {
      $lines = [];
      @exec(
        'iptables -t ' . escapeshellarg($table)
        . ' -L ' . escapeshellarg($chain)
        . ' -v --line-numbers 2>/dev/null',
        $lines
      );
      $num = 0;
      foreach ($lines as $line) {
        // Verbose list includes /* ThunderboltNet-NAT-… */ when comment match
        if (strpos($line, $tag) === false) {
          continue;
        }
        if (preg_match('/^\s*(\d+)\s+/', $line, $m)) {
          $num = (int)$m[1];
          break;
        }
      }
      if ($num < 1) {
        break;
      }
      @exec(
        'iptables -t ' . escapeshellarg($table)
        . ' -D ' . escapeshellarg($chain) . ' ' . $num . ' 2>/dev/null'
      );
    }
  }
}

/**
 * Ensure IPv4 forwarding is on (NAT requires it). Non-fatal if sysctl fails.
 */
function tbn_nat_ensure_forwarding() {
  @exec('sysctl -w net.ipv4.ip_forward=1 >/dev/null 2>&1');
  // Persist hint for reboot (Unraid often already forwards for Docker/VMs)
  $sysctl = '/boot/config/plugins/ThunderboltNet/sysctl-nat.conf';
  $dir = dirname($sysctl);
  if (is_dir($dir) || @mkdir($dir, 0755, true)) {
    @file_put_contents($sysctl, "net.ipv4.ip_forward=1\n");
  }
  // Soft-link into sysctl.d if present
  if (is_dir('/etc/sysctl.d')) {
    $dst = '/etc/sysctl.d/99-thunderboltnet-nat.conf';
    if (!is_file($dst) && is_readable($sysctl)) {
      @copy($sysctl, $dst);
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

  tbn_nat_ensure_forwarding();

  $tag = tbn_nat_comment_tag($if);
  $ife = escapeshellarg($if);
  $upe = escapeshellarg($uplink);
  $pre = escapeshellarg($prefix);
  $tage = escapeshellarg($tag);

  // MASQUERADE underlay → uplink
  $cmd = "iptables -t nat -A POSTROUTING -s {$pre} -o {$upe} -m comment --comment {$tage} -j MASQUERADE 2>&1";
  $out = [];
  $rc = 0;
  @exec($cmd, $out, $rc);
  if ($rc !== 0) {
    return [
      'ok' => false,
      'enabled' => false,
      'error' => 'iptables MASQUERADE failed: ' . trim(implode(' ', $out)),
    ];
  }

  // Forward TB → uplink
  @exec("iptables -A FORWARD -i {$ife} -o {$upe} -m comment --comment {$tage} -j ACCEPT 2>/dev/null");
  // Established return
  @exec("iptables -A FORWARD -i {$upe} -o {$ife} -m state --state RELATED,ESTABLISHED -m comment --comment {$tage} -j ACCEPT 2>/dev/null");

  return [
    'ok' => true,
    'enabled' => true,
    'iface' => $if,
    'uplink' => $uplink,
    'prefix' => $prefix,
  ];
}

/**
 * Status blob for Info / schema (does not change rules).
 */
function tbn_nat_status($if, array $cfg = null) {
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
  @exec('iptables -t nat -S POSTROUTING 2>/dev/null', $lines);
  foreach ($lines as $line) {
    if (strpos($line, $tag) !== false) {
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
    'forwarding' => trim(@file_get_contents('/proc/sys/net/ipv4/ip_forward')) === '1',
  ];
}

} // function_exists guard
