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

## Rate vs lanes

- **Gb/s** in sysfs is the trained signaling rate on the path.  
- **Lanes** (1 vs 2) often decide whether you see ~20&nbsp;G or ~40&nbsp;G class behavior on TB3/4-era paths.  
- A **high-gen host** (TB4/USB4/TB5 class) that only trains **20&nbsp;Gb/s · 1-lane** is usually limited by **cable or port path**, not by “the other host capping you.”

### Link quality messages (plugin)

| Badge | Meaning |
|-------|---------|
| **High rate** | Dual-lane high rate trained — path looks healthy for that generation |
| **20G · 1-lane** | Capable host, low single-lane train — **likely cable/path** |
| Other | Linked at reported rate; see note if present |

For **20G · 1-lane** the UI suggests: certified high-rate **Thunderbolt / USB4** cable (often 40&nbsp;Gbps class for TB4-era, higher-rated for TB5 when both ends support it), re-seat, try other **rear** full-bandwidth ports, avoid unknown front-panel USB-C.

Linux does **not** expose a reliable “this cable SKU is 20G” EEPROM for this UI — the badge is **inference** from capability vs training.

## Cables

| Cable class | Typical result |
|-------------|----------------|
| Certified **Thunderbolt** / **USB4** high-rate (match your hosts), short passive | Best chance of dual-lane / full advertised class |
| Long passive | May drop to 1-lane / lower Gb/s |
| Generic USB-C without high-rate cert | Often 20&nbsp;G × 1 or no host path |
| Active / optical (correct class) | Needed for longer runs |

Marketing stickers lie; **sysfs trained rate** is the ground truth for your pair.

## Throughput expectations

- Trained line rate is not the same as sustained TCP/SMB payload forever — protocol, CPU, storage, and MTU matter.  
- Host-to-host TB net is excellent for low-latency lab links and bulk copy; design around measured numbers on **your** cable.  
- Jumbo MTU only helps if **both** ends and the path allow it (tbn tab).

## Related

- Topology: [links-and-topology.md](links-and-topology.md)  
- Requirements: [requirements.md](requirements.md)  
- Troubleshooting slow links: [troubleshooting.md](troubleshooting.md)  
