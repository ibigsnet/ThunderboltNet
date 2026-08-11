# Links and topology

## Mental model

Linux **Thunderbolt networking** (`thunderbolt_net`) creates an Ethernet-like netdev per **host-to-host network service path**, not per physical USB-C receptacle in all cases.

| You plug… | Typical result |
|-----------|----------------|
| One cable, two hosts | One peer, one `thunderbolt0` (each side) |
| Two cables, **two different** peers | Often `thunderbolt0` + `thunderbolt1` |
| Two cables, **same** two hosts | Frequently still **one** xdomain peer / one netdev — second cable may not add a second host path |
| Host → dock only | Devices in fabric; **no** host netdev until another host peers |

There is no general “TB Ethernet switch fabric” that puts all TB machines on one L2 domain the way a copper switch does.  
For **IP multi-hop / rings / meshes**, use **OpenFabric (FRR)** on top of these netdevs — see [routing-openfabric.md](routing-openfabric.md).

---

## Dual cable between the same pair of PCs

Goals people want: **2× bandwidth**, **redundancy**, or both via bonding.

### Reality today (kernel / TB domain)

1. The fabric must enumerate **two** network-capable peer paths.  
2. If sysfs only shows one peer hop, bonding **cannot invent** a second slave.  
3. Two cables to the **same** peer often do **not** create `thunderbolt0` + `thunderbolt1` — you may still see a single path, or a confused domain.  
4. Dual-plug experiments are a common way to **wedge** bring-up until all cables are cleared.  
5. TB netdevs often reject `set_mac`; many Linux bond modes fail or misbehave.

### Plugin bonding (supported where members exist)

- Offered when **two or more live** `thunderbolt*` members already exist (or bonding was previously enabled).  
- Builds a **TB-only** bond (`bond-tb0`, … — **not** Unraid eth `bond0`).  
- Prefer **active-backup** when you truly have two members (usually **two different peers**).  
- Apply with fewer than two members is ignored.

### Roadmap (not a non-goal)

Dual-cable bonding and multi-path to the **same** peer remain **in scope** for later work when hardware/kernel expose two usable netdevs:

- Better dual-path detection and UI guidance  
- Bond modes that survive TB MAC/MII limits where possible  
- **Bond + OpenFabric**: advertise `bond-tbN` with aggregate/primary metrics  
- Lab procedures for redundancy vs throughput  

Until then: prefer **one cable per peer path** for reliability; use **OpenFabric rings/meshes** across *multiple hosts* for alternate paths rather than dual-cabling one pair hoping for 2× TCP.

---

## Multi-cable and recovery

### Why “unplug everything, then one cable” works

Thunderbolt domains remember paths and services. Extra cables (second link to the same host, a dock still attached, a half-seated rear port) can keep the domain from settling on a single clean host-to-host network service.

**Required recovery** when dual-cable tests went sideways (software-only teardown often fails):

1. Unplug **all** TB/USB4 host cables from **both ends on both machines** (clear every TB port).  
2. Pause a few seconds until peers disappear.  
3. Seat **exactly one** known-good cable only (TB ports, not SS-only USB).  
4. Confirm one peer and one netdev; configure IP + MTU; prove ping.  
5. Re-introduce multi-path only when you already understand whether a second netdev appeared.

### Reseating one cable (when you already have a single link)

Unplug that cable fully (both ends if possible), wait, reconnect. That re-trains the path and is often more effective than re-clicking Apply alone after driver changes.

See [troubleshooting.md — Reseating the cable](troubleshooting.md#reseating-the-cable-why-it-matters).

---

## Rings, stars, and multi-host

| Design | How |
|--------|-----|
| **Star** | One multi-homed Unraid (or Linux) with several TB peers |
| **Partial mesh** | Multiple TB cables among a set of hosts |
| **Full ring** | Each node has two TB neighbors — **first-class OpenFabric goal** |

Thunderbolt still provides **one L2 segment per path**. OpenFabric (FRR `fabricd`) computes SPF over those underlays, with **metrics** preferring faster-trained links. See [routing-openfabric.md](routing-openfabric.md) (path cost, pros/cons, hot-plug device classes).

Bonding is about **multiple netdevs on one host**; OpenFabric is about **reachability across a topology**. They complement; they are not substitutes.

### Ring path cost (simple picture)

OpenFabric does **not** send every flow the long way around a ring. For each possible path it **adds hop metrics** and picks the **lowest total** (same idea as OSPF/IS-IS SPF).

Example: nodes **A–B–C–D–E–A**. Most cables train at **20G**; only **C–D** is **10G**.

```text
Path C→D direct:     cost ≈ 10
Path C→B→A→E→D:     cost ≈ 5+5+5+5 = 20  →  direct wins
```

So C↔D stays on **C and D** under normal metrics. A/B/E become transit for that pair only if C–D is **down**, or metrics are set so the detour is cheaper (e.g. a very slow or penalized direct link).

Full worked table, formula, and calculator-style references: [routing-openfabric.md — Path cost and metrics](routing-openfabric.md#path-cost-and-metrics).

**Mixed sites:** Proxmox/Debian + Unraid on one ring/mesh — [fabric-proxmox-unraid.md](fabric-proxmox-unraid.md).
---

## Hubs and docks

See [peer-scenarios.md](peer-scenarios.md). Summary:

- **RJ45 on dock** → USB/PCIe Ethernet (eth*), not tbn  
- **Hub without a second host** → no ThunderboltIP LAN by itself  
- **Daisy-chain** → still not a free-for-all multi-access LAN for thunderbolt_net  

---

## LOCAL | REMOTE | REMOTE table

On the Thunderbolt tab:

- **LOCAL** — this Unraid (hostname, controller product, OEM)  
- Each **REMOTE** column — one peer; header is **tbnN** + `thunderboltN`  
- Per-column MAC/IP/state are **this host’s** end of that link  

---

## Related

- Speeds: [standards-and-speeds.md](standards-and-speeds.md)  
- Addressing: [addressing.md](addressing.md)  
- OpenFabric / FRR: [routing-openfabric.md](routing-openfabric.md)  
