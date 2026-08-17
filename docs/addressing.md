# IPv4 addressing on Thunderbolt links

Each live kernel interface `thunderboltN` (Settings tab **tbnN**) is its own L2 segment to **one** remote peer path. Addressing is almost always **static**.

**Peer plans:** desired local IPv4 can also be stored per **remote fabric UUID** (Peers tab). On reconnect, that plan is applied to **whichever** `thunderboltN` the peer appears on — so renumber (tbn0↔tbn1) does not strand the IP on the wrong host. See [peers-and-plans.md](peers-and-plans.md).

---

## Contents

- [Product defaults](#product-defaults)
- [Path-slot cfg vs peer plan](#path-slot-cfg-vs-peer-plan)
- [Small LAN (/24) vs point-to-point (/30)](#small-lan-24-vs-point-to-point-30)
- [Hard rule: unique subnet per Thunderbolt link](#hard-rule-unique-subnet-per-thunderbolt-link)
- [Examples](#examples)
- [DHCP](#dhcp)
- [OpenFabric / multi-hop](#openfabric-multi-hop)
- [Related](#related)

## Path-slot cfg vs peer plan

| Store | Key | Use |
|-------|-----|-----|
| `ifaces/thunderboltN.cfg` | Interface **name** | Eth-like tab editor; reapply when no peer plan |
| Peer plan in `peers.json` | Remote **UUID** | Preferred on hotplug/boot when that peer is live on any path |

**Apply on a tbn tab** while linked writes both: path cfg **and** peer plan for the remote host.

## Product defaults

| Item | Default | Rationale |
|------|---------|-----------|
| Assignment | Static | No DHCP server on a pure host↔host cable |
| Suggestion | `10.255.N.2` for `thunderboltN` | Unique third octet per link |
| Mask | **/24** (`255.255.255.0`) | Familiar; room for a few extra addresses (VM, alias) |
| Gateway | empty | Peer-local traffic only |
| Enable default route | **No** | Internet stays on eth0/br0; Yes can steal default via lower metric |

Peer is conventionally `.1` on the same subnet (not enforced).

---

## Small LAN (/24) vs point-to-point (/30)

| | **/24** Small LAN (default) | **/30** Point-to-point |
|--|-----------------------------|-------------------------|
| Usable hosts | 254 | 2 |
| Fit | Host + peer + optional extras | Pure two-host pipe |
| Mental model | Tiny private LAN on this cable | ISP-style link |
| Dual peers on Unraid | Use **different** /24 per tbnN | Use **different** /30 per tbnN |

Thunderbolt networking is **peer-to-peer** at the fabric level either way. The mask is a **policy** choice, not proof of a multi-port Thunderbolt switch.

### When to prefer /24

- You might put a second address or a VM/bridge endpoint on the peer.  
- You want the same habits as a small lab LAN.  
- Default for this plugin.

### When to prefer /30

- Strict two addresses only (Unraid + one peer).  
- You want routing tables to scream “this is a pipe.”  
- You are comfortable with tight subnets.

### /31

Linux supports `/31` (RFC 3021) for P2P; many UIs and humans do not. Not a product default.

---

## Hard rule: unique subnet per Thunderbolt link

**Bad** (two Thunderbolt interfaces, same /24):

| Iface | Address |
|-------|---------|
| thunderbolt0 | 10.255.1.2/24 |
| thunderbolt1 | 10.255.1.3/24 |

The kernel has two paths to `10.255.1.0/24` — ambiguous routing.

**Good**:

| Iface | Address |
|-------|---------|
| thunderbolt0 | 10.255.0.2/24 |
| thunderbolt1 | 10.255.1.2/24 |

Same idea with /30: `10.255.0.2/30` and `10.255.1.2/30`.

---

## Examples

### Single peer (Unraid ↔ Linux)

| Host | Address |
|------|---------|
| Unraid tbn0 | `10.255.0.2/24` |
| Linux | `10.255.0.1/24` |

### Single peer, tight P2P

| Host | Address |
|------|---------|
| Unraid tbn0 | `10.255.0.2/30` |
| Peer | `10.255.0.1/30` |

### Two peers

| Link | Unraid | Peer |
|------|--------|------|
| tbn0 | `10.255.0.2/24` | `10.255.0.1/24` |
| tbn1 | `10.255.1.2/24` | `10.255.1.1/24` |

### Default route

Leave **Enable default route = No** unless this Thunderbolt link should carry Unraid internet traffic (rare).

Thunderbolt interfaces often install a **much lower route metric** than eth0/br0 (high bandwidth). Enabling a default route on tbn can therefore **outrank** the normal LAN/WAN default and send general traffic over the Thunderbolt path — breaking expected topology if you were not counting on that.

A filled **gateway** without default route still only matters for routes you add toward that gateway.

---

## DHCP

**Automatic** on tbnN is best-effort. Pure host↔host links usually have **no** DHCP server. Prefer static both ends.

---

## OpenFabric / multi-hop

Underlay addressing above is still required when OpenFabric is on (link IPs + unique subnets). Multi-hop reachability usually uses **loopback /32 router-ids** learned via FRR, not by putting every host on one big Thunderbolt subnet. See [routing-openfabric.md](routing-openfabric.md) and [fabric-proxmox-unraid.md](fabric-proxmox-unraid.md).

## Related

- [peers-and-plans.md](peers-and-plans.md) — UUID identity, Forget peer, why not Interface Rules  

- Peer OS setup: [peer-scenarios.md](peer-scenarios.md)  
- Settings fields: [settings-reference.md](settings-reference.md)  
- Topology / multi-peer: [links-and-topology.md](links-and-topology.md)  
