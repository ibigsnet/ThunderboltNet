# Standards, speeds, and cables

Thunderbolt Net uses the Linux **`thunderbolt`** + **`thunderbolt_net`** stack: host-to-host networking over a Thunderbolt **fabric**, not plain USB gadget/Ethernet dongles.

This page is the **canonical** explanation of rates, duplex, mixing generations/cables, and what to expect on Unraid. Skim the TOC, then the tables.

---

## Contents

1. [Directionality (simplex vs PCIe)](#directionality-read-this-first)  
2. [Expected bandwidth by standard / class](#expected-bandwidth-by-standard--class-planning-table)  
3. [Mixing generations, cables, and lanes](#mixing-generations-cables-and-lanes)  
4. [What this plugin supports](#what-this-plugin-is-aiming-at)  
5. [Port silkscreen](#port-silkscreen-case--rear-io)  
6. [What is *not* the same thing](#what-is-not-the-same-thing)  
7. [Rate notation (Gb/s vs GB/s)](#rate-notation-gbs-vs-gbs)  
8. [Rate vs lanes (sysfs)](#rate-vs-lanes)  
9. [Link quality in the plugin](#link-quality-messages-plugin)  
10. [Cables](#cables)  
11. [Throughput expectations (TCP / bulk)](#throughput-expectations)  
12. [FAQ](#faq)

---

## Directionality (read this first)

Thunderbolt / USB4 **is not PCIe-style “full duplex at the sticker number.”**

People often treat **PCIe** as: *“40&nbsp;Gb/s” ≈ 40&nbsp;Gbit/s in each direction at once* (true full duplex on the link).  
**USB4 / Thunderbolt lanes are simplex:** each lane is either **TX or RX**, not both.

| Concept | PCIe (common mental model) | USB4 / Thunderbolt (typical) |
|---------|----------------------------|------------------------------|
| Lane | Full duplex (send **and** receive on the path) | **Simplex** — each lane is TX **or** RX |
| “40&nbsp;Gb/s” sticker | Often heard as 40 each way | **~20&nbsp;Gb/s each direction at once** on a classic 40G-class path |
| Simultaneous both ways | Sticker ≈ one-way capacity | Sticker is **sum of directional capacity** (TX pool + RX pool) |

### Classic 40&nbsp;Gb/s-class path (TB3 / TB4 / USB4 40G)

USB4-class hosts use **four lanes**, each up to **~20&nbsp;Gb/s**, and each lane is **one direction only**:

```text
  Host A ──► Host B   up to ~20 Gb/s   (TX lanes)
  Host A ◄── Host B   up to ~20 Gb/s   (RX lanes)

  Marketing “40 Gb/s” ≈ 20 + 20  (both directions at once)
  Not ≈ 40 A→B and 40 B→A at the same time
```

| Phrase | What it should mean for TB host-net |
|--------|-------------------------------------|
| **40&nbsp;Gb/s standard / class** | About **20&nbsp;Gb/s A→B** and **20&nbsp;Gb/s B→A** **simultaneously** (“full duplex 20&nbsp;G”) |
| **Trained 20&nbsp;Gb/s · 1-lane** | Sysfs **per-lane** rate; **1-lane** is common for Linux host↔host |
| **iperf / TCP ~13–15&nbsp;Gbit/s** | Normal payload after protocol/CPU/storage — **not** a failed 40G port |

Lab experience: a dual-capable “40G” host that trains **20&nbsp;Gb/s · 1-lane** often still delivers **~14&nbsp;Gbit/s** TCP one way — fine for bulk copies, not “half a PCIe x4.”

### Higher classes (USB4 v2 / Thunderbolt 5)

Same simplex-lane idea; **per-lane** rate goes up (e.g. toward **40&nbsp;Gb/s per lane**). Modes can be:

| Mode (simplified) | Rough simultaneous budget |
|-------------------|---------------------------|
| Symmetric “80G class” | e.g. **~40&nbsp;G each way** (still not 80 each way) |
| Asymmetric TB5-style | e.g. more lanes one way (marketing **120/40**-style) |

Always trust **sysfs trained** `rx_speed` / `tx_speed` and **lanes**, not the rear-panel “40” alone.

### Mental model (one sentence)

> **Sticker 40&nbsp;Gb/s Thunderbolt ≈ full-duplex 20&nbsp;Gbit/s class** (20 each direction at once),  
> **not** full-duplex 40&nbsp;Gbit/s like many people assume for PCIe.

### For Unraid bulk copies (rsync, NBD, SMB on tbn)

| Trained path (typical) | Planning number (TCP payload, order of) |
|------------------------|-------------------------------------------|
| 20&nbsp;Gb/s · 1-lane | **~10–15&nbsp;Gbit/s** one way |
| Dual-lane high train (when you get it) | Higher; still not sticker×2 as “each way” |
| Soft-RoCE / RDMA on top of TB-net | Often **lower bandwidth** than plain TCP; different use case |

Design storage jobs around **measured** iperf/rsync, not “40G cable ⇒ 5&nbsp;GB/s.”

---

## Expected bandwidth by standard / class (planning table)

Numbers below are **order-of-magnitude** for **host-to-host** when the path actually trains that class.  
**Both ends + cable** must support the row. Linux host-net often trains **below** the max row (commonly **20&nbsp;Gb/s · 1-lane** even on dual-capable hosts).

| Marketing / class | Lanes (typical USB4-style) | Max **each direction** at once (signaling) | Max **both ways at once** (sum / sticker) | Rough **one-way TCP/iperf** to plan for* | Notes |
|-------------------|----------------------------|--------------------------------------------|-------------------------------------------|------------------------------------------|--------|
| **USB 3.2 Gen 2×1** (SS10) | USB SuperSpeed, not TB fabric | ~10&nbsp;Gb/s | USB full duplex (not this plugin) | — | **Not** `thunderbolt_net`. Dongle eth is separate. |
| **USB 3.2 Gen 2×2** (SS20) | SuperSpeed | ~20&nbsp;Gb/s | — | — | Still not TB host-net. |
| **USB4 20G** / single-lane class | Often 1× ~20&nbsp;G class path | ~**20&nbsp;Gb/s** | ~**20+20** if both dirs train well | **~10–15&nbsp;Gbit/s** | Many “USB4” cables/hosts land here. |
| **Thunderbolt 3** | 2 TX + 2 RX @ ~20&nbsp;G/lane (simplex) | ~**20&nbsp;Gb/s** | ~**40&nbsp;Gb/s** sticker sum | **~10–15&nbsp;Gbit/s** typical 1-lane; higher if dual-lane | “40G” ≠ 40 each way. |
| **Thunderbolt 4** / **USB4 40G** | same pattern as TB3 when full | ~**20&nbsp;Gb/s** | ~**40&nbsp;Gb/s** sticker sum | **~10–15&nbsp;Gbit/s** on common 1-lane host-net; dual-lane higher | Same simplex rule. |
| **USB4 v2 / Thunderbolt 5** (symmetric class) | higher per-lane (e.g. ~40&nbsp;G/lane class) | ~**40&nbsp;Gb/s** order | ~**80&nbsp;Gb/s** sticker sum | Measure; still not 80 each way | Asymmetric modes exist. |
| **TB5 asymmetric** (e.g. 120/40 class) | 3+1 style lane allocation | Unequal (e.g. more A→B than B→A) | Sticker sum of dirs | Measure **both** directions | Direction matters for copies. |

\*TCP/SMB/NBD after encoding, CPU, MTU, storage — **not** raw lane rate. Lab 1-lane TB host-net often ~**13–14&nbsp;Gbit/s** iperf.

### How to read one row (TB4 example)

| Question | Answer |
|----------|--------|
| Port says **40**? | **Class** = up to ~20&nbsp;G each direction **simultaneously**. |
| Sysfs `20 Gb/s` · `1-lane`? | Trained path; plan **~10–15&nbsp;Gbit/s** one-way bulk. |
| Sysfs dual-lane high rate? | More capacity; still use **measured** TCP, not sticker÷8 as GB/s. |
| PCIe x4 “~40G” comparison? | Different duplex model — **do not** equate stickers 1:1. |
| Can I force 30&nbsp;G one way / 10&nbsp;G the other? | **No** (see [mixing](#mixing-generations-cables-and-lanes)). Whole-lane allocation only; not half-lanes. |

**Plugin UI:** Link quality **LOCAL** = controller class ceiling; **REMOTE** = **trained** path. Yellow *Single-lane* = below class max, often still normal for Linux host-net.

---

## Mixing generations, cables, and lanes

**Yes, you can mix** TB3 / TB4 / TB5 / USB4 tiers and cables — but only as a **fallback to a common trained path**. You do **not** get a custom hybrid (e.g. “half the lanes TB3, half TB5”).

### What “mix” means

| Mix | What happens |
|-----|----------------|
| **Different host generations** (TB3 ↔ TB4 ↔ TB5, USB4 20G ↔ 40G) | Works when both support a shared host-to-host path; trains at the **lowest common** capability |
| **Different cables** (TB3 cable on TB4 ports) | Often works; rate/lanes limited by the **weakest** piece (often the cable) |
| **Different lane counts** (2-lane-capable host ↔ 1-lane train) | Normal — you get **what trains** |
| **TB host-net + SuperSpeed-only USB-C** | **No** `thunderbolt_net` path — different stack |
| **Per-lane “this lane 20G, that lane 40G”** | **No** — one negotiated path, one train class |
| **Split one 20&nbsp;G lane into two 10&nbsp;G** | **No** — lanes are not subdivided for bonding |

### Host generation mixing

Newer ports generally talk to older peers when the stack allows:

```text
TB5 / USB4 v2  ──►  falls back  ──►  TB4 / USB4 40G  ──►  TB3 / USB4 20G class
```

| Pair | Expectation |
|------|-------------|
| TB4 ↔ TB4 | Best chance of full **40G-class** (20+20) if cable trains dual-lane |
| TB4 ↔ TB3 | Usually works; often **TB3-class** ceiling |
| TB5 ↔ TB4 | Works at **TB4-class** (or lower), not full TB5 rates |
| USB4 20G host ↔ TB4 peer | Limited by the **20G** side |
| USB-C **SS only** ↔ anything | USB data/charge only — **not** ThunderboltNet |

**Rule of thumb:**  
**min(host A class, host B class, cable class, what Linux ICM actually trains).**

### Cable mixing

| Cable | On higher-end ports |
|-------|---------------------|
| Certified **TB4 / USB4 40G**, short | Best shot at dual-lane / full class |
| **TB3** cable | Often trains; may cap at TB3 behavior |
| Generic USB-C / “USB4 20G” | Often **20G · 1-lane** or no host path |
| Long passive | May drop lanes/rate |
| Active / optical (correct class) | For length; class must match |

A **40G host + 20G cable** does **not** give 40G-class both ways.

### Lane allocation (2+2 vs 3+1) — not user “lane mixing”

Lanes are **whole** TX or RX at a **trained** per-lane rate. They are not mixed standards.

| Idea | On classic ~20&nbsp;G/lane 40G-class | User-selectable on Linux host-net? |
|------|--------------------------------------|-------------------------------------|
| **2 TX + 2 RX** (normal) | ~**20&nbsp;G each way** | Default when fully trained |
| **3 + 1** asymmetric | ~**60&nbsp;G one way / 20&nbsp;G other** | Spec/some TB5-style modes; **rarely** a Unraid/plugin knob |
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

Example: two “TB4 40G” ports + mediocre cable → **20&nbsp;Gb/s · 1-lane** is a valid **fallback** result, not a broken mix.

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
| **USB4 Version 2.0** | Higher PHY rates when controllers/cables allow | Often discussed alongside TB5; not a separate plugin mode |

We do **not** lock the product to one marketing number (not TB4-only, not “40G only”).  
If Linux sees a host domain and a `thunderboltN` netdev — TB3, TB4, TB5, USB4 20G/40G-class, or USB4 v2 80G-class — this plugin is for that link.

**Trained 20&nbsp;Gb/s · 1-lane** is usually a **path result**, not a separate product mode you opted into.

---

## Port silkscreen (case / rear I/O)

Match printed marks on the metal panel to decide if a Type‑C is Thunderbolt/USB4 or only SuperSpeed USB. See [port-icons.md](port-icons.md). Short form: **lightning / 40** → TB-class; **SS¹⁰ / SS²⁰ only** → USB SuperSpeed, not this plugin’s path.

---

## What is *not* the same thing

| Name | Relation to Thunderbolt Net |
|------|------------------------------|
| **USB 2.x / USB 3.x only** ports | Normal USB. **No** `thunderbolt_net` host-to-host tunnel. A USB Ethernet dongle is still ordinary `eth`/`enp…`. |
| **USB-C charging / “data” cables** without TB/USB4 high-rate cert | May charge or do slow USB only; often fail to train a full host path or train at 20&nbsp;G × 1 lane. |
| **“USB5”** | **Not** a mainstream USB-IF product name today. Prefer **USB4**, **USB4 Version 2.0**, **Thunderbolt 5**. |
| **Dock RJ45** | Almost always a **USB/PCIe NIC** on the dock — configure like Ethernet, not tbn. |
| **USB4STREAM** / **usb4_rdma** | Other host-to-host paths (raw stream / experimental RDMA). See [usb4stream.md](usb4stream.md). Not required for tbn IP. |

---

## Rate notation (Gb/s vs GB/s)

| Write | Means |
|-------|--------|
| **Gb/s** | Gigabit per second (signaling / link rate). **G** = giga (SI), **b** = **bit**. Sysfs and this plugin use this. |
| **GB/s** | Gigabyte per second (≈ 8× larger than Gb/s). **Do not** use for TB link rate. |
| **Mb/s** | Megabit per second (USB 2.0 class, etc.) |

UI and docs use **Gb/s** consistently (e.g. `20 Gb/s · 1-lane`, `Max ~40 Gb/s · 2-lane`). Prefer “class” or “per direction” over bare `40G`.

---

## Rate vs lanes

- **Gb/s** in sysfs is typically the **per-lane** trained signaling rate.  
- **Lanes** are **directional** (`rx_lanes` / `tx_lanes`). More lanes one way = more capacity that way.  
- On TB3/4-era paths, **1-lane trained** often pairs with ~**20&nbsp;Gb/s** per-lane display; **2-lane** is what people expect from a “40G” sticker when fully trained.  
- A high-gen host that trains **20&nbsp;Gb/s · 1-lane** is **common for Linux host-to-host**. Not a failed install.  
- Sticker **“40&nbsp;Gb/s”** = **class** (often 20+20 when fully trained) — **not** “40&nbsp;Gbit/s TCP each direction like full-duplex PCIe.”

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
| **Single-lane** (yellow) | Trained 1-lane under controller max — common for host-net; ~10–15&nbsp;Gbit/s TCP is normal |
| **Healthy** / near max | Dual-lane high rate for this class (nice when it happens) |
| **Dual-lane** / **Linked** | Up; may still be under ceiling |

Yellow means “below controller class max,” not “broken plugin” or “always fix with a new cable.”

---

## Cables

| Cable class | Typical result |
|-------------|----------------|
| Certified **Thunderbolt** / **USB4** high-rate (match your hosts), short passive | Best chance of dual-lane / full advertised class |
| Long passive | May drop to 1-lane / lower Gb/s |
| Generic USB-C without high-rate cert | Often 20&nbsp;G × 1 or no host path |
| Active / optical (correct class) | Needed for longer runs |

Marketing stickers lie; **sysfs trained rate** is the ground truth for your pair.

---

## Throughput expectations

- Trained line rate ≠ sustained TCP/SMB forever — protocol, CPU, storage, and **MTU** matter.  
- On **1-lane · 20&nbsp;Gb/s** host-net, lab TCP/iperf often lands around **~13–14&nbsp;Gbit/s**; real multi-GiB rsync similar.  
- **MTU 9000 both ends** cuts packets/s and retrans; it does **not** unlock dual-lane or double rate.  
- Design around **measured** numbers; do not plan on sticker 40&nbsp;G host-net as 40&nbsp;Gbit/s TCP each way.  
- Kernel default **MTU 1500** is Ethernet habit, not a Thunderbolt limit. Full tables: **[mtu-and-throughput.md](mtu-and-throughput.md)**.

---

## FAQ

| Question | Answer |
|----------|--------|
| Is 40&nbsp;Gb/s TB the same as PCIe 40G full duplex? | **No.** TB lanes are simplex; 40G-class ≈ **20 each way**. |
| Can I mix TB3/TB4/TB5/USB4? | **Yes** — falls back to a **common** trained path. |
| Can I mix cables? | **Yes** — weakest link wins. |
| Can I assign different standards per lane? | **No.** |
| Can I split 20G into dual 10G or force 30/10? | **No.** Whole lanes only; 3+1 at 20G/lane would be **60/20** if available, not user-selectable on typical Linux host-net. |
| Why is my “40G” port only 20&nbsp;Gb/s · 1-lane? | Common under Linux host-net; see trained path vs sticker. |
| What should I plan for bulk copy? | **~10–15&nbsp;Gbit/s** one way on a common 1-lane train; measure with iperf/rsync. |
| Where is the planning table? | [Expected bandwidth by standard / class](#expected-bandwidth-by-standard--class-planning-table) |

---

## Related

- Topology / dual cable: [links-and-topology.md](links-and-topology.md)  
- Requirements: [requirements.md](requirements.md)  
- MTU & bulk transfer: [mtu-and-throughput.md](mtu-and-throughput.md)  
- USB4STREAM / RDMA naming: [usb4stream.md](usb4stream.md)  
- Troubleshooting slow links: [troubleshooting.md](troubleshooting.md)  
