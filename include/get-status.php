<?php
/**
 * JSON status for ThunderboltNet UI / scripting.
 * Read-only observation; safe over SSH for diagnostics.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$docroot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
require_once "$docroot/webGui/include/Helpers.php";
require_once __DIR__ . '/tbn-lib.php';

echo json_encode(tbn_status(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
