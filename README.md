# Philly Chef Showdown

Marketing site for the Philly Chef Showdown — Philadelphia's premier culinary pitch competition. Presented by Mom Your Business × Katika.

**Event:** October 24, 2026 · 2300 Arena · South Philadelphia
**Live site:** https://philly-chef-showdown.katikaws.com

## Pages
- `index.html` — Home
- `about.html` — About the Showdown
- `apply.html` — Chef Registration / Application Form
- `sponsors.html` — Sponsorship tracks
- `contact.html` — Contact + newsletter
- `america250.html` — America 250 tie-in

## Assets
- `styles.css` — Shared stylesheet
- `logo.png` — Brand logo
- `food-pattern.svg` — Background food-doodle pattern

## Editing
Static HTML. Open any `.html` file directly in a browser to preview.

## Deploy
Git push does NOT deploy. Run `./deploy.sh` (scp to `katika-server`, then `sudo install` into `/home/phillych/public_html`). Live on phillychefshowdown.com and philly-chef-showdown.katikaws.com.

## Analytics
GA4 `G-3NJERJ4Z3B` (Katika LLC account) + Meta Pixel `2171466109974802` on every page, inserted right after the `styles.css` link in `<head>`.
