# AI-Powered Institutional Concern Management System
### with Dynamic Prioritization and Resolution Tracking (ICMS-DPT-RRT)

Capstone project — Surigao del Norte State University, College of Computing & Information Sciences
(Felominos & Tan). Students report campus concerns; a rule-based AI engine scores their urgency and
sets a resolution deadline (SLA); administrators respond, and every step is tracked, archived and reported.

Built with native **PHP 8.2 (PDO)**, **MariaDB / MySQL**, HTML, CSS and vanilla JavaScript, served by
**Apache on XAMPP**. No framework or Composer packages are required.

---

## 1. Quick start (XAMPP on macOS or Windows)

1. Copy the folder to `xamppfiles/htdocs/TrackingSystem` (Windows: `C:\xampp\htdocs\TrackingSystem`).
2. **Raise MySQL's packet size** (attachments are stored in the database). In XAMPP's `my.cnf`
   (Windows: `my.ini`), under `[mysqld]`, set:
   ```
   max_allowed_packet=64M
   ```
   then restart MySQL from the XAMPP control panel.
3. Start **Apache** and **MySQL**.
4. Open **http://localhost/TrackingSystem**. The database `icms_dpt_rrt` is created, migrated and
   seeded automatically on the first visit.
5. macOS only: let Apache (user `daemon`) write uploads and logs:
   ```
   chmod 777 uploads storage
   ```

### Moving the database to another PC
A full export is kept as `icms_dpt_rrt.sql.gz` in the project folder (not in Git; it contains
password hashes). In phpMyAdmin, create the database `icms_dpt_rrt` and **Import** the `.gz` file
directly, with no need to unzip it. It works on both MariaDB (XAMPP) and MySQL.
From the command line:
```
gunzip -c icms_dpt_rrt.sql.gz | mysql --max-allowed-packet=64M -u root icms_dpt_rrt
```
> A dump made on **MySQL 8/9** may use the collation `utf8mb4_0900_ai_ci`, which MariaDB does not
> know (error #1273). Replace it with `utf8mb4_general_ci` before importing.

### Demo accounts

Everyone signs in on the same form; the system detects whether the account is a student or an administrator.

| Role | Sign in with | Password |
|---|---|---|
| Student | `juan.delacruz@student.com` or `SNSU-2022-04189` | `student123` |
| Student | `maria.santos@school.edu` | `student123` |
| Administrator | `admin@school.edu` | `admin123` |
| Department Head | `facilities@snsu.edu.ph` | `admin123` |

Before a demo, re-date the sample data so the SLA states look current:
```
/Applications/XAMPP/xamppfiles/bin/php scripts/refresh_demo_data.php
```

To start over with a clean demo set (27 concerns across every category, priority and status, with
responses, ratings, chat and announcements, each analyzed by the AI), run the reset. It **deletes all
records and every student account except the six demo students**, after saving a backup to `backups/`:
```
/Applications/XAMPP/xamppfiles/bin/php scripts/reset_demo_data.php --yes
```

---

## 2. Features

**Students**
- **Registration in two paths:**
  1. *Find your name.* Search the Registrar's masterlist; your record (college, program, partly
     hidden student number and email) pops up. Confirm your student number, then create a password.
     The account is active immediately and uses the email on record.
  2. *Register manually* if you are not on the list. The account waits for administrator approval.
- One sign-in form for students and staff, forced change of temporary passwords
- **Profile photo** (students and administrators): JPG/PNG up to 8 MB, cropped to a square and saved in the
  database; shown on the profile, top bar, sidebar and the admin Users list
- **Forgot password, three ways:** (1) **recovery code**: a one-time code shown at registration (or made on
  the Profile page) resets the password with no email or administrator; (2) a one-time email link, used only when
  the server can really send mail; (3) an administrator-issued temporary password
- Submit a concern (title, description, optional JPG/PNG/PDF/DOCX evidence up to 20 MB), optionally **anonymous**
- Live **AI prioritization preview** while typing: urgency score, priority, category suggestion and
  **when it should be fixed** (e.g. "Within 3 days")
- "**When Will It Be Fixed?**" card: target date, time left or overdue, and how long similar concerns usually take
- Track status (Pending → In Progress → Resolved), admin responses and the activity timeline
- **Edit** a concern until an administrator responds, or **withdraw** it while it is still Pending
- Rate a resolution (1–5 stars) or **reopen** it within 7 days (max. 3 times)
- **Messages**: real-time chat with the administrators (typing and online indicators, reply, forward,
  edit, unsend, reactions)
- General feedback, announcements, in-app notifications and email updates

