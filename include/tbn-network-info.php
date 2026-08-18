<?php
/**
 * AJAX: Thunderbolt iface "Info" dialog — same shape as dynamix NetworkInfo.php / eth0.
 * POST port=thunderboltN  (csrf_token added by Unraid $(document).ajaxSend)
 */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/tbn-lib.php';

$if = preg_replace('/[^A-Za-z0-9_.-]/', '', (string)($_POST['port'] ?? $_GET['port'] ?? ''));
if ($if === '' || !preg_match('/^thunderbolt\d+$/', $if)) {
  echo '<p>Missing or invalid Thunderbolt interface.</p>';
  exit;
}

$none = 'None';
$missing = '<span class="red-text">Missing</span>';
$present = is_dir('/sys/class/net/' . $if);
$oper = $present ? tbn_sysfs_str('/sys/class/net/' . $if . '/operstate') : '';
$carrier = $present ? tbn_sysfs_str('/sys/class/net/' . $if . '/carrier') : '';
$mtu = $present ? tbn_sysfs_str('/sys/class/net/' . $if . '/mtu') : '';
$mac = $present ? strtoupper(tbn_sysfs_str('/sys/class/net/' . $if . '/address')) : '';
$addrs = $present ? tbn_iface_addrs($if) : [];
$addrs6 = $present ? tbn_iface_addrs6($if) : [];

$rx = $tx = $rl = $tl = $peer = '';
$parent = $present ? @realpath('/sys/class/net/' . $if . '/device') : false;
if ($parent) {
  $up = dirname($parent);
  $peer = tbn_sysfs_str($up . '/device_name');
  if ($peer === '') {
    $peer = tbn_sysfs_str(dirname($up) . '/device_name');
  }
  foreach ([dirname($parent), dirname(dirname($parent))] as $c) {
    if (tbn_sysfs_str($c . '/rx_speed') !== '') {
      $rx = tbn_sysfs_str($c . '/rx_speed');
      $tx = tbn_sysfs_str($c . '/tx_speed');
      $rl = tbn_sysfs_str($c . '/rx_lanes');
      $tl = tbn_sysfs_str($c . '/tx_lanes');
      break;
    }
  }
}
$rate = function_exists('tbn_format_link_rate')
  ? tbn_format_link_rate($rx, $tx, ['rx_lanes' => $rl, 'tx_lanes' => $tl])
  : trim(($rx ?: '') . ' / ' . ($tx ?: ''));
if ($rate === '' || $rate === ' / ') {
  $rate = 'Unknown';
}

$link = 'Unknown';
if ($present) {
  if ($oper === 'up' && ($carrier === '1' || $carrier === '')) {
    $link = 'Yes';
  } elseif ($oper !== '') {
    $link = ucfirst($oper);
  }
}

$gw4 = '';
if ($present) {
  $gw4 = trim((string)@shell_exec(
    'ip -4 route show default dev ' . escapeshellarg($if) . " 2>/dev/null | awk '{print \$3;exit}'"
  ));
}

// Strip CIDR for eth0-like address column (NetworkInfo shows bare IPs)
$ipv4 = [];
foreach ($addrs as $a) {
  $ipv4[] = preg_replace('#/\d+$#', '', $a);
}
$ipv6 = [];
foreach ($addrs6 as $a) {
  // skip link-local for the main list (eth0 NetworkInfo uses scope global)
  if (stripos($a, 'fe80:') === 0) {
    continue;
  }
  $ipv6[] = preg_replace('#/\d+$#', '', $a);
}

echo "<table style='text-align:left;font-size:1.2rem'>";
echo "<tr><td>&nbsp;</td><td>&nbsp;</td></tr>";
echo "<tr><td>Interface link:</td><td>" . htmlspecialchars($link) . "</td></tr>";
echo "<tr><td>Interface speed:</td><td>" . htmlspecialchars($rate) . "</td></tr>";
if ($peer !== '') {
  echo "<tr><td>Peer name:</td><td>" . htmlspecialchars($peer) . "</td></tr>";
}
if ($mac !== '') {
  echo "<tr><td>MAC address:</td><td>" . htmlspecialchars($mac) . "</td></tr>";
}
if ($mtu !== '') {
  echo "<tr><td>MTU:</td><td>" . htmlspecialchars($mtu) . "</td></tr>";
}
if (count($ipv4)) {
  foreach ($ipv4 as $ip) {
    echo "<tr><td>IPv4 address:</td><td>" . htmlspecialchars($ip) . "</td></tr>";
  }
} else {
  echo "<tr><td>IPv4 address:</td><td>" . $missing . "</td></tr>";
}
echo "<tr><td>IPv4 default gateway:</td><td>" . ($gw4 !== '' ? htmlspecialchars($gw4) : $missing) . "</td></tr>";
if (function_exists('tbn_nat_status') && function_exists('tbn_load_iface_cfg')) {
  $nat = tbn_nat_status($if, tbn_load_iface_cfg($if));
  if (!empty($nat['want'])) {
    $nat_txt = !empty($nat['live'])
      ? ('Yes → ' . ($nat['uplink'] !== '' ? $nat['uplink'] : '?') . ' (' . ($nat['prefix'] !== '' ? $nat['prefix'] : '?') . ')')
      : 'Configured (rules not live yet — Apply / wait for link)';
  } else {
    $nat_txt = 'No';
  }
  echo "<tr><td>Share uplink (NAT):</td><td>" . htmlspecialchars($nat_txt) . "</td></tr>";
  if (!empty($nat['schema'])) {
    echo "<tr><td>Address schema:</td><td>" . htmlspecialchars($nat['schema']) . "</td></tr>";
  }
}
if (count($ipv6)) {
  foreach ($ipv6 as $ip) {
    echo "<tr><td>IPv6 address:</td><td>" . htmlspecialchars($ip) . "</td></tr>";
  }
} else {
  echo "<tr><td>IPv6 address:</td><td>" . $missing . "</td></tr>";
}
echo "</table>";
