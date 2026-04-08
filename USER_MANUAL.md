# User Manual — Work Progress Dashboard

## What Is This System?

This is a file workflow system built for TaxCheapo Bookkeeping. It tracks work progress for clients across four processing stages. Files move from Stage 1 through Stage 4, with different team members responsible for uploading and downloading at each stage. The dashboard shows coloured status indicators (LEDs) so everyone can see where things stand at a glance.

**Stage names:**

| Stage | Name |
|---|---|
| Stage 1 | Initial Upload |
| Stage 2 | Processing |
| Stage 3 | Reclassification |
| Stage 4 | Reclassification Complete |

**Status indicators (LEDs):**
- **Grey** — nothing uploaded yet, or waiting for action
- **Green** — files are uploaded and ready to be picked up
- **Orange** — files have been downloaded and processed

---

## Roles

There are four types of users in the system:

| Role | Who they are |
|---|---|
| **Admin** | System manager — full access to everything |
| **Processor 0** | Handles Stage 1 and Stage 3 work |
| **Processor 1** | Handles Stage 2 and Stage 4 work |
| **Client** | The end client — limited self-service access |

---

## Admin

### Can do
- Log in and access all areas
- Create, edit, and manage **clients** (including assigning processors)
- Create, edit, and manage **accounts** for each client (manual or automatic bank feed mode)
- Create, edit, and manage **periods** for each client (manual, monthly range, or fiscal year)
- View the **dashboard** for all clients and periods
- **Upload** files to any stage (1, 2, 3, or 4)
- **Download** files from any stage
- **Lock** a period (prevents any further uploads) — all stages must be orange
- **Unlock** a period (re-opens it for uploads)
- Send **individual reminder emails** per period
- Send **bulk reminder emails** to selected clients with pending bank statements
- Create and manage **user accounts** (all roles)
- View and clear all **activity logs** (with filtering by action type)
- Access the **Pending Work** page showing work queues across all processors
- Access the **Settings** page to delegate permissions and configure email reports
- Export the dashboard to **Excel (.xlsx)** with colour-coded status cells
- Add **stage notes** (chat-style comments) to any stage

### Cannot do
- There are no restrictions — admin has full access

---

## Processor 0

### Can do
- Log in and view the dashboard (only clients assigned to them)
- **Upload** files to **Stage 1** and **Stage 3**
- **Download** files from **Stage 2** and **Stage 4**
- View the **Pending Work** page (their own pending items)
- Add **stage notes** to any stage
- Manage **clients, accounts, and periods** — only if the admin has granted them the "Client Creation / Editing" permission in Settings
- Send **reminder emails** — only if the admin has granted them the "Send Reminder Emails" permission in Settings

### Cannot do
- Upload to Stage 2 or Stage 4
- Download from Stage 1 or Stage 3
- Manage users
- Lock or unlock periods
- View logs
- Access Settings

---

## Processor 1

### Can do
- Log in and view the dashboard (only clients assigned to them)
- **Upload** files to **Stage 2** and **Stage 4**
- **Download** files from **Stage 1** and **Stage 3**
- View the **Pending Work** page (their own pending items)
- Add **stage notes** to any stage
- Manage **clients, accounts, and periods** — only if the admin has granted them the "Client Creation / Editing" permission in Settings
- Send **reminder emails** — only if the admin has granted them the "Send Reminder Emails" permission in Settings

### Cannot do
- Upload to Stage 1 or Stage 3
- Download from Stage 2 or Stage 4
- Manage users
- Lock or unlock periods
- View logs
- Access Settings

---

## Client

### Can do
- Log in and view their **own** periods on the dashboard
- **Upload** files to **Stage 1** (their own accounts only)
- **Download** files from **Stage 1** (their own), **Stage 2**, **Stage 3**, and **Stage 4** when they are ready
- Add **stage notes** to any stage on their own periods
- Reset their password via the "Forgot password?" link

### Cannot do
- View other clients' data
- Upload to any stage other than Stage 1
- Manage clients, accounts, periods, or users
- Lock or unlock periods
- Send reminders
- View logs, Pending Work, or Settings

### Multi-client email linking

If the same email address is used on multiple client records, all of those clients are linked to a single login. The client can see all their linked clients' periods on the dashboard.

---

## Client Management (Admin, or delegated processors)

### Creating a client

