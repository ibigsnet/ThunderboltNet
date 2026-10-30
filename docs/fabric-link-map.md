# Fabric link map (multi-host reports)

Compare link health across **your** Unraid hosts that run **Thunderbolt Net**, using optional peer-to-peer snapshots on a **private LAN / fabric path**.

- **Not** LLDP/CDP and **not** a public Internet protocol.  
- **Not** telemetry: nothing is sent to GitHub, CA, or plugin developers — only host-to-host HTTP JSON with a shared token.  
- **Paths:** Thunderbolt (`tbnN`) when present; optional private Ethernet interfaces you list. A live Thunderbolt cable is not required if you only use eth fabric IPs both peers can reach.  
- **Requires:** the Thunderbolt Net plugin on each participating Unraid (settings under Thunderbolt → Settings).

---

## Colors

| Color | Meaning |
|-------|---------|
| **Green** | Validated — this host and a peer Unraid plugin report **agree** (including valid asymmetric TX/RX, e.g. 20G/60G vs 60G/20G). |
| **Orange** | **Unverified** — local only, peer silent, stale, or underlay poll failed. Often **not** a bad cable — but if Peers also shows **No reply** under Current, check ping/E2E. |
| **Red** | **Mismatch** — both plugins report different speeds; troubleshoot cable/hosts. |
| **Info note** (under Match) | Speeds **agree** but path is below a port’s max capability (slowest partner) — normal, not red. |
| Soft **blue** peer row | Path **Online** (netdev present) — not a link-check result by itself. |

Known peers keep the **last** validation color when offline (until reboot). Soft blue Online + Unverified can still mean “can’t talk” — confirm with ping.

---

## Setup (two Unraid hosts)

1. Install Thunderbolt Net on both.  
2. Put a private IP on the path you care about (Thunderbolt tbn and/or a direct Ethernet link).  
3. **Network Settings → Thunderbolt → Settings → Show Fabric reports** on both: **Enable = Yes**, same **shared token**.  
4. Optional: **Also report Ethernet ifaces** (e.g. `eth0`) and **Mesh peer IPs** for eth-only or multi-hop peers.  
5. Apply. Each host answers on a small beacon, **TCP 10807**, bound only to its Thunderbolt IPs (and any listed Ethernet ifaces) — not the web UI. The peer must reach that port.  
6. Open **Hardware** (fabric reports panel) / **Peers** for validation badges.

Polling runs from a plugin cron (`/boot/config/plugins/ThunderboltNet/thunderboltnet.cron`, loaded with Unraid’s `update_cron`) every minute while the check is on; the **Poll interval** (default **60 s**, minimum 30) is honoured inside. Without a Thunderbolt link or listed peer/Ethernet path the job does nothing. **Refresh fabric reports now** polls at once. Color changes use a **hold-off** (~120s) so training flaps do not blink red/green.

Results (peer snapshots, last poll, link-check colors) are kept in **RAM** (`/var/local/thunderboltnet`), not on the flash drive, so they start empty after a reboot.

---

## Security

- Check **on by default**; a random token is created on the first Settings **Apply**. Hosts only match once both have the **same** token.  
- Token required (`X-Tbn-Mesh-Token`; shared only among your hosts). The token is sent in clear HTTP to each polled peer, so anyone on the other end of a cable you poll can read it.  
- **Private IPs only** by default: RFC1918 and 169.254 sources — that **includes your LAN**, not just Thunderbolt.  
- Beacon: rate-limited (30 requests/min per source), access lines not logged.  
- Poller: no HTTP redirects, 256 KiB snapshot cap, at most 16 targets; neighbours are polled only when they sit inside the Thunderbolt link’s own subnet.  
- Payload: speeds, lanes, hostname, host id — not array data or passwords.  
- Stays on your LAN between Unraid peers; not a cloud feature.  
- Disable on threat-sensitive networks if topology fingerprinting is a concern.

---

## Related

- [settings-reference.md](settings-reference.md)  
- [peer-scenarios.md](peer-scenarios.md)  
