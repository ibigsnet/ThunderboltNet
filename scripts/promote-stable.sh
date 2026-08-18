#!/usr/bin/env bash
# Promote current main tip (or a given commit) to branch stable for CA.
# ALWAYS pins pluginURL/raw/readme to the stable branch so flash installs
# stay on the stable update loop (Unraid Update follows the installed .plg).
#
# Usage:
#   scripts/promote-stable.sh              # merge origin/main → stable + pin
#   scripts/promote-stable.sh <commit>     # merge that commit → stable + pin
#
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
SRC="${1:-origin/main}"
PLG="thunderboltnet.plg"
REPO="ThunderboltNet"

git fetch origin
git checkout stable
git merge "$SRC" -m "sync stable from ${SRC}"

python3 - "$PLG" "$REPO" <<'PY'
import re, sys
plg, repo = sys.argv[1], sys.argv[2]
t = open(plg).read()
t = t.replace(f"{repo}/main/{plg}", f"{repo}/stable/{plg}")
t = re.sub(
    rf'(<!ENTITY raw "https://raw\.githubusercontent\.com/ibigsnet/{repo}/)main(">)',
    r"\1stable\2",
    t,
)
t = re.sub(
    rf'(<!ENTITY pluginURL "https://raw\.githubusercontent\.com/ibigsnet/{repo}/)main(/{re.escape(plg)}">)',
    r"\1stable\2",
    t,
)
t = t.replace(f"github;/main/README", f"github;/stable/README")
t = t.replace(f"/{repo}/main/README", f"/{repo}/stable/README")
open(plg, "w").write(t)
# Fail closed if main still appears in channel entities
bad = []
for line in t.splitlines():
    if "ENTITY raw" in line or "ENTITY pluginURL" in line or "pluginURL=" in line or "readme=" in line:
        if f"/{repo}/main" in line or f"{repo}/main/" in line:
            bad.append(line)
if bad:
    sys.stderr.write("ERROR: stable pin failed — main still in channel URLs:\n")
    sys.stderr.write("\n".join(bad) + "\n")
    sys.exit(1)
ver = re.search(r'ENTITY version "([^"]+)"', t)
print("pinned stable; version=", ver.group(1) if ver else "?")
PY

# Archive for this version must exist on the branch
VER=$(sed -n 's/.*ENTITY version "\([^"]*\)".*/\1/p' "$PLG" | head -1)
TXZ="archive/${REPO}-${VER}-x86_64-1.txz"
if [[ ! -f "$TXZ" ]]; then
  echo "ERROR: missing $TXZ — pack on main first, then re-run promote" >&2
  exit 1
fi

git add "$PLG"
if ! git diff --cached --quiet; then
  git commit -m "chore: pin PluginURL/raw to stable (${VER})"
fi

# Final verification of HEAD tree
if git show HEAD:"$PLG" | grep -E "ENTITY raw|ENTITY pluginURL" | grep -q "/main"; then
  echo "ERROR: HEAD $PLG still references main — abort push" >&2
  exit 1
fi

git push origin stable
echo "OK: stable → ${VER} (CA loop pinned)"
git checkout main
