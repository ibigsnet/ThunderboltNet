<?php
/**
 * Peers tab actions: forget known peers, save/capture peer L3 plans.
 *
 * POST tbn_peer_action (csrf_token required by Unraid local_prepend):
 *   forget          — remove selected keys from peers.json only
 *   save_plan       — write L3 plan fields for tbn_peer_key
 *   capture_plan    — copy current path settings onto selected/live peer(s)
 *   apply_plan      — apply peer plan to live iface for selected/online peer(s)
 *
 * Selection: tbn_peer_keys[] (checkboxes) preferred; tbn_peer_keys_csv /
 * tbn_forget_keys[] / tbn_peer_key still accepted.
 *
 * Does not touch Unraid Interface Rules / network.cfg eth names.
 */
$docroot = $docroot ?? ($_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp');
require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';

$save = false;

$action = strtolower(trim((string)($_POST['tbn_peer_action'] ?? '')));

function tbn_peers_flash($msg) {
  echo htmlspecialchars((string)$msg) . "\n";
}

/**
 * Collect selected peer keys from multi-select checkboxes.
 *
 * @return string[]
 */
function tbn_post_peer_keys() {
  $keys = [];
  $push = static function ($k) use (&$keys) {
    $k = function_exists('tbn_sanitize_peer_key') ? tbn_sanitize_peer_key($k) : trim((string)$k);
    if ($k !== '' && !in_array($k, $keys, true)) {
      $keys[] = $k;
    }
  };
  foreach (['tbn_peer_keys', 'tbn_forget_keys'] as $field) {
    if (!isset($_POST[$field])) {
      continue;
    }
    $raw = $_POST[$field];
    if (is_array($raw)) {
      foreach ($raw as $k) {
        $push($k);
      }
    } else {
      foreach (preg_split('/[\s,]+/', (string)$raw, -1, PREG_SPLIT_NO_EMPTY) as $k) {
        $push($k);
      }
    }
  }
  if (!empty($_POST['tbn_peer_keys_csv'])) {
    foreach (preg_split('/[\s,]+/', (string)$_POST['tbn_peer_keys_csv'], -1, PREG_SPLIT_NO_EMPTY) as $k) {
      $push($k);
    }
  }
  $push($_POST['tbn_peer_key'] ?? '');
  return $keys;
}

/**
 * Live iface for a remembered peer key (prefer current link summaries).
 */
function tbn_live_iface_for_peer_key($key) {
  $key = function_exists('tbn_sanitize_peer_key') ? tbn_sanitize_peer_key($key) : trim((string)$key);
  if ($key === '') {
    return '';
  }
  $peers = tbn_load_peers_memory();
  $if = trim((string)($peers[$key]['last_iface'] ?? ''));
  foreach (tbn_link_summaries() as $L) {
    if (tbn_peer_key_from_link($L, $peers) === $key) {
      $live = trim((string)($L['iface'] ?? ''));
      if ($live !== '' && preg_match('/^thunderbolt\d+$/', $live)) {
        return $live;
      }
    }
  }
  if ($if !== '' && preg_match('/^thunderbolt\d+$/', $if) && is_dir('/sys/class/net/' . $if)) {
    return $if;
  }
  return '';
}

if ($action === 'forget') {
  $keys = tbn_post_peer_keys();
  if (!$keys) {
    tbn_peers_flash('Thunderbolt Net: select at least one peer to forget.');
    return;
  }
  $n = tbn_forget_peers($keys);
  tbn_sync_iface_pages();
  tbn_peers_flash('Thunderbolt Net: forgot ' . $n . ' peer' . ($n === 1 ? '' : 's')
    . ' from Known peers (files on tbn tabs kept).');
  return;
}

if ($action === 'capture_plan') {
  $keys = tbn_post_peer_keys();
  $if_one = trim((string)($_POST['tbn_iface'] ?? ''));
  $n = 0;
  if ($keys) {
    foreach ($keys as $key) {
      $if = tbn_live_iface_for_peer_key($key);
      if ($if !== '') {
        $r = tbn_capture_peer_plan_from_iface($if);
        if (!empty($r['ok'])) {
          $n++;
        }
      }
    }
  } elseif (preg_match('/^thunderbolt\d+$/', $if_one)) {
    $r = tbn_capture_peer_plan_from_iface($if_one);
    if (!empty($r['ok'])) {
      $n++;
    }
  }
  tbn_sync_iface_pages();
  tbn_peers_flash($n ? ('Thunderbolt Net: remembered current on ' . $n . ' peer' . ($n === 1 ? '' : 's'))
    : 'Thunderbolt Net: nothing to remember (need an Online peer).');
  return;
}

if ($action === 'save_plan' || $action === 'apply_plan') {
  $keys = tbn_post_peer_keys();
  if (!$keys) {
    tbn_peers_flash('Thunderbolt Net: select at least one peer.');
    return;
  }

  $has_explicit = isset($_POST['plan_ipaddr']) || isset($_POST['plan_use_dhcp']) || isset($_POST['plan_auto']);
  $n = 0;

  foreach ($keys as $key) {
    if ($action === 'save_plan' || $has_explicit) {
      $plan = [
        'auto' => (strtolower(trim((string)($_POST['plan_auto'] ?? 'yes'))) === 'no') ? 'no' : 'yes',
        'USE_DHCP' => (strtolower(trim((string)($_POST['plan_use_dhcp'] ?? 'no'))) === 'yes') ? 'yes' : 'no',
        'IPADDR' => trim((string)($_POST['plan_ipaddr'] ?? '')),
        'NETMASK' => trim((string)($_POST['plan_netmask'] ?? '24')),
        'GATEWAY' => trim((string)($_POST['plan_gateway'] ?? '')),
        'DEFAULT_ROUTE' => (strtolower(trim((string)($_POST['plan_default_route'] ?? 'no'))) === 'yes') ? 'yes' : 'no',
        'MTU_MODE' => trim((string)($_POST['plan_mtu_mode'] ?? 'default')),
        'MTU' => trim((string)($_POST['plan_mtu'] ?? '1500')),
        'PROTOCOL' => 'ipv4',
      ];
      if ($plan['NETMASK'] === '') {
        $plan['NETMASK'] = '24';
      }
      $listen = null;
      if (isset($_POST['plan_listening'])) {
        $listen = (strtolower(trim((string)$_POST['plan_listening'])) === 'yes') ? 'yes' : 'no';
      }
      tbn_save_peer_plan($key, $plan, $listen);
      $n++;
    }

    if ($action === 'apply_plan') {
      $if = tbn_live_iface_for_peer_key($key);
      if ($if !== '') {
        tbn_apply_peer_plan_to_iface($key, $if);
        $n++;
      }
    }
  }
  tbn_sync_iface_pages();
  tbn_peers_flash('Thunderbolt Net: ' . ($action === 'apply_plan' ? 'applied saved' : 'saved plan')
    . ' for ' . $n . ' peer' . ($n === 1 ? '' : 's') . '.');
  return;
}

tbn_peers_flash('Thunderbolt Net: unknown peer action.');