1. Navigate to **Clients** from the sidebar.
2. Fill in the form:
   - **Name** — the client's display name (required)
   - **Email** — the client's email address (required). Used for reminders and client login.
   - **Phone** — optional
   - **Cycle Type** — `Monthly` or `Yearly`. This controls what period generation options are available later.
   - **Password** — optional. If set, the client can log in using this email and password.
   - **Processor 0** — assign a Processor 0 user to this client (optional)
   - **Processor 1** — assign a Processor 1 user to this client (optional)
3. Click **Save**. After creation, you are redirected to the Accounts page to add accounts.

### Editing a client

Click the **Edit** button next to any client. The form is pre-filled with existing data. Change the password field only if you want to update it — leave blank to keep the current password.

### Deleting a client

Click the **Delete** button. This permanently removes the client and all associated data.

---

## Account Management (Admin, or delegated processors)

Each client has one or more bank accounts. Every account appears as a separate row in Stage 1 on the dashboard.

### Creating an account

1. From the client list, click **Accounts** for the desired client.
2. Fill in:
   - **Account Name** — e.g. "Business Checking", "Savings" (required)
   - **Bank Feed Mode**:
     - **Manual** (default) — Stage 1 starts as grey. The client or Processor 0 must upload files.
     - **Automatic** — Stage 1 starts as orange (indicating bank feeds are automatically pulled). No manual upload is needed for Stage 1.
3. Click **Save**. After creation, you are redirected to the Periods page.

### Editing an account

Click **Edit** next to the account. You can change the name, bank feed mode, and active status.

### Active / Inactive accounts

Accounts have an **active** flag. Deactivating an account hides it from the dashboard. You can reactivate it later by editing.

### Deleting an account

Click **Delete** to permanently remove the account.

---

## Period Management (Admin, or delegated processors)

Periods represent time intervals (months or fiscal years) for which work must be completed. Each period creates status tracking rows for all of the client's active accounts (Stage 1) and for Stages 2–4.

### Creating periods manually

1. From the client list, click **Periods** for the desired client.
2. Enter a **Period Label** (e.g. `Jan 26`, `FY 26`) and click **Save**.

### Generating periods automatically

Use the generation form on the Periods page:

- **Monthly Range** — select a start year and end year (2026–2030). All 12 months per year are created (e.g. `Jan 26`, `Feb 26`, … `Dec 30`). Duplicates are skipped automatically.
- **Fiscal Year** — select a fiscal year label (FY 24 through FY 28). Creates a single period with that label.

**Note:** At least one account must exist before periods can be created. If no accounts exist, the system redirects you to create one first.

### Period visibility

- **Monthly periods** (e.g. `Jan 26`) only appear on the dashboard up to the current month. Future months are hidden automatically.
- **Fiscal year periods** (e.g. `FY 26`) appear up to the current year.
- **Custom labels** are always visible.

### Deleting a period

Click **Delete** next to the period. This permanently removes the period and all associated stage statuses and files.

---

## Dashboard

The dashboard is the main view for all users. It shows a table of periods grouped by client with status LEDs for all four stages.

### Columns

| Column | Visible to |
|---|---|
| Client name | All roles |
| Period label | All roles |
| Account name (Stage 1) | All roles |
| Stage 1 LED | All roles |
| Stage 2 LED | All roles |
| Stage 3 LED | All roles |
| Stage 4 LED | All roles |
| Lock / Unlock button | Admin only |
| Last Reminder date | Admin and users with reminder permission |

### Filtering

- **Clients:** Processors see only clients assigned to them. Clients see only their own data. Admins see all.
- **Periods:** Only periods up to the current month/year are shown. Periods in the future are hidden.
- **Inactive accounts** are hidden from the dashboard.

### Pagination

The dashboard paginates by client (10 clients per page). Use the page controls at the bottom to navigate.

### Status LED behaviour

| Colour | Meaning | Trigger |
|---|---|---|
| Grey | Waiting / no files uploaded | Default state, or reset by upstream re-upload |
| Green | Files uploaded and ready for pickup | Set when files are uploaded to that stage |
| Orange | Files downloaded / processed | Set when files are downloaded from a green stage |

### Automatic bank feed accounts

For accounts in **automatic** bank feed mode, Stage 1 starts as **orange** instead of grey. The `(auto)` label appears next to the account name. No manual upload is required for Stage 1 on these accounts.

### Upload

- Click the **upload area** on the appropriate stage LED.
- Select one or more files. Multiple files can be uploaded at once.
- Upload progress is shown with a progress indicator.
- On success, the LED turns green.
- If files already exist for that stage, a **re-upload confirmation** prompt appears warning that existing files will be replaced and downstream stages will be reset.

