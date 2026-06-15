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
- BSS REST API access

## Installation

1. Clone or copy this folder into the WordPress site root as `registration-manager/`.
2. Ensure the parent `wp-load.php` is one level up (see `bootstrap.php`).
3. Add the required constants to the parent `wp-config.php` (see below).
4. Visit `/registration-manager/` on the site.

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
| `action=get-event&event_code=...` | Registrants for an event |
| `action=register&event_code=...` | Public registration form |

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

## Project structure

```
includes/
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
  register.php            Public registration form
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
