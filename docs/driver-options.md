# Driver options (host-wide)

These live on **Settings → Network Settings → Thunderbolt → Driver options**.  
They affect the **whole Unraid host**, not a single `tbnN` tab.

---

## Load modules on Apply

| Value | Meaning |
|-------|---------|
| **Yes** (default) | Apply runs `modprobe thunderbolt` and `modprobe thunderbolt_net` |
| **No** | Apply does not load modules — only if you load them yourself (e.g. in `/boot/config/go`) |

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
| **No (e2e=0)** (product default) | `e2e=0` | End-to-end TB network flow control **off** |
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

Changing e2e after the module is loaded may require a module reload or reboot and can disrupt **every** TB netdev at once.

### Why the product default is No (`e2e=0`)

Upstream default is **true** (on). In practice, many **cross-host** setups (Unraid ↔ another Linux machine, mixed vendors/firmware) behave more reliably with **`e2e=0`**: fewer stuck “ThunderboltIP login” states, fewer one-way or flaky data paths after the link trains.

That is an empirical reliability choice for Unraid home-lab host networking — not a claim that e2e is “wrong” in every design.

### When to leave No (recommended)

| Scenario | Recommendation |
|----------|----------------|
| Unraid ↔ Linux desktop/laptop (Kernel TB net both sides) | **No (`e2e=0`)** — start here |
| Unraid ↔ second Unraid / Linux server | **No** |
| Multiple peers (tbn0 + tbn1) on Unraid | **No** (one host-wide value; prefer the reliable setting for all) |
| Flaky bring-up, one-way ping, or login timeouts with e2e on | **No** |
| You already know `e2e=0` fixed your pair | Keep **No** |

### When you might try Yes

| Scenario | Notes |
|----------|-------|
| Unraid ↔ **macOS** Thunderbolt Bridge | Try **No** first; if the Mac side is stable only with driver-default behavior on Linux, experiment with **Yes** and retest. Document what works for your Mac OS version. |
| Unraid ↔ **Windows** | Windows Thunderbolt networking support varies by OEM stack; try **No** first, then **Yes** only if the vendor docs or testing require it. |
| Same-vendor lab with upstream guidance | If a kernel bug report or Intel/OEM note says leave e2e on for a specific combo, use **Yes**. |
| Debugging only | Change one variable at a time; reconnect cable after Apply; reboot if `/sys/module/.../e2e` did not change. |

### What E2E is *not*

- Not Ethernet pause frames on eth0  
- Not TCP window size  
- Not a per-cable QoS switch  
- Not related to Unraid “Enable default route” on the tbn tab  

---

## Example workflows

### A. Unraid ↔ Linux PC (most common)

1. Driver options: **Load modules = Yes**, **E2E = No**.  
2. Apply.  
3. Cable both hosts; wait for `thunderbolt0`.  
4. tbn0: static `10.255.0.2/24`, default route **No**.  
5. On the Linux peer: static `10.255.0.1/24` on its TB net iface (NetworkManager or `ip`).  
6. Ping both ways.

See [peer-scenarios.md](peer-scenarios.md).

### B. Unraid with two peers (tbn0 + tbn1)

1. Still **one** E2E setting for the host — leave **No**.  
2. Give each link its **own subnet** (`10.255.0.0/24` and `10.255.1.0/24`).  
3. Do not expect different e2e per peer.

### C. Changing E2E after things already work

1. Note current `/sys/module/thunderbolt_net/parameters/e2e`.  
2. Change UI, Apply.  
3. If the sysfs value did not change, reboot (or carefully reload the module — disrupts all TB nets).  
4. Re-test every peer, not just one.

---

## Related

- Peer OS notes: [peer-scenarios.md](peer-scenarios.md)  
- Settings list: [settings-reference.md](settings-reference.md)  
- Topology / dual cable: [links-and-topology.md](links-and-topology.md)  
