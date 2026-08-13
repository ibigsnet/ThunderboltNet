# Safe Mode / no-plugins recovery (manual Thunderbolt net)

When Unraid boots in **Safe Mode** or **GUI Safe Mode**, community plugins (including Thunderbolt Net) **do not load**. The Settings UI, array-start hooks, OpenFabric apply, and dashboard port patches **will not run**.

You can still bring up a **minimal host-to-host path** by hand for recovery or management, as long as the kernel has Thunderbolt networking and a peer link trains.

This is **not** the same as **array Maintenance mode** (array started, disks not mounted). In array Maintenance, the plugin *can* still load on a normal boot; this page is about **no plugins**.

---


## Local copy (offline / Safe Mode)

After a normal install of this plugin, a **short plain-text** sheet is kept on the flash drive so you can read it when plugins and GitHub are unavailable:

```bash
cat /boot/config/plugins/ThunderboltNet/SAFE-MODE-RECOVERY.txt
```

When plugins are loaded:

```bash
cat /usr/local/emhttp/plugins/ThunderboltNet/SAFE-MODE-RECOVERY.txt
less /usr/local/emhttp/plugins/ThunderboltNet/docs/safe-mode-recovery.md
```

Uninstall leaves the flash copy in place on purpose (recovery help without reinstalling). Delete that file yourself if you want it gone.

This is **documentation only** — it does not bring the link up and is not a persistent auto-network bootstrap.

---
## Recommendation

| Approach | When | Default advice |
|----------|------|----------------|
| **Manual commands** (this page) | Rare recovery boots | **Use this** |
| **Persistent auto-bootstrap** (modprobe.d + `go` / early scripts that run without plugins) | Only if you *must* have TB every safe boot | **Keep off** unless you fully own the flash hooks and uninstall path |

**Highly suggested default: do not enable persistent “always up in every boot environment” automation.**

Reasons to keep persistence **off**:

- Safe Mode is for a **minimal, predictable** system; silent network scripts on flash are easy to forget.
- Thunderbolt domains wedge more often with **multiple cables** or half-connected ports — automation that races “first iface up” can hide that.
- A bad static plan or default route on TB can strand management if you also lose the main NIC.
- Plugin uninstall will not always clean hand-edited `go` / modprobe snippets unless you designed that carefully.

If you ever add a product feature for “persist recovery IP without plugins,” it should default **No**, write only minimal flash hooks, and remove them on disable/uninstall. Until then: **manual only**.

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

SMB/NFS/SSH on TB require the service to listen on that address (or `0.0.0.0`). In safe mode the plugin will **not** edit `network-extra.cfg`. Prefer **SSH to the TB IP** or tools you start by hand. Avoid binding management services to TB unless you understand the exposure.

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
