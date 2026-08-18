# Known peers and Saved addresses

How Thunderbolt Net remembers remote hosts and restores local addressing without Unraid **Interface Rules**.

Peers table columns: **Current** (on the path now) and **Saved** (remembered for that remote UUID). This page is both **how it works** and **why** — forum reports on L3 persistence after reboot and cable reseat, plus follow-ups on renumber, MACs, and Known peers ghosts.

**Forum:** [Plugin support thread](https://forums.unraid.net/topic/200065-plugin-thunderbolt-net-host-to-host-networking-over-thunderbolt-345-and-usb44v2/)

---

## Contents

- [Why this design (field findings)](#why-this-design-field-findings)
- [Two identities](#two-identities)
- [Saved address (L3 follows the remote host)](#saved-address-l3-follows-the-remote-host)
- [What Apply / hotplug do](#what-apply--hotplug-do)
- [Forget peer](#forget-peer)
- [Why not Interface Rules / MAC](#why-not-interface-rules--mac)
- [Multi-peer and renumber](#multi-peer-and-renumber)
- [Related](#related)

## Why this design (field findings)

Thunderbolt host-net under Linux is **not** a stable eth0-style NIC. Several independent problems stacked; the plugin answers them in layers.

### 1. Address disappeared after reboot or unplug (forum reports)

**What users saw**

- Static IPv4 set on `thunderbolt0` / tbn tab looked fine while the cable was up.  
- After **reboot** or **unplug/replug**, the netdev came back **without** the static address (or only half-configured).  
- Sometimes **169.254.x.x** (link-local) appeared **stacked next to** the static address after switching from DHCP-ish bring-up to Static.

**Root causes (plugin + kernel)**

| Cause | Detail |
|-------|--------|
| Netdev lifecycle | `thunderbolt_net` often **destroys and recreates** the netdev on each link setup (new MAC is normal). Unraid `network.cfg` eth blocks do **not** own these ifaces. |
| No reapply hook | Until the persistence work: nothing re-pushed flash plans on **array start** or **udev net add**. |
| Leftover dhcpcd | After a path used DHCP clients, switching to Static without **killing dhcpcd/dhclient** left the client re-adding **169.254.x.x** beside the static. |

**What we shipped (persistence layer)**

| Mechanism | Role |
|-----------|------|
| `ifaces/thunderboltN.cfg` on flash | Path-slot L3 plan (eth-like by **name**) |
| `tbn-net-reapply` + plugin **`startup`** | Early boot: Unraid up, **array not required** — udev + L3 if path already live |
| `tbn-net-reapply` + array **`started`** | When array reaches Started (Normal **or Maintenance**) — second pass + OpenFabric/Dashboard |
| udev `99-thunderboltnet-net.rules` | On `ACTION=add` for `thunderbolt*`, re-apply that iface (works any time rule is installed) |
| Stop dhcpcd/dhclient before static apply | Prevents stacked 169.254 after Static apply |

That layer answers: **“static should come back on this netdev name after drop/reboot.”**  
(See CHANGELOG **2026.08.15ak** for the persistence release notes.)

### 2. Same host, wrong or empty IP after path renumber (multi-peer)

**What users hit next**

- Persistence by **name** is not enough when **order of cables** or **which peer comes up first** changes which remote sits on `thunderbolt0` vs `thunderbolt1`.  
- A plan saved only as “whatever is tbn0” can land on the **wrong** host after swaps.  
- Two remotes both “set up on tbn0 at different times” then plugged together → one path gets the old slot cfg; the other may get nothing unless you Apply tbn1.

**What we shipped (identity layer)**

| Mechanism | Role |
|-----------|------|
| Known peers keyed by **remote fabric UUID** | Stable “who is on the other end” |
| **Saved** (desired local IPv4, etc.) on that UUID | L3 follows the **host**, not the slot name |
| Capture on **tbn Apply** while linked | First good Apply **is** the save — no separate Remember step required |
| Peers → **Remember current** | Manual Current → Saved when live drift needs to stick |
| Reapply prefers **Saved** when the live path has a known UUID | Then falls back to path-slot `ifaces/*.cfg` |
| **Forget peer** | Drop UUID memory/plan without touching eth Interface Rules |

That layer answers: **“this laptop should get its Unraid-side address even if the kernel renames the path.”**  
It is **not** a full replacement for path reapply — it **sits on top** of it.

### 3. Two Known peers rows after unplug/replug

**What users saw**

- Online row with the real peer name **and** an Offline row with a **blank** name, same tbn/IP, low sighting count.

**Root cause**

- On hotplug, fabric **`unique_id` can be empty for a moment**.  
- Plugin fell back to key `iface:thunderboltN`.  
- When UUID appeared, the real row updated online; the `iface:` row stayed offline forever.

**What we shipped**

- Prefer an existing UUID peer on the same iface when UUID is missing.  
- Merge/delete `iface:` ghosts when a UUID peer shares that iface (**2026.08.16ac**).

### 4. Path looks healthy but no ping after reboot

**What users saw**

- Peer **Online**, Current IP present, trained rates look fine — UI does not scream.  
- `ping` / ARP to the peer still fail.

**Cause (generalized)**

- Product default is **E2E = No** (`thunderbolt_net e2e=0`). Older builds only wrote that into RAM `/etc/modprobe.d/`.  
- After reboot the kernel loaded the driver default (**e2e on**). Addresses still reapplied; the underlay stayed flaky.

**What we do**

| Mechanism | Role |
|-----------|------|
| Persist to `/boot/config/modprobe.d/thunderbolt_net.conf` | Survives reboot (Unraid flash) |
| Also write `/etc/modprobe.d/` | Live root this boot |
| `startup` / reapply: reload module if live bit ≠ cfg | Corrects a wrong first auto-load |
| Peers **No carrier** / **No reply** under Current | Quiet cue when Online but underlay silent |

Prefer plugin **≥ 2026.08.17ba**. Confirm `cat /sys/module/thunderbolt_net/parameters/e2e` is `N`/`0` when Settings → E2E is No. See [driver-options.md](driver-options.md) and [troubleshooting.md](troubleshooting.md#online-ip-and-rates-but-no-ping).

### 5. What Saved deliberately does *not* claim

| Not claimed | Why |
|-------------|-----|
| Fixes dual-lane / sticker 40G training | Kernel/ICM/cable — see [standards-and-speeds.md](standards-and-speeds.md) |
| Dual-cable same peer = two netdevs | Often still one path — [links-and-topology.md](links-and-topology.md) |
| Stable local MAC → tbnN like Interface Rules | MAC churn on host-net — wrong tool |
| Every OEM/peer edge case | Forum path was Unraid static + recreate; we generalize from that |

Forum persistence reports drove **path reapply + dhcpcd kill**. Saved addresses address the **next** class of issues once reapply works: **who owns the plan when names move**. E2E flash persist closes the “looks configured, still can’t talk” reboot class.

## Two identities

| Concept | Key | Examples |
|---------|-----|----------|
| **Live path** | Kernel netdev name | `thunderbolt0` → Settings tab **tbn0** |
| **Remote host** | Fabric **unique_id** (UUID) | Peer hostname / advertised name |

`tbn0` / `tbn1` are **path slots** for host-net interfaces. They are **not** rear-panel ports and not permanent “eth-like” NICs. The kernel may assign a different `thunderboltN` after unplug order changes.

Known peers are keyed by **remote fabric UUID** (with a short-lived `iface:thunderboltN` fallback only when UUID is missing during hotplug).

## Saved address (L3 follows the remote host)

**Saved** is the desired **local** IPv4 (and related L3 fields) for *this Unraid* when talking to that remote UUID.

On the Peers table:

| Column | Meaning |
|--------|---------|
| **Current** | Address on the path **right now** |
| **Saved** | Remembered for that UUID; reapplied on reconnect / renumber |

Matching Current and Saved after a normal Apply is expected — not duplicate settings.

**First setup:** Apply on the tbn tab while the peer is linked. That writes path cfg **and** fills Saved. You do **not** need **Remember current** for the first established settings. Opening Peers can also **seed** Saved from last live addrs when Saved is still missing.

**Remember current** / **Apply saved** are for later drift (live changed without Apply, or push memory back onto the path).

| Stored with the peer | Not the long-term identity |
|----------------------|----------------------------|
| IP / mask / DHCP flag / gateway / default-route / MTU mode | Local MAC (often new each link) |
| Listening Yes/No (services on TB IP) | Panel port index |
| Last path (`tbnN` / `thunderboltN`) for display | Unraid Interface Rules |

**Path-slot files** still exist: `ifaces/thunderbolt0.cfg`, etc. They act like an eth-style **name cache**. When a live peer has usable **Saved**, reapply prefers that over the path-slot file alone.

## What Apply / hotplug do

| Event | Behavior |
|-------|----------|
| **tbn Apply** while peer is linked | Writes path cfg, applies live, **captures Saved** onto that UUID |
| Peers → **Remember current** | Same capture without changing other tbn fields (optional after first Apply) |
| Peers → **Apply saved** | Push Saved onto the path that peer currently uses |
| **Hotplug** (udev) | Same reapply when a `thunderbolt*` netdev appears (any array state, if rule is present) |
| **Plugin `startup`** | After plugins install at boot: e2e modprobe persist/reload + udev + reapply (array may still be stopped) |
| **Array `started`** | When array reaches Started (Normal **or Maintenance**) — second pass + OpenFabric/Dashboard |
| Open Peers/Status | Refresh online flags; dedupe ghost `iface:` rows; seed Saved from last addrs if missing |
| **tbnN form open** (after cable change) | Live poll detects peer UUID change on that path and **reloads the tab form** from Saved (L3/MTU/NAT) so fields match the restored device |

**Maintenance mode:** array **Started** still runs `event/started` — L3 reapply and udev reinstall run. Do not assume “maintenance = no plugin events.”

**Array stopped / never started:** rely on **`startup`** + **udev** (and plugin install finish). If you need TB L3 only after a late cable plug with array still stopped, udev handles it once the rule is on disk from `startup` or install.

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
| One peer, unplug/replug | Same Known peers row; Saved reapplied; path name may stay or change |
| Same peer later on tbn1 | Same UUID row; Saved follows; path column updates |
| Two peers at once | Two UUID rows; **two different** Saved subnets; tbn1 does **not** inherit tbn0 |
| Cable order swaps which peer is `thunderbolt0` | **Saved** still lands on the correct remotes; path-slot-only configs can look “wrong” until Saved exists |

Always use **unique subnets per peer path** (e.g. `10.255.0.0/24` vs `10.255.1.0/24`). See [addressing.md](addressing.md).

## Related

- [troubleshooting.md](troubleshooting.md) — IP missing after unplug, 169.254 stack, ghost peers, wrong IP after renumber, Online but no ping  
- [driver-options.md](driver-options.md) — E2E persist across reboot  
- [addressing.md](addressing.md)  
- [peer-scenarios.md](peer-scenarios.md)
- [links-and-topology.md](links-and-topology.md)  
- [settings-reference.md](settings-reference.md)  
- [CHANGELOG.md](../CHANGELOG.md) — **2026.08.15ak** (path reapply / static persistence), **16ac** (ghost rows), **16ad** (Saved addresses), **17az** (Current/Saved UI wording)  
