# Settings reference

## Global — Thunderbolt tab → Driver options

| Field | Default | Scope | Summary |
|-------|---------|-------|---------|
| Load modules on Apply | Yes | Host | `modprobe thunderbolt` + `thunderbolt_net` |
| E2E flow control | No (e2e=0) | **Host / whole module** | See [driver-options.md](driver-options.md) |

Not per-tbn: changing E2E affects every Thunderbolt network interface on Unraid.

---

## Per-link — Thunderbolt tbnN tab

| Field | Default | Summary |
|-------|---------|---------|
| Interface description | empty | Cosmetic label in plugin config |
| MAC address | (live) | Read-only |
| Enable interface | Yes | `ip link set up/down` on Apply |
| Enable bonding | No | TB-only bond of live thunderbolt* |
| Bonding mode | balance-rr | Linux bond mode if bonding Yes |
| Bond name | bond-tb0 | TB-only bond (`bond-tb0`, `bond-tb1`, … — not Unraid `bond0`) |
| Enable bridging | No | Reserved; not auto-applied yet |
| Network protocol | IPv4 only | IPv6 limited |
| IPv4 assignment | Static | Prefer static on host↔host |
| IPv4 address / mask | 10.255.N.2 / 24 | Unique subnet per N |
| IPv4 default gateway | empty | Optional next hop on this link |
| Enable default route | No | Don’t steal system default from eth0 |
| Desired MTU | 1500 kernel default (safe) | Modes: **1500** · **9000 recommended for TB bulk (both ends)** · Custom. Driver often allows up to ~65k; peer must match. See [mtu-and-throughput.md](mtu-and-throughput.md) |
| Unraid services on this link (listening) | No (recommend Yes for SMB/transfers) | Per peer; remembered; overview table + Harden all |
| IPv4 (live) | (live) | Read-only from kernel |
| Bond / bridge membership | (live) | Read-only |

Full addressing discussion: [addressing.md](addressing.md).  
Peer examples: [peer-scenarios.md](peer-scenarios.md).

---

## Help in the UI

Click **Help** or a field **label** for blue `inline_help` panels (same mechanism as eth0 / Storage Guard).  
Long-form text stays in these docs; helpers link here when relevant.
