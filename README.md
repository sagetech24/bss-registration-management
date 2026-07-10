# BSS Registration Manager

Event registration and payment module for Bible Society Singapore (BSS). Runs as a WordPress-backed PHP app inside the main BSS site.

## Features

- Staff dashboard for browsing and filtering events
- Registrant list per event (confirmed and pending)
- Public registration form with HitPay payment integration
- Payment webhook handler for production finalization

## Requirements

- PHP 8.x
- WordPress (parent BSS installation)
- MySQL tables: `bss_events`, `bss_registrant`, `bss_registrant_pendings`
- v2 tables (auto-installed on bootstrap): `event_registration`, `event_registrant`, `event_registration_pendings`, `event_registrant_pendings`
- BSS REST API access

## Installation

1. Clone or copy this folder into the WordPress site root as `registration-manager/`.
2. Ensure the parent `wp-load.php` is one level up (see `bootstrap.php`).
3. Add the required constants to the parent `wp-config.php` (see below).
4. Visit `/registration-manager/` on the site.
5. v2 tables are created automatically on first load via `rm_install_event_registration_tables()`. To run manually:

```bash
php -r "require 'registration-manager/bootstrap.php'; print_r(rm_install_event_registration_tables());"
```

Or apply `migrations/001_event_registration_tables.sql` directly in MySQL.

```
wordpress-root/
├── wp-load.php
├── wp-config.php
└── registration-manager/
    ├── index.php
    ├── bootstrap.php
    ├── webhook.php
    ├── includes/
    └── views/
```

## Configuration

Define these in the parent `wp-config.php`. **Do not commit real values to this repository.**

| Constant | Purpose |
|----------|---------|
| `BSS_API_BEARER_TOKEN` | Bearer token for the BSS REST API |
| `HITPAY_TEST_KEY` | HitPay sandbox API key |
| `HITPAY_LIVE_KEY` | HitPay production API key |
| `HITPAY_TEST_SALT` | Sandbox **API-key salt** (Developers page — for `hmac` in POST body) |
| `HITPAY_LIVE_SALT` | Production **API-key salt** (Developers page — for `hmac` in POST body) |
| `HITPAY_TEST_WEBHOOK_SALT` | Sandbox **webhook-endpoint salt** (Developers → Webhooks → your endpoint — for `Hitpay-Signature` header) |
| `HITPAY_LIVE_WEBHOOK_SALT` | Production **webhook-endpoint salt** (Developers → Webhooks → your endpoint — for `Hitpay-Signature` header) |

HitPay keys can also be stored as WordPress options (`hitpay_test_key`, `hitpay_live_key`) or environment variables.

**Two different salts:** HitPay uses an API-key salt (signs the `hmac` field in payment-request callbacks) and a separate per-webhook salt (signs the raw JSON body in the `Hitpay-Signature` header). Use the salt that matches your webhook format — do not mix them.

Payment webhooks are enabled only on production (`https://biblesociety.sg`). Other environments finalize via the payment-return redirect.

## Routes

Base URL: `/registration-manager/`

| Query param | View |
|-------------|------|
| *(default)* | Events dashboard |
| `action=get-event-registrants&event_code=...&event_id=...` | Registrants for an event |
| `action=get-event&event_code=...` | Event page (reserved) |
| `action=register&event_code=...` | Public registration form |

Legacy group redirect entry: `/registration-manager/redirect.php?e={event_id}` (for v2 group events)

Webhook endpoint: `/registration-manager/webhook.php` (POST, production only)

### HitPay dashboard webhook payload

Configure the webhook **Request data** in the HitPay dashboard to use these fields (HitPay fills the values on each payment):

```json
{
    "payment_id": "{{payment_id}}",
    "payment_request_id": "{{payment_request_id}}",
    "phone": "{{phone}}",
    "amount": "{{amount}}",
    "currency": "{{currency}}",
    "status": "{{status}}",
    "reference_number": "{{reference_number}}",
    "hmac": "{{hmac}}"
}
```

If your dashboard does not support placeholders, use the field names exactly as shown — HitPay sends this structure automatically for payment-request webhooks.

This payload uses the **`hmac` field** (API-key salt from the main Developers page). It is not the same as event webhooks that use the `Hitpay-Signature` header with a per-webhook-endpoint salt.

## v2 registration (event_registration / event_registrant)

New events opt in via `bss_events.settings.registration` JSON. Legacy events without this block continue using `bss_registrant`.

### Enabling v2 for an event

Set `settings.registration.version = 2` or include `settings.registration.mode`. Example:

```json
{
  "registration": {
    "version": 2,
    "mode": "individual",
    "form": { "preset": "full", "fields": [] },
    "group": { "min": 1, "max": 1 },
    "pricing": { "model": "flat" }
  }
}
```

### Modes

| Mode | Key | Description |
|------|-----|-------------|
| Individual | `individual` | Single attendee, one checkout |
| Group flat | `group_flat` | One package price split across N members |
| Per-head tiered | `group_per_head` | `pricing.slots` with per-slot discounts |

### Form presets

`minimal`, `standard`, `full` — expanded server-side into `form.fields`. Custom fields use `source: "custom"` and are stored in `custom_responses` JSON.

### Coexistence

- **No migration** of historical `bss_registrant` rows
- Dashboard dual-reads v2 tables for v2 events, legacy table for older events
- Payment flow reuses HitPay; pending rows live in `event_registration_pendings`

### Legacy group URL redirect

For v2 group events, redirect legacy theme URLs to registration-manager:

1. **Automatic** (when WordPress theme loads): `rm_maybe_redirect_legacy_registration()` hooks `template_redirect` via bootstrap
2. **Manual**: point legacy URLs to `/registration-manager/redirect.php?e={event_id}`
3. **Theme change** (outside this module): replace legacy group template redirect with `rm_registration_url($program_code)`

## Project structure

```
includes/
  schema-install.php           v2 table DDL installer
  registration-config-service.php  Parse settings.registration
  form-schema-service.php      Dynamic form schema + validation
  pricing-service.php          Server-side pricing (incl. early bird)
  event-registration-service.php   v2 header pending/confirmed flow
  event-registrant-service.php     v2 line items + dashboard normalize
  legacy-redirect.php          Legacy group URL → registration-manager
  api-client.php          BSS REST API client
  auth.php                WordPress login guard
  controller.php          Request context builder
  event-service.php       Event filtering and queries
  registrant-service.php  Registrant data helpers
  registration-service.php Registration and order logic
  payment-service.php     HitPay integration
  request.php             GET param parsing
  view.php                Template renderer

views/
  events.php              Event list dashboard
  registrants.php         Registrant list
  register.php            Public registration form (schema-driven for v2)
  partials/
    form-field.php        Single dynamic field renderer
    dynamic-form.php      Schema field loop
    register-wizard.php   Alpine 3-step group wizard
    register-legacy-fields.php  Hardcoded fields for legacy events
  layout.php              Staff layout (Tailwind + Alpine.js)
  layout-public.php       Public layout
```

## Stack

| Layer | Technology |
|-------|------------|
| Runtime | PHP + WordPress |
| Styling | TailwindCSS (CDN) |
| Client JS | Alpine.js 3 (CDN) |
| Data | BSS REST API + direct DB for registrations |
| Auth | WordPress session (`is_user_logged_in()`) |

## Development notes

- All PHP functions use the `rm_` prefix.
- Business logic lives in `includes/`; views are presentation only.
- Never expose `BSS_API_BEARER_TOKEN` or HitPay keys to the browser.
