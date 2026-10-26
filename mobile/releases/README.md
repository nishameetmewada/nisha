# Android build artifacts

Version 1.0.0, application ID `com.nishaglobal.app`, minimum Android API24,
target API36. See `SHA256SUMS` for the exact file checksums.

- `nisha-android-debug.apk`: installable development-signed testing APK. It is
  not signed with the owner's production app key and is not a Google Play release.
- `nisha-android-release-unsigned.aab`: compiled release bundle. Sign with the
  owner's upload key before submitting it to Google Play. It is not directly
  installable as an APK.

Both contain the tested bundled interface, fonts/licences and 80 generated
offline practice tasks. Online accounts, mocks, coaching and AI need the updated
PHP backend and migration010 deployed at `https://nishaglobal.com`.

The iOS Xcode project is in `../ios/App/`. No signed IPA is included: building
and signing an iPhone release needs macOS/Xcode and the owner's Apple identity.

Physical device testing and store privacy/billing/signing setup remain necessary
before a public store release. See the parent mobile README.
