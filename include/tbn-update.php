<?php
/**
 * #include for the global Settings form (#file ThunderboltNet/ThunderboltNet.cfg).
 *
 * Unraid update.php parses #file, runs this include, then saves its own copy
 * plus $_POST unless $save is false. So this include merges the POST itself,
 * writes the cfg, sets $save = false, and applies the *new* settings.
 */
// Unraid update.php includes this on POST only; refuse a direct GET.
if (PHP_SAPI !== 'cli' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  return;
}
require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';
$mesh_php = '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-mesh.php';
if (is_file($mesh_php)) {
  require_once $mesh_php;
}

$save = false;

tbn_locked(function () {
  $cur = tbn_load_cfg();
  $frr_present = false;
  if (function_exists('tbn_of_frr_detect')) {
    $det = tbn_of_frr_detect();
    $frr_present = !empty($det['present']);
  }
  $cfg = (PHP_SAPI === 'cli') ? $cur : tbn_merge_global_post($cur, $_POST, $frr_present);
  if (function_exists('tbn_mesh_ensure_token')) {
    tbn_mesh_ensure_token($cfg);
  }
  tbn_write_global_cfg($cfg);
  $cfg = tbn_load_cfg();

  if (($cfg['load_modules'] ?? 'yes') === 'yes') {
    tbn_load_modules();
  }

  tbn_sync_iface_pages();

  // OpenFabric: write or remove the fragment and reload FRR
  if (function_exists('tbn_of_apply')) {
    tbn_of_apply();
  }

  // Beacon + schedule follow the link-check setting
  if (function_exists('tbn_mesh_enabled')) {
    tbn_mesh_cron_sync($cfg);
    if (tbn_mesh_enabled($cfg)) {
      @tbn_mesh_beacon_ensure($cfg);
    } else {
      @tbn_mesh_beacon_stop();
    }
  }
});

// First poll in the background (peer timeouts must not hold the Apply page)
if (function_exists('tbn_mesh_enabled') && tbn_mesh_enabled(tbn_load_cfg())) {
  @exec('setsid nohup /bin/bash /usr/local/emhttp/plugins/ThunderboltNet/scripts/tbn-mesh-cron --force >/dev/null 2>&1 &');
}
