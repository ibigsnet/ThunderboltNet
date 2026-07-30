<?php
/**
 * Overview forms: set per-peer listening, or harden-all (services No everywhere).
 *
 * POST:
 *   tbn_listen_action = set | harden_all
 *   tbn_iface         = thunderboltN (for set)
 *   tbn_peer_key      = peers.json key (optional but preferred)
 *   INCLUDE_LISTENING = yes | no (for set)
 *
 * Unraid update.php merges POST into #file after this include. We set $save=false
 * and write only via tbn_write_iface_cfg so listen forms never pollute iface cfgs
 * with tbn_listen_action / tbn_peer_key keys.
 */
$docroot = $docroot ?? ($_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp');
require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';

// Prevent update.php from merging listen-form POST junk into the target cfg
$save = false;

$action = strtolower(trim((string)($_POST['tbn_listen_action'] ?? '')));
$apply = (string)($_POST['#apply'] ?? '');

if (
  $action === 'harden_all'
  || stripos($apply, 'Harden') !== false
) {
  tbn_listening_harden_all();
  tbn_sync_iface_pages();
  return;
}

$if = trim((string)($_POST['tbn_iface'] ?? ''));
$key = trim((string)($_POST['tbn_peer_key'] ?? ''));
$want = strtolower(trim((string)($_POST['INCLUDE_LISTENING'] ?? 'no')));
$want = ($want === 'yes') ? 'yes' : 'no';

if ($key === '' && $if !== '' && preg_match('/^thunderbolt\d+$/', $if)) {
  $key = 'iface:' . $if;
}
if ($key === '') {
  return;
}

// Offline peer: remember preference only. Online: update network-extra + iface cfg.
$live_if = '';
if ($if !== '' && preg_match('/^thunderbolt\d+$/', $if) && is_dir('/sys/class/net/' . $if)) {
  $live_if = $if;
}

tbn_set_peer_listening_pref($key, $want, $live_if);
tbn_sync_iface_pages();