### Download

- Click the **download button** on a green stage LED.
- **Single file:** downloads directly with the filename prefixed by the client name.
- **Multiple files:** a ZIP archive is created and downloaded. The ZIP filename includes the client name, stage, and period.
- On download, the LED changes from green to orange.

### Viewing uploaded files

Click any **green** or **orange** stage LED to open a popup showing all files uploaded for that stage. Each file entry shows:

- File name
- Upload date
- Who uploaded it
- A **View** button (eye icon) — if the file type supports in-browser preview

### File preview

Clicking the **View** button opens a **fullscreen preview** of the file without downloading it.

**Supported file types for preview:**

| Type | Extensions |
|---|---|
| PDF | `.pdf` |
| Images | `.jpg`, `.jpeg`, `.png`, `.gif`, `.webp`, `.svg`, `.bmp` |
| Text | `.txt`, `.csv`, `.log` |
| Markup / Data | `.xml`, `.json`, `.htm`, `.html` |
| Excel | `.xlsx`, `.xls` |

- **PDF files** are displayed inline in the browser.
- **Images** are displayed centred and scaled to fit the screen.
- **Text files** are shown in a scrollable text area.
- **Excel files** are rendered as HTML tables. If the file has multiple sheets, tabs appear at the top to switch between them.
- **All other file types** (e.g. `.doc`, `.docx`, `.ppt`, `.zip`) do not show a View button and can only be downloaded.
- Preview is available to **all logged-in users**. Clients can only preview files on their own periods.

### Re-upload behaviour

Re-uploading to a stage clears all downstream stages:

| Re-upload to | Clears |
|---|---|
| Stage 1 | Stages 2, 3, and 4 |
| Stage 2 | Stages 3 and 4 |
| Stage 3 | Stage 4 |
| Stage 4 | Nothing |

Cleared stages have their files deleted and LEDs reset to grey.

### Export to Excel (.xlsx)

Click the **Export .xlsx** button at the top of the dashboard. This downloads a spreadsheet containing:
- Client, Period, Account, Stage 1–4 status, and Lock status columns
- Stage status cells use coloured dot symbols (●) matching the LED colours (grey, green, orange, red)
- Locked periods show a 🔒 symbol

---

## Documents

The Documents screen is a separate file storage area for each client. Use it for general documents (e.g. engagement letters, tax returns, reference files) that are not tied to a specific period or stage.

### Accessing the Documents page

Click **Documents** in the sidebar. The page shows one row per client.

### Who sees what

| Role | Visible clients |
|---|---|
| Admin | All clients |
| Processor 0 / Processor 1 | Only clients assigned to them |
| Client | Only their own client(s) |

### Status LED

- **Green LED** — documents exist. Click the LED to view the file list.
- **Grey LED** — no documents uploaded yet.

### Uploading documents

1. Click the **upload icon** (cloud up) next to the client name.
2. A confirmation prompt appears. Click OK.
3. Select one or more files from your computer.
4. A progress bar shows the upload percentage.
5. On success, the page refreshes and the LED turns green.

**Notes:**
- Multiple files can be uploaded at once.
- If a file with the same name already exists, the new file is automatically renamed (e.g. `report_1.pdf`).
- File size is limited by the server settings.

### Viewing uploaded documents

Click the **green LED** to open a popup listing all documents for that client. The list shows:

| Column | Description |
|---|---|
| # | Row number |
| File Name | Original filename |
| Uploaded | Date and time of upload |
| By | Name of the user who uploaded the file |

### Downloading documents

1. Click the **download icon** (cloud down) next to the client name.
2. A popup shows all documents with checkboxes. All files are selected by default.
3. Use the **Select All** checkbox or select individual files.
4. Click **Download Selected**.
5. **Single file** — downloads directly. **Multiple files** — downloads as a ZIP archive.

---

## Stage Notes

Every stage cell on the dashboard supports a **chat-style notes thread**. Notes are visible to all users who can see that period.

### Adding a note

1. Click the **note icon** on any stage cell in the dashboard.
2. A popup appears showing the existing conversation thread.
3. Type a message (up to 1,000 characters) and click **Send**.
4. The note appears in the thread with your name and timestamp.

### Who can add notes

All logged-in users (Admin, Processor 0, Processor 1, Client) can add notes to stages they can see.

### Note indicators

A stage cell with existing notes shows a small **note badge** icon so you can see at a glance that comments exist.

### Notes in the daily digest

