# MTU and high-speed Thunderbolt transfers

Thunderbolt / USB4 host networking can train at **20 / 40 / 80&nbsp;Gb/s class** rates (generation + cable + both ends). The Linux `thunderbolt_net` driver still defaults the virtual NIC to **MTU 1500** — the classic Ethernet size — even though the driver allows much larger frames (often **maxmtu ~65522** on current kernels).

This page explains why that matters for **rsync, SMB, NFS, and large file copies**, what the plugin does, and how to raise MTU **on both ends**.

---

## Short answers

| Question | Answer |
|----------|--------|
| What does “1500 (kernel default)” mean? | Live sysfs MTU is 1500; we did **not** push a custom size. Label is intentional. |
| Product default MTU? | **9000** for bulk host-net (peer must match). Use **1500** only if the peer cannot do jumbo. |
| Can Unraid set the peer’s MTU? | **No.** Each OS configures its own netdev when the virtual NIC appears. Match manually (or via peer scripts). |
| Is jumbo “emulating a 10G+ NIC”? | MTU is frame size, not line rate. Line rate comes from TB path training. Jumbo reduces **packets/s and CPU** at a given rate. |
| Safe recommended value? | **9000** both ends for host↔host TB bulk. Higher (e.g. 16k–64k) is possible but less portable across stacks. |

---

## Overhead: why 1500 hurts at TB speeds

Every IPv4 TCP segment pays roughly:

| Layer | Typical bytes |
|-------|----------------|
| Ethernet + FCS (approx) | ~18–24 |
| IPv4 header | 20 |
| TCP header | 20 |
| **Per-packet tax** | **~60–70 B** (order of magnitude) |

Payload per frame ≈ `MTU − IP − TCP` (≈ **1460 B** at MTU 1500, ≈ **8960 B** at MTU 9000).

### Packets per second vs link class (payload-bound ideal)

Approximate **minimum** packet rate to fill a pipe if every frame is full-sized (reality is worse with smaller writes and ACKs):

| Link class (marketing) | ≈ bits/s | ≈ B/s | PPS @ MTU 1500 | PPS @ MTU 9000 | Ratio |
|------------------------|----------|--------|----------------|----------------|-------|
| 10&nbsp;Gb/s class | 1×10¹⁰ | ~1.25×10⁹ | ~**830 k** | ~**140 k** | ~6× |
| 20&nbsp;Gb/s class | 2×10¹⁰ | ~2.5×10⁹ | ~**1.7 M** | ~**280 k** | ~6× |
| 40&nbsp;Gb/s class | 4×10¹⁰ | ~5.0×10⁹ | ~**3.3 M** | ~**555 k** | ~6× |
| 80&nbsp;Gb/s class | 8×10¹⁰ | ~1.0×10¹⁰ | ~**6.7 M** | ~**1.1 M** | ~6× |

PPS uses `B/s ÷ MTU` as a simple upper-bound stand-in (includes headers in the MTU bucket). Real TCP also sends ACKs the other way; storage and protocol chat add more. The takeaway is unchanged: **at multi‑20&nbsp;G rates, 1500&nbsp;B frames are millions of packets per second.**

### Header efficiency (full frames)

| MTU | Approx. TCP payload | Header share (order of) |
|-----|---------------------|-------------------------|
| 1500 | ~1460 B | ~3–4% of frame |
| 9000 | ~8960 B | ~0.7% of frame |
| 64000 | ~63940 B | tiny |

On a pure percentage basis jumbo looks like a few percent win. **The larger win is CPU / softirq / syscall overhead** (fewer packets for the same rsync or SMB stream), which is why 10/40/100&nbsp;G Ethernet operators run jumbo on known clean paths — and TB host↔host is exactly that kind of path when both ends agree.

### Worked example: 100&nbsp;GiB copy

| | MTU 1500 | MTU 9000 |
|--|----------|----------|
| Full-size data frames (ideal) | ~73 M | ~12 M |
| Same TB line rate | More interrupts, more per-packet work | Far fewer |

If the link or disk cannot fill 40&nbsp;G, MTU still reduces per-byte CPU on both ends.

---

## Product defaults (plugin)

| Setting | Default | Rationale |
|---------|---------|-----------|
| MTU mode | **9000 — both ends** | Bulk TB host-net; peer must match |
| Safe fallback | **1500 — kernel default** | When peer cannot jumbo |
| Custom | Any value in driver min–max | Keep **identical** on peer; does not raise trained lanes |

Overview **Local iface state** shows e.g. `MTU 1500 (kernel default)` or `MTU 9000 (jumbo)` so it is obvious you have not raised it yet.

---

## Both ends must match

| Unraid | Peer | Typical result |
|--------|------|----------------|
| 1500 | 1500 | Works; higher PPS for bulk |
| 9000 | 9000 | Works; preferred for TB bulk |
| 9000 | 1500 | Black holes, stalls, or silent drops — **fix** |
| 1500 | 9000 | Same problem the other way |

### Peer examples (you set these on the peer — Unraid cannot push them)

**Linux** (persistent via NetworkManager/netplan/systemd-networkd, or once):

```bash
sudo ip link set dev thunderbolt0 mtu 9000
# or whatever name `ip link` shows for the TB netdev
```

**macOS** (interface name varies; Thunderbolt Bridge / service name in System Settings):

```bash
sudo ifconfig bridge0 mtu 9000   # example only — use the real iface from ifconfig
```

**Windows**: adapter advanced properties / netsh — set the TB/USB4 network adapter MTU to the **same** value (9000). Names vary by driver.

---

## What we can and cannot control

| | Unraid (this plugin) | Peer |
|--|----------------------|------|
| Create TB netdev | Kernel when path trains | Kernel / OS when path trains |
| Set MTU on **our** `thunderboltN` | **Yes** — tbn tab → Desired MTU → Apply | N/A |
| Set MTU on **peer** NIC | **No** remote control via thunderbolt_net | Peer admin / script |
| Emulate “10G NIC” speed | No — rate is fabric training | Same |
| GSO/TSO large segments | Kernel offloads already large on modern stacks | Same |

We are **not** limited to 1500 by Thunderbolt hardware in normal cases; we are limited by **configuration agreement** and the Ethernet-sized default.

---

## How to enable 9000 in the plugin

1. Settings → Network Settings → **tbnN** for the link.  
2. **Desired MTU** → **9000 — recommended for TB bulk (both ends)**.  
3. Apply.  
4. On the peer, set **the same MTU** before large transfers.  
5. Confirm overview **Local iface state** shows `MTU 9000 (jumbo)` and peer tools show 9000.

If the peer cannot do jumbo, leave Unraid on **1500**.

---

## Related

- Speeds / generations: [standards-and-speeds.md](standards-and-speeds.md)  
- Addressing: [addressing.md](addressing.md)  
- Settings fields: [settings-reference.md](settings-reference.md)  
- Troubleshooting slow copies: [troubleshooting.md](troubleshooting.md)  
