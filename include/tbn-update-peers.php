<?php
/**
 * Peers tab actions: forget known peers, save/capture peer L3 plans.
 *
 * POST tbn_peer_action:
 *   forget          — remove selected keys from peers.json only
 *   save_plan       — write L3 plan fields for tbn_peer_key
 *   capture_plan    — copy current path settings onto selected/live peer(s)
 *   apply_plan      — apply peer plan to live iface for selected/online peer(s)
 *
 * Selection: tbn_peer_keys[] (checkboxes) preferred; legacy tbn_forget_keys[] /
 * tbn_peer_key / tbn_iface still accepted.
 *
 * Does not touch Unraid Interface Rules / network.cfg eth names.
 */
$docroot = $docroot ?? ($_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp');
require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';

$action = strtolower(trim((string)($_POST['tbn_peer_action'] ?? '')));

/**
 * Collect selected peer keys from multi-select checkboxes.
 *
 * @return string[]
 */
function tbn_post_peer_keys() {
  $keys = [];
  foreach (['tbn_peer_keys', 'tbn_forget_keys'] as $field) {
    if (empty($_POST[$field]) || !is_array($_POST[$field])) {
      continue;
    }
    foreach ($_POST[$field] as $k) {
      $k = trim((string)$k);
      if ($k !== '' && !in_array($k, $keys, true)) {
        $keys[] = $k;
      }
    }
  }
  $one = trim((string)($_POST['tbn_peer_key'] ?? ''));
  if ($one !== '' && !in_array($one, $keys, true)) {
    $keys[] = $one;
  }
  return $keys;
}

/**
 * Live iface for a remembered peer key (prefer current link summaries).
 */
function tbn_live_iface_for_peer_key($key) {
  $key = trim((string)$key);
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
  tbn_forget_peers(tbn_post_peer_keys());
  tbn_sync_iface_pages();
  return;
}

if ($action === 'capture_plan') {
  $keys = tbn_post_peer_keys();
  $if_one = trim((string)($_POST['tbn_iface'] ?? ''));
  if ($keys) {
    foreach ($keys as $key) {
      $if = tbn_live_iface_for_peer_key($key);
      if ($if !== '') {
        tbn_capture_peer_plan_from_iface($if);
      }
    }
  } elseif (preg_match('/^thunderbolt\d+$/', $if_one)) {
    tbn_capture_peer_plan_from_iface($if_one);
  }
  tbn_sync_iface_pages();
  return;
}

if ($action === 'save_plan' || $action === 'apply_plan') {
  $keys = tbn_post_peer_keys();
  // Legacy single-key path with explicit plan fields in POST (no selection)
  if (!$keys) {
    $one = trim((string)($_POST['tbn_peer_key'] ?? ''));
    if ($one !== '') {
      $keys = [$one];
    }
  }
  if (!$keys) {
    return;
  }

  $has_explicit = isset($_POST['plan_ipaddr']) || isset($_POST['plan_use_dhcp']) || isset($_POST['plan_auto']);

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
    }

    if ($action === 'apply_plan') {
      // Apply stored plan (not re-save unless explicit fields posted)
      $if = tbn_live_iface_for_peer_key($key);
      if ($if !== '') {
        tbn_apply_peer_plan_to_iface($key, $if);
      }
    }
  }
  tbn_sync_iface_pages();
  return;
}
