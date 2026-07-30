<?php
/**
 * Overview forms: set per-peer listening, or harden-all (services No everywhere).
 *
 * POST:
 *   tbn_listen_action = set | harden_all
 *   tbn_iface         = thunderboltN (for set)
 *   tbn_peer_key      = peers.json key (optional but preferred)
 *   INCLUDE_LISTENING = yes | no (for set)
 */
$docroot = $docroot ?? ($_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp');
require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';

$action = strtolower(trim((string)($_POST['tbn_listen_action'] ?? $_POST['#arg'][1] ?? 'set')));

if ($action === 'harden_all' || strcasecmp((string)($_POST['#apply'] ?? ''), 'Harden all TB links') === 0) {
  tbn_listening_harden_all();
  tbn_sync_iface_pages();
  return;
}

$if = trim((string)($_POST['tbn_iface'] ?? ''));
$key = trim((string)($_POST['tbn_peer_key'] ?? ''));
$want = strtolower(trim((string)($_POST['INCLUDE_LISTENING'] ?? 'no')));
$want = ($want === 'yes') ? 'yes' : 'no';

if ($if === '' || !preg_match('/^thunderbolt\d+$/', $if)) {
  return;
}
if ($key === '') {
  $key = 'iface:' . $if;
}

tbn_set_peer_listening_pref($key, $want, $if);
tbn_sync_iface_pages();
