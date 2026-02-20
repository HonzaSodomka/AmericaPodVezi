#!/bin/bash
# =============================================================
# setup-fa.sh — stáhne Font Awesome 6.7.2 lokálně
# Spusť jednou v kořeni repozitáře:
#   bash setup-fa.sh
# Pak commitni výsledek:
#   git add fa/ && git commit -m "feat: Font Awesome lokálně" && git push
# =============================================================

set -e

FA_VERSION="6.7.2"
BASE="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/${FA_VERSION}"

echo "📦 Stahuji Font Awesome ${FA_VERSION}..."

mkdir -p fa/css fa/webfonts

# CSS soubory
wget -q "${BASE}/css/fontawesome.min.css" -O fa/css/fontawesome.min.css
wget -q "${BASE}/css/solid.min.css"       -O fa/css/solid.min.css
wget -q "${BASE}/css/brands.min.css"      -O fa/css/brands.min.css

echo "   ✓ CSS hotovo"

# Webfonty (pouze solid + brands — stačí pro tento projekt)
wget -q "${BASE}/webfonts/fa-solid-900.woff2"  -O fa/webfonts/fa-solid-900.woff2
wget -q "${BASE}/webfonts/fa-solid-900.ttf"    -O fa/webfonts/fa-solid-900.ttf
wget -q "${BASE}/webfonts/fa-brands-400.woff2" -O fa/webfonts/fa-brands-400.woff2
wget -q "${BASE}/webfonts/fa-brands-400.ttf"   -O fa/webfonts/fa-brands-400.ttf

echo "   ✓ Fonty hotovo"
echo ""
echo "✅ Hotovo! Commitni výsledek:"
echo "   git add fa/ && git commit -m 'feat: Font Awesome lokálně' && git push"
