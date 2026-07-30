# Troubleshooting

## No Thunderbolt hardware detected

- Board/CPU may lack a host controller, or it is disabled in BIOS.  
- Controller bound to **vfio-pci** — check PCI panel; return NHI to host driver if you want host networking.  
- Copy diagnostics from the empty-state page when filing an issue.

## Interface never appears (`thunderbolt0` missing)

- Peer not connected or not authorizing the link (security mode).  
- Cable is charge-only / not TB/USB4.  
- Peer OS has no Thunderbolt networking stack (common on some Windows SKUs).  
- Modules not loaded — Driver options **Load modules = Yes**, Apply.  
- Dual-cable experiment left the domain odd — unplug all TB cables, reboot if needed, single cable.

## Link trains but no ping

1. Confirm addresses same subnet, unique hosts ([addressing.md](addressing.md)).  
2. `ip link` shows UP, carrier 1.  
3. Peer firewall (Windows especially).  
4. Unraid E2E **No**; reconnect; retest ([driver-options.md](driver-options.md)).  
5. Keep default route **No** so you aren’t blackholing other traffic while testing.

## 20G · 1-lane on a TB4/USB4 host

- **Most likely cable or port path** — try certified 40&nbsp;Gbps short cable, other rear ports.  
- Not usually “the peer OS is capping you” when both ends are high-gen hosts.  
- Details: [standards-and-speeds.md](standards-and-speeds.md).

## Two cables, still one interface

Expected often for same two hosts — see [links-and-topology.md](links-and-topology.md).

## One-way traffic / flaky after reboot

- Re-check E2E (`cat /sys/module/thunderbolt_net/parameters/e2e`) is still `N` if you want e2e=0.  
- Ensure modprobe.d conf persisted under `/boot/config/modprobe.d/`.  
- Peer NetworkManager profiles “never default” / wrong metric.

## Do not

- Unbind Thunderbolt **NHI** to “reset” networking.  
- Put two TB links on the **same** IPv4 prefix.  
- Expect a dock RJ45 to show up as `thunderbolt0`.

## Peer-specific notes

[peer-scenarios.md](peer-scenarios.md)
