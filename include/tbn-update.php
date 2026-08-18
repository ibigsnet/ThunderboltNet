<?php
/**
 * #include after writing ThunderboltNet.cfg (global options).
 */
require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';
$mesh_php = '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-mesh.php';
if (is_file($mesh_php)) {
  require_once $mesh_php;
}

$cfg = tbn_load_cfg();

// Remember explicit OpenFabric No so a later FRR install does not force it back on
if (function_exists('tbn_write_global_cfg')) {
  if (($cfg['openfabric_enable'] ?? 'no') === 'no') {
    $cfg['openfabric_user_off'] = 'yes';
  } else {
    $cfg['openfabric_user_off'] = 'no';
  }
  tbn_write_global_cfg($cfg);
  $cfg = tbn_load_cfg();
}

// Auto-generate mesh token when enabling peer link check
if (function_exists('tbn_mesh_ensure_token')) {
  $live = tbn_load_cfg();
  tbn_mesh_ensure_token($live);
  if (trim((string)($live['mesh_token'] ?? '')) !== '' && function_exists('tbn_write_global_cfg')) {
    $form_tok = trim((string)($cfg['mesh_token'] ?? ''));
    if (($live['mesh_report'] ?? 'yes') === 'yes' && $form_tok === '') {
      tbn_write_global_cfg($live);
    }
  }
}

if (($cfg['load_modules'] ?? 'yes') === 'yes') {
  tbn_load_modules();
}

tbn_sync_iface_pages();

// OpenFabric: regenerate conf; start/reload FRR when available
if (function_exists('tbn_of_apply')) {
  tbn_of_apply();
}

// Mesh beacon (auth-free export) + one poll after settings save if enabled
$mesh_cfg = tbn_load_cfg();
if (function_exists('tbn_mesh_beacon_ensure')) {
  if (function_exists('tbn_mesh_enabled') && tbn_mesh_enabled($mesh_cfg)) {
    @tbn_mesh_beacon_ensure($mesh_cfg);
  } else {
    @tbn_mesh_beacon_stop();
  }
}
if (function_exists('tbn_mesh_maybe_poll') && function_exists('tbn_mesh_enabled') && tbn_mesh_enabled($mesh_cfg)) {
  @tbn_mesh_maybe_poll($mesh_cfg, true);
}