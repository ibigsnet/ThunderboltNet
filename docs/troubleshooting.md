# Troubleshooting


## Contents

- [Slow rsync / SMB despite high trained Gb/s](#slow-rsync-smb-despite-high-trained-gbs)
- [Reseating the cable (why it matters)](#reseating-the-cable-why-it-matters)
- [Multi-cable situations](#multi-cable-situations)
- [No Thunderbolt hardware detected](#no-thunderbolt-hardware-detected)
- [Interface never appears (`thunderbolt0` missing)](#interface-never-appears-thunderbolt0-missing)
- [Link trains but no ping](#link-trains-but-no-ping)
- [Online, IP and rates — but no ping](#online-ip-and-rates-but-no-ping)
- [Static IP missing after reboot or unplug/replug](#static-ip-missing-after-reboot-or-unplugreplug)
- [Static IP plus 169.254.x.x on the same iface](#static-ip-plus-169254xx-on-the-same-iface)
- [Two Known peers rows (one blank Offline)](#two-known-peers-rows-one-blank-offline)
- [Wrong peer got the old tbn0 address after cable swaps](#wrong-peer-got-the-old-tbn0-address-after-cable-swaps)
- [Single-lane / 20 Gb/s · 1-lane on a dual-capable host](#single-lane-20-gbs-1-lane-on-a-dual-capable-host)
- [Two cables, still one interface (or worse)](#two-cables-still-one-interface-or-worse)
- [One-way traffic / flaky after reboot](#one-way-traffic-flaky-after-reboot)
- [Controller probe fails / no Thunderbolt devices after reboot](#controller-probe-fails--no-thunderbolt-devices-after-reboot)
- [Steam / LAN transfers use Wi‑Fi not Thunderbolt](#steam--lan-transfers-use-wifi-not-thunderbolt)
- [Do not](#do-not)
- [OpenFabric / FRR (multi-hop)](#openfabric-frr-multi-hop)
- [Peer-specific notes](#peer-specific-notes)
- [Safe Mode / no plugins (manual TB recovery)](#safe-mode--no-plugins-manual-tb-recovery)

## Slow rsync / SMB despite high trained Gb/s

1. Check overview **Local iface state** — if you still see `MTU 1500 (kernel default)`, you are paying full Ethernet-sized packet rates on a multi‑20&nbsp;G path.  
2. Set **Desired MTU → 9000** on the tbn tab **and** the same MTU on the peer (Unraid cannot set the peer’s NIC).  
3. See [mtu-and-throughput.md](mtu-and-throughput.md) for PPS tables and peer commands.  
4. Also verify storage (array parity, single-disk limits) and CPU — MTU is not the only bottleneck.

---

## Reseating the cable (why it matters)

Software Apply (modules, E2E, static IP) does **not** always force a full Thunderbolt domain re-train. The fabric often keeps the previous xdomain / service state until the **physical path** drops.

**Reseating** (unplug both ends of that cable, wait a few seconds, plug back in) is valuable because it:

- Forces link training again (rate, lanes, authorization)  
- Recreates or restarts the host-to-host network service that becomes `thunderboltN`  
- Clears half-up states (“iface exists but no ping”, stuck login, one-way traffic) after driver option changes  
- Separates **cable/port** faults from **IP/config** faults — if reseat with a known-good cable still fails, look at software; if a different cable works, it was path/cable  

### Good habit after changing E2E or modules

1. Confirm the module parameter actually changed (`/sys/module/thunderbolt_net/parameters/e2e`).  
2. Reseat **one** cable (see multi-cable below).  
3. Then re-check addresses and ping.

Reseat is cheap; NHI unbind is not. Prefer reseat over “reset the controller” tricks.

---

## Multi-cable situations

| Setup | What often happens |
|-------|--------------------|
| **One** cable, two hosts | One peer path, one `thunderbolt0` (each side) — simplest to debug |
| **Two** cables to **two different** peers | Often `thunderbolt0` + `thunderbolt1` |
| **Two** cables to the **same** peer | Frequently still **one** network path (or a confused domain) — not automatic dual netdevs / 2× bandwidth |

### Highly recommended recovery steps (wedged domain / dual-cable mess)

Especially after dual-cable experiments or a half-connected second port:

1. Unplug **every** Thunderbolt / USB4 host cable from **both** machines (all ports).  
2. Wait several seconds (10+ is fine).  
3. Plug **one** known-good cable only (one port pair).  
4. Wait for a single peer + `thunderbolt0`; set IPs; ping.  
5. Only then try a second cable/peer if needed.

Leaving a second cable half-connected while “testing” often keeps the fabric in a bad state so reseating **one** cable is not enough — clear **all** first.

More topology detail: [links-and-topology.md](links-and-topology.md).

---

## Safe Mode / no plugins (manual TB recovery)

**Safe Mode** and **GUI Safe Mode** do **not** load community plugins (including Thunderbolt Net). Array **Maintenance** mode is different (plugin can still load on a normal boot).

For console recovery when plugins are off:

1. Unplug **all** TB/USB4 host cables; wait; plug **one** cable only (same multi-cable steps above).  
2. `modprobe thunderbolt_net`, bring `thunderbolt0` up, set a static `/24` (e.g. `10.255.0.2` / peer `10.255.0.1`).  
3. **Do not** add a default route via Thunderbolt unless you mean to.  
4. Prefer **no** persistent “always run without plugins” flash automation; manual is enough for rare recovery.

On the console: `find /boot -iname '*Thunderbolt*'` then `cat …/ThunderboltNet-RECOVERY.txt`.

Full command list and rationale: **[safe-mode-recovery.md](safe-mode-recovery.md)**.
While plugins work: WebUI **Thunderbolt → Recovery** shows the same path.

---

## Dashboard clock missing or frozen

Often **not** NTP. Thunderbolt Net’s Dashboard port-list patch rewrites dynamix **`nchan/update_3`**, which also publishes **date/time**. A past bug dropped that file’s execute bit and stopped the worker.

| Check | Action |
|-------|--------|
| `tbn-dashboard-ports status` | Expect `update_3` **patched**, **mode=755**, **worker: running** |
| Quick heal | `bash /usr/local/emhttp/plugins/ThunderboltNet/scripts/tbn-dashboard-ports heal` |
| Plugin version | **≥ 2026.08.16af** (fix), **≥ 16ag** (status/heal) |

Full write-up: [dashboard-ports-and-clock.md](dashboard-ports-and-clock.md).

## No Thunderbolt hardware detected

- Board/CPU may lack a host controller, or it is disabled in BIOS.  
- Controller bound to **vfio-pci** — check PCI panel; return NHI to host driver if you want host networking.  
- Copy diagnostics from the empty-state page when filing an issue.

## Interface never appears (`thunderbolt0` missing)

- Peer not connected or not authorizing the link (security mode).  
- Cable is charge-only / not Thunderbolt-capable.  
- Peer OS has no Thunderbolt networking stack (common on some Windows SKUs).  
- Modules not loaded — Driver options **Load modules = Yes**, Apply.  
- Multi-cable mess — use the recovery steps above (all cables out → one cable in).  
- Still dead after that — try another port pair and another cable before chasing software.

## Link trains but no ping

1. Confirm addresses same subnet, unique hosts ([addressing.md](addressing.md)).  
2. `ip link` shows UP, carrier 1.  
3. Peer firewall (Windows especially).  
4. Unraid E2E **No**; **reseat** once; retest ([driver-options.md](driver-options.md)).  
5. Keep default route **No** so you aren’t blackholing other traffic while testing.  
6. If the iface is UP but has **no** IPv4, see [Static IP missing after reboot or unplug/replug](#static-ip-missing-after-reboot-or-unplugreplug).  
7. If Current IP is present and Peers still looks “fine,” see [Online, IP and rates — but no ping](#online-ip-and-rates-but-no-ping).

## Online, IP and rates — but no ping

UI can show **Online**, a **Current** address, and trained rates while `ping`/ARP still fail.

| Check | What to do |
|-------|------------|
| Plugin | Prefer **≥ 2026.08.17ba** (E2E persisted to flash + startup reload) |
| Live E2E | `cat /sys/module/thunderbolt_net/parameters/e2e` — expect `N`/`0` when Settings → E2E is **No** |
| Flash conf | `/boot/config/modprobe.d/thunderbolt_net.conf` should contain `options thunderbolt_net e2e=0` |
| Peers cue | **No carrier** / **No reply** under Current means underlay is silent — not “all clear” |
| After fixing e2e | Soft reboot or reseat once so both ends re-train; retest ping both ways |

Older builds only wrote E2E into RAM `/etc/modprobe.d/`; reboot reloaded the kernel default (**e2e on**). Addresses still came back; the path stayed flaky. Design notes: [peers-and-plans.md](peers-and-plans.md#4-path-looks-healthy-but-no-ping-after-reboot).

## Static IP missing after reboot or unplug/replug

Classic field report (Unraid forum): path trains, `thunderbolt0` exists, **no** (or wrong) static address until you re-open the plugin and Apply.

| Check | What to do |
|-------|------------|
| Plugin version | Prefer **≥ 2026.08.15ak** (reapply + dhcpcd kill), **≥ 2026.08.16ad** (Saved addresses), **≥ 2026.08.16ae** (startup + array started), **≥ 2026.08.17ba** (E2E flash persist) |
| Flash plan | `ifaces/thunderboltN.cfg` or Peers → **Saved** for that UUID |
| udev | `/etc/udev/rules.d/99-thunderboltnet-net.rules` after **startup** or install (RAM root is refilled each boot) |
| Array state | Reapply also runs at plugin **startup** (before array Online). Array **started** (Normal/Maintenance) runs a second pass |
| Logs | `grep -E 'tbn-net-reapply|event/startup|ThunderboltNet' /var/log/syslog` after boot/plug |
| First setup | **tbn Apply** while linked fills path cfg **and** Saved automatically — **Remember current** is optional afterward |

**Why:** host-net netdevs are recreated on link; Unraid eth `network.cfg` does not own them. The plugin re-applies flash plans on array start and netdev add. Saved addresses fix the case where **name** reapply alone is wrong after renumber — [peers-and-plans.md](peers-and-plans.md#why-this-design-field-findings).

## Static IP plus 169.254.x.x on the same iface

| Symptom | Cause | Fix |
|---------|--------|-----|
| `ip addr` shows your static **and** a `169.254.…` | Leftover **dhcpcd** (or dhclient) still managing the iface after switching to Static | Apply Static again on a current plugin (stops DHCP clients first); or `dhcpcd -k thunderbolt0` then re-Apply |

Do not leave mixed static + link-local if you expect clean peer-local routing. Design notes: [peers-and-plans.md](peers-and-plans.md#1-address-disappeared-after-reboot-or-unplug-forum-reports).

## Two Known peers rows (one blank offline)

| Symptom | Cause | Fix |
|---------|--------|-----|
| Online named peer + Offline **—** same tbn/IP | Hotplug briefly had no fabric UUID → ghost key `iface:thunderboltN` | Update to **≥ 2026.08.16ac** (dedupe); open Peers once, or **Forget** the blank row |

[peers-and-plans.md](peers-and-plans.md#3-two-known-peers-rows-after-unplugreplug).

## Wrong peer got the old tbn0 address after cable swaps

| Symptom | Cause | Fix |
|---------|--------|-----|
| After multi-peer or re-order, the “wrong” machine has the IP you saved for tbn0 | Path-slot cfg is by **name**; kernel renumbered who sits on `thunderbolt0` | **tbn Apply** while that peer is linked (fills Saved); confirm Peers → Saved; unplug/replug |

Saved is preferred over path-slot cfg when the live path has a known UUID. [peers-and-plans.md](peers-and-plans.md#2-same-host-wrong-or-empty-ip-after-path-renumber-multi-peer).

## Single-lane / 20 Gb/s · 1-lane on a dual-capable host

- **Common** for Thunderbolt 4/USB4 **host-to-host under Linux** (firmware ICM). Not a failed install.  
- Expect roughly **~10–15&nbsp;Gbit/s** TCP; jumbo MTU helps CPU, not dual-lane.  
- Cable/port can still matter for some pairs, but a short certified Thunderbolt 4 cable often stays 1-lane.  
- Details: [standards-and-speeds.md](standards-and-speeds.md).

## Two cables, still one interface (or worse)

Expected often for the **same** two hosts — still one XDomain / one `thunderboltN`. Bonding usually fails (`set_mac` −95, flaky MII). See [links-and-topology.md](links-and-topology.md#dual-cable-between-the-same-pair-of-pcs).

### Wedged after dual-cable experiments

Software cleanup alone may not fix NO-CARRIER / missing peer:

1. Unplug **all** Thunderbolt/USB4 host cables from **both ends on both machines**.  
2. Wait until the fabric is empty.  
3. Plug back **exactly one** cable (Thunderbolt ports only).  
4. Confirm peer + netdev; re-apply IP / MTU / services.

## One-way traffic / flaky after reboot

- Same checks as [Online, IP and rates — but no ping](#online-ip-and-rates-but-no-ping) (E2E live bit + flash `modprobe.d`).  
- Reseat cable after the parameter is correct.  
- Peer NetworkManager profiles “never default” / wrong metric.

## Controller probe fails / no Thunderbolt devices after reboot

| Symptom | What to do |
|---------|------------|
| `dmesg` shows Thunderbolt controller **probe failed** / timeout; no `/sys/bus/thunderbolt/devices/0-0` | Soft reboot may not clear a wedged host controller |
| WMI `force_power` or PCI rescan did not help | **Full power-off** the machine (shutdown until fans/lights are out), wait ~30s, power on — not only “Restart” |
| Still dead | BIOS Thunderbolt enabled; NHI not bound to vfio; try another cable/port; see [No Thunderbolt hardware detected](#no-thunderbolt-hardware-detected) |

Do **not** unbind the Thunderbolt **NHI** as a reset (can make this worse). Prefer power-off cycle + cable reseat.

## Steam / LAN transfers use Wi‑Fi not Thunderbolt

| Symptom | Cause | Fix |
|---------|--------|-----|
| Steam Local Network Game Transfer uses another PC on Wi‑Fi even though a Thunderbolt peer has the game | Steam discovers peers on the **house LAN subnet**; TB `/24` is a separate island | See [steam-and-lan-discovery.md](steam-and-lan-discovery.md) |
| Steam picked the Thunderbolt peer but transfer is still slow (Wi‑Fi-class) | Discovery IP is the Wi‑Fi address; kernel still routes it on-link via WLAN | On the downloader, `/32` host route for the library’s LAN IP via the TB next-hop ([steam-and-lan-discovery.md](steam-and-lan-discovery.md#linux-desktop--linux-desktop)); confirm with `ip route get` and sub‑ms `ping` |
| Turning Wi‑Fi off on the library PC makes Steam fall back to a different LAN PC | Library left the discovery subnet | Keep library on that subnet, **or** join Unraid TB into `br0`, **or** use a TB-only island (TB IPs + downloader Wi‑Fi off) |
| Route metric / “prefer Thunderbolt” does nothing | Steam’s destination IP is still the Wi‑Fi address | Bridge into house LAN, or transfer to a TB address (not Steam’s auto picker alone) |

Unraid: **tbn → Bridging = Yes → join `br0`**; peer uses a house-LAN IP on its Thunderbolt iface.  
Desktop↔desktop: bring TB IPs up first; for Steam prefer downloader Wi‑Fi off so only the TB peer remains.

## Do not

- Unbind Thunderbolt **NHI** to “reset” networking (can wedge until a full power-off).  
- Put two Thunderbolt links on the **same** IPv4 prefix.  
- Expect a dock RJ45 to show up as `thunderbolt0`.  
- Debug with three variables at once (E2E + new cable + dual plug) — change one thing, then reseat.

## OpenFabric / FRR (multi-hop)

| Symptom | Check |
|---------|--------|
| Chip / status **needs FRR packages** | Expected without FRR. Static tbn still works. Click the chip → Multi-hop companion card (top of overview) → install [FabricRouting](https://github.com/ibigsnet/FabricRouting) (CA or raw `.plg`) → **Network Settings → Fabric Routing** → Apply. Not required for one-cable static peers. |
| OpenFabric On but “FRR not installed” | Same as above. Optional multi-hop only — not a broken Thunderbolt Net install. |
| No multi-hop to a third host | Both ends need fabricd + matching **area**; underlay IPs up; unique subnets per link; IP forwarding only after FRR is present |
| Routes look wrong / hairpin around ring | Metrics: lower sum wins — [routing-openfabric.md](routing-openfabric.md#path-cost-and-metrics) |
| br0 / internet broken after experiments | Confirm tbn **default route = No**; OpenFabric should not enroll br0 by default |

When/why + plugin roles: [routing-openfabric.md](routing-openfabric.md). Mixed Unraid + Proxmox rings: [fabric-proxmox-unraid.md](fabric-proxmox-unraid.md).

## Peer-specific notes

[peer-scenarios.md](peer-scenarios.md)
