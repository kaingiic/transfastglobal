# TransFastGlobal

A FedEx-style shipping website demo — dark/light mode, package tracking, services grid, and a Google Maps–powered locations map.

> Design mockup only. Not affiliated with any real carrier.

## Features

- 🌙 / ☀️ **Dark & light mode toggle** (remembers your choice via `localStorage`)
- 📦 **Package tracking** with a sample tracking timeline modal
- 🗺️ **Google Maps** integration with a custom dark map style that follows the theme
- 🚚 Services grid, how-it-works steps, and responsive layout
- Single self-contained `index.html` — no build step

## Run

Open `index.html` in any browser.

## Enable the live Google Map

1. Create an API key at <https://console.cloud.google.com/> → **APIs & Services → Credentials**.
2. Enable the **Maps JavaScript API**.
3. In `index.html`, find the commented `<script>` block at the bottom, replace `YOUR_GOOGLE_MAPS_API_KEY` with your key, and remove the surrounding `<!-- -->`.
4. (Optional) Change the map center coordinates (`lat`/`lng`) to your city.

Restrict the key to your domain in the Google Cloud console before deploying.
