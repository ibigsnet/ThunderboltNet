<?php
/**
 * #include target from Unraid update.php after writing ThunderboltNet.cfg
 * Develop in ~/projects/ThunderboltNet; ship via GitHub plugin install only.
 */
$docroot = $docroot ?? ($_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp');
require_once "$docroot/plugins/ThunderboltNet/include/tbn-lib.php";

$cfg = tbn_load_cfg();

if (($cfg['load_modules'] ?? 'yes') === 'yes') {
  tbn_load_modules();
}

if (($cfg['include_listening'] ?? 'no') === 'yes') {
  tbn_apply_include_listening(true);
} else {
  // When user turns off, remove our ifaces from include list
  tbn_apply_include_listening(false);
}

if (($cfg['manage_ip'] ?? 'no') === 'yes') {
  tbn_apply_static_ip();
}
