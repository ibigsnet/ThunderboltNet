# Known peers and peer plans

How Thunderbolt Net remembers remote hosts and restores local addressing without Unraid **Interface Rules**.

---

## Contents

- [Two identities](#two-identities)
- [Peer plan (L3 follows the remote host)](#peer-plan-l3-follows-the-remote-host)
- [What Apply / hotplug do](#what-apply--hotplug-do)
- [Forget peer](#forget-peer)
- [Why not Interface Rules / MAC](#why-not-interface-rules--mac)
- [Multi-peer and renumber](#multi-peer-and-renumber)
- [Related](#related)

## Two identities

| Concept | Key | Examples |
|---------|-----|----------|
| **Live path** | Kernel netdev name | `thunderbolt0` → Settings tab **tbn0** |
| **Remote host** | Fabric **unique_id** (UUID) | Holo, NIROG, a laptop |

`tbn0` / `tbn1` are **path slots** for host-net interfaces. They are **not** rear-panel ports and not permanent “eth-like” NICs. The kernel may assign a different `thunderboltN` after unplug order changes.

Known peers are keyed by **remote fabric UUID** (with a short-lived `iface:thunderboltN` fallback only when UUID is missing during hotplug).

## Peer plan (L3 follows the remote host)

A **peer plan** is the desired **local** IPv4 (and related L3 fields) for *this Unraid* when talking to that remote UUID.

| Stored with the peer | Not the long-term identity |
|----------------------|----------------------------|
| IP / mask / DHCP flag / gateway / default-route / MTU mode | Local MAC (often new each link) |
| Listening Yes/No (services on TB IP) | Panel port index |
| Last path (`tbnN` / `thunderboltN`) for display | Unraid Interface Rules |

**Path-slot files** still exist: `ifaces/thunderbolt0.cfg`, etc. They act like an eth-style **name cache**. When a live peer has a usable plan, **reapply prefers the peer plan** over the path-slot file alone.

## What Apply / hotplug do

| Event | Behavior |
|-------|----------|
| **tbn Apply** while peer is linked | Writes path cfg, applies live, **captures peer plan** onto that UUID |
| Peers → **Save live path as peer plan** | Same capture without changing other tbn fields |
| Peers → **Apply peer plan now** | Push plan onto the path that peer currently uses |
| **Hotplug / array-start** (`tbn-net-reapply`) | For each live path: if peer has a plan → apply it; else use `ifaces/*.cfg` if present |
| Open Peers/Status | Refresh online flags; dedupe ghost `iface:` rows; seed plan from last addrs if missing |

## Forget peer

**Forget selected peers** (Peers tab):

- Removes the row from `peers.json` (name, plan, listening memory, history).
- Does **not** delete Unraid eth Interface Rules.
- Does **not** automatically delete every `ifaces/thunderboltN.cfg` (path-slot files may remain until you Reset that tbn tab).

Use this for lab clutter, wrong ghosts, or hosts you no longer want remembered.

## Why not Interface Rules / MAC

Unraid **Settings → Network Settings → Interface Rules** maps **stable NIC MAC/PCI → ethN**. That fits copper/onboard NICs.

Thunderbolt **host-net**:

- Netdev is created/destroyed with the path.
- Local MAC often **changes** on each link setup (plugin udev reapply comments this explicitly).
- Binding by MAC would thrash or never match.

So peer identity and L3 plans stay **inside Thunderbolt Net**, keyed by fabric UUID. We do not register Thunderbolt names into stock Interface Rules (upgrade risk + wrong abstraction).

## Multi-peer and renumber

| Scenario | Expected |
|----------|----------|
| One peer, unplug/replug | Same Known peers row; plan reapplied; path name may stay or change |
| Same peer later on tbn1 | Same UUID row; plan follows; path column updates |
| Two peers at once | Two UUID rows; **two different** plans/subnets; tbn1 does **not** inherit tbn0 |
| Cable order swaps which peer is `thunderbolt0` | Peer plans still land on the correct remotes; path-slot-only configs can look “wrong” until plans exist |

Always use **unique subnets per peer path** (e.g. `10.255.0.0/24` vs `10.255.1.0/24`). See [addressing.md](addressing.md).

## Related

- [addressing.md](addressing.md)  
- [peer-scenarios.md](peer-scenarios.md)  
- [links-and-topology.md](links-and-topology.md)  
- [settings-reference.md](settings-reference.md)  
- [troubleshooting.md](troubleshooting.md)  
