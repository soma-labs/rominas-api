# Notifications

E-mail notifications for admins and academy members, plus the unread list shown in the management
dashboard. Some are sent when something happens (**on demand**), others by the scheduler
(**scheduled**).

Everything here lives in `app/Modules/Notifications/`. The module uses Laravel's own notification
system: the standard `notifications` table and the `Notifiable` trait that `User` and `Member` already
carry. It adds no model of its own.

## 1. The notifications

| Type (`NotificationType`) | Sent when | Recipients |
| --- | --- | --- |
| `member_proposal_needs_review` | a member proposes a future member (`MemberProposalCreated`) | users with `memberProposals` |
| `member_joined` | an invited member signs in for the first time (`MemberActivated`) | users with `members` |
| `new_fraud_alerts` | a `rominas:detect-fraud` run records at least one **new** alert (`FraudAlertsDetected`) | users with `fraudMonitoring` |
| `daily_statistics` | every day at 08:00, while an edition is in progress | users with `reporting` |
| `nominations_opened` | an edition moves to `nominations_open` (`EditionTransitioned`) | members `invited` or `active` |
| `nomination_deadline_reminder` | every day at 10:00, on the reminder days (§4) | members `invited` or `active` who have not submitted |

The first four are **admin notifications**: an e-mail and an item in the user's list in the dashboard,
in English like the dashboard. The last two are **member notifications**: an e-mail in Romanian, nothing
else.

## 2. Who receives an admin notification

Everyone who can act on it. `Actions/NotifyAdminsWithPermissionAction` loads the admin users and keeps
those for whom `$user->can($permission)` is true — the same check the policies use. So wildcard
permissions count, and a `super_admin` receives every notification (the `Gate::before` bypass).

There are no per-user settings. To stop a user receiving a type, remove the permission.

`fraudMonitoring` is deliberately not granted to `admin` ([access-control.md §2h](access-control.md)),
so fraud notifications reach only the fraud monitors, the custodian and super admins.

## 3. How a notification is sent

Each notification class (`Notifications/`) extends `AdminNotification` or `MemberNotification` and goes
out on two channels:

- **`database`** — one row per recipient in `notifications`. `type` holds the `NotificationType` slug,
  not the PHP class name (`databaseType()`). For an admin, `data` holds `title`, `body` and `url` (a path
  inside the dashboard, e.g. `/member-proposals`), written when the notification is created. For a member
  the row is only a record that the e-mail was sent; no endpoint serves it.
- **`Channels/DeliveryChannel`** — the e-mail. It asks the notification for a `NotificationEmail`
  (subject, Blade view, view data) and sends it through `DeliveryAction` with the action key
  `notification-email`. One payload factory, `Delivery/Factories/NotificationEmailPayloadFactory`, serves
  every type. Notification e-mails therefore use the same transport as every other e-mail.

Notifications are **queued** and wait for the database transaction to commit. The two channels are
separate jobs: if the e-mail fails, its job fails and is retried, and the stored row is unaffected.

Links in admin e-mails are built from **`FRONTEND_ADMIN_URL`** (`config/services.php`
`frontend.admin_url`); member e-mails link to `FRONTEND_ACADEMY_URL`.

The Blade views are in `resources/views/emails/notifications/`: `admin` (any admin notification),
`admin-daily-statistics`, `academy/nominations-opened`, `academy/nomination-reminder`.

## 4. The scheduled notifications

Both are scheduled in `routes/console.php` and follow the **Romanian day**, not UTC
(`config/notifications.php`: `timezone`, the two send times, `nomination_reminder_days`).

**`rominas:daily-statistics`** (08:00). Sends only when an edition is in progress
(`nominations_open` up to `committee_review`). The numbers are the Edition Summary report
([reporting.md](reporting.md)) run twice: over the previous Romanian calendar day and over the whole
edition. "Academy nominations in draft" is a count at the moment of sending and has no per-day value.

**`rominas:nomination-reminders`** (10:00). Sends only while nominations are open, and only when
the number of calendar days left until `nominations_end_at` is one of `nomination_reminder_days`
(default: 3 and 1). It goes to members who have no **submitted** nomination for the edition; a saved
draft still gets the reminder. A member who already has the reminder for that day count is skipped, so
running the command twice sends nothing new, once the queue has processed the first run.

Both commands can be run by hand.

**Checking an e-mail by hand.** `rominas:test-mail {email} {template}` sends one e-mail template, or
`all` of them, with sample data to the given address (it asks for whichever is left out). It covers every
member, voter and admin e-mail, including the invitation with and without a proposer. It sends at once,
without the queue, writes nothing to the database, and prints the mail server's reply when sending
fails. Links carry a placeholder token and do not sign anyone in.

## 5. Endpoints

All under `/api/admin/notifications`, Sanctum-guarded. Every admin user has a list; a user sees and
marks only their own (`Policies/NotificationPolicy`, registered for Laravel's `DatabaseNotification` in
`AuthServiceProvider`).

| Method | Path | Returns |
| --- | --- | --- |
| `GET` | `/` | the user's notifications, newest first, paginated (20). `?unread=1` keeps the unread ones |
| `GET` | `/unread-count` | `{count}` |
| `POST` | `/{notification}/read` | the notification, now read. `403` for another user's |
| `POST` | `/read-all` | `{count}`: how many were marked |

An item: `id` (UUID), `type`, `title`, `body`, `url`, `read_at` (null while unread), `created_at`.

Marking as read is **not audited** (`config/audit.php` `ignore`): it is not a sensitive action.

The dashboard's sidebar entry comes from `config/menus.php` (`sidenav.notifications`, no permission).

## 6. Personal data

A stored admin notification names the member or the proposed person, which its recipients can already
see in the dashboard. It holds no e-mail address. A member's row holds only the edition id and the
reminder's day count.

## 7. Adding a notification

1. Add a case to `Enums/NotificationType`.
2. Add the class in `Notifications/`, extending `AdminNotification` (give it `type()`, `title()`,
   `body()`, `url()`) or `MemberNotification` (give it `type()`, `toArray()`, `toDelivery()` and a view).
   Call `$this->afterCommit()` in the constructor.
3. On demand: fire an event from the owning module, add a listener in `Listeners/`, and wire it in
   `EventServiceProvider`. Scheduled: add an action and a command, register the command in
   `bootstrap/app.php` and schedule it in `routes/console.php`.
4. Test it in `tests/Feature/NotificationsTest.php` with `Notification::fake()`.

Tests never send real e-mail: `tests/TestCase.php` points the `smtp` mailer at the in-memory transport.
