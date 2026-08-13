# Driver options (host-wide)

These live on **Settings → Network Settings → Thunderbolt → Driver options**.  
They affect the **whole Unraid host**, not a single `tbnN` tab.

---

## Contents

- [Load modules on Apply](#load-modules-on-apply)
- [Enable USB4STREAM](#enable-usb4stream)
- [E2E flow control](#e2e-flow-control)
- [Example workflows](#example-workflows)
- [Related](#related)

## Load modules on Apply

| Value | Meaning |
|-------|---------|
| **Yes** (default) | Apply runs `modprobe thunderbolt` and `modprobe thunderbolt_net` (and optionally stream — see below) |
| **No** | Apply does not load modules — only if you load them yourself (e.g. in `/boot/config/go`) |

---

## Enable USB4STREAM

| Value | Meaning |
|-------|---------|
| **No** (default) | Do not load `thunderbolt_stream` — IP host-net only (`thunderbolt_net` / tbn tabs) |
| **Yes** | On Apply (with Load modules = Yes), also try `modprobe thunderbolt_stream` if the kernel ships it |

Requires a **Linux kernel** that ships `thunderbolt_stream` (feature landed in mainline **kernel ~7.2**; Unraid product version is irrelevant — check `uname -r` / `modinfo`). On kernels without the module the option is harmless (modprobe fails quietly). This is **not** InfiniBand and **not** Unraid 7.2.x; see [usb4stream.md](usb4stream.md).

### When to use Yes

- Normal installs: you want the controller and host-network function available after Apply.
- You are not hand-managing Thunderbolt modules.

### When to use No

- You already load modules in `go` with custom options and do not want the plugin to race you.
- You are debugging module load order and want full manual control.

### What it does *not* do

- It does **not** unbind the Thunderbolt **NHI** (host interface) from the host driver.  
  Unbinding NHI can **hard-wedge** some controllers (including some Maple Ridge boards) until reboot. Do not use NHI unbind as a “fix.”

---

## E2E flow control

### What it is

Linux module parameter on **`thunderbolt_net`**:

```text
parm: e2e: USB4NET full end-to-end flow control (default: true) (bool)
```

| Plugin UI | Module | Meaning |
|-----------|--------|---------|
| **No (e2e=0)** (product default) | `e2e=0` | End-to-end Thunderbolt network flow control **off** |
| **Yes (driver default)** | `e2e=1` | Driver default — full E2E flow control **on** |

Persisted as:

```text
# typically /boot/config/modprobe.d/thunderbolt_net.conf
options thunderbolt_net e2e=0
```

Live value (after load):

```text
/sys/module/thunderbolt_net/parameters/e2e
```

### Host-wide — not per link

There is **one** `e2e` bit for the entire `thunderbolt_net` module. It applies to **all** `thunderbolt0`, `thunderbolt1`, … interfaces on this host.

| Question | Answer |
|----------|--------|
| Enable e2e on tbn0 only? | **No** — not supported by the driver |
| Different e2e for Mac peer vs Linux peer? | **No** — one policy for the Unraid host |
| Should this move to the tbn tab? | **No** — a per-tab control would be misleading |

If one peer needed e2e on and another needed off, the kernel still cannot do that. Choose the setting that works for the **harder** peer (almost always **off** for mixed Linux host-to-host).

Changing e2e after the module is loaded may require a module reload or reboot and can disrupt **every** Thunderbolt netdev at once.

### Why the product default is No (`e2e=0`)

Upstream default is **true** (on). In practice, many **cross-host** setups (Unraid ↔ another Linux machine, mixed vendors/firmware) behave more reliably with **`e2e=0`**: fewer stuck “ThunderboltIP login” states, fewer one-way or flaky data paths after the link trains.

That is an empirical reliability choice for Unraid home-lab host networking — not a claim that e2e is “wrong” in every design.

### When to leave No (recommended)

| Scenario | Recommendation |
|----------|----------------|
| Unraid ↔ Linux desktop, laptop, or server | **No (`e2e=0`)** — start here |
| Unraid ↔ second Unraid | **No** |
| Multiple peers (tbn0 + tbn1) on Unraid | **No** (one host-wide value for every Thunderbolt netdev) |
| Flaky bring-up, one-way ping, or “ThunderboltIP login” timeouts | **No** — then [reseat the cable](troubleshooting.md#reseating-the-cable-why-it-matters) and retest |

Default **No** is the right starting point for almost every peer OS. You do not need a special “keep No forever” row — if it works, leave it.

### When you might try Yes

| Scenario | Notes |
|----------|-------|
| Unraid ↔ **macOS** Thunderbolt Bridge | Still start with **No**. Only try **Yes** if the link is stable in every other way but fails with e2e off. |
| Unraid ↔ **Windows** | OEM stacks vary; start **No**, then **Yes** only if testing (or vendor docs) require it. |
| Upstream / OEM guidance for a specific combo | Follow that note if you have one; retest after each change. |

### Changing E2E without confusing yourself

1. Change **only** E2E (not IP, not cable, not security) — one variable at a time.  
2. Apply, then confirm `/sys/module/thunderbolt_net/parameters/e2e` actually flipped (`Y`/`N` or `1`/`0`).  
3. If it did **not** change, reboot (or carefully reload the module — that drops **all** Thunderbolt netdevs).  
4. **Reseat the cable** after the module option is correct — many Thunderbolt domains only fully re-train when the physical path drops and returns (see [troubleshooting](troubleshooting.md#reseating-the-cable-why-it-matters)).  
5. If you had **more than one** Thunderbolt cable plugged in, unplug **all** of them first, then plug **one** known-good cable back in ([highly recommended recovery steps](links-and-topology.md#multi-cable-and-recovery)).  
6. Re-test **every** peer: E2E is host-wide.

### What E2E is *not*

- Not Ethernet pause frames on eth0  
- Not TCP window size  
- Not a per-cable QoS switch  
- Not related to Unraid “Enable default route” on the tbn tab  

---

## Example workflows

These are **examples**, not a claim about which peer type is most popular.

### A. Unraid ↔ Linux PC

1. Driver options: **Load modules = Yes**, **E2E = No**.  
2. Apply; confirm `e2e` sysfs if you just changed it.  
3. One cable between the hosts; wait for `thunderbolt0`.  
4. tbn0: static `10.255.0.2/24`, default route **No**.  
5. On the Linux peer: static `10.255.0.1/24` on its Thunderbolt net iface (NetworkManager or `ip`).  
6. Ping both ways. If the iface never appears, [reseat](troubleshooting.md#reseating-the-cable-why-it-matters) once with only that cable plugged in.

See [peer-scenarios.md](peer-scenarios.md).

### B. Unraid with two peers (tbn0 + tbn1)

1. Still **one** E2E setting for the host — leave **No**.  
2. Give each link its **own subnet** (`10.255.0.0/24` and `10.255.1.0/24`).  
3. Do not expect different e2e per peer.  
4. Prefer one cable per peer; dual-homing the **same** peer with two cables is a different problem ([topology](links-and-topology.md#dual-cable-between-the-same-pair-of-pcs)).

### C. After changing E2E (any peer OS)

1. Confirm sysfs.  
2. Clear multi-cable clutter if present (all unplugged → one cable).  
3. Reseat that cable.  
4. Re-test every peer, not just one.

---

## Related

- Peer OS notes: [peer-scenarios.md](peer-scenarios.md)  
- Reseating & multi-cable recovery: [troubleshooting.md](troubleshooting.md#reseating-the-cable-why-it-matters) · [links-and-topology.md](links-and-topology.md)  
- Settings list: [settings-reference.md](settings-reference.md)  
