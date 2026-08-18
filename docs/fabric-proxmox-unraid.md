# Mixed fabric: Proxmox (or Debian) + Unraid

Many labs run **Thunderbolt/USB4 host-net** between Unraid and other Linux systems (often **Proxmox**). This document describes a **general multi-node interop scenario**: one **OpenFabric (FRR)** mesh so Unraid and non-Unraid nodes are first-class peers — multi-hop, rings, and failover — not Unraid-only islands.

**When this guide applies**

- You cable several machines (Unraid + Proxmox/Debian) in a **ring or mesh**, not only one peer cable.  
- You want host A to reach host C **through** B when there is no direct A–C link.  
- You already run (or plan) **FRR OpenFabric** on the non-Unraid side (`apt install frr`, `fabricd`).

**When you can skip it**

- Unraid ↔ one Proxmox node, **one cable**, only static IPs and file copy / SMB — use Thunderbolt Net underlay alone; no FabricRouting required.

Use it as a **reference design and test matrix**. Node counts, brands, and cabling will differ per site.

**Unraid side roles:** [FabricRouting](https://github.com/ibigsnet/FabricRouting) installs FRR packages; **Thunderbolt Net** owns tbn underlay + OpenFabric policy. See [routing-openfabric.md](routing-openfabric.md#what-each-piece-does-plugin-roles).

---

## Contents

- [Example topology (illustrative)](#example-topology-illustrative)
- [What “full interoperability” means](#what-full-interoperability-means)
- [Who owns what](#who-owns-what)
- [Shared design parameters (fabric-wide)](#shared-design-parameters-fabric-wide)
- [Proxmox / Debian side (reference sketch)](#proxmox-debian-side-reference-sketch)
- [Unraid side](#unraid-side)
- [Suggested verification phases (any lab size)](#suggested-verification-phases-any-lab-size)
- [Success criteria (interop “good enough” for a release)](#success-criteria-interop-good-enough-for-a-release)
- [Non-goals](#non-goals)
- [Related](#related)

## Example topology (illustrative)

Five nodes in a **ring** is a convenient teaching example (any ring, mesh, or star works the same way for OpenFabric):

```text
  Node roles in this example only:
    P1, P2, P3  =  Proxmox / Debian-class Linux (apt FRR)
    U1, U2      =  Unraid (Thunderbolt Net + optional Fabric Routing)

      Thunderbolt   Thunderbolt   Thunderbolt   Thunderbolt   Thunderbolt
  P1 ---------- P2 ---------- P3 ---------- U1 ---------- U2 ---------- P1
  |_____________________________________________________________________|

Physical cabling can be a ring, partial mesh, or star.
OpenFabric uses L3 underlay adjacencies and metrics — not chassis brands.
```

| Example node | OS class | Underlay (Thunderbolt IP) | Control plane |
|--------------|----------|------------------|---------------|
| **P1–P3** | Proxmox / Debian Linux | `thunderbolt_net` + static (or site automation) | FRR `fabricd` via **apt** (or equivalent) |
| **U1–U2** | Unraid | **Thunderbolt Net** tbn tabs | FRR via **Fabric Routing** (packages) + TBN OpenFabric conf |

**Interop rule (any site):** same OpenFabric **area**, compatible **NET / router-id** plan, non-overlapping underlay subnets, metrics that behave sensibly on mixed trained rates (see [routing-openfabric.md](routing-openfabric.md#path-cost-and-metrics)).

Smaller sites still apply: e.g. **one Proxmox + one Unraid**, or **two Unraid only**, with the same contract.

---

## What “full interoperability” means

| Capability | Intent |
|------------|--------|
| **Adjacent ping** | Any direct Thunderbolt pair works on underlay alone |
| **Multi-hop** | Reach a node that is not directly cabled, via intermediate fabric routers |
| **Ring / mesh failover** | One Thunderbolt path drops → SPF uses another path when topology allows |
| **Mixed metrics** | Slower direct link can still beat a long detour; detour wins if cheaper or direct is down |
| **No LAN hijack** | br0 / vmbr0 / primary management stay off the fabric unless explicitly opted in |
| **Services** | Optional later: share storage/apps over loopbacks or fabric prefixes — underlay + OpenFabric first |

Not required for initial interop: dual-cable bonding, USB4STREAM, or putting VM bridges into OpenFabric.

---

## Who owns what

```text
┌────────────── Proxmox / Debian peers ──────────────┐
│  Thunderbolt underlay: OS / ifupdown / SDN / NM    │
│  FRR: distro packages → fabricd=yes                │
│  Conf: vtysh / /etc/frr/frr.conf                   │
│  (align area, NET, lo /32, metrics)                │
└──────────────────────────┬─────────────────────────┘
                           │  OpenFabric (fabricd)
                           │  over thunderbolt*
┌──────────────────────────▼─────────────────────────┐
│  Unraid                                            │
│  Thunderbolt underlay: Thunderbolt Net             │
│  FRR packages: FabricRouting (opt-in)              │
│  OpenFabric stanzas: Thunderbolt Net               │
│    (marked block; Thunderbolt interfaces + lo only)│
└────────────────────────────────────────────────────┘
```

| Layer | Proxmox / Debian | Unraid |
|-------|------------------|--------|
| Physical Thunderbolt | Kernel `thunderbolt` + `thunderbolt_net` | Same |
| Underlay IP | Manual / site automation | Thunderbolt Net tbn UI |
| FRR install | `apt install frr` (typical) | **Fabric Routing** |
| fabricd enable | `/etc/frr/daemons` | FabricRouting defaults |
| OpenFabric policy | Hand conf or automation | Thunderbolt Net generate/apply |
| LAN (vmbr0 / br0) | Keep out of fabric by default | Keep out by default |

---

## Shared design parameters (fabric-wide)

Agree these across **all** nodes in a given fabric:

| Parameter | Unraid (Thunderbolt Net) default direction | Peers should match |
|-----------|--------------------------------------------|--------------------|
| OpenFabric area / tag | `1` | Same `router openfabric 1` |
| Router-id | `/32` on `lo` (auto or set) | Unique `/32` per node |
| NET | Auto from router-id or override | Unique NET per node; consistent area style (e.g. `49.0001.…`) |
| Metric policy | `ref / trained_Mbps` (ref default **20000** ≈ 20G Thunderbolt class) | Same idea or explicit manuals that agree |
| Underlay subnets | Unique per link | **No** duplicate subnet on two Thunderbolt links |
| IPv6 fabric | Optional | Match site choice (IPv4-only is fine early) |
| Hello timers | Mild defaults from TBN | Align if adjacencies flap |

### Example loopback plan (illustration only)

| Example node | Loopback /32 (router-id) |
|--------------|---------------------------|
| P1 | 10.254.0.1 |
| P2 | 10.254.0.2 |
| P3 | 10.254.0.3 |
| U1 | 10.254.0.4 |
| U2 | 10.254.0.5 |

Link underlays: one /30 or /24 **per Thunderbolt cable**. Multi-hop reachability is typically to **loopbacks** (or other fabric prefixes), not only on-link underlay IPs.

---

## Proxmox / Debian side (reference sketch)

This project does **not** ship a Proxmox plugin. Peers configure FRR themselves; Thunderbolt Net aims for **conf parity** with common fabricd setups.

```bash
# Example — adjust for your distro version
apt update && apt install -y frr
# /etc/frr/daemons → zebra=yes, fabricd=yes (others as needed)
systemctl restart frr
```

Minimal ideas (align with TBN generator):

```text
interface lo
 ip address 10.254.0.1/32
 ip router openfabric 1
 openfabric passive
!
interface thunderbolt0   ! use the real Thunderbolt netdev name
 ip router openfabric 1
 openfabric metric <auto-or-manual>
!
router openfabric 1
 net 49.0001.xxxx.xxxx.xxxx.00
!
```

Upstream: [FRR fabricd](https://docs.frrouting.org/en/latest/fabricd.html) · [FRRouting](https://frrouting.org/)

---

## Unraid side

1. **Thunderbolt Net** — underlay tbn IPs; OpenFabric **On**; participate per Thunderbolt link.  
2. **Fabric Routing** (optional) — packages so `vtysh` / `fabricd` exist.  
3. Apply TBN → marked conf on Thunderbolt interfaces + lo; does not enroll br0 by default.

---

## Suggested verification phases (any lab size)

Scale the same steps up or down:

| Phase | Goal | Minimum gear |
|-------|------|----------------|
| **L0** | Unraid plugin smoke (UI, static Thunderbolt optional) | 1 Unraid |
| **L1** | FRR live on Unraid (`vtysh`) | 1 Unraid + packages |
| **L2** | Heterogeneous **static** Thunderbolt (e.g. Proxmox ↔ Unraid) | 1 Linux + 1 Unraid |
| **L3** | **OpenFabric** adjacency + loopback reachability | Same pair + FRR both sides |
| **L4** | Multi-hop (3+ nodes) | 3+ hosts |
| **L5** | Ring or mesh **failover** (unplug one path) | Topology with alternate path |
| **L6** | Mixed trained rates / metrics | Multi-link lab |

---

## Success criteria (interop “good enough” for a release)

- [ ] Documented area / NET / loopback plan for the site  
- [ ] Unraid: TBN OpenFabric with FRR present (FabricRouting or other)  
- [ ] Non-Unraid peers: fabricd adjacencies to neighbors  
- [ ] `show openfabric neighbor` / `show openfabric route` sensible on both platforms  
- [ ] Multi-hop between at least one Unraid and one non-Unraid loopback  
- [ ] Failover when an alternate path exists  
- [ ] Primary LAN default routes unchanged  

---

## Non-goals

- Replacing Proxmox SDN UI from this repo  
- Shipping Proxmox packages here (docs + conf parity; helpers only if needed later)  
- Mandating Ceph or any one storage stack on the fabric  
- Treating dual-cable bonding as a substitute for OpenFabric multi-hop  

---

## Related

- [routing-openfabric.md](routing-openfabric.md) — FRR, metrics, rings, Fabric Routing split  
- [links-and-topology.md](links-and-topology.md) — Thunderbolt path model  
- [peer-scenarios.md](peer-scenarios.md) — peer OS matrix  
- Fabric Routing: https://github.com/ibigsnet/FabricRouting  

