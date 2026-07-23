# TransFastGlobal

Official website for **TransFastGlobal** — fast delivery, real-time package tracking, and worldwide shipping.

🌐 Live at **https://transfastglobal.org**

## Features

- 🌙 / ☀️ **Dark & light mode toggle** (remembers your choice via `localStorage`)
- 📦 **Package tracking** with a delivery status timeline
- 🗺️ **Google Maps** locations map (keyless embed) that follows the site theme
- 🚚 Services grid, how-it-works steps, and responsive layout
- Single self-contained `index.html` — no build step

## Run locally

Open `index.html` in any browser.

## Deployment

The site is hosted on GitHub Pages and served on the custom domain `transfastglobal.org`
(configured via the `CNAME` file). A GitHub Actions workflow
(`.github/workflows/pages.yml`) automatically builds and deploys on every push to `main`.

## Change the map location

The locations map uses a keyless Google Maps embed, so it needs no API key. To move it,
edit the `?q=` value in the `<iframe>` inside the Locations section of `index.html`
(e.g. `?q=Los+Angeles,CA`).
