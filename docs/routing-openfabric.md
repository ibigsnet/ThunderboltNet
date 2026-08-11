# Routing: FRR and OpenFabric (long-term support)

Thunderbolt Net brings up **host-to-host L3** on each `thunderboltN` path (static underlay, optional listening).  
**OpenFabric** (FRR’s `fabricd`) is the long-term **control plane** for multi-host interconnect: rings, meshes, multi-hop reachability, and hot-plug peers — without Unraid shipping a TB routing stack itself.

This is **supported product design**. Defaults favor interconnectivity; pure static remains a first-class override. Dual-cable bonding, advanced FRR protocols, and NPU/AI mini-PC interop are **in scope for later stages**, not ruled out.

---

## Why this exists on Unraid

- Stock Unraid does **not** ship FRR, OpenFabric, OSPF, or IS-IS.
- Multi-node labs need **automatic routes** when topology is more than one cable (ring, partial mesh, “through a neighbor”).
- Community stacks (e.g. Proxmox + TB + FRR OpenFabric for Ceph/backhaul) already prove the model; Unraid should get **native UX + defaults**, not only manual `vtysh`.
- Long-term **hot-plug** into modern USB4 / TB AI and mini workstations (e.g. **ASUS/AMD Strix Halo** class, **Gorgon Halo** class, **NVIDIA DGX Spark** class, and similar) should join a fabric with minimal reconfiguration.

Thunderbolt Net owns: TB discovery, underlay addressing, generated FRR config, metrics, Apply/start hooks, status UI, static escape hatches, and docs.  
**Community contributions** for packaging, metrics, and peer OS interop are welcome — see [CONTRIBUTING.md](../CONTRIBUTING.md).

---

## Should I enable OpenFabric?

### Advantages (why default **On** when FRR is available)

