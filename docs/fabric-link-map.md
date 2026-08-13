# Fabric link map (multi-host reports)

See trained Thunderbolt (and optional Ethernet) link health from **one** Unraid host by sharing snapshots with peer Unraid hosts that also run **Thunderbolt Net**.

This is **not** LLDP/CDP and **not** a new Internet protocol. It is optional **HTTP JSON** on the private underlay, authenticated with a shared token.

---

## Colors

| Color | Meaning |
|-------|---------|
| **Green** | Validated — this host and a peer Unraid plugin report **agree** (including valid asymmetric TX/RX, e.g. 20G/60G vs 60G/20G). |
| **Orange** | **Unverified** — local only, peer silent, or stale. **Not** a degraded cable. |
| **Red** | **Disagree** — both plugins report different speeds; troubleshoot cable/hosts. |
| **Info note** | Speeds **agree** but path is below a port’s max capability (slowest partner) — normal, not red. |

Known peers keep the **last** validation color when offline.

---

## Setup (two Unraid hosts)

1. Install Thunderbolt Net on both; put a private IP on each Thunderbolt (or fabric) path.  
2. **Advanced → Fabric reports** on both: **Enable = Yes**, set the **same shared token**.  
3. Apply. Optionally add peer IPs under **Mesh peer IPs**.  
4. Ensure the peer can reach this host’s web UI on that private IP (or use the poll list).  
5. Open **Fabric reports** / **Known peers** for validation badges.

Default poll interval is **60 seconds**. Color changes use a **hold-off** (~120s) so training flaps do not blink red/green.

---

## Security

- Export **default off**.  
- Token required.  
- **Private IPs only** by default (no WAN).  
- Payload: speeds, lanes, hostname, host id — not array data or passwords.  
- Disable on threat-sensitive networks if topology fingerprinting is a concern.

---

## Multi-hop

Local sysfs only sees **direct** Thunderbolt peers. To learn about host C from host A, A needs an **IP route** to C’s mesh export (OpenFabric/static). That is not a custom discovery protocol — see the multi-host plan notes in RELEASES.

---

## Related

- [routing-openfabric.md](routing-openfabric.md) — OpenFabric metrics  
- [links-and-topology.md](links-and-topology.md) — physical topology  
- [troubleshooting.md](troubleshooting.md)  
