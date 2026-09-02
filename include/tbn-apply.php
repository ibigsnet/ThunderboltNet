<?php
/**
 * Apply ThunderboltNet actions (POST or CLI ?action=).
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

require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$out = ['ok' => false, 'action' => $action];

// Read-only actions may use GET; anything that changes state must arrive as a
// POST (Unraid validates csrf_token on the POST channel). CLI is unaffected.
$read_only = ['status', 'openfabric_status', 'openfabric_preview'];
if (PHP_SAPI !== 'cli' && $action !== '' && !in_array($action, $read_only, true)
    && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  $out['error'] = 'action requires POST';
  echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  exit;
}

switch ($action) {
  case 'load_modules':
    $out['modules'] = tbn_load_modules();
    $out['ok'] = true;
    break;

  case 'include_on':
    $out['ok'] = tbn_apply_include_listening(true);
    $out['include_interfaces'] = tbn_read_include_interfaces();
    break;

  case 'include_off':
    $out['ok'] = tbn_apply_include_listening(false);
    $out['include_interfaces'] = tbn_read_include_interfaces();
    break;

  case 'apply_ip':
    $out = array_merge($out, tbn_apply_static_ip());
    break;

  case 'status':
    $out = tbn_status();
    $out['ok'] = true;
    break;

  case 'openfabric_status':
    $out['openfabric'] = function_exists('tbn_of_status') ? tbn_of_status() : [];
    $out['ok'] = true;
    break;

  case 'openfabric_apply':
    if (function_exists('tbn_of_apply')) {
      $out = array_merge($out, tbn_of_apply());
    } else {
      $out['error'] = 'openfabric module missing';
    }
    break;

  case 'openfabric_preview':
    if (function_exists('tbn_of_generate_conf')) {
      $out['conf'] = tbn_of_generate_conf();
      $out['ok'] = true;
    } else {
      $out['error'] = 'openfabric module missing';
    }
    break;

  default:
    $out['error'] = 'unknown action';
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
