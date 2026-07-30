# Standards, speeds, and cables

Thunderbolt Net uses the Linux **`thunderbolt`** + **`thunderbolt_net`** stack: host-to-host networking over a Thunderbolt **fabric**, not plain USB gadget/Ethernet dongles.

## What this plugin is aiming at

| Generation / class | Host networking (when both ends + cable support it) | Notes for this plugin |
|--------------------|------------------------------------------------------|------------------------|
| **Thunderbolt 3** | Yes (classic ThunderboltIP / XDomain path) | Still relevant on many boards and peers |
| **Thunderbolt 4** | Yes | Common desktop/laptop class; stricter cert than many “USB4 20G” cables |
| **Thunderbolt 5** | Yes (higher rates when path trains) | Built on USB4 v2-class signaling; double TB networking bandwidth in Intel’s TB5 materials when everything matches |
| **USB4** (incl. 20G / 40G product tiers) | **Optional** host-to-host in the USB4 spec; works when the platform implements USB4 host router + networking | Same Linux driver family as Thunderbolt on modern kernels |
| **USB4 Version 2.0** | Same idea at higher PHY rates (e.g. 80&nbsp;Gb/s class) when controllers/cables allow | Often discussed alongside TB5; not a separate plugin mode |

We do **not** lock the product to one marketing number (TB4-only or USB4-only). If Linux sees a host domain and a `thunderboltN` netdev, this plugin is for that link.

## Port silkscreen (case / rear I/O)

Match printed marks on the metal panel to decide if a Type‑C is Thunderbolt/USB4 or only SuperSpeed USB. Full table + icons live in the plugin (**Identify ports on your case**) and [port-icons.md](port-icons.md). Short form: **lightning / 40** → TB-class; **SS¹⁰ / SS²⁰ only** → USB SuperSpeed, not this plugin’s path.

## What is *not* the same thing

| Name | Relation to Thunderbolt Net |
|------|------------------------------|
| **USB 2.x / USB 3.x only** ports | Normal USB. **No** `thunderbolt_net` host-to-host tunnel. A USB Ethernet dongle is still ordinary `eth`/`enp…`. |
| **USB-C charging / “data” cables** without TB/USB4 high-rate cert | May charge or do slow USB only; often fail to train a full host path or train at 20&nbsp;G × 1 lane. |
| **“USB5”** | **Not** a mainstream USB-IF product name today. Industry moved through **USB4** and **USB4 Version 2.0**, with **Thunderbolt 5** as Intel’s higher tier. Prefer those names over “USB5.” |
| **Dock RJ45** | Almost always a **USB/PCIe NIC** on the dock — configure like Ethernet, not tbn. |

## Generations (simplified)

| Marketing / class | Typical aggregate | Lanes (common) | Notes |
|-------------------|-------------------|----------------|-------|
| Thunderbolt 3 | up to ~40&nbsp;Gb/s | 2× ~20&nbsp;G | USB-C connector era for many desktops |
| Thunderbolt 4 | 40&nbsp;Gb/s class | 2-lane | Stricter cable/cert than many “USB4 20G” leads |
| USB4 | 20&nbsp;G or 40&nbsp;G class | 1 or 2 | Label and certification matter |
| USB4 v2 / Thunderbolt 5 | higher (e.g. 80&nbsp;G class, TB5 asymmetric modes) | implementation-specific | Still depends on **both** ends + cable |

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

UI and docs use **Gb/s** consistently (e.g. `20 Gb/s · 1-lane`, `Max ~40 Gb/s · 2-lane`). Avoid bare `20G` / `40G` in user-facing strings.

## Rate vs lanes

- **Gb/s** in sysfs is the trained signaling rate on the path.  
- **Lanes** (1 vs 2) often decide whether you see ~20&nbsp;Gb/s or ~40&nbsp;Gb/s class behavior on TB3/4-era paths.  
- A **high-gen host** (TB4/USB4/TB5 class) that only trains **20&nbsp;Gb/s · 1-lane** is usually limited by **cable or port path**, not by “the other host capping you.”

### Link quality messages (plugin)

| Column | What you see |
|--------|----------------|
| **LOCAL** | Host **Max** (e.g. ~40&nbsp;Gb/s · 2-lane), then a **Thunderbolt links** box: each live port with **host max** vs **trained** rate + peer name. Other USB SuperSpeed banks (10/20&nbsp;Gb/s Type-C) sit under a collapsed “Other USB SuperSpeed” list — not full TB networking. |
| **REMOTE** | Status badge (**Below max** / **Healthy** / …) + trained rate; short lead; details under **Why & what to try**. |

| Remote status | Meaning |
|---------------|---------|
| **Below max** (yellow) | Trained ≪ host Max — often cable/path |
| **Healthy** / near max | Dual-lane high rate for this class |
| **Moderate** / **Linked** | Up, but not at ceiling |

Yellow means “this path is weaker than what **this** host can do,” not “20&nbsp;Gb/s is always wrong.”

Linux does **not** expose a reliable cable-SKU EEPROM — inference is capability vs training.

## Cables

| Cable class | Typical result |
|-------------|----------------|
| Certified **Thunderbolt** / **USB4** high-rate (match your hosts), short passive | Best chance of dual-lane / full advertised class |
| Long passive | May drop to 1-lane / lower Gb/s |
| Generic USB-C without high-rate cert | Often 20&nbsp;G × 1 or no host path |
| Active / optical (correct class) | Needed for longer runs |

Marketing stickers lie; **sysfs trained rate** is the ground truth for your pair.

## Throughput expectations

- Trained line rate is not the same as sustained TCP/SMB payload forever — protocol, CPU, storage, and **MTU** matter.  
- Host-to-host TB net is excellent for low-latency lab links and bulk copy; design around measured numbers on **your** cable.  
- Kernel default **MTU 1500** is Ethernet habit, not a Thunderbolt limit (`thunderbolt_net` maxmtu is typically tens of KB). At 20–80&nbsp;Gb/s class links, 1500&nbsp;B frames mean **millions of packets/s** for full-pipe bulk — raise to **9000 on both ends** for rsync/SMB when you can.  
- Full tables (PPS vs MTU, efficiency, peer commands): **[mtu-and-throughput.md](mtu-and-throughput.md)**.

## Related

- Topology: [links-and-topology.md](links-and-topology.md)  
- Requirements: [requirements.md](requirements.md)  
- MTU & bulk transfer overhead: [mtu-and-throughput.md](mtu-and-throughput.md)  
- Troubleshooting slow links: [troubleshooting.md](troubleshooting.md)  
