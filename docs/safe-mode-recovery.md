# Safe Mode / no-plugins recovery (manual Thunderbolt net)

When Unraid boots in **Safe Mode** or **GUI Safe Mode**, community plugins (including Thunderbolt Net) **do not load**. The Settings UI, array-start hooks, OpenFabric apply, and dashboard port patches **will not run**.

You can still bring up a **minimal host-to-host path** by hand for recovery or management, as long as the kernel has Thunderbolt networking and a peer link trains.

This is **not** the same as **array Maintenance mode** (array started, disks not mounted). In array Maintenance, the plugin *can* still load on a normal boot; this page is about **no plugins**.

**Need the offline commands now?** Jump to **[Bare-minimum manual recovery](#bare-minimum-manual-recovery-unraid-console)** — modules, iface, address, ping. Kernel names are `thunderbolt0`, `thunderbolt1`, … (not `tbnN`; those are plugin Settings tab labels only).

## Contents

| | |
|--|--|
| **[Bare-minimum manual recovery](#bare-minimum-manual-recovery-unraid-console)** | Console commands: modules → netdev → **IP on `thunderboltN`** → ping |
| → [1. Modules](#1-modules) | `modprobe thunderbolt` / `thunderbolt_net` |
| → [2. Wait for a netdev](#2-one-cable-then-wait-for-a-netdev) | Find `thunderbolt0` (etc.) |
| → [3. Addresses](#3-addresses-example-plan) | `ip link` / `ip addr` examples |
| → [4. Verify](#4-verify) | `ping` and show address |
| → [5. Services (optional)](#5-services-optional) | SSH/SMB on TB without the plugin |
| [Careful cable practice](#careful-cable-practice-do-this-first) | One cable only — do this before addressing |
| [Offline docs on flash](#offline-docs-on-flash-no-internet) | Local recovery text when GitHub/WebUI is unavailable |
| [Recommendation](#recommendation) | Prefer manual; avoid persistent auto-bootstrap |
| [After recovery](#after-recovery) | Back to normal boot + plugin Apply |
| [What Safe Mode will not do](#what-safe-mode-will-not-do-for-you) | UI / OpenFabric / plugin Apply missing |
| [Related](#related) | Troubleshooting, addressing, drivers |

---

## Careful cable practice (do this first)

Wedged multi-cable domains are the most common reason “TB net won’t come up” during recovery.

1. **Unplug every** Thunderbolt / USB4 host cable from **this** Unraid host **and** from peer machines you care about (all ports).  
2. Wait **10+ seconds**.  
3. Plug **one** known-good cable only (one port pair, one peer).  
4. Wait until a single `thunderbolt0` (or peer login) appears.  
5. Only then apply addresses / ping.  
6. Leave extra cables **unplugged** until that path works.

Do **not** debug with two cables in, or with a second port half-seated. Details: [troubleshooting.md](troubleshooting.md) (multi-cable recovery) and [links-and-topology.md](links-and-topology.md).

---

## Bare-minimum manual recovery (Unraid console)

Use the **console / SSH on the management NIC** if you still have it. If the only path is the TB peer, you need physical console or a peer that can still reach you another way for the first bring-up.

### 1. Modules

```bash
modprobe thunderbolt 2>/dev/null || true
modprobe thunderbolt_net
# optional: confirm
lsmod | grep -E 'thunderbolt'
```

If `modprobe thunderbolt_net` fails, this kernel/build has no host-net path — software recovery cannot invent it. See [requirements.md](requirements.md).

Optional E2E (many cross-host links prefer **off**):

```bash
# only if you know you want e2e=0 for this session
echo 0 > /sys/module/thunderbolt_net/parameters/e2e 2>/dev/null || true
```

### 2. One cable, then wait for a netdev

After the single-cable practice above:

```bash
# list Thunderbolt netdevs (names are kernel thunderboltN, not tbnN)
ls /sys/class/net | grep -E 'thunderbolt|bond-tb|br-tb' || true
ip -br link | grep -i thunder || true
```

If nothing appears: reseat the **one** cable; check peer authorization / security mode; check NHI not bound to vfio. Do not add a second cable yet.

### 3. Addresses (example plan)

Plugin defaults often use **one /24 per link**, e.g. first link `10.255.0.0/24`:

| Role | Example |
|------|---------|
| This Unraid | `10.255.0.2/24` on `thunderbolt0` |
| Peer | `10.255.0.1/24` on its TB netdev |

```bash
IFACE=thunderbolt0
ip link set "$IFACE" up
# remove a wrong trial address if needed: ip addr flush dev "$IFACE"
ip addr add 10.255.0.2/24 dev "$IFACE"
```

On the peer (Unraid, Linux, etc.), matching subnet, different host address:

```bash
ip link set thunderbolt0 up
ip addr add 10.255.0.1/24 dev thunderbolt0
```

**Do not** add a default route via Thunderbolt unless you intend to replace LAN internet for this session:

```bash
# usually leave default on eth0/br0/wlan — do NOT run:
# ip route add default via 10.255.0.1 dev thunderbolt0
```

### 4. Verify

```bash
ping -c 3 10.255.0.1
ip -br addr show thunderbolt0
```

### 5. Services (optional)

SMB/NFS/SSH on TB require the service to listen on that address (or `0.0.0.0`). In Safe Mode the plugin will **not** edit `network-extra.cfg`. Prefer **SSH to the TB IP** or tools you start by hand. Avoid binding management services to TB unless you understand the exposure.

---

## Offline docs on flash (no internet)

A short recovery sheet is kept on the Unraid flash for when GitHub or the WebUI is unavailable (Safe Mode, no plugins, offline lab).

**Path:**

```bash
cat /boot/config/plugins/ThunderboltNet/ThunderboltNet-RECOVERY.txt
```

**Find it when you only remember “Thunderbolt”:**

```bash
find /boot -iname '*Thunderbolt*' 2>/dev/null
ls /boot/config/plugins/ThunderboltNet/ 2>/dev/null
```

Optional pager (local file only — does **not** install a system manpage):

```bash
man -l /boot/config/plugins/ThunderboltNet/ThunderboltNet-RECOVERY.txt
```

**While the WebUI still works** (learn the path *before* Safe Mode):  
**Settings → Network Settings → Thunderbolt → Recovery** shows the same path and commands.

When plugins are loaded, this full guide is also on the host:

```bash
less /usr/local/emhttp/plugins/ThunderboltNet/docs/safe-mode-recovery.md
```

Left on flash after uninstall (recovery text only — not a running service). No system manpages, shell aliases, or extra copies on `/boot/` root.

---

## Recommendation

| Approach | When | Default advice |
|----------|------|----------------|
| **Manual commands** (this page) | Rare recovery boots | **Use this** |
| **Persistent auto-bootstrap** (modprobe.d + `go` / early scripts without plugins) | Only if you *must* have TB every safe boot | **Keep off** unless you own those flash hooks |

**Default: no persistent auto-bootstrap.** Safe Mode should stay minimal; forgotten flash network scripts and multi-cable races are easy ways to strand management.

---

## After recovery

1. Prefer returning to a **normal** boot (plugins on) when the emergency is over.  
2. Fix the underlying issue (LAN, flash, misconfig) so Safe Mode is temporary.  
3. Re-enable the plugin path: Settings → Network Settings → Thunderbolt / tbn tabs → Apply.  
4. Keep multi-cable experiments for **after** single-link stability.

---

## What Safe Mode will not do for you

| Plugin feature | In Safe Mode / no plugins |
|----------------|---------------------------|
| Settings UI / tbn tabs | Not available |
| Load modules on Apply | Not available — use `modprobe` |
| Static IP from plugin cfg | Not applied — use `ip addr` |
| OpenFabric / FRR | Not started by TBN |
| Fabric reports / mesh export | Not available |
| Dashboard thunderbolt port list patch | Not applied (stock eth/bond/wlan only) |

Flash still holds plugin **config files**, but nothing runs them until a normal boot installs the plugin again.

---

## Related

- [troubleshooting.md](troubleshooting.md) — reseat, multi-cable, no iface  
- [addressing.md](addressing.md) — `/24` vs `/30` plans  
- [driver-options.md](driver-options.md) — modules, `e2e`  
- [requirements.md](requirements.md) — hardware / kernel  
- [SECURITY.md](../SECURITY.md) — privilege and defaults  
