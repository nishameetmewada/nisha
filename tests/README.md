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

With the local PHP server running, run `node tests/learning-browser-smoke.js`. It uses Playwright/Chromium and the same loopback-only guard and environment overrides as the full-mock suite. It creates no account. Coverage includes course/search/skill navigation, every response control, server feedback and rejection paths, untimed restore, timed expiry, changed/shared audio resources, visual rendering, self-review-only results and mobile overflow. It checks public JSON for leaked answer keys and unexpected external network requests. The checks use deterministic speech stubs; browser voice quality remains device dependent.

At this release, the pure suites pass 11,543 full-exam assertions and 7,936 library assertions across 139 sessions. Browser suites pass 45 full-mock checks and 53 library checks. These counts cover exercised cases and are not an editorial guarantee for every possible response.
