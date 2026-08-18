# MTU and high-speed Thunderbolt transfers

Thunderbolt / USB4 host networking can train at **20 / 40 / 80&nbsp;Gb/s class** rates (generation + cable + both ends). The Linux `thunderbolt_net` driver still defaults the virtual NIC to **MTU 1500** — the classic Ethernet size — even though the driver allows much larger frames (often **maxmtu ~65522** on current kernels).

**Default recommendation:** leave **MTU 1500**. It works on first plug with no peer coordination.

**Optional jumbo (often 9000):** try when you want fewer packets per second — for example on **older hosts or weaker CPUs** under heavy rsync/SMB — so less time is spent packaging and interrupting on each frame. **Both ends must match**; MTU is not negotiated.

**Directionality:** marketing **40&nbsp;Gb/s** is **not** PCIe-style full duplex 40 each way. USB4/Thunderbolt lanes are **simplex**; a 40G-class path is typically **~20&nbsp;Gb/s each direction at once**. Details: [standards-and-speeds.md — Directionality](standards-and-speeds.md#directionality-read-this-first).

---

## Contents

- [Short answers](#short-answers)
- [When to stay on 1500](#when-to-stay-on-1500)
- [When jumbo can help](#when-jumbo-can-help)
- [Product defaults (plugin)](#product-defaults-plugin)
- [Both ends must match](#both-ends-must-match)
- [What we can and cannot control](#what-we-can-and-cannot-control)
- [How to enable jumbo in the plugin](#how-to-enable-jumbo-in-the-plugin)
- [Related](#related)

## Short answers

| Question | Answer |
|----------|--------|
| What should I use by default? | **1500** — easy, compatible, no peer MTU dance. |
| What does “1500 (kernel default)” mean? | Live sysfs MTU is 1500; we did **not** push a custom size. |
| Can Unraid set the peer’s MTU? | **No.** MTU is **not negotiated**. Each OS sets its own netdev. |
| Does jumbo unlock dual-lane / much more Gbit/s? | **No.** Line rate is from Thunderbolt training. Jumbo mainly **cuts packets/s and CPU** when both ends match. |
| Optional jumbo? | Often **9000 both ends** if you want lower packet overhead. Higher (16k–64k) possible but less portable. |

---

## When to stay on 1500

- First bring-up and day-to-day use  
- Peer OS or dock that is awkward to set MTU on  
- You do not want to debug mismatch black holes  

1500 is a valid Thunderbolt underlay choice, not a “wrong” setting.

---

## When jumbo can help

At multi‑20&nbsp;G rates, MTU 1500 means **millions of packets per second** for a full pipe. Each packet pays header and softirq cost.

Raising MTU (commonly to **9000**) sends the same bulk data in **fewer frames**, which can ease CPU load on **older or weaker machines** during large copies. It does **not** raise trained Thunderbolt rate by itself.

Rough scale (ideal full frames, one-way fill):

| One-way fill target | ≈ PPS @ MTU 1500 | ≈ PPS @ MTU 9000 |
|---------------------|------------------|------------------|
| ~20&nbsp;Gbit/s class | ~**1.7 M** | ~**280 k** |

If the link or disk cannot fill the pipe, jumbo can still reduce per-byte CPU when both ends agree.

---

## Product defaults (plugin)

| Setting | Default | Rationale |
|---------|---------|-----------|
| Desired MTU | **1500** (kernel default) | Easy first plug; no peer coordination |
| Enable jumbo frames | **Off** | Opt in when you deliberately match both ends |
| Jumbo size (typical) | **9000** | Common value; keep **identical** on peer |

Overview **Local iface state** shows e.g. `MTU 1500 (kernel default)` or `MTU 9000 (jumbo)`.

---

## Both ends must match

| Unraid | Peer | Typical result |
|--------|------|----------------|
| 1500 | 1500 | Works (default) |
| 9000 | 9000 | Works; optional CPU/packet relief |
| 9000 | 1500 | Black holes, stalls, or silent drops — **fix** |
| 1500 | 9000 | Same problem the other way |

### Peer examples (you set these on the peer — Unraid cannot push them)

**Linux:**

```bash
sudo ip link set dev thunderbolt0 mtu 9000
# or whatever name `ip link` shows for the Thunderbolt netdev
```

**macOS** (name varies):

```bash
sudo ifconfig bridge0 mtu 9000   # example only — use the real iface from ifconfig
```

**Windows:** adapter advanced properties / netsh — same MTU on the Thunderbolt/USB4 adapter.

---

## What we can and cannot control

| | Unraid (this plugin) | Peer |
|--|----------------------|------|
| Create Thunderbolt netdev | Kernel when path trains | Kernel / OS when path trains |
| Set MTU on **our** `thunderboltN` | **Yes** — tbn tab → Desired MTU → Apply | N/A |
| Set MTU on **peer** NIC | **No** | Peer admin / script |
| Emulate “10G NIC” speed | No — rate is fabric training | Same |

We are **not** limited to 1500 by Thunderbolt hardware in normal cases; jumbo is a **configuration** choice both ends share.

---

## How to enable jumbo in the plugin

1. Settings → Network Settings → **tbnN** for the link.  
2. Leave **1500** unless you need it — then check **Enable jumbo frames** and set **9000** (or another agreed value).  
3. Apply.  
4. Set **the same MTU on the peer** before large transfers.  
5. Confirm overview **Local iface state** shows jumbo and the peer shows the same value.

If the peer cannot do jumbo, keep Unraid on **1500**.

---

## Related

- Speeds / generations: [standards-and-speeds.md](standards-and-speeds.md)  
- Addressing: [addressing.md](addressing.md)  
- Settings fields: [settings-reference.md](settings-reference.md)  
- Troubleshooting slow copies: [troubleshooting.md](troubleshooting.md)  
