# Thunderbolt Net — Documentation

**Host-to-host networking** over Thunderbolt-family ports on Unraid (Linux `thunderbolt_net` / `thunderboltN`).  
That includes **Thunderbolt 3, 4, and 5**, and **USB4 / USB4 v2** host controllers when the platform and kernel expose the same fabric—not plain USB&nbsp;2/3-only ports. See [standards and speeds](docs/standards-and-speeds.md).

**Install:** Plugins → Install Plugin →  
`https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/stable/thunderboltnet.plg`

**Source / project:** [github.com/ibigsnet/ThunderboltNet](https://github.com/ibigsnet/ThunderboltNet)  
**Support (Unraid forum):** [Plugin support thread](https://forums.unraid.net/topic/200065-plugin-thunderbolt-net-host-to-host-networking-over-thunderbolt-345-and-usb44v2/)  
**Issues / code:** [GitHub Issues](https://github.com/ibigsnet/ThunderboltNet/issues)  
**Support development:** [Patreon](https://www.patreon.com/cw/IBIGSNet) · [PayPal](https://www.paypal.com/paypalme/RifleJock)

`README.md` is only the short Unraid Plugins-list blurb. This file and [`docs/`](docs/README.md) are the full documentation.

---

## Contents

- [What it does](#what-it-does)
- [Documentation map](#documentation-map)
- [Ecosystem: what each plugin does](#ecosystem-what-each-plugin-does)
- [Quick start](#quick-start)
- [UI help (blue helpers)](#ui-help-blue-helpers)
- [What this is *not*](#what-this-is-not)
- [Long-term product pillars](#long-term-product-pillars)
- [Install / uninstall](#install-uninstall)
- [Releases](#releases)

## What it does

| Area | Where in Unraid | What you control |
|------|-----------------|------------------|
| Fabric overview | **Settings → Network Settings → Thunderbolt** | Local controller, remote peers table, PCI/IOMMU, driver options |
| Per-link network | **Thunderbolt tbn0 / tbn1 / …** tabs | Enable, IP, mask, default route, MTU, listening include, Thunderbolt bond |
| Live kernel ifaces | `thunderbolt0`, `thunderbolt1`, … | Created by `thunderbolt_net` when a peer path trains |

The plugin does **not** replace Unraid’s eth0/br0 configuration. Thunderbolt links are usually **peer-to-peer** pipes used alongside your main LAN.

### Product defaults

| Setting | Default | Why |
|---------|---------|-----|
| Load modules on Apply | **Yes** | Bring up `thunderbolt` + `thunderbolt_net` without hand-editing `go` |
| E2E flow control | **No (`e2e=0`)** | Persisted on flash across reboot; more reliable for many cross-host Linux links (see [driver options](docs/driver-options.md)) |
| Per-link Enable | **Yes** | Bring the netdev up when you Apply |
| IPv4 assignment | **Static** | No DHCP on a pure host↔host cable |
| Suggested IPv4 | `10.255.N.2` / **24** for `thunderboltN` | One subnet per link; room for a few addresses |
| Enable default route | **No** | Keep internet on eth0/br0; Thunderbolt stays peer-local |
| Include listening | **No** | Don’t advertise Unraid services on Thunderbolt unless you opt in |
| MTU | **1500** (kernel default) | Safe first plug; use **9000 both ends** for bulk at 20–80&nbsp;G class — [MTU & throughput](docs/mtu-and-throughput.md) |
| Bonding / bridging | **Off** until ≥2 live Thunderbolt netdevs | Thunderbolt-only `bond-tb*`; dual-cable same-peer limited today — [roadmap](docs/links-and-topology.md) |

---

## Documentation map

| Topic | Doc |
|-------|-----|
| Driver options (`e2e`, modules) — **host-wide** | [docs/driver-options.md](docs/driver-options.md) |
| Unraid ↔ Mac / Linux / Windows / docks & hubs | [docs/peer-scenarios.md](docs/peer-scenarios.md) |
| `/24` vs `/30`, unique subnets per link | [docs/addressing.md](docs/addressing.md) |
| Thunderbolt 3–5 / USB4: **directionality**, bandwidth table, **mixing** gens/cables/lanes, FAQ | [docs/standards-and-speeds.md](docs/standards-and-speeds.md) |
| MTU 1500 vs 9000, PPS overhead, both-ends setup | [docs/mtu-and-throughput.md](docs/mtu-and-throughput.md) |
| Dashboard throughput / errors for tbn (thunderboltN) | [docs/dashboard-network.md](docs/dashboard-network.md) |
| USB4STREAM (raw Thunderbolt stream; needs **Linux kernel** with module — not Unraid 7.2 product) | [docs/usb4stream.md](docs/usb4stream.md) |
| Port silkscreen guide (Thunderbolt lightning / SS¹⁰ / SS²⁰) | [docs/port-icons.md](docs/port-icons.md) (docs only) |
| Hardware, BIOS, modules, security | [docs/requirements.md](docs/requirements.md) |
| Multi-host fabric reports (peer validation colors) | [docs/fabric-link-map.md](docs/fabric-link-map.md) |
| Topology: multi-peer, dual cable, bonding, rings | [docs/links-and-topology.md](docs/links-and-topology.md) |
| **FRR / OpenFabric** multi-host routing (when/why, plugin roles) | [docs/routing-openfabric.md](docs/routing-openfabric.md) |
| Mixed Unraid + Proxmox/Debian fabric (rings) | [docs/fabric-proxmox-unraid.md](docs/fabric-proxmox-unraid.md) |
| Topic index (all docs) | [docs/README.md](docs/README.md) |
| Every Settings field | [docs/settings-reference.md](docs/settings-reference.md) |
| Common failures | [docs/troubleshooting.md](docs/troubleshooting.md) |
| Safe Mode / no plugins — manual TB recovery | [docs/safe-mode-recovery.md](docs/safe-mode-recovery.md) |
| Install / update (`stable`, freezes, tags) | [RELEASES.md](RELEASES.md) |
| Optional FRR packages on Unraid | [FabricRouting](https://github.com/ibigsnet/FabricRouting) (separate plugin) |

---

## Ecosystem: what each plugin does

Related **ibigsnet** pieces are optional companions. Install only what you need.

| Product | What it does | When you want it | When you can skip it |
|---------|--------------|------------------|----------------------|
| **Thunderbolt Net** | Thunderbolt/USB4 **host-net underlay** (`tbnN` IPs), peers, listening, OpenFabric **policy** | Any Unraid host-to-host Thunderbolt networking | No Thunderbolt host controller |
| **Fabric Routing (FRR)** / FabricRouting | Installs **FRR packages/daemons** (`fabricd`, `vtysh`) — Network Settings → **Fabric Routing** | Rings, multi-hop, Proxmox/Linux FRR fabric | One cable + static IPs only |
| **NBD Export** | Host or pull **raw disks** over NBD — Network Services → **NBD** (Status · Host · Pull · Settings) | Imaging/cloning large disks over Thunderbolt | File shares (SMB/NFS) are enough |
| **Storage Guard** | Free-space thresholds and main-page free-bar colors | Know when a failed disk still leaves room | Optional |
| **USB4STREAM** | Not a plugin — **kernel** module `thunderbolt_stream` | Experimental raw stream when kernel has it | Always optional; not FRR |

OpenFabric **policy** lives on Thunderbolt Net (Advanced). FRR **packages** live under **Network Settings → Fabric Routing**. NBD is independent TCP block export under **Network Services → NBD**.

---

## Quick start

1. Confirm a Thunderbolt-family **host controller** is visible (Thunderbolt tab shows hardware, not the empty state).
2. Use a certified Thunderbolt / USB4-class cable that matches both hosts (Thunderbolt 3/4/5, USB4, USB4 v2 as applicable — not SS-only SuperSpeed USB). Start with **one cable per peer path**; multi-path bonding is optional when two netdevs exist ([topology](docs/links-and-topology.md)).
3. On **Thunderbolt → Driver options**, leave **E2E flow control = No** unless a peer scenario says otherwise.
4. When `thunderbolt0` appears, open **tbn0**, set a **static** IPv4 (e.g. `10.255.0.2/24`), leave **MTU 1500** unless both ends will set jumbo, **Apply**. That also stores a **peer plan** for the remote host (fabric UUID) so unplug/replug can restore L3 even if the path renumbers.
5. On the peer, matching address (e.g. `10.255.0.1/24`). MTU is not auto-negotiated — only raise to 9000 if you set **both** ends.
6. Ping both ways. Check **Peers**: one Known peers row; **Saved** should already show your Unraid-side address (filled by that Apply — no extra Remember step). Set listening **Yes** if you want SMB/NFS/web on the TB IP.
7. **Trained** rate can be less than the port sticker (e.g. **20 Gb/s · 1-lane**). Sticker **40 Gb/s** is typically **~20 G each direction** (simplex lanes) — expect roughly **~10–15 Gbit/s** TCP one way on a 1-lane train ([speeds](docs/standards-and-speeds.md)).
8. If dual-cable experiments wedged the fabric: unplug **all** Thunderbolt cables on **both** machines, wait, plug **one** cable only ([troubleshooting](docs/troubleshooting.md)).

---

## UI help (blue helpers)

Same pattern as stock Unraid eth0 and Storage Guard:

- Click the header **Help** button to show all blue help panels, or  
- Click a **field label** (cursor becomes a help pointer) to toggle that field’s help.

Helpers summarize settings; long examples and peer tables live here in the docs (linked from helpers and from the Documentation bar on the Settings pages).

---

## What this is *not*

- Not a promise of dual-lane / sticker **40 Gb/s** host-net TCP (1-lane is common under Linux).
- Not a promise that **dual-cable to the same peer** yields 2× TCP today (often one netdev; can wedge fabric) — bonding remains a **roadmap** when two paths appear ([topology](docs/links-and-topology.md)).
- Not a substitute for 10/25/40/100G Ethernet to a switch (unless you deliberately run Thunderbolt host networking).
- Not automatic L2 multi-host “Thunderbolt switch LAN” — each `thunderboltN` is typically **one peer path**; multi-hop uses **OpenFabric** ([routing](docs/routing-openfabric.md)).
- A dock’s **Ethernet RJ45** is usually a **USB/PCIe NIC**, not `thunderbolt_net` — configure it like any other eth device ([peer scenarios](docs/peer-scenarios.md)).
- Never “fix” networking by unbinding the Thunderbolt **NHI** from the host driver — that can wedge some controllers until reboot.
- Soft port power-cycle / custom ICM dual-lane forcing is out of scope for the plugin (kernel/firmware territory).

---

## Long-term product pillars

These are **supported directions**, not throwaway experiments. Defaults favor interconnectivity; static-only remains a first-class override.

| Pillar | Intent | Default direction | Doc |
|--------|--------|-------------------|-----|
| **Host-net underlay** | Per-path Thunderbolt/USB4 L3 (one netdev per peer path), honest trained rate | Static tbnN, MTU 1500, no default route via Thunderbolt | This file, [addressing](docs/addressing.md) |
| **OpenFabric / FRR** | Multi-host mesh/**ring**/multi-hop; hot-plug device classes | **On** when FRR available; global/per-link **off** | [routing-openfabric.md](docs/routing-openfabric.md) |
| **Mixed OpenFabric fabric** | Unraid + Proxmox/Debian (or other FRR) peers | Shared area/NET/metrics; FRR both sides | [fabric-proxmox-unraid.md](docs/fabric-proxmox-unraid.md) |
| **FabricRouting (companion)** | Opt-in install of FRR packages/daemons (invasive) | Separate plugin; not required for static Thunderbolt | [ibigsnet/FabricRouting](https://github.com/ibigsnet/FabricRouting) |
| **Bonding multi-path** | Thunderbolt-only `bond-tb*` when ≥2 netdevs | Off by default; dual-cable same-peer **roadmap** | [links-and-topology.md](docs/links-and-topology.md) |
| **Peer memory + peer plans** | Remember hosts by fabric UUID; desired local IPv4 follows the peer | **Supported** — see [peers-and-plans.md](docs/peers-and-plans.md) |
| **Activity / unplug** | Safe to disconnect hints | Heuristic today; tighter idle later | Settings UI |
| **USB4STREAM** | Raw path awareness where kernel allows | Off until module exists; never break tbn IP | [usb4stream.md](docs/usb4stream.md) |

### Peer plug-and-play (underlay)

| Supported now | Notes |
|---------------|--------|
| Peer stored in `peers.json` by **remote fabric UUID** (not MAC, not panel port) | Status/Peers load, Apply, hotplug reapply |
| **Saved** address bound to that UUID | Filled automatically on **tbn Apply** while linked (Remember current optional later) |
| Saved reapplied on **hotplug / array start** to whichever `thunderboltN` that peer is on | Survives tbn0↔tbn1 renumber when cable order changes |
| **Known peers** table: online/offline, path, Current / Saved, listening, **Forget** | Does **not** use Unraid Interface Rules; quiet No reply if underlay silent |
| Ghost offline rows after unplug (blank name) | Deduped when UUID returns (**16ac+**) |
| Path-slot cfg `ifaces/thunderboltN.cfg` | Still eth-like cache for the **name**; peer plan is preferred when a UUID plan exists |

| Optional next | |
|---------------|--|
| Inline edit of peer plan while offline | Capture + Apply is enough for v1 |
| Tighter activity / idle (share-aware unplug) | Heuristic today |

**Not** stock Network Settings → Interface Rules (MAC→ethN). Thunderbolt host-net MACs often change each link; binding by MAC would thrash. Identity is fabric UUID inside this plugin.

**Why this path (short):** Forum reports showed static addresses failing after reboot/unplug and 169.254 stacking with Static — fixed with path reapply, udev hotplug, and dhcpcd kill. Peer plans extend that so L3 follows the **remote host** when path names renumber. Full findings → solutions: [peers-and-plans.md — Why this design](docs/peers-and-plans.md#why-this-design-field-findings). Symptom index: [troubleshooting.md](docs/troubleshooting.md#static-ip-missing-after-reboot-or-unplugreplug).

Goal: plug in a laptop or mini-PC, transfer, unplug when idle is safe — without redoing IP every time **and** without assuming tbn0 is always the same remote host. Multi-hop/rings = OpenFabric; dual-cable bonding = separate roadmap.

Full write-up: [peers-and-plans.md](docs/peers-and-plans.md).

---

## Install / uninstall

**Install:** Apps (CA) or Plugins → Install Plugin — see [RELEASES.md](RELEASES.md) (same two-track pattern as Storage Guard).

**Uninstall:** Plugins → Thunderbolt Net → Remove. Cleans Thunderbolt listening includes, modprobe snippets, Dashboard port patches, emhttp paths, and **wipes** flash `/boot/config/plugins/ThunderboltNet` (including `peers.json` and `ifaces/`) for a clean reinstall. Does **not** remove Fabric Routing or FRR. Hard-refresh after remove. Recovery text may be left only if a release still documents a recovery file — prefer [safe-mode-recovery.md](docs/safe-mode-recovery.md).

## Releases

Install / update URLs: [RELEASES.md](RELEASES.md).
