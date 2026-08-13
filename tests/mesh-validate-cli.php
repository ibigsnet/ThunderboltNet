<?php
/** CLI unit tests for mesh validator (no Unraid required). */
function tbn_cfg_dir() { return sys_get_temp_dir() . '/tbn-mesh-test'; }
function tbn_load_cfg() { return []; }
function tbn_link_summaries() { return []; }
function tbn_load_peers_memory() { return []; }
function tbn_save_peers_memory($p) { return true; }
function tbn_sysfs_str($p) { return ''; }
function tbn_iface_addrs($if) { return []; }

require_once dirname(__DIR__) . '/include/tbn-mesh.php';

$fail = 0;
function assert_true($c, $msg) {
  global $fail;
  if (!$c) { echo "FAIL $msg\n"; $fail++; } else { echo "ok $msg\n"; }
}

$a = ['tx_mbps' => 20000, 'rx_mbps' => 20000, 'media' => 'thunderbolt'];
$b = ['tx_mbps' => 20000, 'rx_mbps' => 20000, 'media' => 'thunderbolt'];
$r = tbn_mesh_validate_pair($a, $b);
assert_true(($r['status'] ?? '') === 'green', 'symmetric agree');

$a = ['tx_mbps' => 20000, 'rx_mbps' => 60000, 'media' => 'thunderbolt'];
$b = ['tx_mbps' => 60000, 'rx_mbps' => 20000, 'media' => 'thunderbolt'];
$r = tbn_mesh_validate_pair($a, $b);
assert_true(($r['status'] ?? '') === 'green' && ($r['reason'] ?? '') === 'agree_asymmetric', 'asymmetric agree');

$a = ['tx_mbps' => 20000, 'rx_mbps' => 20000, 'media' => 'thunderbolt'];
$b = ['tx_mbps' => 10000, 'rx_mbps' => 10000, 'media' => 'thunderbolt'];
$r = tbn_mesh_validate_pair($a, $b);
assert_true(($r['status'] ?? '') === 'red', 'disagree red');

$r = tbn_mesh_validate_pair($a, null);
assert_true(($r['status'] ?? '') === 'orange', 'local only orange');

$a = ['tx_mbps' => 2500, 'rx_mbps' => 2500, 'negotiated_mbps' => 2500, 'max_mbps' => 10000, 'media' => 'ethernet'];
$b = ['tx_mbps' => 2500, 'rx_mbps' => 2500, 'negotiated_mbps' => 2500, 'max_mbps' => 2500, 'media' => 'ethernet'];
$r = tbn_mesh_validate_pair($a, $b);
assert_true(($r['status'] ?? '') === 'green' && ($r['info'] ?? '') !== '', 'eth underutilized info');

$prev = ['status' => 'green', 'reason' => 'agree', 'checked_at' => date('c', time() - 10)];
$new = ['status' => 'red', 'reason' => 'disagree_speed', 'local' => [], 'peer' => [], 'info' => ''];
$h = tbn_mesh_apply_holdoff($prev, $new, 120);
assert_true(($h['status'] ?? '') === 'green' && ($h['pending_status'] ?? '') === 'red', 'holdoff keeps green');

exit($fail ? 1 : 0);
