# ThunderboltNet — ship workflow (same as StorageGuard)

## Develop here only

```text
/home/rifle/projects/ThunderboltNet
```

Open in Kate / editor on this machine. **Do not** treat Unraid’s
`/usr/local/emhttp/plugins/ThunderboltNet/` as the source of truth.

## Workflow

1. **Change code on this machine only**
2. **Commit + push to `main`**
   - When shipping plugin assets, bump `<!ENTITY version "YYYY.MM.DD…">` in `thunderboltnet.plg`
   - Unraid updates use **strcmp** (lexicographic), not `version_compare` — see StorageGuard notes
3. **Wait briefly** for `raw.githubusercontent.com` CDN if needed
4. **Install on hosts via official Unraid path only:**
   ```bash
   plugin install https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/main/thunderboltnet.plg
   ```
   (or a **commit-SHA** raw URL if CDN is lagging)
5. **Hard-refresh** Unraid Settings → Thunderbolt Net

## OK to use SSH for

- Read-only checks: status JSON, logs, `ls /sys/bus/thunderbolt`, `ip link`
- `php …/get-status.php`, confirm installed version
- Diagnostics on NIROG / HoloX3D Unraid

## Not the normal ship path

- SCP / `curl -o` into `/usr/local/emhttp/plugins/ThunderboltNet/` as the “release”
- Editing plugin files live on the server except rare CDN force after install when explicitly requested

## First-time GitHub

```bash
cd /home/rifle/projects/ThunderboltNet
git init
git add .
git commit -m "Initial ThunderboltNet scaffold (StorageGuard workflow)"
# create empty repo ibigsnet/ThunderboltNet on GitHub, then:
git remote add origin git@github.com:ibigsnet/ThunderboltNet.git
git branch -M main
git push -u origin main
```

Then on Unraid:

```bash
plugin install https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/main/thunderboltnet.plg
```
