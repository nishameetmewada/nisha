# Fix unavailable registration or sign-in

The message “Registration is temporarily unavailable” means the website cannot
use its account database. The form fields can work normally while database
setup is incomplete. Retrying a student's details does not fix this condition,
and an account has not been created when this message appears.

The application requires only the `users` table to register or sign in. Missing
practice, mock or AI history tables are handled separately and must not prevent
account access. Import the complete schema to enable saved results as well.

## Check in Hostinger hPanel

1. Choose the affected website and open **Databases → Management**. Confirm a
   MySQL database exists and a database user is assigned to it. Copy the complete
   database name and username, including their account prefixes, privately.
2. Open **Files → File Manager → public_html/config/config.php**. This must be a
   real copy of `config.example.php`, not just the example file. Set `db_name`,
   `db_user` and `db_password` to the values for that database. Use the database
   host shown in hPanel, commonly `localhost`, and normally port `3306`. Preserve
   existing database values when adding AI settings or updating the website.
3. Open **phpMyAdmin** from that database, select it in the left sidebar and
   check for `users`, `attempts`, `exam_runs`, `ai_reviews` and `ai_review_usage`.
   Import the project's **database/schema.sql** if tables are missing. It uses
   `CREATE TABLE IF NOT EXISTS`, so importing it again preserves existing tables,
   accounts and results. It does not repair mismatched columns in an unrelated
   pre-existing table; back up and inspect those before making schema changes.
4. In the website's **PHP Configuration**, use PHP 8.2 or newer and enable
   `pdo_mysql`. The PHP version selected in SSH/CLI can differ from the version
   serving the website; verify the website setting too.
5. Return to `register.php` in a new browser tab. Create a test account, sign out,
   then sign in with the same details. Complete a short practice session and a
   mock to confirm their saved results appear on the dashboard.

Do not send database passwords, API keys or the contents of `config/config.php`
in chat. They belong only in the hosting configuration. If the database password
has to be reset, update the corresponding private config value at the same time.

## Optional private command-line check

If the hosting plan provides SSH, open a terminal in the website's `public_html`
directory and run:

```sh
php check-setup.php
```

This command reads the same application settings and reports fixed diagnostic
codes with the relevant next step. It never prints database names, usernames,
passwords, API keys or raw database exceptions, and does not modify records or
make a paid API request. Direct browser access to the script returns 404.

| Result code | What to check |
| --- | --- |
| `missing_configuration` | Create the private config and fill in the full database name and user. |
| `invalid_configuration` | Correct the private PHP config syntax or permissions. |
| `missing_mysql_driver` | Enable `pdo_mysql` for the selected PHP version. |
| `database_access_denied` | Check the database user, password and assigned database access. |
| `database_not_found` | Check the full database name and its Hostinger prefix. |
| `database_connection_failed` | Check the database host, port and service availability. |
| `missing_accounts_table` | Import `database/schema.sql` into the selected database. |
| `outdated_accounts_table` | Back up and compare the existing `users` columns with the schema. |
| `accounts_table_unavailable` | Check table permissions and schema. |

The command checks read access without inserting a test student. A successful
command is followed by a real registration/sign-in check, because registration
also needs `INSERT` permission and password upgrades need `UPDATE` permission.
AI setup results are optional for account access; a configured key does not
establish provider access, billing readiness or grading quality.
