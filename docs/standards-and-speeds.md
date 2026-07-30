# Standards, speeds, and cables

## Generations (simplified)

| Marketing / class | Typical aggregate | Lanes (common) | Notes |
|-------------------|-------------------|----------------|-------|
| Thunderbolt 3 | up to ~40&nbsp;Gb/s | 2× ~20&nbsp;G | USB-C connector |
| Thunderbolt 4 | 40&nbsp;Gb/s class | 2-lane | Stricter cable/cert requirements than many “USB4 20G” leads |
| USB4 | 20&nbsp;G or 40&nbsp;G class | 1 or 2 | Label and certification matter |
| USB4 v2 / TB5 era | higher | implementation-specific | Still depends on cable and both ends |

Linux sysfs often exposes:

- `generation` on the local controller (`0-0`)  
- `rx_speed` / `tx_speed` and `rx_lanes` / `tx_lanes` on the **peer path**  
- optional `USB4_VERSION` in uevent  

Thunderbolt Net’s **Link quality** badge compares **trained path** vs **controller capability**.

---

## Rate vs lanes

- **Gb/s** in sysfs is the trained signaling rate on the path.  
- **Lanes** (1 vs 2) often decide whether you see ~20&nbsp;G or ~40&nbsp;G class behavior.  
- A **Gen 3+ / USB4** host that only trains **20&nbsp;Gb/s · 1-lane** is usually limited by **cable or port path**, not by “the other host capping you.”

### Link quality messages (plugin)

| Badge | Meaning |
|-------|---------|
| **High rate** | Dual-lane high rate trained — path looks healthy |
| **20G · 1-lane** | Capable host, low single-lane train — **likely cable/path** |
| Other | Linked at reported rate; see note if present |

For **20G · 1-lane** the UI suggests: certified **40&nbsp;Gbps TB4/USB4** cable, re-seat, try other **rear** full-bandwidth ports, avoid unknown front-panel USB-C.

Linux does **not** expose a reliable “this cable SKU is 20G” EEPROM for this UI — the badge is **inference** from capability vs training.

---

## Cables

| Cable class | Typical result on TB4/USB4 host |
|-------------|----------------------------------|
| Certified TB4 / USB4 **40&nbsp;Gbps**, short passive | Best chance of 2-lane high rate |
| Long passive | May drop to 1-lane / 20&nbsp;G |
| “USB4” or USB-C charging cable without 40G cert | Often 20&nbsp;G × 1 |
| Active / optical (correct class) | Needed for longer runs |

Marketing stickers lie; **sysfs trained rate** is the ground truth for your pair.

---

## Throughput expectations

- Trained **40&nbsp;G** is not the same as 40&nbsp;Gbit/s of TCP payload forever — protocol, CPU, storage, and MTU matter.  
- Host-to-host TB net is excellent for low-latency lab links and bulk copy; design around measured `iperf3`/`smb` numbers on **your** cable.  
- Jumbo MTU only helps if **both** ends and the path allow it (tbn tab).

---

## Related

- Topology: [links-and-topology.md](links-and-topology.md)  
- Troubleshooting slow links: [troubleshooting.md](troubleshooting.md)  
