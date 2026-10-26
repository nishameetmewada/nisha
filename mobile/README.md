# NISHA for Android and iOS

A bundled Capacitor 8 application for `com.nishaglobal.app`, using the existing
PHP/MySQL website at **https://nishaglobal.com**. The local interface includes
offline practice, phone-sized question controls, listening voices and optional
recording. It does not load the website as its app shell.

## Build

Use Node 22+ and npm. Android needs a **JDK 21**, Android SDK platform 36 and build
tools 36.0.0. iOS needs macOS, Xcode and an Apple signing account.

```sh
cd mobile
npm ci
npm run build
npx cap sync
```

For Android, configure `ANDROID_HOME` using your Android SDK installation:

```sh
cd android
./gradlew :app:assembleDebug :app:bundleRelease
```

The debug APK is in `android/app/build/outputs/apk/debug/`. It is signed for
development testing, not with the owner's production key. The release AAB in
`android/app/build/outputs/bundle/release/` must be signed with the owner's
upload key before Google Play submission. Keep signing keys and passwords
outside Git. Do not replace an existing production app's signing identity.

On a Mac, run `npm run ios`, select the owner's team in Xcode, set the unique
bundle identifier if required before first publication, then archive for
TestFlight/App Store. The iOS project uses Swift Package Manager. No iPhone IPA
is produced or verified by the Linux cloud build.

## Website deployment

Deploy the updated PHP files to Hostinger, preserving private configuration.
Import `database/migrations/010_mobile_app.sql` after migrations 004–009, or the
complete additive schema. The mobile migration adds access-token hashes,
authentication rate limits and account-deletion requests. The complete schema
now contains 38 tables.

Check `https://nishaglobal.com/api/mobile.php?action=health`: it should return
protocol version 1 and `mobile_ready: true`. A 404 means the mobile API has not
been deployed. Database setup errors do not prevent the bundled offline pack
from working. A Git push or cloud environment publication is not a Hostinger
deployment.

Tokens are separate from website cookies and expire after 30 days. Android uses
Keystore-backed encryption; iOS uses device-only Keychain. Logout revokes the
server token. Password, email, role and suspension changes invalidate mobile
access through the account authentication version. No API keys belong in the
mobile bundle.

The production API URL is fixed in `src/api.ts`. Production CORS admits the
bundled native origins and the NISHA domain. Development loopback origins and
plain HTTP require explicit private `mobile_allow_loopback` configuration; this
does not enable arbitrary remote API hosts in a production app.

## Practice and privacy

The app bundles 80 generated practice tasks, 20 per course, across reading,
listening, writing and speaking. They are not teacher-reviewed. Objective
offline points are calculated locally; offline answers are not silently uploaded
as verified results. Full mocks, current libraries, account history, coaching
and AI feedback need the deployed API. Full mocks retain the website's one-free
then paid-access rules and server marking.

Microphone access starts only after the student's recording action. WAV capture
is limited to five minutes; recordings are temporary and upload only after an
explicit AI review request. Native and browser speech voices depend on the
device. AI feedback is separate practice guidance, not official exam scoring.

Publish `/mobile-privacy.php` and `/delete-account.php` on the website, configure
the student support contact, and process requests in `/admin-deletion.php`.
Account deletion is distinct from clearing local answers or uninstalling.

## Store release

The included projects and Android testing artifacts are not published store
listings. Release work still includes owner signing, real-device microphone and
interruption tests, privacy/data-safety declarations, screenshots and review.

This version has no in-app purchase flow or Stripe card collection. It uses
existing NISHA access. To sell digital passes inside store builds, implement and
configure StoreKit/Google Play Billing with server receipt verification and
restore purchases, or use a permitted regional programme after reviewing its
requirements. Do not add a website checkout link and assume store acceptance.

## Checks

```sh
npm run build
node tests/offline-integrity.mjs
php ../scripts/build-mobile-content.php --check
php ../tests/mobile-auth-integrity.php
php ../tests/mobile-learning-integrity.php
node ../tests/mobile-api-http-smoke.js
```

The PHP/API suites use isolated local databases. They create no live accounts,
charges, AI calls or teacher approvals. Native signing and physical iOS/audio
behaviour require the owner's devices and developer tools.
