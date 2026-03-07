# Project overview

This repository is a WordPress site (PHP) root. Key entry points: `index.php`, `wp-config.php`, and `wp-settings.php`.
Active code lives under `wp-content/` — themes in `wp-content/themes/` and plugins in `wp-content/plugins/`.

## Big picture architecture

Traditional single-site WordPress structure. Core WP bootstrap is in `wp-settings.php` and the runtime configuration is in `wp-config.php` (DB constants, `WP_ENVIRONMENT_TYPE`, and `WP_DEBUG`).

Theme: `wp-content/themes/bricks/` (commercial Bricks theme). Inspect `bricks/functions.php` and `bricks/includes/` for theme-specific initializers and constants (asset suffixing, admin checks, builder mode helpers).

Plugins: notable installed plugins include `woocommerce/` (full composer-like autoloader under `src/`), `woocommerce-payments/`, and several custom or site plugins (e.g. `admin-site-enhancements/`, `automaticcss-plugin/`). Plugins may use their own autoloaders or include patterns; check their top-level plugin PHP file for bootstrapping.

## Developer workflows & commands

This is a PHP project running inside a local environment (DB credentials in `wp-config.php` indicate local:mysql root/root). Use a LocalWP or similar environment to run the site.

Common dev actions (not automated here):

- Start local site via LocalWP (or LAMP/WAMP) and visit site root.
- Enable debugging by setting `WP_DEBUG` to `true` in `wp-config.php` and optionally `define('SCRIPT_DEBUG', true);` for non-minified assets.
- For plugin/theme code changes, reload PHP/FPM or restart LocalWP service as required.

## Project-specific conventions & patterns

Bricks theme sets many BRICKS\_\* constants and uses `BRICKS_ASSETS_SUFFIX` to choose `.min` vs unminified assets. Search for `BRICKS_ASSETS_SUFFIX` when modifying enqueues.

WooCommerce is included as a plugin and uses namespaced PSR-like autoloading in `wp-content/plugins/woocommerce/src/Autoloader.php` and a DI container (`$GLOBALS['wc_container']`). Prefer obtaining instances via `wc_get_container()` rather than global-singletons when interacting with WC internals.

Plugins may provide their own `includes/` or `src/` autoloaders; inspect top-level plugin files (e.g. `woocommerce/woocommerce.php`) for load order hooks and initialization sequences.

## Integration points & external deps

Database: credentials are in `wp-config.php` (DB_NAME DB_USER DB_PASSWORD DB_HOST). Local setup uses DB_NAME `local` and user `root`.

External services: Bricks references `BRICKS_REMOTE_URL` for licensing and remote templates; WooCommerce/Payments may call external payment gateways — be cautious when running code that triggers network calls.

## How AI agents should make edits

- Focus changes to `wp-content/*` unless explicitly updating core. Avoid editing WordPress core files in repository root.
- Preserve constants and DB credentials in `wp-config.php`; if changing for CI or test harness, add guarded edits and document them.
- When adding or modifying PHP files, follow existing naming and namespace patterns (Bricks uses namespaced classes under `Bricks\\...`, WooCommerce uses `Automattic\\WooCommerce\\...`).
- Prefer small, localized changes with unit-style tests if adding logic. There are no central test runners configured — propose adding tests as a follow-up.

## Examples to reference

- Enable non-minified Bricks assets: edit `BRICKS_DEBUG` or `SCRIPT_DEBUG` in `wp-content/themes/bricks/functions.php`.
- Use WooCommerce container: `wc_get_container()->get( 'logger' )` rather than global access.

## Files worth checking first

- `wp-config.php` — environment flags and DB settings
- `wp-content/themes/bricks/functions.php` and `wp-content/themes/bricks/includes/` — theme bootstrap patterns
- `wp-content/plugins/woocommerce/woocommerce.php` and `wp-content/plugins/*/` top-level PHP files — plugin bootstrap/autoloader patterns

## If something's unclear

- Ask for the developer's preferred local run process (LocalWP, Docker, XAMPP) and whether CI or tests will be added. Provide specific file paths or failing stack traces for faster iteration.

Please review these instructions and tell me any missing workflows or conventions to include.
