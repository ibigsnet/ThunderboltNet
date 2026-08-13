# Requirements

## Unraid host

- Unraid with a kernel that includes the **Thunderbolt** driver family (covers Thunderbolt 3/Thunderbolt 4/Thunderbolt 5-class and USB4 host routers; see `.plg` `min=`).  
- A visible Thunderbolt **host controller** (NHI / host router) bound to the host driver — not permanently stuck on `vfio-pci` if you want host networking.  
- Kernel modules: `thunderbolt`, `thunderbolt_net` (plugin can modprobe on Apply).  
- **Not** a USB-only board with no Thunderbolt/USB4 host router — USB&nbsp;3 SuperSpeed ports alone are not enough.

## Peer

- Another **host** that implements Thunderbolt-family **networking** (Linux `thunderbolt_net`, macOS Thunderbolt Bridge, or Windows when the OEM stack provides it).  
- Or accept that docks/hubs with RJ45 are usually **USB Ethernet**, not Thunderbolt net — see [peer-scenarios.md](peer-scenarios.md).

## Cable

- Prefer a **certified Thunderbolt / USB4-class** cable that matches **both** hosts:
  - Thunderbolt 3/Thunderbolt 4 era: often 40&nbsp;Gbps-class passive  
  - Thunderbolt 5 / USB4 v2: 80&nbsp;Gbps-class when both ends support it  
- Not SS-only SuperSpeed USB ports/cables (no host-net tunnel).  
- Details: [standards-and-speeds.md](standards-and-speeds.md).

## BIOS / firmware (typical checklist)

Exact menus vary by vendor (ASUS, MSI, Gigabyte, …):

- Thunderbolt / USB4 (or “USB4 and Thunderbolt”) support **Enabled**  
- Security mode you understand (**None** is easiest for a closed lab; **User/Secure** needs authorization UX)  
- Do not force the port into pure USB2/USB3-only mode if you want full Thunderbolt-family rates  
- After BIOS changes, cold boot and recheck sysfs / the Thunderbolt tab  

## Security domain

Sysfs `security` on the domain reflects Thunderbolt security level.  
Peers may need approval depending on mode. Lab setups often use **none** for simplicity; that is a **trust** tradeoff on shared physical access.

## IOMMU / VFIO

If the Thunderbolt controller or NHI is bound to **vfio-pci** for VM passthrough, the **host** cannot use it for Thunderbolt Net until it is returned to the host driver.  
The plugin’s PCI panel warns about this. Do not unbind NHI as a random fix — use proper VFIO bind/unbind procedures and expect reboot if the controller wedges.

## What you do *not* need

- A Thunderbolt Ethernet “switch” appliance  
- LACP to a TOR switch for basic host↔host Thunderbolt net  
- Binding Thunderbolt into Unraid’s `br0` (optional advanced; not required for peer IP)

## Related

- [driver-options.md](driver-options.md)  
- [troubleshooting.md](troubleshooting.md)  
