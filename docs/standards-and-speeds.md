# Standards, speeds, and cables

Thunderbolt Net uses the Linux **`thunderbolt`** + **`thunderbolt_net`** stack: host-to-host networking over a Thunderbolt **fabric**, not plain USB gadget/Ethernet dongles.

This page is the **canonical** explanation of rates, duplex, mixing generations/cables, and what to expect on Unraid. Skim the TOC, then the tables.

---

## Contents

- [Directionality (read this first)](#directionality-read-this-first)
- [Expected bandwidth by standard / class (planning table)](#expected-bandwidth-by-standard-class-planning-table)
- [Mixing generations, cables, and lanes](#mixing-generations-cables-and-lanes)
- [What this plugin is aiming at](#what-this-plugin-is-aiming-at)
- [Port silkscreen (case / rear I/O)](#port-silkscreen-case-rear-io)
- [What is *not* the same thing](#what-is-not-the-same-thing)
- [Rate notation (Gb/s vs GB/s)](#rate-notation-gbs-vs-gbs)
- [Rate vs lanes](#rate-vs-lanes)
- [Link quality messages (plugin)](#link-quality-messages-plugin)
- [Cables](#cables)
- [Throughput expectations](#throughput-expectations)
- [FAQ](#faq)
- [Related](#related)

---

## Directionality (read this first)

Each USB4 / Thunderbolt lane has its own transmit pair and its own receive pair, so a lane carries its rate in both directions at once (USB4 specification). Linux reports the same rates for a host-to-host link:

| Link | Each direction | Linux reports |
|------|----------------|---------------|
| Gen 3, one lane | 20&nbsp;Gb/s | 20000&nbsp;Mb/s |
| Gen 3, two bonded lanes (the "40&nbsp;Gb/s" sticker) | 40&nbsp;Gb/s | 40000&nbsp;Mb/s |
| Gen 4, two bonded lanes (the "80&nbsp;Gb/s" sticker) | 80&nbsp;Gb/s | 80000&nbsp;Mb/s |

That is the rate of the link. What a host can send over it can be lower, as the measurements below show.

### Measured: two Maple Ridge hosts, host-to-host

Two ASUS X670E boards with onboard Maple Ridge 4C, firmware connection manager, Linux 6.18 and 7.2. Each number is per direction unless stated.

| Test | Result |
|------|--------|
| TCP (iperf3), one lane | 13&ndash;14&nbsp;Gb/s |
| TCP, two lanes bonded by hand (lab test, not a plugin feature) | 13&ndash;14&nbsp;Gb/s |
| Raw 4&nbsp;KiB frames, no TCP, 1 to 4 DMA rings, one lane or two | 13&ndash;14.8&nbsp;Gb/s in total; rings share it |
| Raw frames, no interrupts, batched doorbells | 15.2&nbsp;Gb/s |
| Same, with the receiver taken out of the path | 15.7&nbsp;Gb/s at most |
| Raw frames, both directions at once | about 13&nbsp;Gb/s each way |

- These changed nothing beyond the variation between runs: MTU 1500, 9000 and 65522; end-to-end flow control; path credits 14 to 60; PCIe MaxReadReq 512 to 4096; CPU sleep states.
- On these hosts the firmware leaves the second lane disabled on host-to-host links, and the BIOS does not give Linux control of USB4.
- The limit is on the sending host, between RAM and the controller. Which part of that path sets it has not been identified.
- The link from the controller to the chipset runs at 8&nbsp;GT/s x4 on both boards (31.5&nbsp;Gb/s before PCIe packet overhead).
- Other controllers have not been tested here.

### For Unraid bulk copies (rsync, NBD, SMB on tbn)

| Trained path (typical) | Planning number (TCP payload, order of) |
|------------------------|-------------------------------------------|
| 20&nbsp;Gb/s · 1-lane | 13&ndash;14&nbsp;Gb/s one way measured on Maple Ridge; measure yours |
| Two lanes | Measure yours; two lanes did not raise it on Maple Ridge |
| Soft-RoCE / RDMA on top of Thunderbolt-net | Often **lower bandwidth** than plain TCP; different use case |

Design storage jobs around **measured** iperf/rsync, not “40G cable ⇒ 5&nbsp;GB/s.”

---

## Expected bandwidth by standard / class (planning table)

Numbers below are **order-of-magnitude** for **host-to-host** when the path actually trains that class.  
**Both ends + cable** must support the row.

| Marketing / class | Lanes (typical USB4-style) | Max **each direction** at once (signaling) | Max **both ways at once** (sum / sticker) | Rough **one-way TCP/iperf** to plan for* | Notes |
|-------------------|----------------------------|--------------------------------------------|-------------------------------------------|------------------------------------------|--------|
| **USB 3.2 Gen 2×1** (SS10) | USB SuperSpeed, not Thunderbolt fabric | ~10&nbsp;Gb/s | USB full duplex (not this plugin) | — | **Not** `thunderbolt_net`. Dongle eth is separate. |
| **USB 3.2 Gen 2×2** (SS20) | SuperSpeed | ~20&nbsp;Gb/s | — | — | Still not Thunderbolt host-net. |
| **USB4 20G** / single-lane class | One lane at Gen 3 | 20&nbsp;Gb/s | 20 + 20 | Measure | |
| **Thunderbolt 3** | Two lanes, each with TX and RX | 40&nbsp;Gb/s bonded, 20 on one lane | 40 + 40 | Measure | |
| **Thunderbolt 4** / **USB4 40G** | Two lanes, each with TX and RX | 40&nbsp;Gb/s bonded, 20 on one lane | 40 + 40 | 13&ndash;14&nbsp;Gb/s measured on Maple Ridge (above) | |
| **USB4 v2 / Thunderbolt 5** (symmetric class) | Two lanes at Gen 4 | 80&nbsp;Gb/s | 80 + 80 | Measure | Asymmetric modes exist. |
| **Thunderbolt 5 asymmetric** (e.g. 120/40 class) | 3+1 style lane allocation | Unequal (e.g. more A→B than B→A) | Sticker sum of dirs | Measure **both** directions | Direction matters for copies. |

\*Payload rate, not link rate.

### How to read one row (Thunderbolt 4 example)

| Question | Answer |
|----------|--------|
| Port says **40**? | 40&nbsp;Gb/s each direction with two lanes bonded. |
| Sysfs `20 Gb/s` · `1-lane`? | One lane active: 20&nbsp;Gb/s each direction. |
| Sysfs two lanes? | 40&nbsp;Gb/s each direction on the link; measure what the hosts send. |
| Can I force 30&nbsp;G one way / 10&nbsp;G the other? | **No** (see [mixing](#mixing-generations-cables-and-lanes)). Whole-lane allocation only; not half-lanes. |

**Plugin UI:** Link quality **LOCAL** = controller class ceiling; **REMOTE** = **trained** path. Yellow *Single-lane* = one lane active on a host that supports two.

---

## Mixing generations, cables, and lanes

**Yes, you can mix** Thunderbolt 3 / 4 / 5 / USB4 tiers and cables — but only as a **fallback to a common trained path**. You do **not** get a custom hybrid (e.g. “half the lanes Thunderbolt 3, half Thunderbolt 5”).

### What “mix” means

| Mix | What happens |
|-----|----------------|
| **Different host generations** (Thunderbolt 3 ↔ Thunderbolt 4 ↔ Thunderbolt 5, USB4 20G ↔ 40G) | Works when both support a shared host-to-host path; trains at the **lowest common** capability |
| **Different cables** (Thunderbolt 3 cable on Thunderbolt 4 ports) | Often works; the link runs at what both ends and the cable support |
| **Different lane counts** (2-lane-capable host ↔ 1-lane train) | Normal — you get **what trains** |
| **Thunderbolt host-net + SuperSpeed-only USB-C** | **No** `thunderbolt_net` path — different stack |
| **Per-lane “this lane 20G, that lane 40G”** | **No** — one negotiated path, one train class |
| **Split one 20&nbsp;G lane into two 10&nbsp;G** | **No** — lanes are not subdivided for bonding |

### Host generation mixing

Newer ports generally talk to older peers when the stack allows:

```text
Thunderbolt 5 / USB4 v2  ──►  falls back  ──►  Thunderbolt 4 / USB4 40G  ──►  Thunderbolt 3 / USB4 20G class
```

| Pair | Expectation |
|------|-------------|
| Thunderbolt 4 ↔ Thunderbolt 4 | Best chance of full **40G-class** (20+20) if cable trains dual-lane |
| Thunderbolt 4 ↔ Thunderbolt 3 | Usually works; often **Thunderbolt 3-class** ceiling |
| Thunderbolt 5 ↔ Thunderbolt 4 | Works at **Thunderbolt 4-class** (or lower), not full Thunderbolt 5 rates |
| USB4 20G host ↔ Thunderbolt 4 peer | Limited by the **20G** side |
| USB-C **SS only** ↔ anything | USB data/charge only — **not** ThunderboltNet |

**Rule of thumb:**  
**min(host A class, host B class, cable class, what Linux ICM actually trains).**

### Cable mixing

| Cable | On higher-end ports |
|-------|---------------------|
| Certified **Thunderbolt 4 / USB4 40G**, short | Best shot at dual-lane / full class |
| **Thunderbolt 3** cable | Often trains; may cap at Thunderbolt 3 behavior |
| Generic USB-C / “USB4 20G” | Often **20G · 1-lane** or no host path |
| Long passive | May drop lanes/rate |
| Active / optical (correct class) | For length; class must match |

A **40G host + 20G cable** does **not** give 40G-class both ways.

### Lane allocation (2+2 vs 3+1) — not user “lane mixing”

Lanes are **whole** TX or RX at a **trained** per-lane rate. They are not mixed standards.

| Idea | On classic ~20&nbsp;G/lane 40G-class | User-selectable on Linux host-net? |
|------|--------------------------------------|-------------------------------------|
| **2 TX + 2 RX** (normal) | ~**20&nbsp;G each way** | Default when fully trained |
| **3 + 1** asymmetric | ~**60&nbsp;G one way / 20&nbsp;G other** | Spec/some Thunderbolt 5-style modes; **rarely** a Unraid/plugin knob |
| **30/10 by splitting 20G lanes** | **Not valid** | Half-lanes don’t exist for this |
| Dual cable same peer for 2× speed | Usually **one** host-net path | Bonding same peer is not the product model |

**Thunderbolt 5 / USB4 v2** advertise asymmetric modes more (e.g. **120/40**-style). Still whole-lane allocation; still both ends + cable + connection manager.

### What Linux / the plugin show when you mix

Trust **trained** values, not stickers:

| Signal | Meaning |
|--------|---------|
| `rx_speed` / `tx_speed` | Rate that **actually trained** (often per-lane) |
| `rx_lanes` / `tx_lanes` | Lanes each direction |
| Plugin **Link quality** | Controller **max class** vs **trained** path |

### One sentence

> Generations and cables **interoperate by down-training** to a common path; you don’t assign different standards per lane.

---

## What this plugin is aiming at

| Generation / class | Host networking (when both ends + cable support it) | Notes for this plugin |
|--------------------|------------------------------------------------------|------------------------|
| **Thunderbolt 3** | Yes (classic ThunderboltIP / XDomain path) | Still relevant on many boards and peers |
| **Thunderbolt 4** | Yes | Common desktop/laptop class; stricter cert than many “USB4 20G” cables |
| **Thunderbolt 5** | Yes (higher rates when path trains) | Built on USB4 v2-class signaling; higher class when everything matches |
| **USB4** (20G / 40G product tiers) | **Optional** host-to-host when the platform implements host router + networking | Same Linux driver family as Thunderbolt on modern kernels |
| **USB4 Version 2.0** | Higher PHY rates when controllers/cables allow | Often discussed alongside Thunderbolt 5; not a separate plugin mode |

We do **not** lock the product to one marketing number (not Thunderbolt 4-only, not “40G only”).  
If Linux sees a host domain and a `thunderboltN` netdev — Thunderbolt 3, Thunderbolt 4, Thunderbolt 5, USB4 20G/40G-class, or USB4 v2 80G-class — this plugin is for that link.

On Maple Ridge, **20&nbsp;Gb/s · 1-lane** on a host-to-host link is the firmware leaving the second lane disabled.

---

## Port silkscreen (case / rear I/O)

Match printed marks on the metal panel to decide if a Type‑C is Thunderbolt/USB4 or only SuperSpeed USB. See [port-icons.md](port-icons.md). Short form: **lightning / 40** → Thunderbolt-class; **SS¹⁰ / SS²⁰ only** → USB SuperSpeed, not this plugin’s path.

---

## What is *not* the same thing

| Name | Relation to Thunderbolt Net |
|------|------------------------------|
| **USB 2.x / USB 3.x only** ports | Normal USB. **No** `thunderbolt_net` host-to-host tunnel. A USB Ethernet dongle is still ordinary `eth`/`enp…`. |
| **USB-C charging / “data” cables** without Thunderbolt/USB4 high-rate cert | May charge or do slow USB only; often fail to train a full host path or train at 20&nbsp;G × 1 lane. |
| **“USB5”** | **Not** a mainstream USB-IF product name today. Prefer **USB4**, **USB4 Version 2.0**, **Thunderbolt 5**. |
| **Dock RJ45** | Almost always a **USB/PCIe NIC** on the dock — configure like Ethernet, not tbn. |
| **USB4STREAM** / **usb4_rdma** | Other host-to-host paths (raw stream / experimental RDMA). See [usb4stream.md](usb4stream.md). Not required for tbn IP. |

---

## Rate notation (Gb/s vs GB/s)

| Write | Means |
|-------|--------|
| **Gb/s** | Gigabit per second (signaling / link rate). **G** = giga (SI), **b** = **bit**. Sysfs and this plugin use this. |
| **GB/s** | Gigabyte per second (≈ 8× larger than Gb/s). **Do not** use for Thunderbolt link rate. |
| **Mb/s** | Megabit per second (USB 2.0 class, etc.) |

UI and docs use **Gb/s** consistently (e.g. `20 Gb/s · 1-lane`, `Max ~40 Gb/s · 2-lane`). Prefer “class” or “per direction” over bare `40G`.

---

## Rate vs lanes

- **Gb/s** in sysfs is typically the **per-lane** trained signaling rate.  
- **Lanes** are **directional** (`rx_lanes` / `tx_lanes`). More lanes one way = more capacity that way.  
- On Thunderbolt 3/4-era paths, **1-lane trained** often pairs with ~**20&nbsp;Gb/s** per-lane display; **2-lane** is what people expect from a “40G” sticker when fully trained.  
- **20&nbsp;Gb/s · 1-lane** on a two-lane host is not a failed install.  
- Sticker **“40&nbsp;Gb/s”** = 40&nbsp;Gb/s each direction on two bonded lanes. See the measurements above for what hosts can send.

### How the UI builds “20 Gb/s full-duplex · using 1 of 2 lanes”

**Not dummy text.** `tbn_format_link_rate()` reads live sysfs on the peer path:

| Input | Source |
|-------|--------|
| Rate | `rx_speed` / `tx_speed` (trained) |
| Used lanes | `max(rx_lanes, tx_lanes)` from the **same path** |
| “of M” | Host controller **class max** lanes (`tbn_controller_capability()`, e.g. 2 for TB4 dual-lane class) |

So “using **1** of **2** lanes” means: **1** lane is active each way, while this host’s controller class supports two. It is **not** a live PPS/throughput calculation — it is **trained link state** from the connection manager / thunderbolt driver.

Linux sysfs often exposes:

- `generation` on the local controller (`0-0`)  
- `rx_speed` / `tx_speed` and `rx_lanes` / `tx_lanes` on the **peer path**  
- optional `USB4_VERSION` in uevent  

---

## Link quality messages (plugin)

| Column | What you see |
|--------|----------------|
| **LOCAL** | Host **Max** (e.g. ~40&nbsp;Gb/s · 2-lane) = controller class ceiling, not a guarantee. |
| **REMOTE** | Status badge + **trained** rate; details under **Why & what to try**. |

| Remote status | Meaning |
|---------------|---------|
| **Single-lane** (yellow) | One lane active on a host that supports two |
| **Healthy** / near max | Dual-lane high rate for this class (nice when it happens) |
| **Dual-lane** / **Linked** | Up; may still be under ceiling |

Yellow means “below controller class max,” not “broken plugin” or “always fix with a new cable.”

---

## Cables

| Cable class | Typical result |
|-------------|----------------|
| Certified **Thunderbolt** / **USB4** high-rate (match your hosts), short passive | Use a cable rated for the link class |
| Long passive | Check the cable's rating for length |
| Generic USB-C without high-rate cert | Often 20&nbsp;G × 1 or no host path |
| Active / optical (correct class) | Needed for longer runs |

Marketing stickers lie; **sysfs trained rate** is the ground truth for your pair.

---

## Throughput expectations

- Trained line rate ≠ sustained TCP/SMB forever — protocol, CPU, storage, and **MTU** matter.  
- Measured on Maple Ridge: 13&ndash;14&nbsp;Gb/s TCP on one lane or two.  
- **MTU 1500** is the easy default. Measured on Maple Ridge: MTU 1500, 9000 and 65522 gave the same throughput.  
- Design around **measured** numbers.  
- Full MTU guidance: **[mtu-and-throughput.md](mtu-and-throughput.md)**.

---

## FAQ

| Question | Answer |
|----------|--------|
| Is 40&nbsp;Gb/s Thunderbolt the same as PCIe 40G full duplex? | **No.** Thunderbolt lanes are simplex; 40G-class ≈ **20 each way**. |
| Can I mix Thunderbolt 3/Thunderbolt 4/Thunderbolt 5/USB4? | **Yes** — falls back to a **common** trained path. |
| Can I mix cables? | **Yes** — weakest link wins. |
| Can I assign different standards per lane? | **No.** |
| Can I split 20G into dual 10G or force 30/10? | **No.** Whole lanes only; 3+1 at 20G/lane would be **60/20** if available, not user-selectable on typical Linux host-net. |
| Why is my “40G” port only 20&nbsp;Gb/s · 1-lane? | On Maple Ridge the firmware leaves the second lane disabled on host-to-host links. |
| What should I plan for bulk copy? | Measure with iperf/rsync. Maple Ridge measured 13&ndash;14&nbsp;Gb/s one way. |
| Where is the planning table? | [Expected bandwidth by standard / class](#expected-bandwidth-by-standard--class-planning-table) |

---

## Related

- Topology / dual cable: [links-and-topology.md](links-and-topology.md)  
- Requirements: [requirements.md](requirements.md)  
- MTU & bulk transfer: [mtu-and-throughput.md](mtu-and-throughput.md)  
- USB4STREAM / RDMA naming: [usb4stream.md](usb4stream.md)  
- Troubleshooting slow links: [troubleshooting.md](troubleshooting.md)  
