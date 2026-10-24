# NISHA regression checks

Run from the project root with PHP 8.2 or newer:

```sh
php tests/exam-integrity.php
```

In the prepared cloud environment, PHP is available at `/workspace/.tools/bin/php`.
The suite uses the actual canonical exam files and pure grading functions. It
does not need a database, server, network access, student account, or browser.

It checks full-exam section counts, time budgets, resources, passage/script
lengths, unique question IDs, accepted-answer validity, and recursive removal of
answer keys from public data. Independent grading fixtures cover each objective
interaction, skipped questions, partial points and deductions, word limits,
ungraded writing/speaking, stale exam versions, and invalid submission shapes.
Known-content regressions also pin selected real answers and distractors from
the shipped IELTS and PTE mocks. These checks cannot
establish that every question is editorially unambiguous or replace listening
to audio and trying the exam runner in a browser.

The exams are original NISHA practice. The configured task counts and timing are
mock settings, not a claim that every official IELTS or PTE delivery is identical.

## Browser integration

Start the local PHP server and MySQL with the imported schema, then run:

```sh
node tests/exam-browser-smoke.js
```

This optional development suite requires Node.js, Playwright and Chromium. They
are available in the prepared cloud environment; they are not website hosting
requirements. Override `NISHA_PHP_BIN`, `NISHA_CHROMIUM_BIN`, or `NISHA_TEST_BASE`
if needed. The account-creating test accepts loopback development servers only.
It creates one uniquely named test student and deletes that exact account and
its dependent results in cleanup; do not use it against real hosting.

The browser suite completes both actual mocks through the real marking API,
checks account saving and retry, verifies review/history, and exercises all
interaction types. It also checks device resume, expired section/item timing,
forward-only navigation, omitted-word audio, optional recording, failed-request
recovery, and responsive layouts. Audio event timing is tested with an explicit
speech stub for deterministic checks; audible voice quality still needs a
manual check on the student's real browser. Microphone checks use Chromium's
fake test device, not the user's microphone. Screenshots and test downloads go
to a temporary directory outside the checkout.

## Learning library checks

Run `php tests/learning-integrity.php` for the four-course catalogue, all generated practice/section definitions, known type coverage, resources, answer validation and correct/incorrect/skipped grading. It needs no database or network.

With the local PHP server running, run `node tests/learning-browser-smoke.js`. It uses Playwright/Chromium and the same loopback-only guard and environment overrides as the full-mock suite. Guided and sectional cases run as guests; full-mock launch cases use one exact disposable account and a synthetic paid-access ledger, cleaned afterward. Coverage includes course/search/skill navigation, every response control, server feedback and rejection paths, untimed restore, timed expiry, changed/shared audio resources, visual rendering, AI availability and mobile overflow. It checks public JSON for leaked answer keys and unexpected external network requests. The checks use deterministic speech stubs; browser voice quality remains device dependent.

At this release, the pure suites pass 11,543 full-exam assertions and 7,936 library assertions across 139 sessions. Browser suites pass 45 full-mock checks and 53 library checks. These counts cover exercised cases and are not an editorial guarantee for every possible response.

## AI coaching checks

Run `php tests/ai-review-integrity.php` for pure prompt/schema/scoring, PCM-WAV and effective PHP upload-limit checks. Add `--database` to exercise real local persistence, ownership, idempotency, duplicate reservations, daily quotas and read-only recovery of interrupted reviews using temporary accounts. It uses explicit CLI-only fake HTTP transports, never a paid API call. Its cleanup removes only its own accounts/counters and subtracts only its own global reservations. The complete suite currently passes 622 checks.

Run `node tests/ai-review-browser-smoke.js` for the 83-check local HTTP/browser suite. It starts an isolated loopback PHP server and upload temporary directory. It tests actual auth/CSRF/unconfigured states, temp-file cleanup, browser PCM conversion and a five-minute upload, as well as feedback UI, retries, stale-answer handling, safe rendering and private saved history. It also verifies all four quick-studio writing/speaking routes, read-only status recovery after dropped connections, unchanged recording/request references, pending and failed requests, changed-source protection, hosting HTML upload/timeout errors, and effective PHP upload caps before POST. Recovery works without the original file or an enabled provider key and never silently resubmits paid work. Provider replies in UI tests are explicitly route mocked. No production test-mode environment variable or provider-URL override exists.

