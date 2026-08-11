# Troubleshooting


## Contents

- [Slow rsync / SMB despite high trained Gb/s](#slow-rsync-smb-despite-high-trained-gbs)
- [Reseating the cable (why it matters)](#reseating-the-cable-why-it-matters)
- [Multi-cable situations](#multi-cable-situations)
- [No Thunderbolt hardware detected](#no-thunderbolt-hardware-detected)
- [Interface never appears (`thunderbolt0` missing)](#interface-never-appears-thunderbolt0-missing)
- [Link trains but no ping](#link-trains-but-no-ping)
- [Single-lane / 20 Gb/s · 1-lane on a dual-capable host](#single-lane-20-gbs-1-lane-on-a-dual-capable-host)
- [Two cables, still one interface (or worse)](#two-cables-still-one-interface-or-worse)
- [One-way traffic / flaky after reboot](#one-way-traffic-flaky-after-reboot)
- [Do not](#do-not)
- [OpenFabric / FRR (multi-hop)](#openfabric-frr-multi-hop)
- [Peer-specific notes](#peer-specific-notes)

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

## No Thunderbolt hardware detected

- Board/CPU may lack a host controller, or it is disabled in BIOS.  
- Controller bound to **vfio-pci** — check PCI panel; return NHI to host driver if you want host networking.  
- Copy diagnostics from the empty-state page when filing an issue.

## Interface never appears (`thunderbolt0` missing)

- Peer not connected or not authorizing the link (security mode).  
- Cable is charge-only / not TB-capable.  
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

## Single-lane / 20 Gb/s · 1-lane on a dual-capable host

- **Common** for TB4/USB4 **host-to-host under Linux** (firmware ICM). Not a failed install.  
- Expect roughly **~10–15&nbsp;Gbit/s** TCP; jumbo MTU helps CPU, not dual-lane.  
- Cable/port can still matter for some pairs, but a short certified TB4 cable often stays 1-lane.  
- Details: [standards-and-speeds.md](standards-and-speeds.md).

## Two cables, still one interface (or worse)

Expected often for the **same** two hosts — still one XDomain / one `thunderboltN`. Bonding usually fails (`set_mac` −95, flaky MII). See [links-and-topology.md](links-and-topology.md#dual-cable-between-the-same-pair-of-pcs).

### Wedged after dual-cable experiments

Software cleanup alone may not fix NO-CARRIER / missing peer:

1. Unplug **all** TB/USB4 host cables from **both ends on both machines**.  
2. Wait until the fabric is empty.  
3. Plug back **exactly one** cable (TB ports only).  
4. Confirm peer + netdev; re-apply IP / MTU / services.

## One-way traffic / flaky after reboot

- Re-check E2E (`cat /sys/module/thunderbolt_net/parameters/e2e`) is still off if you want e2e=0.  
- Ensure modprobe.d conf persisted under `/boot/config/modprobe.d/`.  
- Reseat cable after confirming the parameter.  
- Peer NetworkManager profiles “never default” / wrong metric.

## Do not

- Unbind Thunderbolt **NHI** to “reset” networking (can wedge until reboot).  
- Put two TB links on the **same** IPv4 prefix.  
- Expect a dock RJ45 to show up as `thunderbolt0`.  
- Debug with three variables at once (E2E + new cable + dual plug) — change one thing, then reseat.

## OpenFabric / FRR (multi-hop)

| Symptom | Check |
|---------|--------|
| Chip / status **needs FRR packages** | Expected without FRR. Static tbn still works. Click the chip → Multi-hop companion card (top of overview) → install [UnraidFRR](https://github.com/ibigsnet/UnraidFRR) (CA or raw `.plg`) → **Network Settings → Fabric Routing** → Apply. Not required for one-cable static peers. |
| OpenFabric On but “FRR not installed” | Same as above. Optional multi-hop only — not a broken Thunderbolt Net install. |
| No multi-hop to a third host | Both ends need fabricd + matching **area**; underlay IPs up; unique subnets per link; IP forwarding only after FRR is present |
| Routes look wrong / hairpin around ring | Metrics: lower sum wins — [routing-openfabric.md](routing-openfabric.md#path-cost-and-metrics) |
| br0 / internet broken after experiments | Confirm tbn **default route = No**; OpenFabric should not enroll br0 by default |

When/why + plugin roles: [routing-openfabric.md](routing-openfabric.md). Mixed Unraid + Proxmox rings: [fabric-proxmox-unraid.md](fabric-proxmox-unraid.md).

## Peer-specific notes

[peer-scenarios.md](peer-scenarios.md)
