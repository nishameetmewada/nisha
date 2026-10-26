# NISHA — your next chapter

A responsive PHP and MySQL study website for students preparing for IELTS and PTE. There is no Composer, npm, or frontend build step. Upload the PHP files and assets to a PHP hosting account, import the database, and add your database settings.

The production domain is **https://nishaglobal.com**. See [production launch](docs/launch-nishaglobal.md) for deployment, private administrator creation and Stripe activation. Code readiness does not establish live hosting or merchant setup.

## Included

- A bundled Android/iOS mobile app in `mobile/`, with an 80-question offline pack, secure native account storage, optional recordings, online mocks, coaching and history. See [mobile build and deployment](mobile/README.md). Android testing APK and unsigned release AAB are included; iPhone compilation/signing requires a Mac and Apple developer setup.
- A responsive, original NISHA landing page and mobile navigation.
- Registration, login, session-based student accounts, and a private dashboard.
- A searchable practice library for IELTS Academic, IELTS General Training, PTE Academic and PTE Core, with skill filters and question-type exercises.
- Untimed guided sessions with server-checked answers, explanations and optional ChatGPT API coaching for writing/speaking.
- Larger, readable typography and a persistent light/dark theme that initially follows the device preference.
- Timed sectional mocks for every full-exam section, plus four complete original mocks.
- Each IELTS mock: 40 listening and 40 reading questions, two writing tasks and three speaking parts.
- PTE Academic: 68 tasks across 22 question types. PTE Core: 60 tasks across 19 question types.
- Interactive choices, typed and dropdown gaps, matching, paragraph ordering, incorrect-word selection, writing and optional microphone recording.
- Original passages, audio scripts, charts, tables, maps and process diagrams.
- Device autosave, resumed sessions, server marking, and private account history for guided, sectional and full attempts.
- A quick studio at `quick-practice.php`, with six-question reading/listening warm-ups and direct routes into AI-capable guided writing/speaking.
- A MySQL schema for users, short practice history, and complete reports.
- One reserved, resumable free full mock per student account; further full mocks require an active one-time access pass. The editable launch plan is **AUD 29 for 30 days**, with no automatic renewal.
- Hosted Stripe Checkout, signed payment webhooks, payment/access history and an owner billing-settings page. Checkout stays disabled until private provider setup is complete.
- Admin controls for pricing, percentage/fixed promotions, account editing, password resets, suspension, roles and audit history. Security changes revoke existing sessions.
- Editable homepage tagline, public announcement and support email. Coaching lessons support keyword, course and skill filters with accurate result counts.
- Teacher applications, moderated coaching videos, paid-student viewing and a private teacher portal.
- An owner-approved teacher reward ledger: **90% of positive verified net profit after business operating expenses and carried losses**, apportioned by qualified student video views. Actual payouts are recorded manually after the owner transfers money.
- A separate public generated library with **30,000 question variants and 3,000 assembled mock variants** across all four courses, with course, skill and task-type filters. Generated content is labelled as not teacher-reviewed; source scenarios and question structures repeat. It is not 30,000 independent questions or 3,000 unseen papers.
- A separate teacher-review workflow targeting **30,000 reviewed practice questions and 3,000 reviewed mock versions**. The reviewed question/mock libraries contain only actual teacher-approved publications, with their own live counts.

The configured full mock budgets are 164 minutes for each IELTS version, 140 minutes for PTE Academic and 120 minutes for PTE Core. These are NISHA learning choices; real test counts and delivery arrangements vary. Objective tasks report raw practice points. Writing and speaking offer optional OpenAI API practice estimates, criterion feedback and improvement plans when the site owner configures a key. Self-review remains available. AI estimates are separate from objective points and are not official or validated proficiency scores. Listening uses browser speech rather than professional test recordings. Some same-format questions are shared between course variants and practice modes, as disclosed in the catalogue.

NISHA is an independent practice platform and is not affiliated with IELTS, Pearson PTE or Language Academy. All questions and interface assets are original or locally licensed. The homepage's growth illustration is decorative, not a real student's results. The hero portrait was generated for this website.

For full exam formats, timing, audio limitations, privacy, scoring, and upgrade instructions, read [Full mock exams](docs/full-mock-exams.md) and [Practice library coverage](docs/practice-library.md). To enable AI coaching, import `database/migrations/003_ai_reviews.sql` and add your API key privately to the existing config; see [AI coaching](docs/ai-coaching.md). For browser warnings on a deployed site, see [Deployment security checks](docs/deployment-security.md).

## Put it on Hostinger

