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

$cfg = tbn_load_cfg();
if (PHP_SAPI === 'cli' || ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  // CLI or POST (csrf_token checked by Unraid): poll now.
  $force = true;
  if (PHP_SAPI !== 'cli') {
    $force = (string)($_POST['force'] ?? '1') !== '0';
  }
  $res = tbn_mesh_maybe_poll($cfg, $force);
  if ($res === null) {
    $res = ['enabled' => false, 'error' => 'mesh disabled or not due', 'polled' => 0, 'ok' => 0];
  }
} else {
  // GET is read-only: last poll result from flash, no peer fetch.
  $res = ['enabled' => tbn_mesh_enabled($cfg), 'cached' => true, 'polled' => 0, 'ok' => 0];
  $last = @json_decode((string)@file_get_contents(tbn_cfg_dir() . '/mesh_last_poll.json'), true);
  if (is_array($last) && is_array($last['result'] ?? null)) {
    $res = $last['result'] + ['cached' => true, 'cached_at' => (string)($last['at'] ?? '')];
  }
}
echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (PHP_SAPI === 'cli') {
  echo "\n";
}
