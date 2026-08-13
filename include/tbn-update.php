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

// Auto-generate mesh token when enabling fabric reports
if (function_exists('tbn_mesh_ensure_token')) {
  $live = tbn_load_cfg();
  tbn_mesh_ensure_token($live);
  if (trim((string)($live['mesh_token'] ?? '')) !== '' && function_exists('tbn_write_global_cfg')) {
    // If form left token empty but report=yes, persist generated token
    $form_tok = trim((string)($cfg['mesh_token'] ?? ''));
    if (($live['mesh_report'] ?? 'no') === 'yes' && $form_tok === '') {
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

// One mesh poll after settings save if enabled
if (function_exists('tbn_mesh_maybe_poll') && function_exists('tbn_mesh_enabled') && tbn_mesh_enabled(tbn_load_cfg())) {
  @tbn_mesh_maybe_poll(tbn_load_cfg(), true);
}