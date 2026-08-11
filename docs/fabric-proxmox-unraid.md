# Target fabric: Proxmox A/B/C + Unraid D/E

Long-term product goal: **one OpenFabric (FRR) mesh** over Thunderbolt/USB4 host-net so Proxmox and Unraid nodes are first-class peers — full multi-hop, rings, and failover — not Unraid-only islands.

This is a **supported direction**, not a side experiment.

---

## Target topology (your plan)

```text
Nodes:
  A, B, C  =  Proxmox (Debian-based, apt FRR)
  D, E     =  Unraid  (Thunderbolt Net + UnraidFRR for packages)

Example ring (each link = TB/USB4 host-net path):

        TB        TB        TB        TB        TB
   A -------- B -------- C -------- D -------- E -------- A
   |______________________________________________________|

Any physical cabling that forms a ring, partial mesh, or star is fine;
OpenFabric cares about L3 underlay adjacencies + metrics, not brand logos.
```

| Node | OS | Underlay (TB IP) | Control plane |
|------|-----|------------------|---------------|
| **A, B, C** | Proxmox | Linux `thunderbolt_net` + static (or your net plan) | FRR `fabricd` via **apt** (`frr`, enable fabricd) |
| **D, E** | Unraid | **Thunderbolt Net** tbn tabs | FRR via **UnraidFRR** packages + TBN OpenFabric conf |

