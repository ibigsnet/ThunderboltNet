<?php
/**
 * Apply ThunderboltNet actions (POST).
 * Develop locally; ship via plugin install from GitHub.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$docroot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
require_once "$docroot/webGui/include/Helpers.php";
require_once __DIR__ . '/tbn-lib.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$out = ['ok' => false, 'action' => $action];

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

  default:
    $out['error'] = 'unknown action';
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
