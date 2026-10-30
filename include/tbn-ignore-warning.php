<?php
/**
 * #include: record a warning key in ignore_warnings (global plugin cfg).
 */
// Unraid update.php includes this on POST only; refuse a direct GET.
if (PHP_SAPI !== 'cli' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  return;
}
require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';
// One writer at a time (boot / udev reapply can run at the same moment)
if (tbn_lock_acquire(60)) {
  register_shutdown_function('tbn_lock_release');
}

$save = false;

$key = trim((string)($_POST['tbn_ignore_key'] ?? ''));
if ($key !== '') {
  tbn_ignore_warning($key);
}
