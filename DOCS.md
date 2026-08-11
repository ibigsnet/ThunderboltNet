# Thunderbolt Net — Documentation

**Host-to-host networking** over Thunderbolt-family ports on Unraid (Linux `thunderbolt_net` / `thunderboltN`).  
That includes **Thunderbolt 3, 4, and 5**, and **USB4 / USB4 v2** host controllers when the platform and kernel expose the same fabric—not plain USB&nbsp;2/3-only ports. See [standards and speeds](docs/standards-and-speeds.md).

**Install:** Plugins → Install Plugin →  
`https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/main/thunderboltnet.plg`

**Source / project:** [github.com/ibigsnet/ThunderboltNet](https://github.com/ibigsnet/ThunderboltNet)  
**Support (Unraid forum):** [Plugin support thread](https://forums.unraid.net/topic/200065-plugin-thunderbolt-net-host-to-host-networking-over-thunderbolt-345-and-usb44v2/)  
**Issues / code:** [GitHub Issues](https://github.com/ibigsnet/ThunderboltNet/issues)  
**Support development:** [Patreon](https://www.patreon.com/cw/IBIGSNet) · [PayPal](https://www.paypal.com/paypalme/RifleJock)

`README.md` is only the short Unraid Plugins-list blurb. This file and [`docs/`](docs/README.md) are the full documentation.

---

## What it does

| Area | Where in Unraid | What you control |
|------|-----------------|------------------|
| Fabric overview | **Settings → Network Settings → Thunderbolt** | Local controller, remote peers table, PCI/IOMMU, driver options |
| Per-link network | **Thunderbolt tbn0 / tbn1 / …** tabs | Enable, IP, mask, default route, MTU, listening include, TB bond |
| Live kernel ifaces | `thunderbolt0`, `thunderbolt1`, … | Created by `thunderbolt_net` when a peer path trains |

The plugin does **not** replace Unraid’s eth0/br0 configuration. Thunderbolt links are usually **peer-to-peer** pipes used alongside your main LAN.

### Product defaults

| Setting | Default | Why |
|---------|---------|-----|
| Load modules on Apply | **Yes** | Bring up `thunderbolt` + `thunderbolt_net` without hand-editing `go` |
| E2E flow control | **No (`e2e=0`)** | More reliable for many cross-host Linux links (see [driver options](docs/driver-options.md)) |
| Per-link Enable | **Yes** | Bring the netdev up when you Apply |
| IPv4 assignment | **Static** | No DHCP on a pure host↔host cable |
| Suggested IPv4 | `10.255.N.2` / **24** for `thunderboltN` | One subnet per link; room for a few addresses |
| Enable default route | **No** | Keep internet on eth0/br0; TB stays peer-local |
| Include listening | **No** | Don’t advertise Unraid services on TB unless you opt in |
| MTU | **1500** (kernel default) | Safe first plug; use **9000 both ends** for bulk at 20–80&nbsp;G class — [MTU & throughput](docs/mtu-and-throughput.md) |
| Bonding / bridging | **Off** until ≥2 live TB netdevs | TB-only `bond-tb*`; dual-cable same-peer limited today — [roadmap](docs/links-and-topology.md) |

---

## Documentation map

| Topic | Doc |
|-------|-----|
| Driver options (`e2e`, modules) — **host-wide** | [docs/driver-options.md](docs/driver-options.md) |
| Unraid ↔ Mac / Linux / Windows / docks & hubs | [docs/peer-scenarios.md](docs/peer-scenarios.md) |
| `/24` vs `/30`, unique subnets per link | [docs/addressing.md](docs/addressing.md) |
| TB3–5 / USB4: **directionality**, bandwidth table, **mixing** gens/cables/lanes, FAQ | [docs/standards-and-speeds.md](docs/standards-and-speeds.md) |
| MTU 1500 vs 9000, PPS overhead, both-ends setup | [docs/mtu-and-throughput.md](docs/mtu-and-throughput.md) |
| Dashboard throughput / errors for tbn (thunderboltN) | [docs/dashboard-network.md](docs/dashboard-network.md) |
| USB4STREAM (raw TB stream, Linux ~7.2+) | [docs/usb4stream.md](docs/usb4stream.md) |
| Port silkscreen guide (TB lightning / SS¹⁰ / SS²⁰) | [docs/port-icons.md](docs/port-icons.md) (docs only) |
| Hardware, BIOS, modules, security | [docs/requirements.md](docs/requirements.md) |
| Topology: multi-peer, dual cable, bonding, rings | [docs/links-and-topology.md](docs/links-and-topology.md) |
| **FRR / OpenFabric** multi-host routing | [docs/routing-openfabric.md](docs/routing-openfabric.md) |
| Mixed Unraid + Proxmox/Debian fabric | [docs/fabric-proxmox-unraid.md](docs/fabric-proxmox-unraid.md) |
| Topic index (all docs) | [docs/README.md](docs/README.md) |
| Every Settings field | [docs/settings-reference.md](docs/settings-reference.md) |
| Common failures | [docs/troubleshooting.md](docs/troubleshooting.md) |
| Install, version strings, **git tags / ship checklist** | [RELEASES.md](RELEASES.md) |
| Optional FRR packages on Unraid | [UnraidFRR](https://github.com/ibigsnet/UnraidFRR) (separate plugin) |

---

## Quick start

1. Confirm a Thunderbolt-family **host controller** is visible (Thunderbolt tab shows hardware, not the empty state).
2. Use a certified Thunderbolt / USB4-class cable that matches both hosts (TB3/4/5, USB4, USB4 v2 as applicable — not SS-only SuperSpeed USB). Start with **one cable per peer path**; multi-path bonding is optional when two netdevs exist ([topology](docs/links-and-topology.md)).
3. On **Thunderbolt → Driver options**, leave **E2E flow control = No** unless a peer scenario says otherwise.
4. When `thunderbolt0` appears, open **tbn0**, set a **static** IPv4 (e.g. `10.255.0.2/24`), leave **MTU 1500** unless both ends will set jumbo, Apply.
5. On the peer, matching address (e.g. `10.255.0.1/24`). MTU is not auto-negotiated — only raise to 9000 if you set **both** ends.
6. Ping both ways. **Trained** rate can be less than the port sticker (e.g. **20 Gb/s · 1-lane** on a dual-capable TB4 host is common under Linux). A sticker **40 Gb/s** class path is typically **~20 G each direction** (simplex lanes), not PCIe-style 40 each way — expect roughly **~10–15 Gbit/s** TCP one way on a 1-lane train ([speeds / directionality](docs/standards-and-speeds.md)).
7. If dual-cable experiments wedged the fabric: unplug **all** TB cables on **both** machines, wait, plug **one** cable only ([troubleshooting](docs/troubleshooting.md)).

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
- Not a substitute for 10/25/40/100G Ethernet to a switch (unless you deliberately run TB host networking).
- Not automatic L2 multi-host “TB switch LAN” — each `thunderboltN` is typically **one peer path**; multi-hop uses **OpenFabric** ([routing](docs/routing-openfabric.md)).
- A dock’s **Ethernet RJ45** is usually a **USB/PCIe NIC**, not `thunderbolt_net` — configure it like any other eth device ([peer scenarios](docs/peer-scenarios.md)).
- Never “fix” networking by unbinding the Thunderbolt **NHI** from the host driver — that can wedge some controllers until reboot.
- Soft port power-cycle / custom ICM dual-lane forcing is out of scope for the plugin (kernel/firmware territory).

---

## Long-term product pillars

These are **supported directions**, not throwaway experiments. Defaults favor interconnectivity; static-only remains a first-class override.

| Pillar | Intent | Default direction | Doc |
|--------|--------|-------------------|-----|
| **Host-net underlay** | Per-path TB/USB4 L3 (one netdev per peer path), honest trained rate | Static tbnN, MTU 1500, no default route via TB | This file, [addressing](docs/addressing.md) |
| **OpenFabric / FRR** | Multi-host mesh/**ring**/multi-hop; hot-plug device classes | **On** when FRR available; global/per-link **off** | [routing-openfabric.md](docs/routing-openfabric.md) |
| **Mixed OpenFabric fabric** | Unraid + Proxmox/Debian (or other FRR) peers | Shared area/NET/metrics; FRR both sides | [fabric-proxmox-unraid.md](docs/fabric-proxmox-unraid.md) |
| **UnraidFRR (companion)** | Opt-in install of FRR packages/daemons (invasive) | Separate plugin; not required for static TB | [ibigsnet/UnraidFRR](https://github.com/ibigsnet/UnraidFRR) |
| **Bonding multi-path** | TB-only `bond-tb*` when ≥2 netdevs | Off by default; dual-cable same-peer **roadmap** | [links-and-topology.md](docs/links-and-topology.md) |
| **Peer memory** | Remember hosts and last address plan | Store peers today; auto-restore plan next | Below |
| **Activity / unplug** | Safe to disconnect hints | Heuristic today; tighter idle later | Settings UI |
| **USB4STREAM** | Raw path awareness where kernel allows | Off until module exists; never break tbn IP | [usb4stream.md](docs/usb4stream.md) |

### Peer plug-and-play (underlay)

| Supported now | Next supported increments |
|---------------|---------------------------|
| Open Thunderbolt tab while a peer is connected → peer stored in `peers.json` | Auto-restore last static IP when that laptop returns |
| **Activity / unplug** row (refresh twice for traffic rate) | Tighter idle detection (share/session aware) |
| **Known peers** table (online/offline, last rates, last IPv4) | Health strip / notifications optional |
| Manual tbnN Apply for addresses | One-click “use last plan for this peer” |

Goal: plug in a laptop or mini-PC (including future Strix Halo / Gorgon Halo / DGX Spark class peers), transfer, unplug when idle is safe — without redoing IP setup every time. Multi-hop and **rings** are the OpenFabric pillar; dual-cable bonding is a separate multi-path roadmap.

---

## Install / uninstall

**Install:** Apps (CA) or Plugins → Install Plugin — see [RELEASES.md](RELEASES.md) (same two-track pattern as Storage Guard).

**Uninstall:** Plugins → Thunderbolt Net → Remove. The plugin remove script cleans TB listening includes, modprobe snippets, Dashboard port patches, emhttp paths, and flash config under `/boot/config/plugins/ThunderboltNet`. It does **not** remove UnraidFRR or FRR. Hard-refresh after remove.

## Releases

Install / update URLs: [RELEASES.md](RELEASES.md).
