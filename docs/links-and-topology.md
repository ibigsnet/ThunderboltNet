# Links and topology

## Mental model

Linux **Thunderbolt networking** (`thunderbolt_net`) creates an Ethernet-like netdev per **host-to-host network service path**, not per physical USB-C receptacle in all cases.

| You plug… | Typical result |
|-----------|----------------|
| One cable, two hosts | One peer, one `thunderbolt0` (each side) |
| Two cables, **two different** peers | Often `thunderbolt0` + `thunderbolt1` |
| Two cables, **same** two hosts | Frequently still **one** xdomain peer / one netdev — second cable may not add a second host path |
| Host → dock only | Devices in fabric; **no** host netdev until another host peers |

There is no general “TB Ethernet switch fabric” that puts all TB machines on one L2 domain the way a copper switch does.

---

## Dual cable between the same pair of PCs

Goals people want: 2× bandwidth or redundancy via bonding.

Reality:

1. The fabric must enumerate **two** network-capable peer paths.  
2. If sysfs only shows one peer hop, bonding cannot invent a second slave.  
3. Two cables to the **same** peer often do **not** create `thunderbolt0` + `thunderbolt1` — you may still see a single path, or a confused domain with no clean networking.  
4. Dual-plug experiments are a common way to **wedge** bring-up until all cables are cleared.

Plugin **Enable bonding** builds a **TB-only** bond (`bond-tb` by default), not Unraid’s main `bond0` on eth ports. Use only when two live `thunderbolt*` members already exist.

## Multi-cable and recovery

### Why “unplug everything, then one cable” works

Thunderbolt domains remember paths and services. Extra cables (second link to the same host, a dock still attached, a half-seated rear port) can keep the domain from settling on a single clean host-to-host network service.

**Recovery pattern (high value):**

1. Unplug **all** TB/USB4 host cables from **both** ends of every machine under test.  
2. Pause a few seconds.  
3. Seat **one** known-good cable only.  
4. Confirm one peer and one netdev; configure IP; prove ping.  
5. Add other peers/cables only after that baseline works.

### Reseating one cable (when you already have a single link)

Unplug that cable fully (both ends if possible), wait, reconnect. That re-trains the path and is often more effective than re-clicking Apply alone after driver changes.

See [troubleshooting.md — Reseating the cable](troubleshooting.md#reseating-the-cable-why-it-matters).

---

## Hubs and docks

See [peer-scenarios.md](peer-scenarios.md). Summary:

- **RJ45 on dock** → USB/PCIe Ethernet (eth*), not tbn  
- **Hub without a second host** → no ThunderboltIP LAN by itself  
- **Daisy-chain** → still not a free-for-all multi-access LAN for thunderbolt_net

---

## LOCAL | REMOTE | REMOTE table

On the Thunderbolt tab:

- **LOCAL** — this Unraid (hostname, controller product, OEM)  
- Each **REMOTE** column — one peer; header is **tbnN** + `thunderboltN`  
- Per-column MAC/IP/state are **this host’s** end of that link  

---

## Related

- Speeds: [standards-and-speeds.md](standards-and-speeds.md)  
- Addressing: [addressing.md](addressing.md)  
