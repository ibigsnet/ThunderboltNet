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

// MTU: update.php can miss jumbo (disabled number input; duplicate USE_MTU=no/yes).
// Reconcile from POST and rewrite flash before apply so jumbo survives reboot.
if (strcasecmp((string)$apply, 'Reset') !== 0 && function_exists('tbn_load_iface_cfg')) {
  $cfg = tbn_load_iface_cfg($if);
  $use = $_POST['USE_MTU'] ?? null;
  if (is_array($use)) {
    $use = in_array('yes', $use, true) ? 'yes' : 'no';
  }
  if ($use !== null) {
    $use = (strtolower(trim((string)$use)) === 'yes') ? 'yes' : 'no';
    $cfg['USE_MTU'] = $use;
    if ($use === 'yes') {
      $mtu = trim((string)($_POST['MTU'] ?? ''));
      if ($mtu === '' || (int)$mtu < 68) {
        $mtu = '9000';
      }
      $cfg['MTU'] = $mtu;
      $mode = trim((string)($_POST['MTU_MODE'] ?? ''));
      if (!in_array($mode, ['9000', 'custom'], true)) {
        $mode = ((int)$mtu === 9000) ? '9000' : 'custom';
      }
      $cfg['MTU_MODE'] = $mode;
    } else {
      $cfg['USE_MTU'] = 'no';
      $cfg['MTU_MODE'] = 'default';
      $cfg['MTU'] = '1500';
    }
    if (function_exists('tbn_normalize_mtu_mode')) {
      $cfg['MTU_MODE'] = tbn_normalize_mtu_mode($cfg);
      $cfg['USE_MTU'] = ($cfg['MTU_MODE'] === 'default') ? 'no' : 'yes';
      if ($cfg['MTU_MODE'] === '9000') {
        $cfg['MTU'] = '9000';
      }
    }
    tbn_write_iface_cfg($if, $cfg);
  }
}

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