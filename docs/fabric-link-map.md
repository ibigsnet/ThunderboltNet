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

Known peers keep the **last** validation color when offline. Soft blue Online + Unverified can still mean “can’t talk” — confirm with ping.

---

## Setup (two Unraid hosts)

1. Install Thunderbolt Net on both.  
2. Put a private IP on the path you care about (Thunderbolt tbn and/or lab Ethernet).  
3. **Network Settings → Thunderbolt → Settings → Show Fabric reports** on both: **Enable = Yes**, same **shared token**.  
4. Optional: **Also report Ethernet ifaces** (e.g. `eth0`) and **Mesh peer IPs** for eth-only or multi-hop peers.  
5. Apply. Peer web UI must be reachable on that private IP.  
6. Open **Hardware** (fabric reports panel) / **Peers** for validation badges.

Default poll interval is **60 seconds**. Color changes use a **hold-off** (~120s) so training flaps do not blink red/green.

---

## Security

- Export **default off**.  
- Token required (shared only among your hosts).  
- **Private IPs only** by default (no WAN).  
- Payload: speeds, lanes, hostname, host id — not array data or passwords.  
- Stays on your LAN between Unraid peers; not a cloud feature.  
- Disable on threat-sensitive networks if topology fingerprinting is a concern.

---

## Related

- [settings-reference.md](settings-reference.md)  
- [peer-scenarios.md](peer-scenarios.md)  
