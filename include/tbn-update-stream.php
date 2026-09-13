<?php
/**
 * Stream tab actions (POST + csrf via Unraid local_prepend).
 *
 * tbn_stream_action:
 *   create   path + name + hopids
 *   destroy  path + name (or selected keys path/name)
 *   apply    recreate saved streams
 *   copy     send|receive + device + file (optional raw / overwrite)
 *
 * $save = false — do not write ThunderboltNet.cfg from this include.
 */
$docroot = $docroot ?? ($_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp');
require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';
if (is_file('/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-usb4stream.php')) {
  require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-usb4stream.php';
}

$save = false;

if (PHP_SAPI !== 'cli' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo "Stream actions require POST.\n";
  return;
}

$action = strtolower(trim((string)($_POST['tbn_stream_action'] ?? '')));

function tbn_stream_flash($msg) {
  echo htmlspecialchars((string)$msg) . "\n";
}

function tbn_stream_post_keys() {
  $keys = [];
  $raw = $_POST['tbn_stream_keys'] ?? [];
  if (!is_array($raw)) {
    $raw = preg_split('/[\s,]+/', (string)$raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
  }
  foreach ($raw as $k) {
    $k = trim((string)$k);
    if ($k === '' || strpos($k, '/') === false) {
      continue;
    }
    [$p, $n] = explode('/', $k, 2);
    if (function_exists('tbn_stream_path_ok') && tbn_stream_path_ok($p)
        && function_exists('tbn_stream_name_ok') && tbn_stream_name_ok($n)) {
      $keys[] = ['path' => $p, 'name' => $n];
    }
  }
  return $keys;
}

if ($action === '') {
  tbn_stream_flash('No stream action.');
  return;
}

if ($action === 'create') {
  $path = trim((string)($_POST['tbn_stream_path'] ?? ''));
  $name = trim((string)($_POST['tbn_stream_name'] ?? ''));
  $in = (int)($_POST['tbn_stream_in_hopid'] ?? -1);
  $out = (int)($_POST['tbn_stream_out_hopid'] ?? -1);
  $r = tbn_stream_create($path, $name, $in, $out, true);
  if (!empty($r['ok'])) {
    $dev = $r['stream']['node'] ?? $r['stream']['device'] ?? '';
    tbn_stream_flash('Created ' . $path . '/' . $name
      . ($dev !== '' ? (' → ' . $dev) : '')
      . ' in_hopid=' . ($r['in_hopid'] ?? '')
      . ' out_hopid=' . ($r['out_hopid'] ?? ''));
  } else {
    tbn_stream_flash('Create failed: ' . ($r['error'] ?? 'unknown'));
  }
  return;
}

if ($action === 'destroy') {
  $keys = tbn_stream_post_keys();
  $path = trim((string)($_POST['tbn_stream_path'] ?? ''));
  $name = trim((string)($_POST['tbn_stream_name'] ?? ''));
  if (!$keys && $path !== '' && $name !== '') {
    $keys[] = ['path' => $path, 'name' => $name];
  }
  if (!$keys) {
    tbn_stream_flash('Nothing selected to tear down.');
    return;
  }
  foreach ($keys as $k) {
    $r = tbn_stream_destroy($k['path'], $k['name'], true);
    tbn_stream_flash((!empty($r['ok']) ? 'Removed ' : 'Failed ')
      . $k['path'] . '/' . $k['name']
      . (empty($r['ok']) ? (': ' . ($r['error'] ?? '')) : ''));
  }
  return;
}

if ($action === 'apply') {
  $r = tbn_stream_apply(true);
  if (!empty($r['ok'])) {
    tbn_stream_flash('Applied saved streams'
      . (!empty($r['applied']) ? (': ' . implode(', ', $r['applied'])) : ''));
  } else {
    tbn_stream_flash('Apply failed: ' . ($r['error'] ?? implode('; ', $r['errors'] ?? [])));
  }
  return;
}

if ($action === 'copy') {
  @set_time_limit(0);
  $dirn = strtolower(trim((string)($_POST['tbn_stream_copy_dir'] ?? '')));
  $dev = trim((string)($_POST['tbn_stream_copy_dev'] ?? ''));
  $file = trim((string)($_POST['tbn_stream_copy_file'] ?? ''));
  $raw = ($_POST['tbn_stream_copy_raw'] ?? '') === 'yes';
  $overwrite = ($_POST['tbn_stream_copy_overwrite'] ?? '') === 'yes';
  $plan = tbn_stream_copy_cmd($dirn, $dev, $file, $raw, $overwrite);
  if (empty($plan['ok'])) {
    tbn_stream_flash('Copy not started: ' . ($plan['error'] ?? 'unknown'));
    return;
  }
  tbn_stream_flash('Running: ' . $plan['cmd']);
  $rc = 0;
  passthru($plan['cmd'] . ' 2>&1', $rc);
  tbn_stream_flash($rc === 0 ? 'Copy finished.' : ('Copy failed (exit ' . $rc . ').'));
  return;
}

tbn_stream_flash('Unknown stream action.');
