# Thunderbolt Net — Documentation

**Host-to-host networking** over Thunderbolt-family ports on Unraid (Linux `thunderbolt_net` / `thunderboltN`).  
That includes **Thunderbolt 3, 4, and 5**, and **USB4 / USB4 v2** host controllers when the platform and kernel expose the same fabric—not plain USB&nbsp;2/3-only ports. See [standards and speeds](docs/standards-and-speeds.md).

**Install:** Plugins → Install Plugin →  
`https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/main/thunderboltnet.plg`

**Source:** [github.com/ibigsnet/ThunderboltNet](https://github.com/ibigsnet/ThunderboltNet)  
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
| Bonding / bridging | **No** | Only when you really have multiple TB netdevs to bond |

---

## Documentation map

| Topic | Doc |
|-------|-----|
| Driver options (`e2e`, modules) — **host-wide** | [docs/driver-options.md](docs/driver-options.md) |
| Unraid ↔ Mac / Linux / Windows / docks & hubs | [docs/peer-scenarios.md](docs/peer-scenarios.md) |
| `/24` vs `/30`, unique subnets per link | [docs/addressing.md](docs/addressing.md) |
| TB3–5, USB4/USB4 v2, rates, lanes, cables (not USB3-only) | [docs/standards-and-speeds.md](docs/standards-and-speeds.md) |
| MTU 1500 vs 9000, PPS overhead, both-ends setup | [docs/mtu-and-throughput.md](docs/mtu-and-throughput.md) |
| Port silkscreen guide (TB lightning / SS¹⁰ / SS²⁰) | [docs/port-icons.md](docs/port-icons.md) (docs only) |
| Hardware, BIOS, modules, security | [docs/requirements.md](docs/requirements.md) |
| Topology, dual cables, hubs with NICs | [docs/links-and-topology.md](docs/links-and-topology.md) |
| Every Settings field | [docs/settings-reference.md](docs/settings-reference.md) |
| Common failures | [docs/troubleshooting.md](docs/troubleshooting.md) |

---

## Quick start

1. Confirm a Thunderbolt-family **host controller** is visible (Thunderbolt tab shows hardware, not the empty state).
2. Use a **certified Thunderbolt / USB4** cable rated for your hosts (short high-rate passive is the usual lab choice).
3. On **Thunderbolt → Driver options**, leave **E2E flow control = No** unless a peer scenario says otherwise.
4. When `thunderbolt0` appears, open **tbn0**, set a **static** IPv4 (e.g. `10.255.0.2/24`), Apply.
5. On the peer, set a matching address (e.g. `10.255.0.1/24`) on its Thunderbolt network interface (macOS: Thunderbolt Bridge, etc.).
6. Ping both ways. If link quality shows **Below max** / **20 Gb/s · 1-lane** on a host that can do ~40&nbsp;Gb/s, try a better cable first ([speeds](docs/standards-and-speeds.md)).

---

## UI help (blue helpers)

Same pattern as stock Unraid eth0 and Storage Guard:

- Click the header **Help** button to show all blue help panels, or  
- Click a **field label** (cursor becomes a help pointer) to toggle that field’s help.

Helpers summarize settings; long examples and peer tables live here in the docs (linked from helpers and from the Documentation bar on the Settings pages).

---

## What this is *not*

- Not a substitute for 10/25/40/100G Ethernet to a switch (unless you deliberately run TB host networking).
- Not automatic multi-host “TB LAN” switching — each `thunderboltN` is typically **one peer path**.
- A dock’s **Ethernet RJ45** is usually a **USB/PCIe NIC**, not `thunderbolt_net` — configure it like any other eth device ([peer scenarios](docs/peer-scenarios.md)).
- Never “fix” networking by unbinding the Thunderbolt **NHI** from the host driver — that can wedge some controllers until reboot.

---

## Plug and play (today vs coming)

| Today (groundwork) | Coming later |
|--------------------|--------------|
| Open Thunderbolt tab while a peer is connected → peer stored in `peers.json` | Auto-restore last static IP when that laptop returns |
| **Activity / unplug** row (refresh twice for traffic rate) | Tighter idle detection (share/session aware) |
| **Known peers** table (online/offline, last rates, last IPv4) | Health strip / notifications optional |
| Manual tbnN Apply for addresses | One-click “use last plan for this peer” |

Goal: plug in a laptop, transfer, unplug when idle is safe — without redoing IP setup every time.

---

## Releases

Install / update URLs: [RELEASES.md](RELEASES.md).