Use a Hostinger **web hosting plan with PHP and MySQL**. This project is deployed as PHP files, not through the Hostinger Website Builder.

1. In hPanel, choose your website and set its PHP version to **8.2 or newer** (the code's minimum is PHP 8.1). Enable the `pdo_mysql` extension. MySQL 8+ or MariaDB 10.6+ is recommended.
2. Create a MySQL database and its own database user under database management. Keep the database host, database name, username, and password available privately. Hostinger database names and usernames often include an account prefix; copy the full values.
3. Open phpMyAdmin for that database and **import `database/schema.sql`**. Select the database first. The import does not create or select a database and can be run again without deleting existing tables or records.
4. Use File Manager or SFTP to upload the **contents** of this project into the site's `public_html` directory. `index.php` should sit directly in `public_html`, alongside `assets`, `includes`, `config`, `api`, and the other PHP pages. Include the hidden root `.user.ini`, which requests speaking-upload limits on hosts that support directory overrides. Preserve the root `.htaccess` and each protected folder's `.htaccess`. Remove a default `index.html` if it overrides your new homepage.
5. Copy `config/config.example.php` to **`config/config.php`**. Edit only this new file using the database values from step 2. For Hostinger shared hosting, `db_host` is usually `localhost`; use the value actually shown in hPanel. Use port `3306` unless instructed otherwise. Never put database values in JavaScript or commit this private file to Git. Keep file permissions restricted to your hosting user and PHP (typically 600 or 640, depending on the host).
6. Enable your domain's SSL certificate and turn on **Force HTTPS** in hPanel. HTTPS is required for browser microphone recording on a public site and for secure session cookies. Core practice and accounts need no external API. Optional ChatGPT coaching requires a separately billed OpenAI API key; follow [AI coaching setup](docs/ai-coaching.md).
7. Visit your domain, create a new account, finish a reading/listening session or full mock, and check that the saved result appears on **My dashboard**. Sign out and sign in again. On your domain, requests to `/config/config.php`, `/database/schema.sql`, and `/includes/bootstrap.php` should return a forbidden response; the supplied Apache/LiteSpeed rules protect those directories.

A deployment ZIP is provided separately with the project files at its root. It includes the example configuration but **does not include the local database configuration, students, passwords, database data, or development runtime**. Extract it into `public_html`, then complete steps 2, 3, 5, 6, and 7. Do not upload the `.git` directory, development tools, or database backups.

For an existing website, preserve **`public_html/config/config.php`** when uploading an update. Import `database/schema.sql` into its configured database if any tables are missing; the import preserves existing tables and records. Uploading the ZIP alone does not connect the website to MySQL or activate OpenAI. If registration shows “temporarily unavailable”, follow the [database setup guide](docs/database-setup.md). An owner with SSH access can run `php check-setup.php` privately for a credential-free report; browser access to that script returns 404.

For this platform upgrade, import migrations **004 through 010** in order, or re-import the complete additive `database/schema.sql`. The mobile update adds **010_mobile_app.sql** to the earlier 007/008/009 platform upgrade. The full schema now contains 38 tables. Follow [administrator setup](docs/admin.md) to create a private owner login or promote a verified existing numeric account ID. Ordinary registration never creates an administrator. Complete [Stripe setup](docs/commerce.md), configure teacher video hosts and rewards, and assign approved teachers to the [content review queue](docs/question-bank.md). Preserve database/API credentials when adding settings from the updated example.

The 30,000/3,000 expansion needs real teacher review and sufficient distinct content. Generated drafts and assembled mock drafts are private review material, not teacher certification. A validation script cannot publish or certify them. The original four starter mocks remain separate and are not represented as teacher-reviewed papers. Live charging requires your Stripe account and webhook; no real charge or automatic teacher bank transfer is made by local tests.

If your host uses Nginx instead of Apache/LiteSpeed, configure equivalent server rules denying direct access to `config`, `database`, `includes`, dotfiles, and source/deployment archives. Nginx does not process `.htaccess`.

### Configuration

`config/config.php` returns the same keys as the example:

```php
<?php
return [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'YOUR_DATABASE_NAME',
    'db_user' => 'YOUR_DATABASE_USER',
    'db_password' => 'YOUR_DATABASE_PASSWORD',
];
```

These are placeholders, not usable credentials. Alternatively, configure server environment variables `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`. A local config file takes precedence. Public practice and the landing page work before a database is configured; account forms explain that accounts are not available until the database and schema are ready.

## Local development

Install PHP 8.2+ with PDO MySQL and a running MySQL/MariaDB server. Create a database and user, import the schema, and create your private `config/config.php` as above. Use the existing checkout; no Git worktree is needed.

From this directory:

```sh
php -S 127.0.0.1:8000
```

Use your browser to visit the PHP development server at port 8000. This server is only for local development and **does not enforce `.htaccess`**. Do not expose it as a production server. Hostinger runs the PHP site through its own web server; you do not run this command on shared hosting.

For optional AI coaching, cURL or verified HTTPS streams and fileinfo are also needed. The prepared cloud instance retains PHP and MariaDB tools outside this repository at `/workspace/.tools`. Its saved environment startup instructions describe how to restart the database and development server. These tools are not part of the Hostinger upload.

## Files to edit

| File | Purpose |
| --- | --- |
| `index.php` | Landing page copy and sections |
| `assets/css/style.css` | Shared design and responsive landing page |
| `includes/header.php`, `includes/footer.php` | Shared navigation and footer |
| `practice.php`, `assets/js/learning.js`, `assets/css/learning.css` | Searchable course and question-type library |
| `includes/learning-catalog.php` | Canonical modules and generated practice/section definitions |
| `generated-practice.php`, `generated-mocks.php`, `includes/generated-public-library.php` | Public generated variants and paged catalogues, separate from reviewed content |
| `includes/practice/ielts-modules.php` | Additional original IELTS question-type exercises |
| `quick-practice.php`, `includes/practice-data.php`, `assets/js/practice.js` | Legacy short studio |
| `includes/exams/` | Original full exam content |
| `exams.php`, `assets/js/exams.js` | Shared guided, sectional and full exam room |
| `api/practice-feedback.php` | Practice-only objective answer checks |
| `includes/ai-review.php`, `includes/ai-review-service.php`, `api/ai-review.php` | OpenAI requests, validated scoring, quotas and private persistence |
| `assets/js/ai-review.js`, `ai-reviews.php` | AI review controls and owner-only coaching history |
| `assets/css/theme.css`, `assets/js/theme.js` | Readable light/dark design and preference |
| `database/migrations/003_ai_reviews.sql` | Add AI review history and durable usage limits |
| `includes/exam-grading.php` | Server-side practice marking |
| `database/schema.sql` | Database tables |
| `database/migrations/002_full_exams.sql` | Add full mock history to an existing installation |
| `config/config.php` | Your private server settings; ignored by Git |

Add content through the canonical exam files or the supplemental module bank; see [Practice contract](docs/practice-contract.md). Module counts and launch IDs are generated from actual content. New guided practice keeps answer keys on the server and checks only the requested item. Timed sections and full mocks reveal answers at final submission. The optional legacy studio delivers its short answer keys to the browser. These are learning exercises, not secure proctored assessments.

## Readiness checks

- Check PHP syntax: `find . -name '*.php' -not -path './config/config.php' -exec php -l {} \;`.
- Run `php tests/exam-integrity.php` and `php tests/learning-integrity.php` for content, coverage, generated-session and marking checks.
- Run `node tests/exam-browser-smoke.js` and `node tests/learning-browser-smoke.js` against the local PHP server with Playwright and Chromium installed; see `tests/README.md`.
- Run `php tests/auth-database-integrity.php`, `node tests/auth-browser-smoke.js` and `node tests/pages-browser-smoke.js` for account failure cases and the full page/link/theme audit. These account tests create and clean disposable local records; do not run them against production.
- Load the home, registration, login, practice, and full mock pages at desktop and mobile widths.
- Test full-mock resume, section expiry, forward-only PTE navigation, server submission, and saved history.
- Register, sign out, sign in, complete a quiz, and confirm the dashboard result survives a new login.
- Test IELTS/PTE selection, question navigation, unanswered-question confirmation, feedback, and retry.
- Check a listening exercise with browser audio and its transcript. Audio requires the browser's speech-synthesis capability; voice quality and offline availability depend on the device.
- Write and download a sample response. In a supported browser over HTTPS, record and play back a speaking response; verify that permission denial is explained gracefully.
- Enable HTTPS and verify the protected directory rules on the deployed server.

Passwords are hashed with PHP's password API. SQL uses prepared statements, state-changing requests use CSRF protection, and sessions regenerate on sign-in. Login delays are per session, so hosting-level rate limiting is still useful for a high-traffic public deployment. Password reset/email delivery and official exam certification are not provided. Owner moderation, content review and payment controls require explicit owner setup. As the site owner, manage database backups, access, business accounting and student privacy/contact policies.

Manrope and DM Sans are self-hosted open-source fonts; their license notices are included in `assets/fonts`. The hero image, fonts, and all other interface assets ship locally. No third-party requests are needed to display or use the site.
