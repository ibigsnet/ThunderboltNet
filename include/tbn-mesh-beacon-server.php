<?php
/**
 * Mesh export beacon for php -S (no Unraid login / auth_request).
 * Peers poll this instead of /plugins/.../tbn-mesh-export.php (that returns 302).
 *
 * Usage: php -S 0.0.0.0:10807 /path/to/tbn-mesh-beacon-server.php
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (strpos($remote, '::ffff:') === 0) {
  $remote = substr($remote, 7);
}

$lib = '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';
$mesh = '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-mesh.php';
if (!is_file($lib)) {
  $lib = dirname(__FILE__) . '/tbn-lib.php';
  $mesh = dirname(__FILE__) . '/tbn-mesh.php';
}
if (!is_file($lib) || !is_file($mesh)) {
  http_response_code(500);
  echo json_encode(['error' => 'plugin missing']);
  exit;
}
require_once $lib;
require_once $mesh;

$cfg = tbn_load_cfg();
if (!tbn_mesh_enabled($cfg)) {
  http_response_code(403);
  echo json_encode(['error' => 'mesh_export_disabled']);
  exit;
}

$token = trim((string)($cfg['mesh_token'] ?? ''));
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

if (($cfg['mesh_private_only'] ?? 'yes') === 'yes') {
  if ($remote !== '127.0.0.1' && $remote !== '::1' && !tbn_mesh_is_private_ip($remote)) {
    http_response_code(403);
    echo json_encode(['error' => 'private_only', 'remote' => $remote]);
    exit;
  }
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($uri !== '/' && $uri !== '/index.json' && $uri !== '/mesh' && $uri !== '/export') {
  http_response_code(404);
  echo json_encode(['error' => 'not found']);
  exit;
}

echo json_encode(tbn_mesh_snapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