**Interop rule:** same OpenFabric **area**, compatible **NET / router-id** plan, underlay addresses that don’t collide, metrics that make sense on mixed 10G/20G links (see [routing-openfabric.md](routing-openfabric.md#path-cost-and-metrics)).

---

## What “full interoperability” means here

| Capability | Intent |
|------------|--------|
| **Adjacent ping** | Any direct TB pair (e.g. C–D, D–E) works on underlay alone |
| **Multi-hop** | e.g. Proxmox A reaches Unraid E via B–C–D without static routes on every hop |
| **Ring failover** | One TB cable unplugged → SPF uses the other arc |
| **Mixed metrics** | Slower link (e.g. C–D 10G) preferred when direct is still cheapest; detour only if cheaper or direct is down |
| **No LAN hijack** | br0 / vmbr0 / Proxmox management stay off the fabric unless you explicitly opt in |
| **Services** | Optional later: SMB/NFS/Ceph on loopbacks or fabric /32s — underlay + OpenFabric first |

Not required for “interop day one”: dual-cable bonding, USB4STREAM, or putting VMs’ bridges into OpenFabric.

---

## Who owns what

```text
┌───────────── Proxmox A/B/C ─────────────┐
│  TB underlay: OS / ifupdown / SDN / NM  │
│  FRR: apt install frr → fabricd=yes     │
│  Conf: vtysh / /etc/frr/frr.conf        │
│  (align area, NET, lo /32, metrics)     │
└──────────────────┬──────────────────────┘
                   │  OpenFabric (fabricd)
                   │  over thunderbolt*
┌──────────────────▼──────────────────────┐
│  Unraid D/E                             │
│  TB underlay: Thunderbolt Net           │
│  FRR packages: UnraidFRR (opt-in)       │
│  OpenFabric stanzas: Thunderbolt Net    │
│    (marked block; TB ifaces + lo only)  │
└─────────────────────────────────────────┘
```

| Layer | Proxmox | Unraid |
|-------|---------|--------|
| Physical TB | Kernel `thunderbolt` + `thunderbolt_net` | Same |
| Underlay IP | Manual / your automation | Thunderbolt Net tbn UI |
| FRR install | `apt install frr` | **UnraidFRR** |
| fabricd enable | `/etc/frr/daemons` | UnraidFRR defaults |
| OpenFabric policy | Hand conf or your Ansible | Thunderbolt Net generate/apply |
| LAN (vmbr0 / br0) | Keep out of fabric by default | Keep out by default |

---

## Shared design parameters (must match fabric-wide)

Document these as a **lab contract** so five nodes agree:

| Parameter | Product default (Unraid TBN) | Proxmox should use |
|-----------|------------------------------|--------------------|
| OpenFabric area / tag | `1` | Same `router openfabric 1` |
| Router-id | `/32` on `lo` (auto `10.254.x.y` or set) | Unique `/32` per node (document table) |
| NET | Auto from router-id or override | Unique NET per node; same area prefix style (e.g. `49.0001.…`) |
| Metric policy | `ref / trained_Mbps` (ref default 100000) | Same formula or manual metrics that match |
| Underlay subnets | Unique per link (`10.255.N.0/24` style) | Same plan — **no** duplicate /24 on two links |
| IPv6 fabric | Optional | Match fabric decision (v4-only is fine for phase 1) |
| Hello timers | TBN generates mild defaults | Align if adjacency flaky |

### Example address plan (illustration only)

| Node | Role | Loopback /32 (router-id) |
|------|------|---------------------------|
| A | Proxmox | 10.254.0.1 |
| B | Proxmox | 10.254.0.2 |
| C | Proxmox | 10.254.0.3 |
| D | Unraid | 10.254.0.4 |
| E | Unraid | 10.254.0.5 |

Link underlays: one /30 or /24 **per TB cable** (not shared across cables).  
Traffic A→E uses OpenFabric to pick path; destination often the **loopback**, not a random underlay hop IP.

---

## Proxmox side (reference sketch)

Not a full Proxmox product — operators configure FRR themselves; we keep **interop parity** with what Thunderbolt Net emits.

```bash
# Example — adjust for your Proxmox version
apt update && apt install -y frr
# /etc/frr/daemons → zebra=yes, fabricd=yes, staticd=yes (others no unless needed)
systemctl restart frr
```

Minimal ideas (align with TBN generator):

```text
interface lo
 ip address 10.254.0.1/32
 ip router openfabric 1
 openfabric passive
!
interface enX   ! or thunderbolt0 — use the real TB netdev name
 ip router openfabric 1
 openfabric metric <auto-or-manual>
!
router openfabric 1
 net 49.0001.xxxx.xxxx.xxxx.00
!
```

Community TB+OpenFabric notes (Proxmox/Ceph meshes) are useful background; our contract is **same fabricd area + metrics**, not copying any one gist blindly.

Upstream: [FRR fabricd](https://docs.frrouting.org/en/latest/fabricd.html) · [FRRouting](https://frrouting.org/)

---

## Unraid side

1. **Thunderbolt Net** — underlay tbn IPs, OpenFabric **On**, participate on each TB link.  
2. **UnraidFRR** — packages so `vtysh` / `fabricd` exist ([scope & safety](https://github.com/ibigsnet/UnraidFRR/blob/main/docs/scope-and-safety.md): host-wide FRR, LAN-safe defaults).  
3. Apply TBN → marked conf on TB ifaces + lo; does not enroll br0.

---

## Lab phases (your five-node goal)

| Phase | Goal | Nodes needed |
|-------|------|----------------|
| **L0** | Unraid D (or E) alone: TBN + idle UnraidFRR | 1 Unraid |
| **L1** | UnraidFRR packages + `vtysh` | 1 Unraid |
| **L2** | Proxmox A ↔ Unraid D **static** TB ping | 1 PVE + 1 Unraid |
| **L3** | Same pair **OpenFabric** adjacency; loopback reachability | 1 PVE + 1 Unraid |
| **L4** | Add B or E — 3-node path, multi-hop | 2+2 or 3+1 |
| **L5** | Full A–B–C–D–E ring; pull one cable; SPF failover | 3 PVE + 2 Unraid |
| **L6** | Mixed metrics (e.g. one 10G hop); confirm no hairpin waste | Full set |

When you re-hook lab gear, we start at **L2/L3** unless packages aren’t ready yet (**L0/L1** first).

---

## Success criteria (interop “done” for a release)

- [ ] Documented area / NET / loopback table for A–E  
- [ ] D and E: TBN OpenFabric running with UnraidFRR  
- [ ] A/B/C: fabricd adjacencies to neighbors  
- [ ] `show openfabric neighbor` / `show openfabric route` consistent on PVE and Unraid  
- [ ] Multi-hop: e.g. A loopback ↔ E loopback with C–D or other mid path  
- [ ] Failover: break one ring link, traffic recovers on alternate arc  
- [ ] br0/vmbr0 default routes unchanged  

---

## Non-goals (for this fabric story)

- Replacing Proxmox SDN UI with an Unraid plugin  
- Shipping a Proxmox `.deb` from this repo (docs + conf parity only, unless we later add a small helper project)  
- Forcing Ceph public/cluster networks onto TB without an explicit design pass  
- Dual-cable bonding as a substitute for OpenFabric multi-hop  

---

## Related

- [routing-openfabric.md](routing-openfabric.md) — FRR, metrics, rings, UnraidFRR split  
- [links-and-topology.md](links-and-topology.md) — TB path limits  
- [peer-scenarios.md](peer-scenarios.md) — peer OS matrix  
- [DEVELOPMENT.md](DEVELOPMENT.md) — stage lab checklist  
- UnraidFRR: https://github.com/ibigsnet/UnraidFRR  
