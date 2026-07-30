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
3. Some dual-plug attempts leave the domain wedged — unplug **both** ends, single-cable recovery, then retry carefully with known-good 40G cables.

Plugin **Enable bonding** builds a **TB-only** bond (`bond-tb` by default), not Unraid’s main `bond0` on eth ports. Use only when two live `thunderbolt*` members exist.

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
