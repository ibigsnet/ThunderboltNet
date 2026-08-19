# IPv4 addressing on Thunderbolt links

Each live kernel interface `thunderboltN` (Settings tab **tbnN**) is its own L2 segment to **one** remote peer path. Addressing is almost always **static**.

**Saved** (Peers tab): desired local IPv4 stored per **remote fabric UUID**. On reconnect it is applied to **whichever** `thunderboltN` the peer appears on — so renumber (tbn0↔tbn1) does not strand the IP on the wrong host. **Current** is what is on the path right now. See [peers-and-plans.md](peers-and-plans.md).

---

## Contents

- [Product defaults](#product-defaults)
- [Path-slot cfg vs Saved](#path-slot-cfg-vs-saved)
- [Address assignment modes](#address-assignment-modes)
- [Autofill by assignment mode](#autofill-by-assignment-mode)
- [Small LAN (/24) vs point-to-point (/30)](#small-lan-24-vs-point-to-point-30)
- [Hard rule: unique subnet per Thunderbolt link](#hard-rule-unique-subnet-per-thunderbolt-link)
- [Examples](#examples)
- [DHCP](#dhcp)
- [OpenFabric / multi-hop](#openfabric-multi-hop)
- [Related](#related)

## Path-slot cfg vs Saved

| Store | Key | Use |
|-------|-----|-----|
| `ifaces/thunderboltN.cfg` | Interface **name** | Eth-like tab editor; reapply when no Saved plan |
| **Saved** in `peers.json` | Remote **UUID** | Preferred on hotplug/boot when that peer is live on any path |

**Apply on a tbn tab** while linked writes both: path cfg **and** Saved for the remote host.

## Product defaults

| Item | Default | Rationale |
|------|---------|-----------|
| Assignment | Static | No DHCP server on a pure host↔host cable |
| Suggestion (seed) | `10.255.N.2` for `thunderboltN` | Historical seed; unique third octet per link |
| Mask | **/24** (`255.255.255.0`) | Familiar; room for a few extra addresses (VM, alias) |
| Gateway | empty | Peer-local only; set only if tbn is an uplink (rare) |
| Enable default route | **No** | Internet stays on eth0/br0; Yes can steal default via lower metric |

### Recommended host numbers (.1 vs .2)

From a routing/tshoot habit (CCIE-style): treat **Unraid as `.1`** on each link subnet (stable infrastructure / “near” end of the pipe) and give the **peer `.2`**. That matches how most people label router↔host and reads cleanly in `ip route` / traceroute.

| Role | Recommended | Notes |
|------|-------------|--------|
| Unraid tbnN | **`10.255.N.1/24`** (or `/30`) | Prefer for **new** links |
| Far peer | **`10.255.N.2/...`** | |
| Current plugin seed | still `10.255.N.2` on Unraid | Kept so existing labs are not reshuffled; either works for P2P if both ends agree |

Either `.1`/`.2` orientation is fine for peer-to-peer **without** a default route. Be consistent per link; do not put two Thunderbolt ifaces in the same subnet.

### Address assignment modes

| Mode | Today | Notes |
|------|-------|--------|
| **Static (Manual User Config)** | Yes (default) | You set the IPv4 on this path — usual for two known hosts |
| **DHCP Client (Automatic Assignment)** | Yes | This Unraid asks the peer for an address (may get 169.254/16 if nothing serves) |
| **DHCP Server (Unraid-Managed)** | Yes | Unraid runs **dnsmasq** on this underlay only; default host **`.1`**, pool **`.2–.254`** (editable) |

### Autofill by assignment mode

What the tbnN form does when you change **IPv4 address assignment** (and on **Reset**):

| Mode | Fields shown | Autofill / seed | Apply does |
|------|--------------|-----------------|------------|
| **Static** (default) | IPv4 address + mask, gateway, default route, NAT | **Reset / new iface:** `10.255.N.2/24`. Switching *into* Static does **not** rewrite a filled address. | Sets that static on the netdev; captures Saved for the live peer |
| **DHCP Client** | (address row hidden) | No IP autofill — kernel/dhcpcd gets the lease (or 169.254 if nothing serves) | Starts DHCP client on the underlay; stops on leave |
| **DHCP Server** | Unraid IPv4 + mask, DHCP pool start–end, (NAT still under static-only block — use Static or set before switch) | On switch into Server: if address empty **or** still ends in `.2`, fill **`10.255.N.1`** + `/24`; empty pool → **`.2`–`.254`** (largest usable range that excludes host). Keeps non-seed edits. | Host L3 = Unraid address; dnsmasq serves the pool on **this** `thunderboltN` only (never eth0/br0) |

| Related default | Value |
|-----------------|-------|
| Gateway | empty |
| Enable default route | **No** |
| Share host uplink (NAT) | **No** (stamped into Saved on Apply while linked) |
| NAT uplink | **Auto** |
| IPv6 assignment | Static; address empty until you set one |
| IPv6 DHCP server | Shares IPv4 dnsmasq / RA on the underlay |

**NAT** and **gateway / default route** only appear in the static IPv4 block (assignment = Static). For “peer needs internet via Unraid,” prefer Static + NAT rather than DHCP server + default route.

### DHCP Server (Unraid-Managed)

On the tbn tab, pick **DHCP Server (Unraid-Managed)** then set (or accept defaults):

| Field | Default |
|-------|---------|
| Unraid IPv4 address | `10.255.N.1/24` |
| DHCP pool | `10.255.N.2` – `10.255.N.254` |

Pool must stay on the same subnet and must not include Unraid’s own address.

### DHCP server (come-and-go clients)

For Macs / laptops that plug in for backups or syncs:

1. On the Unraid tbnN tab set **IPv4 address assignment → DHCP server** (optionally **IPv6 → DHCP server (RA)**).
2. Apply. Host moves to **`10.255.N.1/24`**; dnsmasq serves **`.2–.254`** on **that** `thunderboltN` / `bond-tb*` / `br-tb*` only — **never** eth0/br0.
3. Clients use DHCP; they should reach Unraid at `.1` for SMB/NFS/rsync if **Include listening** / peer services allow it.

**Safety:** before start we probe ARP/neigh and local addresses. If `.1` is already taken, server mode is **blocked** with a warning (post diagnostics on the [forum thread](https://forums.unraid.net/topic/200065-plugin-thunderbolt-net-host-to-host-networking-over-thunderbolt-345-and-usb44v2/)).

### Unraid ↔ Unraid addressing

Most users are Unraid↔Mac/PC, not two Unraids. If you **do** run two Unraid plugins on one cable:

- Prefer **Static** with complementary hosts: one **`.1`**, one **`.2`** on the same `10.255.N.0/24`, **or**
- **DHCP server** on one side only (that box becomes `.1` and serves the peer).

Leaving **both** on the historical seed **`.2`** means neither can reach the other’s link-check export → Peer link check stays **Unverified** even with a matching token. The UI only orange-flags a **confirmed** duplicate (same IP + two MACs in ARP), not the mere use of `.2`.

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

### Gateway and default route

For normal host↔host peer-to-peer, leave **IPv4/IPv6 default gateway** empty and **Enable default route = No**.

Only configure a gateway (and optionally default route) if this Thunderbolt link is intentionally an **uplink** or you need specific routes via that peer — uncommon. Otherwise you are wiring routing policy you do not need for P2P addressing.

Thunderbolt interfaces often install a **much lower route metric** than eth0/br0 (high bandwidth). Enabling a **default route** on tbn can **outrank** the normal LAN/WAN default and send general traffic over the Thunderbolt path — breaking expected topology if you were not counting on that.

A filled **gateway** without default route still only matters for routes you add toward that gateway (it does not by itself replace eth0/br0 internet).

### Share host uplink (NAT)

If the peer has no Wi‑Fi or viable Ethernet of its own and should reach the internet **through Unraid**, use **Share host uplink (NAT)** on the Unraid tbn tab (not “Enable default route”). Peer sets its default gateway to Unraid’s TB IP. Cannot combine with Enable bridging. See [nat-share-uplink.md](nat-share-uplink.md).

---

## DHCP

**DHCP client** on tbnN is best-effort. Pure host↔host links usually have **no** DHCP server unless the peer (or this Unraid in **DHCP server** mode) provides one. Prefer **Static** for two managed Unraids; use **DHCP server** when transient clients should just plug in.

Leases: `/var/lib/thunderboltnet/dhcp/<netdev>.leases`. Conf: `/boot/config/plugins/ThunderboltNet/dhcp/<netdev>.conf`.

---

## OpenFabric / multi-hop

Underlay addressing above is still required when OpenFabric is on (link IPs + unique subnets). Multi-hop reachability usually uses **loopback /32 router-ids** learned via FRR, not by putting every host on one big Thunderbolt subnet. See [routing-openfabric.md](routing-openfabric.md) and [fabric-proxmox-unraid.md](fabric-proxmox-unraid.md).

## Related

- [peers-and-plans.md](peers-and-plans.md) — UUID identity, Forget peer, why not Interface Rules  
- [nat-share-uplink.md](nat-share-uplink.md) — share Unraid br0/eth0/wlan0 with TB peers  
- Peer OS setup: [peer-scenarios.md](peer-scenarios.md)  
- Settings fields: [settings-reference.md](settings-reference.md)  
- Topology / multi-peer: [links-and-topology.md](links-and-topology.md)

## Join an Unraid bridge (`br0`, `br0.10`, …)

Optional. Default No. Use when the Thunderbolt peer should use the **same addressing as an existing Unraid bridge** (`br0`, `br0.10`, …) — same idea as eth0 when bridged. Direct Thunderbolt `/24` static links stay on their own subnet. Mutually exclusive with Share host uplink (NAT).

On each tbn tab: **Enable bridging = Yes**, then pick an **existing** bridge from the list
(Unraid Network Settings must already have created `br0` / VLAN bridges).

- Thunderbolt becomes a **member** — no own IP on that tab; addressing stays on the bridge.
- The **peer** puts a LAN-range address on its Thunderbolt iface (LAN DHCP or static in that bridge’s subnet).
- The plugin does **not** create or delete Unraid bridges.
- Hotplug / boot reapply re-enslaves when bridging stays Yes.
- Prefer **one** side joining `br0` unless you understand L2 loops / STP.
- Underlay DHCP server on that tab is not used while the iface is a bridge member.
- Default **No** — management LAN is not enrolled unless you opt in.

