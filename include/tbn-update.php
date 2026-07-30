<?php
/**
 * #include after writing ThunderboltNet.cfg (global options).
 */
require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';

$cfg = tbn_load_cfg();

if (($cfg['load_modules'] ?? 'yes') === 'yes') {
  tbn_load_modules();
}

tbn_sync_iface_pages();