**Administrators**
- Dashboard: Rapid Response (Critical) alerts, status counts, **My Queue**, SLA health, open work by category
- Concern queue sorted by priority and deadline, with search and filters (category, status, priority, SLA state, assignee)
- Respond, endorse to a department, change status, reclassify category/priority, **assign** to an administrator
- **Possible duplicate** detection for similar open concerns
- **Automatic SLA escalation**: alerts when a ticket is at risk (≤ 25% of its window left) or breached
- **Student masterlist**: CSV import, add, **edit** (optionally updating the student's account), archive
- Users: approve or reject registrations, password resets, archive/restore accounts
- Announcements (create, edit, urgent notifications), categories, feedback with replies, messages
- Reports with date range: resolution rate, SLA compliance, response/resolution times, satisfaction,
  ISO/IEC 25010 evaluation results (entered from the real survey), CSV export
- **Activity Log** of every account change, password reset, announcement, assignment and archive

**Records are archived, never deleted**
- Announcements, categories, finished concerns (Resolved / Withdrawn), user accounts and masterlist
  entries are **archived** (who and when are recorded) and can be **restored** from each page's Archived view.
- Archived items disappear from everyday lists and from students, but stay in reports and history.
- Rejected registrations are kept in `registration_rejections`; reopening a ticket keeps the old rating on record.

**Security**
- Bcrypt passwords, PDO prepared statements, CSRF tokens on every form, POST-only state changes
- Login lockout (5 failures per account / 20 per IP in 15 minutes), 30-minute idle sign-out
- Masterlist claims: student number must be verified before a password can be set; 5 wrong tries lock it for 15 minutes
- Upload checks by extension **and** MIME type (images are also decoded); attachments are stored as binary data in the
  database (`concern_attachments`) and served only to the owning student or an administrator via `api/attachment.php`
- `config/`, `storage/`, `scripts/`, `backups/`, `uploads/` locked by `.htaccess`
- Role checks on every page and endpoint; audit trail for every ticket change

---

## 3. AI Dynamic Prioritization & SLA

Each concern gets an **urgency score (1–100)**, which is how urgent it is, not a count of reports. The
score sets the priority, the SLA (resolution target) and the Rapid Response alert:

| Score | Priority | Expected to be fixed | Rapid Response alert |
|---|---|---|---|
| ≥ 80 | Critical | within 1 day (24 h) | Yes |
| 60–79 | High | within 2 days (48 h) | — |
| 40–59 | Medium | within 3 days (72 h) | — |
| < 40 | Low | within 5 days (120 h) | — |

Two engines produce the score (`includes/ai_llm.php` chooses):

1. **Google Gemini (language model)**, used when an API key is set in `config/ai.local.php`
   (copy `config/ai.local.example.php`; free key at https://aistudio.google.com/apikey). Gemini reads
   the whole concern in English, Filipino or Cebuano and understands context, negation ("walang
   sunog") and harmless mentions ("fire drill"). It returns a score, the best category and short reasons
   as JSON in a fixed schema. Instructions written inside a concern are ignored (prompt-injection guard).
2. **Rule-based engine** (`includes/ai_engine.php`), used when there is no key, no internet, the free
   quota is used up (a 60-second back-off follows) or Gemini times out or answers badly. It uses
   keyword tiers (English, Tagalog and Cebuano), negation detection and category weights.

Each concern records which engine scored it (`concerns.ai_engine`); the admin ticket page and the
student's live preview show it. Preview results are cached per session, so the score a student sees is
the one saved, and the preview asks the AI only after the student pauses typing.

- Check the setup: `php scripts/test_ai.php`.
- **Privacy:** concern text is sent to Google. On the free tier Google may use prompts to improve its
  models. Say so in the system's privacy notice and in the paper.

The same function powers the live preview (`api/ai_prioritize.php`) and the stored result. SLA states
(On track, At risk, Breached, Met, Missed) are computed in `includes/sla.php`.

---

## 4. Project structure

```
index.php, register.php, forgot_password.php, reset_password.php, change_password.php, logout.php
student/      dashboard, submit_concern, my_concerns, concern_details, messages, feedback, announcements, profile
admin/        dashboard, concerns, concern_details, users, masterlist, messages, announcements, categories,
              feedback, reports, activity, profile
actions/      form handlers (auth, register, register_lookup/claim, concern, response, users, masterlist, ...)
api/          ai_prioritize, attachment, messages, notifications, keepalive (JSON)
includes/     helpers, security (CSRF), ai_engine, sla, sla_monitor, registration, duplicates, anonymity,
              messages/chat, mailer, reports, uploads, layout parts
config/       db.php (connection + auto-migrate), mail.php, ai.php (+ local.php, mail.local.php, ai.local.php; not in Git)
database/     schema.sql, seed.sql, migrations/001–019, migrate.php, demo_data.php, sample_masterlist.csv
scripts/      CLI: backup_database, process_email_queue, send_test_email, sla_monitor, refresh_demo_data,
              reset_demo_data, import_attachments
assets/       css (design system), js, fonts (Inter, bundled for offline use), img (favicons)
storage/      mail.log, monitor timestamps (not web-accessible)
uploads/      demo images only (attachments live in the concern_attachments table)
```

### Database
25 tables.
- **Core:** `students`, `administrators`, `categories`, `statuses`, `concerns`, `concern_attachments`,
  `responses`, `feedback`, `announcements`, `notifications`, `concern_audit_logs`, `admin_activity_logs`
- **Registration:** `student_masterlist`, `registration_rejections`
- **Messaging:** `conversations`, `messages`, `message_reactions`, `message_hidden`, `chat_presence`
- **Support:** `login_attempts`, `password_reset_requests`, `password_reset_tokens`, `email_outbox`,
  `iso_evaluation_results`, `schema_migrations`

Schema changes are numbered SQL files in `database/migrations/`; they are applied automatically on the
next page load or with `php database/migrate.php`.

---

## 5. Email notifications

- **Default (log mode, no setup):** emails are written to `storage/mail.log`. Forgot Password uses the
  admin-assisted flow (the admin issues a temporary password).
- **Real email (e.g. Gmail):** enable 2-Step Verification, create an **App Password**, copy
  `config/mail.local.example.php` to `config/mail.local.php` and fill it in. Test with
  `php scripts/send_test_email.php you@example.com`. Forgot Password then sends one-time reset links
  (valid 60 minutes).
- **Who receives mail:** real addresses (e.g. a student's Gmail) get their own emails. Mail for the
  made-up demo addresses (`@school.edu`, `@student.com`, `@example.com`, and those listed in
  `demo_recipients`) goes to `demo_redirect_to` instead, with the intended recipient in the subject.
  Set `redirect_all_to` only if **every** email should go to one inbox.
- Links in emails use `app_url`. With `http://localhost/...` they open only on the PC running XAMPP.
- Emails are queued in `email_outbox` and sent after the page finishes. For background sending, set
  `'send_on_request' => false` and run `scripts/process_email_queue.php` from cron or Task Scheduler.

## 6. Scheduled jobs (optional)

The web app already runs the SLA monitor every 2 minutes while people use it. On a server you can also
schedule it:
```
* * * * *  php /path/to/TrackingSystem/scripts/sla_monitor.php
* * * * *  php /path/to/TrackingSystem/scripts/process_email_queue.php
```

## 7. Database backup

```
/Applications/XAMPP/xamppfiles/bin/php scripts/backup_database.php     # macOS
C:\xampp\php\php.exe scripts\backup_database.php                      # Windows
```
Creates `backups/icms_dpt_rrt_YYYYMMDD_HHMMSS.sql` (all tables and data) using XAMPP's `mysqldump`,
or a built-in PHP exporter if `mysqldump` is unavailable. Restore into an empty database with:
```
mysql --max-allowed-packet=64M -u root icms_dpt_rrt < backups/<file>.sql
```
`backups/` is blocked from the web and excluded from Git because it contains password hashes.

## 8. Automated tests

```
/Applications/XAMPP/xamppfiles/bin/php test_suite.php                 # XAMPP default URL
/Applications/XAMPP/xamppfiles/bin/php test_suite.php http://host/path
```
50 end-to-end tests: authentication, AI engines (Gemini answer handling, rule-based Tagalog/Cebuano and negation), uploads,
CSRF, registration and masterlist claims, lockout, password reset and recovery codes, ratings, reopening, withdrawing,
editing, SLA escalation, assignment, archiving and restoring, masterlist editing, email routing,
reports, CSV export, idle timeout, and a smoke test that opens every page. The suite removes every
record and file it creates. On Windows run `test_system.ps1`.

## 9. Troubleshooting

| Problem | Fix |
|---|---|
| Import fails with **#1273 Unknown collation** | The dump came from MySQL 8/9. Replace `utf8mb4_0900_ai_ci` with `utf8mb4_general_ci`. |
| Import or upload fails with **packet too large** / "MySQL server has gone away" | Set `max_allowed_packet=64M` under `[mysqld]` and restart MySQL. |
| Emails fail with "Cannot write storage/mail.log" (macOS) | `chmod 777 storage` and `chmod 666 storage/*`. Copying the project can reset these permissions. |
| Demo tickets all show **Overdue** | Run `scripts/refresh_demo_data.php`. |

## 10. Before deploying outside XAMPP

Free hosting step by step: see **[DEPLOY-INFINITYFREE.md](DEPLOY-INFINITYFREE.md)**. Server-specific
database and chat settings go in `config/local.php` on the server (copy `config/local.example.php`; not in Git).
Keep the host's copy in `deploy/local.php` and build the upload package with `sh scripts/build_deploy_package.sh`.

- Create a MySQL user with a password and set `DB_USER` / `DB_PASS` environment variables.
- Serve over HTTPS (session cookies switch to `Secure` automatically) and set `app_url` to the real address.
- Replace `chmod 777` with ownership by the web-server user.
- Never commit `config/mail.local.php` or the database dumps.
