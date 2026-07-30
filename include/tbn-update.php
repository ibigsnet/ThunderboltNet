<?php
/**
 * #include target from Unraid update.php after writing ThunderboltNet.cfg
 */
$docroot = $docroot ?? ($_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp');
require_once "$docroot/plugins/ThunderboltNet/include/tbn-lib.php";

$cfg = tbn_load_cfg();

if (($cfg['load_modules'] ?? 'yes') === 'yes') {
  tbn_load_modules();
}

// Always try to admin-up configured ifaces when present (helps carrier without full IP manage)
tbn_bring_up_ifaces();

if (($cfg['include_listening'] ?? 'no') === 'yes') {
  tbn_apply_include_listening(true);
} else {
  tbn_apply_include_listening(false);
}

if (($cfg['manage_ip'] ?? 'no') === 'yes') {
  tbn_apply_static_ip();
}
