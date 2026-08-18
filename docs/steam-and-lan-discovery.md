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

Wi‑Fi **client** NICs generally cannot be bridged like Unraid `br0`. For two desktops (or a desktop and a handheld) on a direct Thunderbolt cable:

1. Put IPv4 on both Thunderbolt ifaces (e.g. library `10.255.2.1/24`, peer `10.255.2.2/24`). Prefer **manual** static on both ends (or DHCP without pushing a default route). Keep **MTU 1500** unless you have verified jumbo end-to-end.  
2. Prove `ping` both ways on the TB `/24` (expect sub‑millisecond RTT).  
3. This is **not** a routing-advertisement / OSPF problem. Steam does not pick “cheapest outbound interface” for CDN downloads. Local Network Game Transfers **discover** a peer on the house LAN, then open TCP to that IP. Path selection is the **downloader’s** route to that IP (FLOWZ-side).

### Host route (path) + source address (Steam identity)

Steam’s library host remembers the downloader as its Wi‑Fi IP (e.g. `192.168.1.133`). If the downloader connects **from** its Thunderbolt address (`10.255.2.2`), the library logs `Unexpected peer connection` and serves **0 chunks**. Steer the path via Thunderbolt but keep the **Wi‑Fi source address**:

```bash
# On the downloader (example): library advertises as 192.168.1.86 on Wi‑Fi
nmcli con modify "$TB_CONNECTION" ipv4.routes "192.168.1.86/32 10.255.2.1 25 src=192.168.1.133"
nmcli con modify "$TB_CONNECTION" ipv4.never-default yes
nmcli con modify "$TB_CONNECTION" 802-3-ethernet.mtu 1500
nmcli con up "$TB_CONNECTION"
ip route get 192.168.1.86   # via 10.255.2.1 dev thunderboltN src 192.168.1.133
```

Use loose `rp_filter` on the TB iface if replies to the Wi‑Fi IP arrive on Thunderbolt (`rp_filter=2`). Mirror a `/32` on the library toward the downloader’s Wi‑Fi IP if either side can download.

**Wi‑Fi off on the downloader** remains the simple TB-only island if you do not want host routes.

Steam may still **prefer Valve CDN** when it is fast, or fail peer serve when the library is missing the same depot/build. Check `logs/content_log.txt` for `Unexpected peer connection`, `0 chunks`, and `SteamCache` vs `Peer`. The host route only steers traffic once Steam uses that peer — it does not hide CDN or other Wi‑Fi libraries.

## Related

- [addressing.md](addressing.md) — static `/24`, DHCP server, join `br0`  
- [troubleshooting.md](troubleshooting.md#steam--lan-transfers-use-wifi-not-thunderbolt)  
- [peers-and-plans.md](peers-and-plans.md) — Current / Saved  
- [peer-scenarios.md](peer-scenarios.md)  
