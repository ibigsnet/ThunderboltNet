# Standards, speeds, and cables

Thunderbolt Net uses the Linux **`thunderbolt`** + **`thunderbolt_net`** stack: host-to-host networking over a Thunderbolt **fabric**, not plain USB gadget/Ethernet dongles.

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
  Host A ──► Host B   up to ~20 Gb/s   (one or more TX lanes)
  Host A ◄── Host B   up to ~20 Gb/s   (one or more RX lanes)

  Marketing “40 Gb/s” ≈ 20 + 20  (both directions at once)
  Not ≈ 40 A→B and 40 B→A at the same time
```

So:

| Phrase | What it should mean for TB host-net |
|--------|-------------------------------------|
| **40&nbsp;Gb/s standard / class** | About **20&nbsp;Gb/s A→B** and **20&nbsp;Gb/s B→A** **simultaneously** (“full duplex 20&nbsp;G”) |
| **Trained 20&nbsp;Gb/s · 1-lane** | Sysfs often shows **per-lane** rate; **1-lane** trained is common for Linux host↔host — roughly one ~20&nbsp;G hop class, not dual-lane |
| **iperf / TCP ~13–15&nbsp;Gbit/s** | Normal payload after protocol/CPU/storage — **not** a failed 40G port |

That matches lab experience: a dual-capable “40G” host that trains **20&nbsp;Gb/s · 1-lane** still often delivers **~14&nbsp;Gbit/s** TCP one way — fine for bulk copies, not “half a PCIe x4.”

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
| Soft-RoCE / RDMA experiments on top of TB-net | Often **lower bandwidth** than plain TCP; different use case |

Design storage jobs around **measured** iperf/rsync, not “40G cable ⇒ 5&nbsp;GB/s.”

## What this plugin is aiming at

| Generation / class | Host networking (when both ends + cable support it) | Notes for this plugin |
|--------------------|------------------------------------------------------|------------------------|
| **Thunderbolt 3** | Yes (classic ThunderboltIP / XDomain path) | Still relevant on many boards and peers |
| **Thunderbolt 4** | Yes | Common desktop/laptop class; stricter cert than many “USB4 20G” cables |
| **Thunderbolt 5** | Yes (higher rates when path trains) | Built on USB4 v2-class signaling; double TB networking bandwidth in Intel’s TB5 materials when everything matches |
| **USB4** (incl. 20G / 40G product tiers) | **Optional** host-to-host in the USB4 spec; works when the platform implements USB4 host router + networking | Same Linux driver family as Thunderbolt on modern kernels |
| **USB4 Version 2.0** | Same idea at higher PHY rates (e.g. 80&nbsp;Gb/s class) when controllers/cables allow | Often discussed alongside TB5; not a separate plugin mode |

We do **not** lock the product to one marketing number (not TB4-only, not “40G only”).  
If Linux sees a host domain and a `thunderboltN` netdev — whether that host is TB3, TB4, TB5, USB4 20G/40G-class, or USB4 v2 80G-class — this plugin is for that link.

**Trained 20 Gb/s · 1-lane** is usually a **path result** (how the link trained), not a separate “20G Thunderbolt product mode” you opted into. Dual-capable hosts still often land there on host-to-host under Linux.

## Port silkscreen (case / rear I/O)

Match printed marks on the metal panel to decide if a Type‑C is Thunderbolt/USB4 or only SuperSpeed USB. See [port-icons.md](port-icons.md). Short form: **lightning / 40** → TB-class; **SS¹⁰ / SS²⁰ only** → USB SuperSpeed, not this plugin’s path.

## What is *not* the same thing

| Name | Relation to Thunderbolt Net |
|------|------------------------------|
| **USB 2.x / USB 3.x only** ports | Normal USB. **No** `thunderbolt_net` host-to-host tunnel. A USB Ethernet dongle is still ordinary `eth`/`enp…`. |
| **USB-C charging / “data” cables** without TB/USB4 high-rate cert | May charge or do slow USB only; often fail to train a full host path or train at 20&nbsp;G × 1 lane. |
| **“USB5”** | **Not** a mainstream USB-IF product name today. Industry moved through **USB4** and **USB4 Version 2.0**, with **Thunderbolt 5** as Intel’s higher tier. Prefer those names over “USB5.” |
| **Dock RJ45** | Almost always a **USB/PCIe NIC** on the dock — configure like Ethernet, not tbn. |

## Generations (simplified)

Marketing numbers below are **class labels**. For simultaneous both-ways capacity, see **Directionality** above (40G-class ≈ **20 each way**, not 40 each way).

| Marketing / class | Class label | Simultaneous both ways (order of) | Notes |
|-------------------|-------------|-------------------------------------|--------|
| Thunderbolt 3 | ~40&nbsp;Gb/s class | ~**20&nbsp;G** A→B + ~**20&nbsp;G** B→A | USB-C era; lanes simplex |
| Thunderbolt 4 | 40&nbsp;Gb/s class | same order when fully trained | Stricter cert than many “USB4 20G” leads |
| USB4 | 20&nbsp;G or 40&nbsp;G class | 20G-class ≈ less; 40G-class ≈ 20+20 | Label and certification matter |
| USB4 v2 / Thunderbolt 5 | 80&nbsp;G class / asymmetric | e.g. ~40+40 or 120/40-style modes | Still both ends + cable |

Linux sysfs often exposes:

- `generation` on the local controller (`0-0`)  
- `rx_speed` / `tx_speed` and `rx_lanes` / `tx_lanes` on the **peer path**  
- optional `USB4_VERSION` in uevent  

Thunderbolt Net’s **Link quality** badge compares **trained path** vs **controller capability**.

## Rate notation (Gb/s vs GB/s)

| Write | Means |
|-------|--------|
| **Gb/s** | Gigabit per second (signaling / link rate). **G** = giga (SI), **b** = **bit**. This is what sysfs and this plugin use. |
| **GB/s** | Gigabyte per second (≈ 8× larger than Gb/s). **Do not** use for TB link rate. |
| **Mb/s** | Megabit per second (USB 2.0 class, etc.) |

UI and docs use **Gb/s** consistently (e.g. `20 Gb/s · 1-lane`, `Max ~40 Gb/s · 2-lane`). Avoid bare `20G` / `40G` in user-facing strings without “class” or “per direction.”

## Rate vs lanes

- **Gb/s** in sysfs is typically the **per-lane** trained signaling rate.  
- **Lanes** are **directional** (RX lanes vs TX lanes in sysfs). More lanes one way = more capacity that way — not “PCIe dual-simplex free.”  
- On TB3/4-era paths, **1-lane trained** often pairs with ~**20&nbsp;Gb/s** per-lane display; **2-lane** is the dual-lane class people expect from a “40G” sticker.  
- A **high-gen host** that trains **20&nbsp;Gb/s · 1-lane** is **common for Linux host-to-host** (firmware ICM). Not a failed install. Cable can matter for some pairs, but a short certified TB4 cable often stays 1-lane too.  
- Sticker **“40&nbsp;Gb/s”** = **class** (often dual-lane / 20+20 both ways when fully trained) — **not** “40&nbsp;Gbit/s TCP each direction like full-duplex PCIe.”

### Link quality messages (plugin)

| Column | What you see |
|--------|----------------|
| **LOCAL** | Host **Max** (e.g. ~40&nbsp;Gb/s · 2-lane) = controller class ceiling, not a guarantee. Live TB links show trained rate vs max. |
| **REMOTE** | Status badge + **trained** rate; lead sentence; details under **Why & what to try**. |

| Remote status | Meaning |
|---------------|---------|
| **Single-lane** (yellow) | Trained 1-lane under controller max — common for host-net; ~10–15&nbsp;Gbit/s TCP is normal |
| **Healthy** / near max | Dual-lane high rate for this class (nice when it happens) |
| **Dual-lane** / **Linked** | Up; may still be under ceiling |

Yellow means “below controller class max,” not “broken plugin” or “always fix with a new cable.”

## Cables

| Cable class | Typical result |
|-------------|----------------|
| Certified **Thunderbolt** / **USB4** high-rate (match your hosts), short passive | Best chance of dual-lane / full advertised class |
| Long passive | May drop to 1-lane / lower Gb/s |
| Generic USB-C without high-rate cert | Often 20&nbsp;G × 1 or no host path |
| Active / optical (correct class) | Needed for longer runs |

Marketing stickers lie; **sysfs trained rate** is the ground truth for your pair.

## Throughput expectations

- Trained line rate ≠ sustained TCP/SMB forever — protocol, CPU, storage, and **MTU** matter.  
- On **1-lane · 20&nbsp;Gb/s** host-net, lab TCP/iperf often lands around **~13–14&nbsp;Gbit/s**; real rsync of multi-GiB files similar (~12.5–13&nbsp;Gbit/s) even from Gen5 NVMe.  
- **MTU 9000 both ends** cuts packets/s and retrans; it does **not** unlock dual-lane or double rate.  
- Design around **measured** numbers; do not plan on sticker 40&nbsp;G host-net.
- Kernel default **MTU 1500** is Ethernet habit, not a Thunderbolt limit (`thunderbolt_net` maxmtu is typically tens of KB). At 20–80&nbsp;Gb/s class links, 1500&nbsp;B frames mean **millions of packets/s** for full-pipe bulk — raise to **9000 on both ends** for rsync/SMB when you can.  
- Full tables (PPS vs MTU, efficiency, peer commands): **[mtu-and-throughput.md](mtu-and-throughput.md)**.

## Related

- Topology: [links-and-topology.md](links-and-topology.md)  
- Requirements: [requirements.md](requirements.md)  
- MTU & bulk transfer overhead: [mtu-and-throughput.md](mtu-and-throughput.md)  
- Troubleshooting slow links: [troubleshooting.md](troubleshooting.md)  
