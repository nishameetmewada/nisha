# Launch at nishaglobal.com

Production URL: **https://nishaglobal.com**. Source updates and local tests do
not deploy files, create a live merchant account or configure Hostinger.

## Deployment and administrator

1. Back up Hostinger's website and database. Upload the updated project contents
   into this domain's `public_html`, including `.htaccess` and `.user.ini`.
   Preserve the existing private `config/config.php` and its database/AI values.
2. Import `database/schema.sql` into that existing database in phpMyAdmin. Its 38
   additive tables preserve records; importing does not repair incompatible old
   columns. Sites on the earlier platform need migrations 007, 008, 009 and 010.
   For the mobile app, deploy `api/mobile.php` and the new includes as well as
   migration010, then verify `/api/mobile.php?action=health` returns protocol1
   with `mobile_ready: true`. See [mobile setup](../mobile/README.md).
3. Complete [administrator setup](admin.md), choosing the password privately.
   Sign in at `https://nishaglobal.com/login.php` and open `/admin.php`.
4. Verify HTTPS and configure any `www` redirect in Hostinger. Payment return
   URLs use the explicit production URL, not the browser's Host header.

## Stripe activation

Activate your business in your own Stripe account. Add these values to the
**existing private** `config/config.php`, preserving all other settings:

```php
'site_url' => 'https://nishaglobal.com',
'stripe_secret_key' => 'YOUR_LIVE_STRIPE_SECRET_KEY',
'stripe_webhook_secret' => 'YOUR_LIVE_ENDPOINT_SIGNING_SECRET',
'payment_amount_minor' => 2900,
'payment_currency' => 'aud',
'payment_access_days' => 30,
'payments_enabled' => false,
```

Store the actual `sk_live_…` key privately on the PHP server. The endpoint secret
is a separate `whsec_…` value. Never put keys in chat, screenshots, JavaScript or
a public repository. This hosted Checkout integration needs no publishable key.

Create the live-mode webhook destination:

**https://nishaglobal.com/api/stripe-webhook.php**

Subscribe to:

- `checkout.session.completed`
- `checkout.session.async_payment_succeeded`
- `checkout.session.async_payment_failed`
- `checkout.session.expired`
- `charge.refunded`
- `charge.dispute.created`
- `charge.dispute.funds_withdrawn`

Use that endpoint's signing secret. PHP needs verified outbound HTTPS access to
`api.stripe.com`; cURL or OpenSSL HTTPS streams are supported. Do not disable
certificate checks.

Exercise a real checkout and signed webhook with matching **test-mode** keys
first, then set the live key and live endpoint secret together. Verify the
price and connection status in admin billing settings, then enable new checkout.
An environment override such as `NISHA_PAYMENTS_ENABLED=0` takes precedence and
must be adjusted privately if present. The launch price is AUD 29 for 30 days,
with no automatic renewal; server-validated discounts appear before payment.

The success redirect never activates access. Only a settled, correctly signed
webhook matching the server order/student/amount/currency/session grants a pass.
Check Stripe's delivery log and the student's access after the first authorised
checkout. Local fixture tests do not prove live settlement.

Live charging still requires private Stripe configuration, business activation
and deployment access. Secrets are managed through private hosting controls.

## Coaching lessons

Search shows only published lessons from approved teachers. Filters do not
create lessons. When none are published, approve a teacher, submit an allowed
HTTPS MP4/WebM lesson, then review and publish it in the admin dashboard.
Students need paid access to play videos; public search shows metadata only.
