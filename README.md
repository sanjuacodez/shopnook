# Shopnook – Shoppable Images for WooCommerce

Tag WooCommerce products on core Image and Gallery blocks, show them as pins with product cards, and track views, clicks and the sales they bring.

The WordPress.org listing lives in `readme.txt`. Releases: push a `v*` tag matching the plugin version and `.github/workflows/deploy.yml` publishes to WordPress.org (needs the `SVN_USERNAME` and `SVN_PASSWORD` secrets).

## Layout

- `shopnook.php` – bootstrap.
- `includes/` – settings, pins, Media Library pins, rendering (images and galleries), products, tracking and attribution, stats, REST, editor loader, privacy.
- `includes/admin/` – dashboard and settings screens.
- `assets/js/` – `shopnook.js` (front end, no dependencies), `pin-editor.js` (shared pin editor), `block-editor.js`, `media.js`, `admin.js`. Plain scripts on the `wp.*` globals, no build step.
- `bin/` – development only (excluded from the zip).

## Development

```sh
./bin/lint                                   # PHP/JSON/JS syntax checks
wp eval-file bin/test-integration.php        # integration checks (needs WooCommerce)
./bin/build-blueprint --local /tmp/bp.json   # Playground blueprint with demo shop
./bin/build-zip                              # distribution zip
```

Playground with the demo shop:

```sh
npx @wp-playground/cli@3 server --php=8.3 --wp=latest \
  --mount="$PWD:/wordpress/wp-content/plugins/shopnook" \
  --blueprint=/tmp/bp.json --login
```

## Data

- Option `shopnook_settings`.
- Attachment meta `_shopnook_image` (`hotspots`, `overrides`).
- Block attribute `shopnook` on `core/image` and `core/gallery` (`enabled`, `custom`, `hotspots`, `overrides`, gallery `layout`, `columns`).
- Table `{prefix}shopnook_stats`: daily counters per event, post, image and product.
- Order meta `_shopnook_attribution`, `_shopnook_counted`.
- Cookie `shopnook_ref`: `product.source.attachment.post.timestamp` entries joined by `_`.
