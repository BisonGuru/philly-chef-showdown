#!/usr/bin/env bash
# Deploy the static site to BOTH hostnames on the Katika LiquidWeb box:
#   phillychefshowdown.com              -> /home/phillych/public_html                          (cPanel user phillych)
#   philly-chef-showdown.katikaws.com   -> /home/katika/public_html/projects/philly-chef-showdown (cPanel user katika)
# Usage: ./deploy.sh            -> all .html/.css/.php/.svg files
#        ./deploy.sh index.html -> just the listed files
set -euo pipefail
cd "$(dirname "$0")"
FILES=("$@"); [ ${#FILES[@]} -eq 0 ] && FILES=(*.html *.css *.php *.svg)
ssh katika-server 'mkdir -p /tmp/pcs'
scp -q "${FILES[@]}" katika-server:/tmp/pcs/
ssh katika-server '
  set -e
  cd /tmp/pcs
  for f in *; do
    sudo install -o phillych -g phillych -m 644 "$f" "/home/phillych/public_html/$f"
    sudo install -o katika   -g katika   -m 644 "$f" "/home/katika/public_html/projects/philly-chef-showdown/$f"
  done
  rm -f /tmp/pcs/*
  echo DEPLOYED to phillychefshowdown.com + philly-chef-showdown.katikaws.com
'
