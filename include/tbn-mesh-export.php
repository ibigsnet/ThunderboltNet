<?php
/**
 * Mesh snapshot export for peer Unraid Thunderbolt Net hosts.
 * Auth: X-Tbn-Mesh-Token (or ?token=) must match mesh_token when mesh_report=yes.
 * Default: private-IP sources only when mesh_private_only=yes.
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
$helpers = $docroot . '/webGui/include/Helpers.php';
if (is_file($helpers)) {
  require_once $helpers;
}

require_once __DIR__ . '/tbn-lib.php';
require_once __DIR__ . '/tbn-mesh.php';
if (is_file(__DIR__ . '/tbn-openfabric.php')) {
  require_once __DIR__ . '/tbn-openfabric.php';
}

$cfg = tbn_load_cfg();
$want = (($cfg['mesh_report'] ?? 'no') === 'yes');
$token = trim((string)($cfg['mesh_token'] ?? ''));

if (!$want || $token === '') {
  http_response_code(403);
  echo json_encode(['error' => 'mesh_export_disabled', 'message' => 'Fabric reports export is off or token unset']);
  exit;
}

$provided = '';
if (!empty($_SERVER['HTTP_X_TBN_MESH_TOKEN'])) {
  $provided = (string)$_SERVER['HTTP_X_TBN_MESH_TOKEN'];
} elseif (isset($_GET['token'])) {
  $provided = (string)$_GET['token'];
}
if ($provided === '' || !hash_equals($token, $provided)) {
  http_response_code(403);
  echo json_encode(['error' => 'unauthorized']);
  exit;
}

if (($cfg['mesh_private_only'] ?? 'yes') === 'yes' && PHP_SAPI !== 'cli') {
  $remote = $_SERVER['REMOTE_ADDR'] ?? '';
  // Allow localhost for diagnostics
  if ($remote !== '127.0.0.1' && $remote !== '::1' && !tbn_mesh_is_private_ip($remote)) {
    http_response_code(403);
    echo json_encode(['error' => 'private_only', 'message' => 'Export limited to private source IPs']);
    exit;
  }
}

// Simple rate limit: 30/min per IP file
if (PHP_SAPI !== 'cli') {
  $rip = preg_replace('/[^0-9a-fA-F.:]/', '', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
  $rl = '/var/tmp/tbn-mesh-rl-' . md5($rip) . '.json';
  $now = time();
  $bucket = ['t' => $now, 'n' => 0];
  if (is_readable($rl)) {
    $bucket = json_decode((string)@file_get_contents($rl), true) ?: $bucket;
  }
  if (($now - (int)($bucket['t'] ?? 0)) > 60) {
    $bucket = ['t' => $now, 'n' => 0];
  }
  $bucket['n'] = (int)($bucket['n'] ?? 0) + 1;
  @file_put_contents($rl, json_encode($bucket));
  if ($bucket['n'] > 30) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limited']);
    exit;
  }
}

$snap = tbn_mesh_snapshot();
echo json_encode($snap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (PHP_SAPI === 'cli') {
  echo "\n";
}