Stage notes from the previous day are included in the daily digest email sent to processors. See the **Daily Digest** section below.

---

## Locking and Unlocking Periods (Admin Only)

### Locking a period

1. On the dashboard, click the **Lock** button for the period.
2. The system checks that **all stage LEDs are orange** (all stages completed). If any stage is not orange, locking is rejected with an error message.
3. Once locked, a 🔒 icon appears and all upload buttons are disabled for that period.

### Unlocking a period

Click the **Unlock** button on a locked period. Uploads are re-enabled.

### Effects of locking

- No user (including admin) can upload files to a locked period.
- Locked periods are excluded from reminder emails.
- Locked periods still appear on the dashboard and can still be downloaded from.

---

## Reminder System

The reminder system sends emails to clients about pending bank statement uploads. Only **past** periods (before the current month) with **grey** Stage 1 statuses in **unlocked** periods are considered pending.

### Individual reminders

On the dashboard, each period row may have a **Send Reminder** button. Clicking it sends a single reminder email to the client's email address. The email covers all pending periods and accounts for that client (including any other clients sharing the same email address).

### Bulk reminders

1. Click the **Send Reminder** button in the toolbar area of the dashboard.
2. A modal popup shows a checklist of all clients who have pending bank statements, grouped by email address.
3. Select or deselect individual clients.
4. Click **Send Reminders** to dispatch one email per unique email address.
5. Each email lists all pending accounts and periods for that email's associated clients.

### Reminder email content

Each reminder email includes:
- Client name(s)
- Account names with pending status
- Period labels for each pending account
- A request to send the required files

### Last Reminder tracking

The dashboard shows a **Last Reminder** column with the date of the most recent reminder sent to each client in `mm/dd/yyyy` format. If no reminder has been sent, it shows `—`.

### Who can send reminders

- **Admin** — always
- **Processors** — only if the admin has granted them the "Send Reminder Emails" permission in Settings

---

## Pending Work Page

The Pending Work page provides a grid-based overview of outstanding work for processors. It is accessible from the sidebar.

### What it shows

Work items that are waiting to be actioned — i.e., stages where the previous stage is complete but the current one has not been started.

### Admin view

Admins see a card for **each processor** showing their pending items:
- **Processor 0 cards** show:
  - Stage 1 — accounts with grey status (initial upload still needed), with account names and bank feed mode
  - Stage 3 — periods where Stage 2 is complete but Stage 3 is not started
- **Processor 1 cards** show:
  - Stage 2 — periods where all Stage 1 accounts are non-grey but Stage 2 is grey
  - Stage 4 — periods where Stage 3 is complete but Stage 4 is not started

### Processor view

Each processor sees only their own pending items in the same format.

### Clients

Clients do not have access to the Pending Work page.

### Data scope

Only **past periods** (before the current month) are included. Current and future periods are excluded.

---

## Activity Logs (Admin Only)

### Viewing logs

Navigate to **Logs** from the sidebar. The page shows the most recent 200 log entries.

### Filtering

Use the **Action** dropdown filter to show only specific event types.

### Logged events

| Action | Description |
|---|---|
| `login` | User or client logged in |
| `upload` | Files uploaded to a stage |
| `reupload` | Files re-uploaded (replacing existing files) |
| `download` | Files downloaded from a stage |
| `reminder_sent` | Reminder email sent to a client |
| `period_locked` | Period was locked |
| `period_unlocked` | Period was unlocked |

Each log entry records: timestamp, user, action, period, stage, account, IP address, and metadata (file count, filenames, etc.).

### Clearing logs

Click the **Clear All Logs** button to permanently delete all log entries.

---

## Settings (Admin Only)

The Settings page provides system-wide configuration options.

### Client Creation / Editing Permission

Select which processor users (Processor 0 and/or Processor 1) are allowed to create and edit clients, accounts, and periods — in addition to admin. Check the box next to each user to grant them this permission.

### Send Reminder Emails Permission

Select which processor users are allowed to send reminder emails (individual and bulk) — in addition to admin.

### Pending Work Report Email

Enter an email address to receive a **daily pending work report** after the daily digest cron job runs. This report summarises all outstanding work across all processors. Leave blank to disable.

---

## User Management (Admin Only)

### Creating a user

1. Navigate to **Users** from the sidebar.
2. Fill in:
   - **Name** — display name (required)
   - **Email** — login email (required)
   - **Role** — `Admin`, `Processor 0`, or `Processor 1`
   - **Password** — required for new users
3. Click **Save**.

### Editing a user

