#!/usr/bin/env bash
# Deploy the static site to phillychefshowdown.com (Katika LiquidWeb box, cPanel user "phillych").
# Usage: ./deploy.sh            -> all .html/.css/.php/.svg files
#        ./deploy.sh index.html -> just the listed files
set -euo pipefail
cd "$(dirname "$0")"
FILES=("$@"); [ ${#FILES[@]} -eq 0 ] && FILES=(*.html *.css *.php *.svg)
ssh katika-server 'mkdir -p /tmp/pcs'
scp -q "${FILES[@]}" katika-server:/tmp/pcs/
ssh katika-server 'cd /tmp/pcs && for f in *; do sudo install -o phillych -g phillych -m 644 "$f" "/home/phillych/public_html/$f"; done && rm -f /tmp/pcs/* && echo DEPLOYED'
