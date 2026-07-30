<?php
/**
 * JSON status for ThunderboltNet UI / scripting.
 * Read-only observation; safe over SSH for diagnostics.
 */
if (PHP_SAPI !== 'cli') {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
}

$docroot = $_SERVER['DOCUMENT_ROOT'] ?? '';
if ($docroot === '' || !is_dir($docroot)) {
  $docroot = '/usr/local/emhttp';
}
$_SERVER['DOCUMENT_ROOT'] = $docroot;

// Optional: parse_plugin_cfg lives in Unraid Helpers (web or CLI).
$helpers = $docroot . '/webGui/include/Helpers.php';
if (is_file($helpers)) {
  require_once $helpers;
}

require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';

echo json_encode(tbn_status(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo (PHP_SAPI === 'cli') ? "\n" : '';
