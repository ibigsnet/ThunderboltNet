<?php
/**
 * #include: record a warning key in ignore_warnings (global plugin cfg).
 */
require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';

$key = trim((string)($_POST['tbn_ignore_key'] ?? ''));
if ($key !== '') {
  tbn_ignore_warning($key);
}
