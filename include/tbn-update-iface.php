<?php
/**
 * #include after update.php writes ThunderboltNet/ifaces/<iface>.cfg
 * #arg[1] or POST tbn_iface = kernel iface name (thunderbolt0).
 */
$docroot = $docroot ?? ($_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp');
require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';

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

// Reset button: Unraid may pass name="#apply" value="Reset"
$apply = $_POST['#apply'] ?? '';
if (strcasecmp((string)$apply, 'Reset') === 0) {
  tbn_reset_iface_cfg($if);
  // reload defaults into live cfg file already written — overwrite with defaults
  tbn_write_iface_cfg($if, tbn_iface_defaults($if));
}

// Ensure iface dir exists
@mkdir(tbn_iface_cfg_dir(), 0755, true);

// Global modules from main cfg
$g = tbn_load_cfg();
if (($g['load_modules'] ?? 'yes') === 'yes') {
  tbn_load_modules();
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