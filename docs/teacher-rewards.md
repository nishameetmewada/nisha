# Teacher coaching and profit-share rewards

Teachers apply through `teacher-portal.php`. The owner approves their profile,
then reviews each submitted coaching lesson before it appears in `coaching.php`.
Only an existing account assigned an owner role can approve profiles, publish
videos, activate rewards or close business profit periods. Registering an email
address does not grant an owner or teacher role.

## Owner setup

1. Import `database/migrations/004_commerce.sql`, then
   `database/migrations/005_teachers.sql` into the existing NISHA database. A new
   install uses `database/schema.sql`. Preserve existing accounts and results.
2. Register your own account normally. In phpMyAdmin, read its numeric `users.id`
   privately. Add that existing ID to the `admin_user_ids` array in your private
   `config/config.php`, or, if SSH is available, run this in `public_html`:

   ```sh
   php scripts/owner-role.php grant-admin YOUR_EXISTING_ACCOUNT_ID
   ```

   The script is CLI-only and returns 404 through a browser. It never creates an
   account or prints credentials. A database role can be removed with
   `revoke-admin`; remove any corresponding private allowlist entry too.
3. Sign in to `admin.php`. Review teacher applications and approve only people
   qualified to teach the material. Owner access alone does not certify someone
   as a teacher. The content-review workflow separately requires an approved
   teacher profile before anyone can certify questions as teacher-reviewed.
4. Set exact approved video hostnames in the teacher policy. Teachers supply
   direct HTTPS `.mp4` or `.webm` links from those hosts. You can use your own
   HTTPS video domain or a suitable media CDN. No arbitrary HTML, iframe code,
   server-side remote fetch or executable upload is accepted. A YouTube webpage
   URL is not a direct MP4 file and is not supported by this player.
5. Verify the full lesson content, publishing rights and actual duration before
   publishing. Editing a lesson increments its version and returns it to review.
6. Enable the teacher reward policy when ready to operate it. The default is
   **90% of verified positive profit after business expenses and carried losses**,
   with a **70% unique-watch threshold** and active-paid-student eligibility.
   The owner can adjust the watch threshold, approved hosts and eligibility in
   the policy form; the profit share in that form remains 90%.

The student coaching page exposes playback links only to students with an
active paid access pass, the owner, or the teacher previewing their own lesson.
Free students can browse lesson descriptions and visit pricing. The media
service must enforce any access restrictions you need beyond the application;
this HTML5 player does not provide DRM or prevent copying a public media URL.

## Which views qualify

A student must watch the configured percentage of a published lesson while its
tab is visible. The default requires active paid access. Owner views, approved
teacher views and the creator's own views do not qualify. Time is bounded by
elapsed server time; pauses, large seeks and stale heartbeats grant no time.
The server stores the union of viewed intervals, so replaying an opening section
or restarting a player cannot add the same time repeatedly. Only one active
lesson can receive verified time for an account at once.

Qualification is unique for a student, lesson and UTC calendar month. A changed
lesson starts progress for its new version, but cannot create a second reward
view for that lesson in the same month. Historical qualification is retained
separately from the new version's progress. Watch metadata contains account,
lesson, progress, month and qualification data; no microphone audio or camera
recording is collected by the video player.

These checks reduce basic replay, seek and concurrent-tab inflation. Browser
heartbeats cannot prove attention or eliminate account farms; review unusual
traffic and the business totals before approving a period. No earnings are
guaranteed solely because a browser reports a view.

## Monthly business accounting

The owner enters reconciled totals for a completed UTC calendar month in one
currency: collected revenue from all relevant website/business sales channels,
refunds and **all operating expenses**. Expenses include hosting, OpenAI,
payment fees, staff, content and other actual business costs. Convert any other
currencies using the business's accounting process before entering totals;
the site does not add mixed currencies or invent exchange rates.

The current month's teacher pool is a distribution of the resulting profit.
Do **not** deduct that same pool again as an operating expense in this form.
Previously approved losses carry forward before a future pool can be funded.
Approve months in chronological order; a closed ledger cannot be edited or
awarded twice. This is an owner-verified accounting ledger, not an automatic
audit of bank balances or external invoices.

```
net after losses = collected revenue - refunds - operating expenses - prior losses
reward pool = floor(max(0, net after losses) × 90%)
teacher share = reward pool × teacher qualified views / all eligible qualified views
```

Calculations use integer minor units. Rounding remainder cents are distributed
deterministically without exceeding the pool. Negative net profit produces no
pool and carries the remaining loss into the next approved period. With no
eligible qualified views, no teacher allocations or transfers are created;
the unallocated pool remains with the business.

For example, AUD 100 collected revenue minus AUD 10 refunds, AUD 20 expenses
and AUD 20 prior losses leaves AUD 50. The pool is AUD 45. Teachers with qualified
views in a 2:1 ratio receive AUD 30 and AUD 15, respectively.

## Paying teachers

Approved allocations appear in each teacher's portal. Pay them through the
banking or payout service you choose, then use **Record completed payout** in
`admin.php` with the actual transfer reference and confirmation. This records
an already completed transfer; the application does not initiate money movement,
store bank details or claim that a pending allocation has already been paid.
An allocation cannot be recorded as paid twice. A fully automated payout service
would need a separately configured provider and its recipient onboarding.

## Verification

Run `php tests/teachers-integrity.php` against the prepared local MariaDB admin
wrapper. It creates a disposable database and least-privilege fixture user,
then cleans them up. Checks cover owner-only moderation, safe video URLs,
90% accounting, replay/seek and concurrent playback protection, lesson versions,
unique monthly views, prior losses, frozen periods and payout idempotency.
It makes no external media request and transfers no money.
