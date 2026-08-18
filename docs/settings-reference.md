# Settings reference

## Global — Thunderbolt tab → Routing (OpenFabric / FRR)

OpenFabric is the **control plane** for multi-hop fabrics (rings, “reach C through B”, mixed Proxmox/Unraid labs). It does **not** install FRR packages — use optional companion [FabricRouting](https://github.com/ibigsnet/FabricRouting) or supply FRR yourself. Without FRR, leave OpenFabric on or off as you like; underlay static still works. UI chip **needs FRR packages** links to the Multi-hop companion card at the top of the overview.

**Skip OpenFabric / FabricRouting** for a single peer cable with static tbn IPs. Details: [routing-openfabric.md](routing-openfabric.md#when-do-i-need-this-read-this-first).

| Field | Default | Scope | Summary |
|-------|---------|-------|---------|
| Enable OpenFabric | **No** until FRR packages present | Host | Hidden until FRR; auto-on when FRR appears (user may set No) |
| OpenFabric IPv6 | Yes | Host | Also run IPv6 openfabric on ifaces |
| OpenFabric area | 1 | Host | Must match peers in the same fabric |
| Router ID | auto `10.254.x.y` | Host | `/32` on `lo`, passive |
| OpenFabric NET | auto | Host | Advanced IS-IS style NET override |
| Metric reference (Mb/s) | **20000** (~20G) | Host | Auto metric ≈ ref / trained_Mbps; ≥20G floors at 1; use manual for DAC preference |

Full design, pros/cons, cost model, rings, mixed Proxmox/Unraid: [routing-openfabric.md](routing-openfabric.md) · [fabric-proxmox-unraid.md](fabric-proxmox-unraid.md).

---

## Global — Thunderbolt tab → Driver options

| Field | Default | Scope | Summary |
|-------|---------|-------|---------|
| Load modules on Apply | Yes | Host | `modprobe thunderbolt` + `thunderbolt_net` |
| E2E flow control | No (e2e=0) | **Host / whole module** | See [driver-options.md](driver-options.md) |
| Enable USB4STREAM | No | Host | Load `thunderbolt_stream` when kernel has it |

Not per-tbn: changing E2E affects every Thunderbolt network interface on Unraid.

---

## Peers tab — Known peers

Full detail: [peers-and-plans.md](peers-and-plans.md).

| Field / action | Summary |
|----------------|---------|
| Status | Online / offline for remembered fabric UUID |
| Path | Live `tbnN` / `thunderboltN` (may renumber) |
| Current | IPv4 on the path right now; quiet **No carrier** / **No reply** if underlay silent |
| Saved | Remembered IPv4 for this UUID; preferred on hotplug/boot |
| Remember current | Copy Current (IP/MTU/services) into Saved — optional; first **tbn Apply** already captures |
| Apply saved | Push Saved onto the path that peer currently uses |
| Unraid services | Per-peer listening Yes/No (remembered) |
| Forget selected | Drop from `peers.json` only — not eth Interface Rules |
| Link check | Optional peer Unraid rate compare (shared token) |

**First setup:** Apply on the tbn tab while linked — that fills **Saved**. Matching Current and Saved afterward is normal.

Does **not** register Thunderbolt in stock **Interface Rules** (MAC→name). Host-net MACs are unstable.

---

## Per-link — Thunderbolt tbnN tab

| Field | Default | Summary |
|-------|---------|---------|
| Interface description | empty | Cosmetic label in plugin config |
| MAC address | (live) | Read-only (often changes each link) |
| Enable interface | Yes | `ip link set up/down` on Apply |
| Apply (side effect) | — | Also **captures Saved** for the live remote UUID on this path (no separate Remember step) |
| Enable bonding | No (hidden unless ≥2 live Thunderbolt interfaces) | Thunderbolt-only bond when two+ `thunderbolt*`; same-peer dual-cable often one netdev — [roadmap](links-and-topology.md) |
| Bonding mode | active-backup | Prefer active-backup; 802.3ad usually fails on Thunderbolt |
| Bond name | bond-tb0 | Thunderbolt-only bond (`bond-tb0`, … — not Unraid `bond0`) |
| OpenFabric participate | **Yes** | yes / passive / no on this underlay |
| OpenFabric metric mode | auto | auto from trained rate, or manual |
| OpenFabric metric | empty | Manual integer when mode=manual |
| Enable bridging | No | Join an **existing** Unraid bridge (`br0`, `br0.N`, …). Member has no own IP; peer uses house-LAN addressing on its TB iface. Default No — [addressing.md](addressing.md#join-an-unraid-bridge-br0-br010-) |
| Network protocol | IPv4 only | IPv6 limited |
| IPv4 assignment | Static | Prefer static on host↔host |
| IPv4 address / mask | 10.255.N.2 / 24 | Unique subnet per N |
| IPv4 default gateway | empty | Optional next hop on this link |
| Enable default route | No | Don’t steal system default from eth0 |
| Desired MTU | **1500** (default) | Leave 1500 unless you opt into jumbo. **Enable jumbo frames** + 9000 (typical) can reduce packet/CPU cost on older or weaker hosts; **both ends must match**. See [mtu-and-throughput.md](mtu-and-throughput.md) |
| Unraid services on this link (listening) | No (recommend Yes for SMB/NFS/web) | Per peer; remembered; overview table + Harden all. Does **not** start NBD — use **NBD Export** (Network Services → NBD) and bind to the Thunderbolt IP. |
| Link rate (Known peers / quality) | (live / last) | Equal RX≈TX → **N Gb/s full-duplex** (optional · N-lane). Asymmetric → **TX … (to peer) · RX … (from peer)**. |
| IPv4 (live) | (live) | Read-only from kernel |
| Bond / bridge membership | (live) | Read-only |

Full addressing discussion: [addressing.md](addressing.md).  
Peer examples: [peer-scenarios.md](peer-scenarios.md).  
Routing: [routing-openfabric.md](routing-openfabric.md).  

---

## Help in the UI

Click **Help** or a field **label** for blue `inline_help` panels (same mechanism as eth0 / Storage Guard).  
Long-form text stays in these docs; helpers link here when relevant.
