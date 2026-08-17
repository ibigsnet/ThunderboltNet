<?php
/**
 * AJAX: Thunderbolt iface "Info" dialog (eth0 Network Info analogue).
 * POST port=thunderboltN
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
  : trim(($rx ?: '—') . ' / ' . ($tx ?: '—'));

$gw4 = '';
if ($present) {
  $gw4 = trim((string)@shell_exec("ip -4 route show default dev " . escapeshellarg($if) . " 2>/dev/null | awk '{print \$3;exit}'"));
}
$cfg = tbn_load_iface_cfg($if);
$cfg_ip = trim((string)($cfg['IPADDR'] ?? ''));

echo "<table style='text-align:left;font-size:1.2rem'>";
echo "<tr><td>&nbsp;</td><td>&nbsp;</td></tr>";
echo "<tr><td>Interface:</td><td><code>" . htmlspecialchars($if) . "</code> (" . htmlspecialchars(tbn_label_for_iface($if)) . ")</td></tr>";
echo "<tr><td>Link state:</td><td>" . htmlspecialchars($oper !== '' ? $oper : 'Unknown')
  . " · carrier " . htmlspecialchars($carrier === '' ? 'n/a' : $carrier) . "</td></tr>";
echo "<tr><td>MAC address:</td><td>" . htmlspecialchars($mac !== '' ? $mac : $none) . "</td></tr>";
echo "<tr><td>Trained rate:</td><td>" . htmlspecialchars($rate !== '' ? $rate : $none) . "</td></tr>";
echo "<tr><td>Peer name:</td><td>" . htmlspecialchars($peer !== '' ? $peer : $none) . "</td></tr>";
echo "<tr><td>MTU:</td><td>" . htmlspecialchars($mtu !== '' ? $mtu : $none) . "</td></tr>";
if ($cfg_ip !== '') {
  echo "<tr><td>Configured IPv4:</td><td><code>" . htmlspecialchars($cfg_ip) . "</code> <span class='tbn-muted'>(form / flash)</span></td></tr>";
}
if ($addrs) {
  foreach ($addrs as $ip) {
    echo "<tr><td>IPv4 (kernel):</td><td><code>" . htmlspecialchars($ip) . "</code></td></tr>";
  }
} else {
  echo "<tr><td>IPv4 (kernel):</td><td>" . $missing . "</td></tr>";
}
echo "<tr><td>IPv4 default gateway:</td><td>" . ($gw4 !== '' ? htmlspecialchars($gw4) : $none) . "</td></tr>";
if ($addrs6) {
  foreach ($addrs6 as $ip) {
    echo "<tr><td>IPv6 (kernel):</td><td><code>" . htmlspecialchars($ip) . "</code></td></tr>";
  }
} else {
  echo "<tr><td>IPv6 (kernel):</td><td>" . $none . "</td></tr>";
}
echo "</table>";
