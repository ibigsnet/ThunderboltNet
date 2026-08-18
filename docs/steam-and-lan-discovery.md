# Steam and house-LAN discovery over Thunderbolt

How Steam Local Network Game Transfers relate to Thunderbolt Net — and how to get transfers onto the fast path.

---

## Contents

- [What Steam actually does](#what-steam-actually-does)
- [Why Wi‑Fi off can pick the wrong PC](#why-wifi-off-can-pick-the-wrong-pc)
- [Route metrics will not fix this](#route-metrics-will-not-fix-this)
- [Working patterns](#working-patterns)
- [Unraid: join house LAN (bridge)](#unraid-join-house-lan-bridge)
- [Linux desktop ↔ Linux desktop](#linux-desktop--linux-desktop)
- [Related](#related)

## What Steam actually does

Local Network Game Transfers:

1. **Discover** other Steam clients on the local network (same subnet / broadcast-style).  
2. **Connect** to the IP they advertised.  
3. Copy game files over that TCP path.

House Wi‑Fi/Ethernet is usually one subnet (e.g. `192.168.1.0/24`).  
A Thunderbolt host-net link with its own static `/24` (e.g. `10.255.2.0/24`) is a **different** LAN island.

Steam does **not** mean “use the fastest interface.” It means “use peers I can see on this discovery LAN.”

## Why Wi‑Fi off can pick the wrong PC

| Situation | What Steam sees |
|-----------|-----------------|
| Source and downloader both on house Wi‑Fi | Both (and any other Steam PCs) on `192.168.1.0/24` — picker ranks them |
| Source’s Wi‑Fi disabled | Source **leaves** that subnet → other Wi‑Fi PCs remain; picker may fall back to them |
| Thunderbolt has no IP yet | TB cannot carry the transfer even if a cable is up |

So: turning off Wi‑Fi on the **library** machine removes it from Steam’s LAN list unless that machine is still reachable on the same discovery network another way (e.g. bridged into `br0`, or a TB-only island both clients share).

## Route metrics will not fix this

Lowering Thunderbolt’s route metric does not redirect Steam.  
If Steam connects to `192.168.1.86`, the kernel sends packets to that address via whatever interface owns that route — typically Wi‑Fi — not via `10.255.2.1`.

NAT alone also does not put a peer on the house discovery subnet. You need **L2 join (bridge)** or a deliberate **TB-only discovery island**.

## Working patterns

| Pattern | When to use |
|---------|-------------|
| **Join house LAN (bridge)** | Unraid has `br0`; peer should look like any LAN host for Steam/SMB/discovery |
| **TB-only island** | Both ends have TB IPs; downloader Wi‑Fi off so Steam only sees the TB subnet |
| **Unraid in the middle** | Desktops peer to Unraid over TB; Unraid joins `br0` so peers get house-LAN addressing |

## Unraid: join house LAN (bridge)

On the Thunderbolt **tbnN** tab:

1. **Enable bridging = Yes**  
2. **Join bridge** = existing `br0` (or `br0.N`) from Network Settings  
3. Apply  

Effects:

- `thunderboltN` becomes a **bridge member** (no own IP on that tab).  
- Peer sets a **house-LAN** address on its Thunderbolt iface (DHCP from the LAN, or static in the LAN range).  
- Steam discovery stays on the house subnet; frames to that peer can run at Thunderbolt rate through the bridge.

Defaults stay **No** (do not silently enroll management LAN). Prefer **one** end joining `br0` unless you understand loops/STP. See [addressing.md — Join an Unraid bridge](addressing.md#join-an-unraid-bridge-br0-br010-).

## Linux desktop ↔ Linux desktop

Wi‑Fi **client** NICs generally cannot be bridged like Unraid `br0`. For two desktops on a direct Thunderbolt cable:

1. Put static IPs on both Thunderbolt ifaces (e.g. `10.255.2.1` / `.2`).  
2. Prove `ping` both ways.  
3. For Steam: leave the **library** machine online; turn **Wi‑Fi off on the downloader** (or ensure Steam on the library is reachable on the TB address and is the only LAN source you care about).

Optional: NetworkManager **Shared** connection on the library’s Thunderbolt iface (DHCP toward the peer). Same idea as a TB-only island if the downloader is not using house Wi‑Fi for Steam discovery.

Do not expect Steam to auto-prefer Thunderbolt while both machines also see other PCs on Wi‑Fi.

## Related

- [addressing.md](addressing.md) — static `/24`, DHCP server, join `br0`  
- [troubleshooting.md](troubleshooting.md#steam--lan-transfers-use-wifi-not-thunderbolt)  
- [peers-and-plans.md](peers-and-plans.md) — Current / Saved  
- [peer-scenarios.md](peer-scenarios.md)  
