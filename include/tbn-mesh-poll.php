<?php
/**
 * Trigger mesh poll (UI button or CLI).
 * Browser: requires Unraid session (normal page). CLI: always allowed.
 */
$docroot = $_SERVER['DOCUMENT_ROOT'] ?? '';
if ($docroot === '' || !is_dir($docroot)) {
  $docroot = '/usr/local/emhttp';
}
$_SERVER['DOCUMENT_ROOT'] = $docroot;
$helpers = $docroot . '/webGui/include/Helpers.php';
if (is_file($helpers)) {
  require_once $helpers;
}

require_once __DIR__ . '/tbn-lib.php';
require_once __DIR__ . '/tbn-mesh.php';
if (is_file(__DIR__ . '/tbn-openfabric.php')) {
  require_once __DIR__ . '/tbn-openfabric.php';
}

if (PHP_SAPI !== 'cli') {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
}

$force = true;
if (PHP_SAPI !== 'cli') {
  // A forced poll contacts peers and rewrites peers.json; only allow it on a
  // POST (csrf-checked by Unraid). GET polls only when the interval is due.
  $force = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')
    && (string)($_POST['force'] ?? '1') !== '0';
}

$cfg = tbn_load_cfg();
$res = tbn_mesh_maybe_poll($cfg, $force);
if ($res === null) {
  $res = ['enabled' => false, 'error' => 'mesh disabled or not due', 'polled' => 0, 'ok' => 0];
}
echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (PHP_SAPI === 'cli') {
  echo "\n";
}