| Advantage | Detail |
|-----------|--------|
| **Multi-hop reachability** | Host A can reach C through B when A–B and B–C have TB links — no static routes on every node. |
| **Ring and partial-mesh topologies** | SPF recomputes when a link drops; a full ring has alternate paths (see [Topologies](#supported-topologies)). |
| **Hot-plug friendly** | New `thunderboltN` + participate → adjacency can form; routes appear without rewriting every peer’s static table. |
| **Interop with Linux FRR peers** | Same OpenFabric area / NET style as common TB mesh write-ups. |
| **Sane underlay still works** | tbn static IPs remain; OpenFabric is the control plane on top. |
| **Cost-aware paths (planned/metric)** | Prefer faster-trained links when multiple paths exist ([Path cost](#path-cost-and-metrics)). |

### Disadvantages (why you might turn it **Off**)

| Disadvantage | Detail |
|--------------|--------|
| **Extra software** | Needs FRR (`zebra` + `fabricd`). Unraid does not ship it; plugin detects or (later) installs a package. |
| **Host becomes a router** | IP forwarding for TB prefixes; not a full edge firewall. Mis-advertising br0 is avoided by defaults but advanced knobs exist. |
| **Two-node static is enough** | Single cable, two hosts, only file copy: pure static /24 is simpler; OpenFabric adds little. |
| **Peer without FRR** | No adjacency; underlay static still works. Fabric side idles cleanly if timers are sane. |
| **Operational surface** | Adjacencies, LSPs, metrics — CCIEs will feel at home; casual users rely on UI status + this doc. |
| **Not a substitute for TB domain health** | Wedged dual-cable fabric is still a **physical** recovery problem ([links-and-topology](links-and-topology.md)). |

**Rule of thumb**

- **Leave On** if you run (or plan) **3+ hosts**, **rings**, **multi-homed Unraid**, storage/VM backhaul over TB, or hot-plug laptops/mini-PCs into a lab fabric.  
- **Turn Off** (global) if you only ever use **one peer cable**, never multi-hop, and want zero routing daemons.

Static override is always available: global **OpenFabric = No**, or per-link **Participate = No**.

---

## How FRR and OpenFabric fit (loose deep dive)

### FRRouting (FRR)

[FRRouting](https://frrouting.org/) is an open-source IP routing suite for Linux (and other Unix). It provides:

| Daemon (examples) | Role |
|-------------------|------|
| **zebra** | RIB / kernel route install; interface to the OS |
| **fabricd** | **OpenFabric** (IS-IS–derived fabric routing) |
| **bgpd / ospfd / isisd / …** | Other IGPs/EGPs — available in FRR; Thunderbolt Net’s **first** control plane is OpenFabric, with room for optional protocols later |

Upstream: [github.com/FRRouting/frr](https://github.com/FRRouting/frr) · docs: [docs.frrouting.org](https://docs.frrouting.org/en/latest/)

On Unraid, FRR is **not** part of the base OS. The plugin **detects** an existing install and will **optionally manage a package** long-term (see [Packaging](#frr-on-unraid-packaging)).

### OpenFabric (`fabricd`)

[OpenFabric](https://docs.frrouting.org/en/latest/fabricd.html) (IETF draft [draft-white-openfabric](https://datatracker.ietf.org/doc/html/draft-white-openfabric)) is an **IS-IS–derived** link-state protocol aimed at **simple fabrics** (leaf/spine and similar), with efficient flooding and a reduced operational model vs full classic IS-IS.

Rough mental model (for operators who know Cisco IS-IS / OSPF):

| Concept | OpenFabric / FRR |
|---------|------------------|
| Link-state | Yes — each node floods topology; SPF computes paths |
| Area | `router openfabric <name/tag>` (plugin default area **1**) |
| NET | NSAP-style NET (plugin auto-generates; override allowed) |
| Interfaces | `ip router openfabric 1` on underlay TB ifaces; **passive** on loopback/router-id |
| Metric | Per-interface metric (lower preferred) — see [Path cost](#path-cost-and-metrics) |
| RIB → FIB | zebra installs kernel routes |

It is **not** Ethernet bridging and **not** Thunderbolt “domain fabric” in the sysfs sense. TB still creates **per-path** `thunderboltN` netdevs; OpenFabric runs **IP routing on top**.

### Underlay vs control plane

```text
  Application / SMB / NFS / Ceph / VMs
              │
              ▼
     Kernel IP stack  ◄── zebra installs fabric routes
              │
     OpenFabric (fabricd)  ◄── adjacencies, SPF, metrics
              │
     Underlay: thunderbolt0..N (+ optional bond-tb*)
              │
     Thunderbolt / USB4 physical path (trained rate, lanes)
```

---

## Path cost and metrics

Operators (and FRR) choose a **path** by **lowest total metric** along a path (SPF), same family of idea as OSPF cost or IS-IS metric — not Cisco EIGRP composite “composite metric,” but the same operational goal: **prefer better links**.

### What “cost” means here

| Term | Meaning in Thunderbolt Net |
|------|----------------------------|
| **Interface metric** | Integer OpenFabric metric on a TB (or bond) interface |
| **Path cost** | Sum of interface metrics along a candidate path |
| **Best path** | Lowest path cost (ECMP possible when FRR/kernel allow equal cost) |

### Default metric policy (product)

**Goal:** when a ring or mesh has multiple paths, prefer **faster-trained** TB links without manual tuning for the happy path.

Proposed formula (implemented as defaults; overridable per link):

```text
reference_mbps = 100000          # 100 Gbit/s reference (configurable)
bandwidth_mbps = trained_tx_or_min(tx,rx) in Mbit/s   # from TB sysfs / status
metric = max(1, round(reference_mbps / max(bandwidth_mbps, 1)))
```

Examples with reference **100000**:

| Trained class (approx.) | bandwidth_mbps | metric |
|-------------------------|----------------|--------|
| ~80 G class | 80000 | 1–2 |
| ~40 G class (dual-ish path) | 40000 | 3 |
| ~20 G / common 1-lane | 20000 | 5 |
| ~10 G | 10000 | 10 |
| unknown / down | — | high sentinel or interface not in fabric |

**Why inverse bandwidth?** Same spirit as OSPF auto-cost reference bandwidth: faster link → lower cost → SPF prefers it on a **ring** when both directions around the ring are available.

### SPF in one sentence

For each candidate path, **add the metrics of every hop**, then **use the path with the lowest total**. That is the whole idea (same family as OSPF/IS-IS SPF — not EIGRP composite metric).

### Worked example: five-node ring (C–D is slower)

```text
        20G      20G      10G      20G      20G
   A -------- B -------- C -------- D -------- E -------- (back to A)
```

With reference **100000**, approximate interface metrics:

| Link class | Metric |
|------------|--------|
| 20G hops (A–B, B–C, D–E, E–A, …) | **5** each |
| 10G hop (C–D only) | **10** |

**Question:** how does the fabric treat traffic that must be *routed* from C toward D (e.g. loopback-to-loopback, or another prefix learned via OpenFabric)?

| Candidate path | Path cost (sum of hop metrics) |
|----------------|--------------------------------|
| **Direct** C → D | **10** |
| **Long way** C → B → A → E → D | 5+5+5+5 = **20** |

**Result: use direct C–D** (10 &lt; 20). Nodes A, B, and E are **not** transit for normal C↔D flows.

That answers the common fear: *“Won’t the ring shove C–D traffic all the way around and load every host?”*  
**Not with sane metrics.** A slower *direct* link still beats several faster hops unless its metric is large enough that the detour wins (or the direct link is down).

| If C–D were… | C–D metric (approx.) | vs long way (20) | Winner |
|--------------|----------------------|------------------|--------|
| 10G | 10 | 20 | **Direct** |
| 20G | 5 | 20 | **Direct** |
| ~1G | ~100 | 20 | **Long way** (detour is cheaper) |
| Down / withdrawn | — | 20 | **Long way** (failover — why you built a ring) |

**On-link vs SPF:** if C and D only talk using addresses **on the shared C–D underlay subnet**, the kernel often uses a **connected** route and never “picks” the long arc. SPF path choice matters most for **remote** prefixes (other nodes, loopbacks, multi-hop). Either way, adjacent 10G neighbors are **not** meant to hairpin around the ring by default.

**Manual policy:** set C–D metric very high if you *want* the direct link to be backup-only (CCIE-style admin cost). Default auto-metric will not do that for a healthy 10G vs four 20G hops.

### Calculators and “doing the math”

There is no special Thunderbolt calculator required. Path math is:

```text
1) metric per link ≈ reference / trained_Mbps
2) path cost = sum of metrics on that path
3) best path = lowest path cost
```

Handy references (OSPF uses the **same inverse-bandwidth idea**; OpenFabric metrics are the same *style* of thinking):

| Resource | Use |
|----------|-----|
| Pencil / spreadsheet | List links, assign metrics, sum two or three candidate paths — enough for lab rings |
| [Study-CCNA: OSPF cost](https://study-ccna.com/ospf-cost-metric/) | Clear formula + examples (`cost = reference / bandwidth`) |
| [NetworkLessons: OSPF reference bandwidth](https://networklessons.com/ospf/ospf-reference-bandwidth) | Why reference Mbps matters when links are multi-gig |
| [ipwithease OSPF cost notes](https://ipwithease.com/ospf-cost-calculation/) | Another worked cost walkthrough |
| Plugin UI (when FRR is up) | Per-link metric on Thunderbolt / tbn tabs; later: RIB / neighbor views via `vtysh` |

You do **not** need a Cisco OSPF simulator for a TB ring — five nodes and a table of metrics is enough to reason about C→D vs the long arc.

### Manual override

| Control | Use |
|---------|-----|
| Global **reference_mbps** | Scale all auto metrics |
| Per-link **metric mode** | `auto` (default) · `manual` |
| Per-link **metric** | Fixed integer when manual |
| Bond members | Metric on **bond-tbN** when bonding is used; slaves not double-advertised |

### What we do **not** claim

- Metric does not invent dual-lane TCP or fix a wedged TB domain.  
- Metric does not equal “user-visible Gbit/s” after TCP/CPU overhead ([standards-and-speeds](standards-and-speeds.md)).  
- Until FRR is running, metrics are **planned/generated only** (dry-run conf still useful).  
- Default metrics avoid hairpinning adjacent neighbors around a ring when a direct path is cheaper; they do not replace capacity planning for *transit* when a link is intentionally expensive or down.
---

## Supported topologies

| Topology | Underlay | Control plane | Notes |
|----------|----------|---------------|-------|
| **P2P single cable** | One `thunderboltN` each side | OpenFabric optional | Static alone is fine; fabric still works as 2-node |
| **Star** | Hub node multi-homed | OpenFabric | Leaves learn each other via hub |
| **Partial mesh** | Multiple TB paths | OpenFabric | SPF + metrics |
| **Full ring** | Each node 2 neighbors | OpenFabric | **First-class goal** — link loss uses alternate arc |
| **Dual-homed Unraid** | 2+ TB peers | OpenFabric | Different peers ⇒ multiple netdevs |
| **Dual cable same peer** | Often **one** netdev today | Bonding roadmap | See [Bonding](#bonding-and-dual-cable-roadmap); not required for rings |

```text
Ring example (3 nodes):

   [Unraid A] ----tb---- [Linux B]
       |                     |
       +--------tb--------[DGX/Strix C]

OpenFabric: A↔C traffic can go direct or A–B–C; metric prefers better trained links.
```

**Full ring** is an explicit long-term support target: designs, lab tests, and docs should keep rings working (timers, metrics, forwarding, hot-unplug of one chord).

---

## Bonding and dual-cable (roadmap — not a non-goal)

**Today (honest limits):**

- Linux often exposes **one** host-net path for two cables between the **same** pair; bonding cannot invent a second slave.
- TB slaves often reject `set_mac`; many bond modes fail; dual-plug can **wedge** the TB domain until all cables are cleared.
- Plugin bonding is **available when ≥2 live `thunderbolt*`** exist (typically **two different peers**, or rare multi-path cases). Prefer **active-backup**. Names: `bond-tb0`, … (not Unraid `bond0`).

**Later (in scope):**

- Better dual-path detection when the kernel exposes two netdevs to the same peer.
- Bond + OpenFabric: advertise **bond-tbN**, metric from aggregate or primary.
- Documented lab procedures for redundancy vs throughput modes.
- No promise of 2× sticker TCP from dual-cable until hardware+kernel actually provide two usable paths.

See [links-and-topology.md](links-and-topology.md). Bonding is **not** excluded from the project.

---

## Hot-plug and target device classes

Long-term **native fabric join** when a peer appears on TB/USB4 host-net:

| Class (examples) | Intent |
|------------------|--------|
| **ASUS / AMD Strix Halo** mini-PCs & laptops | USB4 host-net peer; hot-plug into Unraid fabric |
| **Gorgon Halo** class systems | Same — document trained rates + FRR interop |
| **NVIDIA DGX Spark** (and similar Grace/Blackwell mini systems) | High-bandwidth peer for AI/storage backhaul over TB/USB4 where Linux exposes `thunderbolt_net` |
| **Generic Linux + FRR** | Full OpenFabric interop |
| **macOS / Windows** | Underlay static / best-effort; no fabricd on peer |

“Native” means: plugin remembers peer + last plan, optional auto OpenFabric participate, status shows adjacency, docs cover the device class — **not** a guarantee every OEM ships a perfect TBIP stack on day one.

Stages: detect peer → underlay IP (manual then auto-restore) → OpenFabric adjacency → metric from trained rate.

---

## Architecture

```text
┌──────────────────────────────────────────────────────────┐
│ Thunderbolt Net                                          │
│  · TB sysfs / tbnN underlay                              │
│  · metric policy + OpenFabric conf generate              │
│  · FRR detect / (later) package                          │
│  · Apply, array-start, hotplug hooks                     │
│  · UI: status, neighbors, routes, overrides              │
└────────────────────────────┬─────────────────────────────┘
                             │
                             ▼
┌──────────────────────────────────────────────────────────┐
│ FRR: zebra + fabricd                                     │
│  · router openfabric 1                                   │
│  · TB ifaces + lo /32 (passive)                          │
│  · interface metrics from policy                         │
└────────────────────────────┬─────────────────────────────┘
                             ▼
                   thunderbolt* / bond-tb* underlay
```

### Address model (default direction)

| Piece | Default direction |
|-------|-------------------|
| **Router ID / loopback** | Stable host `/32` on `lo` (auto or configured) |
| **Link addresses** | Existing tbn plan (`10.255.N.x/24` or `/30`); unique per link |
| **OpenFabric NET** | Auto from router-id / machine-id; advanced override |
| **Forwarding** | IPv4 forward on when OpenFabric enabled (IPv6 if fabric IPv6 on) |

---

## Product defaults

| Setting | Default | Override |
|---------|---------|----------|
| **OpenFabric** | **Yes** when FRR available | Global No |
| **Per-link participate** | **Yes** | no / passive |
| **Metric** | **auto** from trained rate | manual |
| **Auto-install FRR** | **Yes** when package path exists | no / system-only |
| **IPv6 fabric** | Yes if addresses exist | force v4 |
| **Default route via TB** | **No** | existing tbn option |
| **br0/eth0 in fabric** | **No** | advanced passive (rare) |

If FRR is missing: static underlay only; UI states *OpenFabric preferred; FRR not available*.

---

## FRR on Unraid (packaging) — separate companion plugin

Thunderbolt Net does **not** install FRR packages. That work is intentionally **split out** (same idea as Unassigned Devices vs optional companions): package install is more invasive than TB Settings UI.

| Plugin | Repo | Role |
|--------|------|------|
| **UnraidFRR** | [ibigsnet/UnraidFRR](https://github.com/ibigsnet/UnraidFRR) | Opt-in: place/install FRR `.txz`, enable `zebra` / `fabricd`, array-start hooks |
| **Thunderbolt Net** | this repo | TB underlay + OpenFabric *policy* when FRR is already present |

| Option | Who | Status |
|--------|-----|--------|
| **A. UnraidFRR packages** | Companion + flash `packages/` | Scaffold ready; real `.txz` builds fill in over time |
| **B. System / hand-installed FRR** | Any source of `vtysh`/`fabricd` | Thunderbolt Net **detects** and uses it |
| **C. A + B** | Product target | Detect first; point users at UnraidFRR when missing |

**Rules:** neither plugin `require`s the other’s PHP; UnraidFRR works **without** Thunderbolt Net; Thunderbolt Net without FRR stays on **static underlay**.

Install UnraidFRR (after the repo is published):

`https://raw.githubusercontent.com/ibigsnet/UnraidFRR/main/unraidfrr.plg`

OpenFabric conf this plugin writes lives under `/boot/config/plugins/ThunderboltNet/` and uses marked FRR blocks:

```text
! BEGIN ThunderboltNet OpenFabric
...
! END ThunderboltNet OpenFabric
```

Integration notes: [UnraidFRR docs](https://github.com/ibigsnet/UnraidFRR/blob/main/docs/integration-thunderboltnet.md).  
**LAN safety:** UnraidFRR/FRR is host-wide, not TB-only; defaults avoid eth/br enrollment. See [UnraidFRR scope & safety](https://github.com/ibigsnet/UnraidFRR/blob/main/docs/scope-and-safety.md). Thunderbolt Net only marks OpenFabric on `thunderbolt*` + loopback (not br0).
---

## Config keys

Global (`ThunderboltNet.cfg`):

```ini
openfabric_enable="yes"
; Reserved / UI hint only — package install lives in UnraidFRR, not this plugin
openfabric_auto_install_frr="yes"
openfabric_ipv6="yes"
openfabric_area="1"
openfabric_router_id=""
openfabric_net=""
openfabric_metric_reference_mbps="100000"
openfabric_hello_multiplier=""
```

Per-iface:

```ini
OPENFABRIC_PARTICIPATE="yes"    ; yes | no | passive
OPENFABRIC_METRIC_MODE="auto"   ; auto | manual
OPENFABRIC_METRIC=""            ; when manual
```

---

## UI

**Thunderbolt overview → Routing (OpenFabric)**

- Enable OpenFabric (default Yes)
- FRR: installed / version / running / errors
- Router ID + NET (generated + advanced)
- Metric reference; link table with auto/manual metric
- Neighbors / routes (`vtysh` when available)
- Generated conf preview (dry-run)
- Links to this doc + FRR upstream

**tbnN → Participate / metric mode / metric**

---

## Lifecycle

| Event | When OpenFabric enabled |
|-------|-------------------------|
| Install / array start | Detect/install FRR; write conf; enable fabricd; reload |
| tbn Apply | Refresh underlay + iface stanzas + metrics; reload |
| Link up / hotplug | Participate if yes; adjacency |
| Link down | Withdraw via OpenFabric; do not touch br0 |
| OpenFabric → No | Remove TBN markers / stop managing fabricd if we own it |
| Remove plugin | Stop managing; do not wipe unrelated FRR |

---

## Interop matrix

| Peer | Expectation |
|------|-------------|
| Unraid + Thunderbolt Net | Happy path, both default On |
| **Proxmox / Debian + FRR fabricd** | First-class mixed fabric with Unraid ([fabric-proxmox-unraid.md](fabric-proxmox-unraid.md)) |
| Linux + FRR fabricd | Full OpenFabric interop |
| Strix Halo / Gorgon Halo / DGX Spark (Linux) | Host-net + FRR when available; document per platform |
| macOS / Windows | Static underlay; no fabricd |
| FRR-less Linux | Static only |

### Mixed Unraid + non-Unraid fabrics

Sites often combine **Unraid** with **Proxmox or other Linux** on one TB/OpenFabric mesh (ring or mesh, multi-hop, failover).  
Reference design, example five-node ring, shared parameters, verification phases: **[fabric-proxmox-unraid.md](fabric-proxmox-unraid.md)**.

Sample peer `vtysh` patterns stay aligned with generated conf (area, passive lo, metrics). Contributions of tested peer snippets are welcome.

---

## Safety

- Never steal **default route** to TB unless tbn option enabled.
- Forwarding on = **router for TB prefixes**, not “replace Unraid firewall.”
- Do not put br0 in OpenFabric by default.
- OpenFabric does not unwedge a dual-cable TB domain — clear cables physically when needed.

---

## Other FRR protocols (later, not excluded)

OpenFabric is **stage-1 control plane**. Optional later (contrib-friendly):

- Classic **IS-IS** or **OSPF** on TB underlay for sites that standardize there  
- **BGP** unnumbered / EVPN-style designs for advanced labs  
- Always behind explicit enable; never replace OpenFabric defaults without a migration note  

---

## Implementation stages

| Stage | Deliverable |
|-------|-------------|
| **0** | Design + release process docs |
| **1** | Detect FRR; keys; UI; conf generate + dry-run; metric policy |
| **2** | Apply: write conf, fabricd, participate; defaults On |
| **3** | **UnraidFRR** companion: real FRR `.txz` builds + CA listing |
| **4** | Neighbors/routes UI; hot-plug polish; device-class notes |
| **5** | Peer last-plan restore + fabric coexist |
| **6** | Bonding improvements + bond-in-fabric metrics |
| **7** | Ring lab validation matrix; optional OSPF/BGP contrib hooks |

Versioning: [RELEASES.md](../RELEASES.md).

---

## Upstream and further reading

| Resource | URL |
|----------|-----|
| FRRouting project | https://frrouting.org/ |
| FRR source | https://github.com/FRRouting/frr |
| OpenFabric (fabricd) docs | https://docs.frrouting.org/en/latest/fabricd.html |
| OpenFabric IETF draft | https://datatracker.ietf.org/doc/html/draft-white-openfabric |
| FRR IS-IS docs (related) | https://docs.frrouting.org/en/latest/isisd.html |
| Example community TB + OpenFabric notes | Search “FRR OpenFabric Thunderbolt” (e.g. Proxmox/Ceph mesh write-ups) |

---

## Related

- [addressing.md](addressing.md) · [links-and-topology.md](links-and-topology.md) · [settings-reference.md](settings-reference.md)  
- [standards-and-speeds.md](standards-and-speeds.md) · [RELEASES.md](../RELEASES.md) · [CONTRIBUTING.md](../CONTRIBUTING.md)  
