<?php
/**
 * #include for a tbnN form (#file ThunderboltNet/ifaces/<iface>.cfg).
 * #arg[1] or POST tbn_iface = kernel iface name (thunderbolt0).
 */
// Unraid update.php includes this on POST only; refuse a direct GET.
if (PHP_SAPI !== 'cli' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  return;
}
$docroot = $docroot ?? ($_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp');
require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';
// One writer at a time (boot / udev reapply can run at the same moment)
if (tbn_lock_acquire(60)) {
  register_shutdown_function('tbn_lock_release');
}

$if = '';
if (!empty($argv[1])) {
  $if = $argv[1];
} elseif (!empty($_POST['tbn_iface'])) {
  $if = $_POST['tbn_iface'];
} elseif (!empty($_POST['#arg'][1])) {
  $if = $_POST['#arg'][1];
}
// Infer from #file ThunderboltNet/ifaces/thunderbolt0.cfg
if ($if === '' && !empty($_POST['#file']) && preg_match('#ifaces/(thunderbolt\d+)\.cfg#', $_POST['#file'], $m)) {
  $if = $m[1];
}
if ($if === '' || !preg_match('/^thunderbolt\d+$/', $if)) {
  return;
}

// Unraid update.php runs this include *before* it saves $_POST into the iface
// cfg, so merge the form here, write it, and stop update.php from saving its
// older copy over ours ($save = false). Apply then uses the new settings.
$save = false;
$apply = $_POST['#apply'] ?? '';
if (strcasecmp((string)$apply, 'Reset') === 0) {
  tbn_reset_iface_cfg($if);
} elseif (PHP_SAPI !== 'cli') {
  tbn_write_iface_cfg($if, tbn_merge_iface_post($if, tbn_load_iface_cfg($if), $_POST));
}

// Global modules from main cfg
$g = tbn_load_cfg();
if (($g['load_modules'] ?? 'yes') === 'yes') {
  tbn_load_modules();
}

// Apply on the tbn tab is the user confirming the peer on this path for this boot
if (function_exists('tbn_live_peer_key_for_iface')) {
  $live_key = tbn_live_peer_key_for_iface($if);
  if ($live_key !== '') {
    tbn_peer_confirm($live_key);
  }
}
tbn_apply_iface($if);
tbn_sync_iface_pages();

// Refresh OpenFabric metrics / iface stanzas after underlay Apply
if (function_exists('tbn_of_apply')) {
  $g = tbn_load_cfg();
  if (($g['openfabric_enable'] ?? 'no') === 'yes') {
    tbn_of_apply();
  }
}