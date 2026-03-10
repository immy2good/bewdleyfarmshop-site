# Bewdley Custom Plugin - Developer Documentation

This plugin contains site-specific newsletter and CRM integration logic for Bewdley Farm Shop.

## Purpose

The plugin does four main jobs:

1. Captures newsletter consent on WooCommerce checkout (Block and Classic checkout).
2. Syncs opted-in order contacts into FluentCRM.
3. Provides admin tools for consent auditing, legacy CSV export, and CRM tag backfill.
4. Provides frontend newsletter signup UX via shortcodes and AJAX.

Primary file:

- `bewdley-custom.php`

Frontend scripts:

- `assets/signup.js`
- `assets/signup-bar.js`

## Quick Start (Developer Runbook)

Use this when onboarding or validating a fresh environment.

1. Confirm required plugins are active: WooCommerce and FluentCRM.
2. Open Tools -> Email Sync Settings and verify consent key/settings values.
3. Confirm `enable_order_sync` is set correctly for the current environment.
4. Place two test orders (one opted-in, one not opted-in).
5. Verify order meta writes `_bewdley_marketing_optin` as `yes` or `no`.
6. Submit `[bewdley_signup]` with both a new and existing email.
7. Confirm FluentCRM contact updates and `subscribe_button` tag behavior.
8. Run the verification checklist in this document before release.

## Compatibility Matrix

This plugin is currently intended/tested in the Bewdley stack:

- WordPress: 6.9.x
- WooCommerce: active and current project standard
- FluentCRM: active and required for contact/list/tag operations
- PHP: 8.2.x

If platform/plugin versions change significantly, re-run the full checklist and staging validation before production rollout.

## Key Constants

- `BEWDLEY_CUSTOM_OPTION_KEY`: stores plugin settings in `wp_options`.
- `BEWDLEY_CONSENT_META_KEY`: canonical order meta key for consent (`_bewdley_marketing_optin`).

## Current Data Model

### Consent storage

Canonical consent meta key on orders:

- `_bewdley_marketing_optin`

Stored values written by plugin:

- `yes` (opted in)
- `no` (not opted in)

### Settings option

Option name:

- `bewdley_custom_email_settings`

Default fields:

- `enable_order_sync`: `yes|no`
- `consent_meta_key`: default `_bewdley_marketing_optin`
- `consent_allowed_values`: comma-separated values matched as opt-in
- `allow_legacy_without_consent`: `yes|no`
- `default_source_label`: contact source label
- `fluentcrm_list_targets`: list names/slugs/ids
- `fluentcrm_tag_targets`: tag names/slugs/ids

## Hook and Feature Map

### Admin pages (Tools menu)

Registered via `admin_menu`:

- Email Consent Audit
- Email Sync Settings
- Legacy Subscriber Export
- CRM Backfill Tools

### Settings registration

- Hook: `admin_init`
- Registers settings group `bewdley_custom_email`
- Sanitizer: `bewdley_sanitize_email_settings()`

### Checkout consent capture

#### Block checkout path

- Hook: `woocommerce_init`
- Uses Woo Additional Fields API to register checkbox id:
  - `bewdley-custom/marketing-optin`
- Hook: `woocommerce_store_api_checkout_order_processed`
- Copies field into canonical meta key `_bewdley_marketing_optin` as `yes|no`.

#### Classic checkout fallback

- Hook: `woocommerce_checkout_fields`
- Adds checkbox under `billing` with key `_bewdley_marketing_optin`.
- Hook: `woocommerce_checkout_create_order`
- Persists `yes|no` into order meta.

### Order -> FluentCRM sync

Triggered on:

- `woocommerce_order_status_processing`
- `woocommerce_order_status_completed`

Entry point:

- `bewdley_maybe_sync_order_contact($order_id)`

Rules:

- Exits unless `enable_order_sync = yes`.
- Requires valid billing email.
- Checks consent by configured `consent_meta_key` and `consent_allowed_values`.
- Optional legacy mode if consent key missing.

Sync behavior:

- Builds payload from order billing fields.
- Fires extension action before sync:
  - `do_action('bewdley_email_sync_contact', $payload, $order, $settings)`
- Calls `bewdley_try_sync_fluentcrm()` for create/update.

FluentCRM integration:

- Uses `FluentCrm\App\Models\Subscriber`.
- Upserts subscriber as `subscribed`.
- Attaches configured lists and tags if resolvable.

### Legacy CSV export

Endpoint:

- `admin_post_bewdley_export_legacy_subscribers`

Output:

- CSV stream download from WooCommerce orders.
- Includes: email, first_name, last_name, source, consent key/value, order id/date.
- Deduplicates by email.

### CRM tag backfill tool

Endpoint:

- `admin_post_bewdley_backfill_contact_tags`

Behavior:

- Resolves configured tags (creates missing tags when needed).
- Optionally limits target contacts by configured lists.
- Applies tags to subscribed FluentCRM contacts in chunks.

### Newsletter signup AJAX + shortcodes

AJAX action:

- `bewdley_newsletter_signup` (priv and nopriv)