Click **Edit** next to the user. You can change their name, email, role, and password. Leave the password blank to keep the current one.

### Deleting a user

Click **Delete** next to the user. You cannot delete your own account.

---

## Daily Digest (Cron Job)

A cron script sends consolidated daily email summaries to processors about uploads made during the day.

### How it works

1. Every time a file is uploaded to any stage, a notification is queued in the database.
2. The cron job (`cron/send_daily_digest.php`) runs once daily (recommended: end of business, e.g. 5:00 PM).
3. Each processor receives **one email** covering all uploads for their assigned clients that day.
4. Uploads to automatic bank feed accounts are excluded from notifications.

### Email content

Each digest email includes:
- Client name, period label, stage name, and account name (for Stage 1)
- Who uploaded the files
- Number of files and filenames
- **Stage notes summary** — any notes added the previous day for the processor's assigned clients

### Pending work report

After sending digests, the cron job also sends a **Pending Work Report** email to the address configured in Settings (if set). This report lists all processors' outstanding work items grouped by stage.

### Setup (Windows Task Scheduler)

```
php C:\xampp\htdocs\dash\cron\send_daily_digest.php
```

---

## Authentication & Security

### Login

Users and clients share the same login page. Enter your email and password.

- **Users** (Admin, Processor 0, Processor 1) are authenticated against the `users` table.
- **Clients** are authenticated against the `clients` table. If multiple client records share the same email, they are all linked to one session.

### Post-login redirect

- **Admin** is redirected to the **Pending Work** page after login.
- **All other roles** are redirected to the **Dashboard**.

### Account lockout

After **3 failed login attempts** from the same email + IP address combination, the account is locked for **1 hour**. A countdown message shows how long until the lockout expires.

### Honeypot field

The login form includes a hidden honeypot field to deter automated bots. If filled in, the login is silently rejected.

### CSRF protection

All forms in the system use CSRF tokens. If a token is invalid or expired, the action is rejected.

### Password reset

1. Click the **"Forgot password?"** link on the login page.
2. Enter your email address. If the email is registered (in the users table), a reset link is sent.
3. The reset link is valid for **1 hour**.
4. Click the link and enter a new password.
5. The same message is shown whether the email exists or not (prevents email enumeration).

**Note:** Password reset is available for system users only (Admin, Processor 0, Processor 1). Client passwords must be reset by an admin through the Client edit form.

---

## General Rules (All Users)

- **Locked periods** — no one can upload to a locked period until an admin unlocks it.
- **Re-uploading a stage clears later stages** — if you upload to Stage 1, any files already in Stages 2, 3, and 4 for that period are removed and their statuses reset to grey.
- **Downloading a green stage turns it orange** — downloading files marks them as consumed.
- **Account lockout** — after 3 failed login attempts, the account is locked for 1 hour.
- **Password reset** — use the "Forgot password?" link on the login page if you are locked out or have forgotten your password (users only).
- **File size limits** — uploads are limited by the server's `post_max_size` and `upload_max_filesize` PHP settings. If an upload exceeds these limits, an error message is shown.
- **Locking requires all-orange** — a period can only be locked when every stage LED is orange.
- **Automatic bank feed accounts** skip manual Stage 1 upload — their Stage 1 starts as orange.
- **Period visibility** — future months/years are hidden from the dashboard automatically.
- **Reminders only for past periods** — only periods before the current month are included in reminder emails.

---

## Typical Workflow (in order)

1. **Admin** creates a client, assigns processors, creates accounts, and generates periods.
2. **Client or Processor 0** uploads their bank statement files to **Stage 1** (Initial Upload). Automatic bank feed accounts skip this step.
3. **Processor 1** sees Stage 1 turn green, downloads the files, processes them, and uploads the output to **Stage 2** (Processing).
4. **Processor 0** sees Stage 2 turn green, downloads the files, reclassifies them, and uploads the output to **Stage 3** (Reclassification).
5. **Processor 1** sees Stage 3 turn green, downloads the files, completes the reclassification, and uploads the output to **Stage 4** (Reclassification Complete).
6. **Client or Processor 0** downloads the final output from **Stage 4**.
7. **Admin** locks the period once all stages are complete (all LEDs are orange).

### Using the Pending Work page

Processors can check the **Pending Work** page at any time to see which items are waiting for them. Admins can use this page to monitor all processors' workloads.

### Using reminders

If clients have not uploaded their bank statements for past periods, the admin (or a delegated processor) can send individual or bulk reminder emails from the dashboard.


