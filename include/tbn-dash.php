<?php
/**
 * Dashboard tile data (read-only JSON): Thunderbolt netdevs, byte counters,
 * trained link speed. Never writes anything.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function tbn_dash_read($path) {
  $v = @file_get_contents($path);
  return ($v === false) ? '' : trim($v);
}

// thunderbolt_net reports no netdev speed; the trained rate lives on the
// Thunderbolt XDomain device (parent or grandparent of the netdev device).
function tbn_dash_trained($name) {
  $dev = @realpath('/sys/class/net/' . $name . '/device');
  if ($dev) {
    foreach ([dirname($dev), dirname(dirname($dev))] as $c) {
      $rx = tbn_dash_read($c . '/rx_speed');
      if ($rx !== '') {
        return ['rx' => $rx, 'tx' => tbn_dash_read($c . '/tx_speed')];
      }
    }
  }
  return ['rx' => '', 'tx' => ''];
}

$out = ['time' => microtime(true), 'ports' => []];
foreach (glob('/sys/class/net/*') ?: [] as $p) {
  $name = basename($p);
  if (!preg_match('/^(thunderbolt\d+|bond-tb\d+|br-tb\d+)$/', $name)) {
    continue;
  }
  $label = preg_match('/^thunderbolt(\d+)$/', $name, $m) ? ('tbn' . $m[1]) : $name;
  $speed = (strpos($name, 'thunderbolt') === 0) ? tbn_dash_trained($name) : ['rx' => '', 'tx' => ''];
  $out['ports'][] = [
    'name' => $name,
    'label' => $label,
    'up' => tbn_dash_read($p . '/carrier') === '1',
    'mtu' => (int)tbn_dash_read($p . '/mtu'),
    'rx_bytes' => (int)tbn_dash_read($p . '/statistics/rx_bytes'),
    'tx_bytes' => (int)tbn_dash_read($p . '/statistics/tx_bytes'),
    'rx_speed' => $speed['rx'],
    'tx_speed' => $speed['tx'],
  ];
}
echo json_encode($out, JSON_UNESCAPED_SLASHES);
