# Student access and payments

NISHA offers one completed full mock per student account. Guided practice and
sectional sessions remain free. The launch pass is **AUD 29 for 30 days**, paid
once through Stripe Checkout, with no automatic renewal. The owner can change
the price, supported currency and duration at `billing-settings.php`.

Checkout is disabled until the owner enables it and the private provider
configuration is complete. A visible price is not evidence that payments are
connected. No live payment or refund has been performed by the development
checks.

## Set up the website

1. Back up the database, select the website's database in phpMyAdmin and import
   `database/migrations/004_commerce.sql`. New installations use the complete
   `database/schema.sql`. The migration adds tables without deleting existing
   students, results or AI feedback.
2. Complete the teacher/owner role migration and identify the owner's existing
   account through the private `admin_user_ids` setting or an assigned admin
   role. An email address alone cannot make an account an owner.
3. Add these values to the **existing private** `config/config.php`, preserving
   its database and OpenAI settings:

   ```php
   'site_url' => 'https://YOUR_WEBSITE_HOST',
   'stripe_secret_key' => 'YOUR_STRIPE_SECRET_KEY',
   'stripe_webhook_secret' => 'YOUR_ENDPOINT_SIGNING_SECRET',
   'payment_amount_minor' => 2900,
   'payment_currency' => 'aud',
   'payment_access_days' => 30,
   'payments_enabled' => false,
   ```

   The Stripe secret key and endpoint signing secret are different values. They
   never belong in JavaScript, HTML, a public repository or chat. Environment
   equivalents are `NISHA_SITE_URL`, `NISHA_STRIPE_SECRET_KEY`,
   `NISHA_STRIPE_WEBHOOK_SECRET`, `NISHA_PAYMENT_AMOUNT_MINOR`,
   `NISHA_PAYMENT_CURRENCY`, `NISHA_PAYMENT_ACCESS_DAYS` and
   `NISHA_PAYMENTS_ENABLED`. Explicit environment values override saved owner
   price settings. The signing secret must be available to PHP locally for HMAC
   verification; a network-only credential placeholder cannot replace it.
4. Configure Stripe's webhook destination as
   `https://YOUR_WEBSITE_HOST/api/stripe-webhook.php`. Subscribe to
   `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
   `charge.refunded`, `charge.dispute.created` and
   `charge.dispute.funds_withdrawn`. Use the signing secret for that endpoint.
5. Enable verified outbound HTTPS requests to `api.stripe.com` on the PHP host.
   The application uses cURL or OpenSSL HTTPS streams, keeps certificate
   verification enabled and does not follow provider redirects. Customers enter
   card details on `checkout.stripe.com`; NISHA never stores card numbers.
6. In Stripe test mode, sign in to the owner portal, check the price and enable
   new checkout. Complete a provider test checkout and verify the signed event
   activates a pass. Confirm cancellation, signature rejection, event replay,
   refund and disputed-payment behaviour before switching to matching live keys
   and live endpoint signing settings. Check the business's public purchase,
   contact, tax and refund terms before accepting actual customers.

Owner price changes are recorded in a non-secret audit ledger. Pausing **new
checkout** does not stop valid signed events for purchases or refunds already in
flight. Changes affect fresh orders; an existing checkout retains its original
displayed price, currency and duration until its 24-hour expiry. The pricing
page shows that pending agreement before the customer resumes it.

## Free mock and paid access rules

Full paper data is released only by the authenticated CSRF-protected start API,
one selected paper at a time. Browsing the catalogue reveals metadata. The first
start reserves the account's free paper and attempt reference. Parallel tabs
cannot reserve different free papers. The same attempt resumes safely and is
consumed only after its result is saved. A previously saved full mock counts as
the account's first free mock; guided and sectional results do not.

A second full mock requires an active paid pass. Granting access depends on a
signed, settled webhook bound to the server-created order's exact user, amount,
currency and checkout session. Visiting `pricing.php?checkout=success` cannot
activate access. Duplicate events do not duplicate a pass.

The website prevents a new purchase while an active pass exists. Passes do not
stack or renew automatically. If two previously opened orders settle, each
grants only its own advertised period from settlement; neither inherits time
from a later-refunded purchase. The owner should resolve accidental duplicate
purchases with the customer through Stripe.

A full mock started during paid access has a 24-hour completion window even if
the pass naturally expires meanwhile. Its grant records the purchase funding
it. A settled partial or full refund, or a dispute, revokes that purchase's
unfinished mock grants as well as its pass. Other valid purchases survive.
Completed saved reports remain readable. An owner issues refunds through
Stripe; this website processes their signed updates and does not perform an
automatic money transfer or promise automatic dispute reinstatement.

## Development checks

Run `php tests/commerce-integrity.php` with the prepared local PHP/MariaDB tools.
It creates and removes its own disposable database and least-privilege user,
uses a deliberately fake CLI-only Stripe transport, and never calls Stripe.
It exercises price controls, trial reuse, completion and historical results,
checkout idempotence, immutable order prices, signature expiry/tampering,
settled-event bindings, replay, checkout pause, refund funding, natural expiry
grace and separate-purchase isolation. HTTP/browser access tests are documented
in `tests/README.md`. These tests do not establish live merchant onboarding,
provider permissions, currency availability, tax setup or actual funds movement.
