# NISHA — your next chapter

A responsive PHP and MySQL study website for students preparing for IELTS and PTE. There is no Composer, npm, or frontend build step. Upload the PHP files and assets to a PHP hosting account, import the database, and add your database settings.

## Included

- A responsive, original NISHA landing page and mobile navigation.
- Registration, login, session-based student accounts, and a private dashboard.
- IELTS and PTE reading and listening: original six-question sets, timer, question navigation, answer explanations, retries, and saved quiz results for signed-in students.
- Writing prompts with a word count, reflection checklist, and text download.
- Speaking prompts with optional browser microphone recording, playback, and download. Recordings never upload to the server.
- A MySQL schema for users and practice history.

The exercises are short skill-building activities, not full-length official mock exams. Reading and listening report raw correct-answer totals, not official exam scores. Writing and speaking have no automated marking. NISHA is an independent practice platform and is not affiliated with IELTS or Pearson PTE. The homepage's growth illustration is decorative, not a real student's results. The hero portrait was generated for this website.

## Put it on Hostinger

Use a Hostinger **web hosting plan with PHP and MySQL**. This project is deployed as PHP files, not through the Hostinger Website Builder.

1. In hPanel, choose your website and set its PHP version to **8.2 or newer** (the code's minimum is PHP 8.1). Enable the `pdo_mysql` extension. MySQL 8+ or MariaDB 10.6+ is recommended.
2. Create a MySQL database and its own database user under database management. Keep the database host, database name, username, and password available privately. Hostinger database names and usernames often include an account prefix; copy the full values.
3. Open phpMyAdmin for that database and **import `database/schema.sql`**. Select the database first. The import does not create or select a database and can be run again without deleting existing tables or records.
4. Use File Manager or SFTP to upload the **contents** of this project into the site's `public_html` directory. `index.php` should sit directly in `public_html`, alongside `assets`, `includes`, `config`, `api`, and the other PHP pages. Preserve the root `.htaccess` and each protected folder's `.htaccess`. Remove a default `index.html` if it overrides your new homepage.
5. Copy `config/config.example.php` to **`config/config.php`**. Edit only this new file using the database values from step 2. For Hostinger shared hosting, `db_host` is usually `localhost`; use the value actually shown in hPanel. Use port `3306` unless instructed otherwise. Never put database values in JavaScript or commit this private file to Git. Keep file permissions restricted to your hosting user and PHP (typically 600 or 640, depending on the host).
6. Enable your domain's SSL certificate and turn on **Force HTTPS** in hPanel. HTTPS is required for browser microphone recording on a public site and for secure session cookies. No email, paid API, or external AI key is needed.
7. Visit your domain, create a new account, finish a reading or listening session, and check that the saved result appears on **My dashboard**. Sign out and sign in again. On your domain, requests to `/config/config.php`, `/database/schema.sql`, and `/includes/bootstrap.php` should return a forbidden response; the supplied Apache/LiteSpeed rules protect those directories.

A deployment ZIP is provided separately with the project files at its root. It includes the example configuration but **does not include the local database configuration, students, passwords, database data, or development runtime**. Extract it into `public_html`, then complete steps 2, 3, 5, 6, and 7. Do not upload the `.git` directory, development tools, or database backups.

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

The prepared cloud instance retains PHP and MariaDB tools outside this repository at `/workspace/.tools`. Its saved environment startup instructions describe how to restart the database and development server. These tools are not part of the Hostinger upload.

## Files to edit

| File | Purpose |
| --- | --- |
| `index.php` | Landing page copy and sections |
| `assets/css/style.css` | Shared design and responsive landing page |
| `includes/header.php`, `includes/footer.php` | Shared navigation and footer |
| `includes/practice-data.php` | Original practice sets, prompts, and explanations |
| `assets/js/practice.js` | Interactive practice behavior |
| `database/schema.sql` | Database tables |
| `config/config.php` | Your private server settings; ignored by Git |

Add more original practice sets by extending the practice data and studio selector together. This starter currently has one six-question set per exam and objective skill (24 questions total). The question answers are delivered to the browser for instant practice feedback; these exercises are not secure proctored assessments.

## Readiness checks

- Check PHP syntax: `find . -name '*.php' -not -path './config/config.php' -exec php -l {} \;`.
- Load the home, registration, login, and practice pages at desktop and mobile widths.
- Register, sign out, sign in, complete a quiz, and confirm the dashboard result survives a new login.
- Test IELTS/PTE selection, question navigation, unanswered-question confirmation, feedback, and retry.
- Check a listening exercise with browser audio and its transcript. Audio requires the browser's speech-synthesis capability; voice quality and offline availability depend on the device.
- Write and download a sample response. In a supported browser over HTTPS, record and play back a speaking response; verify that permission denial is explained gracefully.
- Enable HTTPS and verify the protected directory rules on the deployed server.

Passwords are hashed with PHP's password API. SQL uses prepared statements, state-changing requests use CSRF protection, and sessions regenerate on sign-in. Login delays are per session, so hosting-level rate limiting is still useful for a high-traffic public deployment. There is no password reset/email workflow, payment system, admin CMS, or official exam certification in this starter. As the site owner, manage database backups and access and adapt your privacy/contact policies to your operation before collecting real student data.

Manrope and DM Sans are self-hosted open-source fonts; their license notices are included in `assets/fonts`. The hero image, fonts, and all other interface assets ship locally. No third-party requests are needed to display or use the site.