The existing full and learning suites still pass 11,543 and 7,936 pure assertions plus 45 and 53 browser checks. Typography/theme validation covered light/dark preference, keyboard controls, persistence, system changes, accounts and responsive pages at 320/390/768/1440. A real API key is still needed to verify model availability, service/billing permissions, transcription quality and scoring accuracy; these tests do not establish those properties.

## Cache and account regressions

`php tests/asset-versioning.php` validates content-based JS/CSS URLs and actual shared header/footer output (28 checks). Every PHP page now uses versioned shared assets, so new deployments request current interface code even if old files were cached.

`node tests/auth-browser-smoke.js` checks 24 local browser cases: visible editable inputs in both themes and phone/desktop layouts, genuine registration/login/logout, wrong-password rejection, private session isolation, and an isolated unavailable-database scenario. It cleans only its own test accounts. Missing hosting configuration must produce a clear error, never fake authentication or disabled/invisible fields.

`php tests/auth-database-integrity.php` checks 15 cases using a disposable local database and database user: missing settings/driver/table/column, denied access, a failed connection, private diagnostic output, and genuine account insertion/password verification with only the users table present. It needs the prepared local MariaDB admin wrapper (`NISHA_TEST_MYSQL_ADMIN_BIN` overrides the path); the fixture connects to loopback and does not use the application's private database credentials. Missing history tables must not prevent registration or sign-in. See [database setup](../docs/database-setup.md) for the hosting-side diagnosis; the CLI-only `check-setup.php` never makes a paid API call or prints credentials.

The AI browser suite also restores an old local result, preserves its responses/attempt/raw JSON, checks the visible AI setup/sign-in/ready/progress/completed status and explicit review shortcut, and reproduces the two-item PTE written-summary result without substituting an official PTE score.

## All-page browser audit

Run `node tests/pages-browser-smoke.js` against the local PHP server and imported
database. This loopback-only suite checks 17 public, student, owner and reviewer pages in
light and dark mode at 320, 390, 768 and 1440 pixels. Its 170 checks cover readable
inputs, mobile overflow, images, scripts and styles, JavaScript exceptions,
navigation and keyboard theme controls, internal links and anchors, legacy
IELTS/PTE practice links, and HTTP launches for all 139 sessions. It also creates
one temporary student, saves a genuine mock result, opens its dashboard and
private result/history, and signs out. It also checks owner/reviewer permissions,
metadata-only full papers before access approval, and 21 loaded assets. Cleanup deletes only its exact fixture accounts
and their dependent results. It makes no paid AI calls and does not establish that
a separately deployed Hostinger database or API key is configured correctly.

## Payments, teachers and human review

The following checks use local fixtures and do not create real charges, teacher payouts or production content approvals:

```sh
php tests/commerce-integrity.php
php tests/teachers-integrity.php
php tests/content-review-integrity.php --database
node tests/commerce-http-smoke.js
node tests/mock-access-browser-smoke.js
node tests/platform-browser-smoke.js
```

The commerce suite passes 54 checks for one-free-mock reservation, concurrency/idempotency, payment bindings, signed webhook replay handling, paused checkout settlement, and refund/dispute revocation. Teacher tests pass 50 checks for role approvals, video revisions, elapsed/unique watch coverage, 90% positive net-profit allocation, carried losses and payout records. Content tests pass 108 checks for draft secrecy, explicit approved-teacher certification, duplicate rejection, frozen edits and paper dependency withdrawals. These three PHP suites use disposable databases.

The payment HTTP suite passes 18 checks; the mock-access browser/API suite passes 21 checks; the teacher/owner platform suite passes 17 checks, including stale video-approval rejection. The platform suite temporarily changes only its own owner-managed policy fixture and restores it, so run it sequentially with other tests that edit that singleton policy.

`php scripts/validate-content-drafts.php --all` validates 30,000 question slots and 3,000 assembled draft paper slots without publishing. The current templates contain 23,401 distinct normalised question structures, so repeated slots need substantive teacher editing or rejection. Structural validity does not establish 30,000 unique, correct, teacher-reviewed questions. Public inventory starts at zero and comes only from the human review ledger.

If an OpenAI credential is present in the development environment, run the no-key HTTP/browser AI suite with scoped `NISHA_OPENAI_API_KEY='' OPENAI_API_KEY='' node tests/ai-review-browser-smoke.js`. It starts its own local server and uses explicit provider fixtures. Do not call a real model merely to run these regression checks.
