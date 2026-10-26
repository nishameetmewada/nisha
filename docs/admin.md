# Administrator setup and controls

There is no shared default password or automatic email-based administrator role.
After deploying the files and importing the full schema, run privately in SSH:

```sh
php scripts/create-admin.php --email YOUR_ADMIN_EMAIL
```

The command asks for the name and a hidden 12–72 byte password, stores its hash,
and records the role assignment. It never accepts a password as a command-line
argument. An existing email is not overwritten. To promote an existing account
that you have verified belongs to you, use its explicit numeric ID:

```sh
php scripts/create-admin.php --existing-id ACCOUNT_ID
```

Sign in again using that account's password, then open `admin.php`. Promotion
revokes earlier sessions. Without SSH, register and sign in normally, verify the
intended numeric account ID in Hostinger's phpMyAdmin, and add that ID to the
private `config/config.php` setting `admin_user_ids`. Do not identify an owner
by email alone. Remove a configured ID from the private configuration to revoke
it; the browser cannot remove a configured owner.

- `admin.php`: business overview, recent orders, teacher/video moderation and
  verified monthly profit/reward ledgers.
- `admin-accounts.php`: search names/emails/IDs, edit profiles, set new passwords,
  suspend/restore accounts, and grant/revoke administrator roles. Changes require
  the acting admin's current password and a reason; role changes also require
  confirmation. Self-lockout and removal of the last active owner are blocked.
- `billing-settings.php`: price, currency, access period and checkout activation.
- `admin-promotions.php`: percent/fixed discounts, UTC windows, usage caps,
  featured offer text and activation controls.
- `admin-settings.php`: homepage tagline, support contact and public announcement.
- `admin-deletion.php`: confirmed mobile/web account-deletion requests. Completing
  a request removes private practice/AI history, anonymises the account and hides
  teacher content. Financial/reward references are retained without reopening
  access. The admin must re-enter their password and confirm the action.
- `content-review.php`: certification still requires an approved teacher profile.

Password, email, status and role changes invalidate existing browser sessions on
their next request. Suspended accounts cannot sign in or use private APIs.
Changes are audited without passwords or hashes. There is no hard-delete action
for student or financial records. Administrator reauthentication has a persisted
failure limit.

Import migrations 007, 008 and 009, or the full updated `database/schema.sql`,
after backing up the existing database. These add tables without replacing
student records. Account sign-in remains compatible when the optional security
table is absent; admin writes require the complete new schema. Setup scripts
return 404 over HTTP and are also protected by `.htaccess`.
