# Settings reference

## Global — Thunderbolt tab → Routing (OpenFabric / FRR)

| Field | Default | Scope | Summary |
|-------|---------|-------|---------|
| Enable OpenFabric | **Yes** | Host | Fabric control plane when FRR available; No = static only |
| OpenFabric IPv6 | Yes | Host | Also run IPv6 openfabric on ifaces |
| OpenFabric area | 1 | Host | Must match peers in the same fabric |
| Router ID | auto `10.254.x.y` | Host | `/32` on `lo`, passive |
| OpenFabric NET | auto | Host | Advanced IS-IS style NET override |
| Metric reference (Mb/s) | 100000 | Host | Auto metric ≈ ref / trained_Mbps (rings) |

Full design, pros/cons, cost model, rings, hot-plug: [routing-openfabric.md](routing-openfabric.md).

---

## Global — Thunderbolt tab → Driver options

| Field | Default | Scope | Summary |
|-------|---------|-------|---------|
| Load modules on Apply | Yes | Host | `modprobe thunderbolt` + `thunderbolt_net` |
| E2E flow control | No (e2e=0) | **Host / whole module** | See [driver-options.md](driver-options.md) |
| Enable USB4STREAM | No | Host | Load `thunderbolt_stream` when kernel has it |

Not per-tbn: changing E2E affects every Thunderbolt network interface on Unraid.

---

## Per-link — Thunderbolt tbnN tab

| Field | Default | Summary |
|-------|---------|---------|
| Interface description | empty | Cosmetic label in plugin config |
| MAC address | (live) | Read-only |
| Enable interface | Yes | `ip link set up/down` on Apply |
| Enable bonding | No (hidden unless ≥2 live TB ifaces) | TB-only bond when two+ `thunderbolt*`; same-peer dual-cable often one netdev — [roadmap](links-and-topology.md) |
| Bonding mode | active-backup | Prefer active-backup; 802.3ad usually fails on TB |
| Bond name | bond-tb0 | TB-only bond (`bond-tb0`, … — not Unraid `bond0`) |
| OpenFabric participate | **Yes** | yes / passive / no on this underlay |
| OpenFabric metric mode | auto | auto from trained rate, or manual |
| OpenFabric metric | empty | Manual integer when mode=manual |
| Enable bridging | No | Reserved; not auto-applied yet |
| Network protocol | IPv4 only | IPv6 limited |
| IPv4 assignment | Static | Prefer static on host↔host |
| IPv4 address / mask | 10.255.N.2 / 24 | Unique subnet per N |
| IPv4 default gateway | empty | Optional next hop on this link |
| Enable default route | No | Don’t steal system default from eth0 |
| Desired MTU | **1500** (default) | Modes: **1500** · **9000 both ends** · Custom. Not negotiated to peer. Jumbo optional. See [mtu-and-throughput.md](mtu-and-throughput.md) |
| Unraid services on this link (listening) | No (recommend Yes for SMB/transfers) | Per peer; remembered; overview table + Harden all |
| IPv4 (live) | (live) | Read-only from kernel |
| Bond / bridge membership | (live) | Read-only |

Full addressing discussion: [addressing.md](addressing.md).  
Peer examples: [peer-scenarios.md](peer-scenarios.md).  
Routing: [routing-openfabric.md](routing-openfabric.md).  

---

## Help in the UI

Click **Help** or a field **label** for blue `inline_help` panels (same mechanism as eth0 / Storage Guard).  
Long-form text stays in these docs; helpers link here when relevant.