Handler:

- `bewdley_handle_newsletter_signup()`

Validates:

- nonce
- email format
- consent presence
- FluentCRM class availability

Creates/updates contact:

- source `Newsletter Signup Form`
- status `subscribed`
- optional first/last name
- uses FluentCRM `updateOrCreate()` for consistent handling of both new and existing contacts
- attaches configured list targets
- always attaches the `subscribe_button` tag (created if missing)

Shortcodes:

- `[bewdley_signup]`: form with name, email, consent checkbox
- `[bewdley_bar]`: fixed bottom signup bar with delayed appearance

## What Was Implemented In This Phase

The plugin currently reflects the completed signup/sync implementation:

1. Added block checkout newsletter field using Woo Additional Fields API.
2. Added classic checkout fallback to avoid checkout-mode dependency.
3. Standardized consent storage to `_bewdley_marketing_optin` with `yes|no` values.
4. Added guarded order lifecycle sync to FluentCRM, controlled by settings.
5. Added admin tooling for audit/export/backfill for safe historical handling.
6. Added frontend newsletter signup interfaces with AJAX submission.
7. Updated `[bewdley_signup]` contact handling to ensure `subscribe_button` is applied to existing contacts as well as new contacts.

## Dependencies and Expectations

Required for full functionality:

- WooCommerce (for checkout and orders)
- FluentCRM (for contact/list/tag operations)

Optional but expected in this project context:

- FluentSMTP + SES for sending infrastructure (outside this plugin code)

## Safe Extension Points

Preferred extension point:

- `bewdley_email_sync_contact` action before FluentCRM sync.

Use this action to:

- enrich payload fields
- add project-specific mapping logic
- integrate custom segmentation decisions

## Operational Notes

- Keep consent handling conservative: never subscribe without explicit consent unless legacy mode is intentionally enabled.
- Keep list/tag targets configurable via settings; avoid hardcoding IDs in custom edits.
- Exception: `[bewdley_signup]` intentionally uses the fixed `subscribe_button` tag to separate button/form signups from checkout-origin tags.
- Existing logic uses best-effort guards (class/function checks) to fail safely when dependencies are unavailable.

## Data Governance Notes

- Consent-first policy: do not market to contacts without explicit consent.
- Checkout-origin contacts and shortcode/form-origin contacts are intentionally separated by tagging strategy.
- `[bewdley_signup]` uses `source = Newsletter Signup Form` and always targets `subscribe_button`.
- Checkout sync behavior is controlled by consent settings and order lifecycle hooks.

## Troubleshooting

### Symptom: `[bewdley_signup]` submits but tag is missing

Checks:

- Verify FluentCRM is active.
- Verify AJAX request is successful (HTTP 200 and success JSON).
- Confirm tag `subscribe_button` exists or can be created.
- Re-test using one new and one existing contact email.

### Symptom: contact is created but list assignment is wrong

Checks:

- Review `fluentcrm_list_targets` in Email Sync Settings.
- Confirm list names/slugs/IDs are valid in FluentCRM.

### Symptom: order sync does not run

Checks:

- Confirm `enable_order_sync = yes`.
- Confirm order reaches `processing` or `completed` status.
- Verify consent key/value mapping in order meta matches allowed values.

### Symptom: shortcode UI works but no contact update

Checks:

- Validate nonce and `admin-ajax.php` availability.
- Check PHP logs and WooCommerce logs for warnings from source `bewdley-custom`.

## Rollback Notes

Use minimal rollback steps in this order:

1. Disable order sync from Email Sync Settings (`enable_order_sync = no`) if behavior is uncertain.
2. Revert plugin code commit on development branch, test, then promote.
3. Keep FluentCRM data intact; avoid destructive contact/tag cleanup unless explicitly required.
4. Re-run staging verification before any live rollout after rollback.

## Change History

### 2026-03-10

- Restored plugin README into active development branch from `main` baseline.
- Documented fixed shortcode tagging rule for `[bewdley_signup]`.
- Updated signup flow implementation notes to reflect FluentCRM `updateOrCreate()` usage for new and existing contacts.
- Clarified that `[bewdley_signup]` always applies `subscribe_button`.

## Known Technical Debt / Cleanup Candidates

- Inline CSS in shortcode output could be moved to dedicated assets.
- Newsletter bar localized success string appears to contain a malformed character in source text and should be normalized to plain ASCII.
- Plugin currently lives in a single PHP file; future refactor could split into modules (admin, checkout, sync, frontend).

## Quick Verification Checklist After Any Change

1. Place one checkout order with consent checked and one unchecked.
2. Confirm `_bewdley_marketing_optin` values on both orders.
3. Move order to processing/completed and verify FluentCRM contact behavior.
4. Test both shortcodes (`[bewdley_signup]` and `[bewdley_bar]`) on a test page.
5. Validate admin tools pages load without PHP notices.

## File Ownership

Edit only this plugin for custom behavior:

- `wp-content/plugins/bewdley-custom/`

Do not modify:

- WordPress core
- WooCommerce core plugin files
- Bricks parent theme
