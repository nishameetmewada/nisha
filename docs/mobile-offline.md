# Offline phone practice

The Android and iOS application bundles **80 free generated practice tasks**:
20 for each IELTS Academic, IELTS General Training, PTE Academic and PTE Core
course. Each course includes six reading, six listening, four writing and four
speaking tasks, selected from different original NISHA topic/perspective pairs.
They are explicitly labelled generated and **not teacher-reviewed**.

The local pack contains only these guided-practice tasks and the marking keys
needed on the device. It includes no full mock paper, private student record,
provider credential or teacher certification. Timed full mocks remain online
and use the same account/free-mock/paid-access rules as the website.

Offline objective marking follows the PHP marker: normalized text and word
limits, choice marking, deductions for incorrect multiple selections, ordered
adjacent-pair points, and per-blank gap credit. Writing and speaking are
**ungraded offline**. A student must connect, sign in and explicitly request an
AI review before an answer or recording is sent for coaching. Listening uses
the device's available speech voice; a visible script remains available when
audio is unavailable.

Local results say they were marked on the device. They do not become verified
server results, consume a free full mock, establish a paid entitlement or claim
an official IELTS/PTE score. They are not automatically uploaded. Clearing app
data removes local device copies.

Regenerate the checked-in pack from the repository root with PHP 8.2+:

```sh
php scripts/build-mobile-content.php
php scripts/build-mobile-content.php --check
```

The generator is CLI-only, deterministic and validates all sessions before
writing. `--check` verifies the existing JSON matches the current templates.
The manifest records the source version, content hash and the build runtime's
optional PHP Unicode-normalisation settings. Building the app from
its checked-in pack does not require a paid model call or a PHP server. A newer
server content version can require an updated app pack before online coaching
accepts an older local task.

Run `node mobile/tests/offline-integrity.mjs` after installing the mobile package
dependencies. It compares local marking with the actual PHP marker for correct,
incorrect and skipped responses across the complete pack, and checks filtering,
word limits, provenance, tampered caller metadata and invalid responses. These
fixtures need no database or external service.
