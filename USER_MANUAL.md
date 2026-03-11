# User Manual — Work Progress Dashboard

## What Is This System?

This is a file workflow system. It tracks work progress for clients across four processing stages. Files move from Stage 1 through Stage 4, with different team members responsible for uploading and downloading at each stage. The dashboard shows coloured status indicators (LEDs) so everyone can see where things stand at a glance.

**Status indicators:**
- **Grey** — nothing uploaded yet, or waiting
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
| **Client** | The end client — limited access |

---

## Admin

### Can do
- Log in and access all areas
- Create, edit, and manage **clients**
- Create, edit, and manage **accounts** for each client
- Create, edit, and manage **periods** for each client
- View the **dashboard** for all clients and periods
- **Upload** files to any stage (1, 2, 3, or 4)
- **Download** files from any stage
- **Lock** a period (prevents any further uploads)
- **Unlock** a period (re-opens it for uploads)
- Send **bulk reminder emails** to all clients with pending bank statements in one action
- Create and manage **user accounts** (all roles)
- View all **activity logs**

### Cannot do
- There are no restrictions — admin has full access

---

## Processor 0

### Can do
- Log in and view the dashboard
- **Upload** files to **Stage 1** and **Stage 3**
- **Download** files from **Stage 2** and **Stage 4**

### Cannot do
- Upload to Stage 2 or Stage 4
- Download from Stage 1 or Stage 3
- Manage clients, accounts, periods, or users
- Lock or unlock periods
- Send reminders
- View logs

---

## Processor 1

### Can do
- Log in and view the dashboard
- **Upload** files to **Stage 2** and **Stage 4**
- **Download** files from **Stage 1** and **Stage 3**

### Cannot do
- Upload to Stage 1 or Stage 3
- Download from Stage 2 or Stage 4
- Manage clients, accounts, periods, or users
- Lock or unlock periods
- Send reminders
- View logs

---

## Client

### Can do
- Log in and view their **own** periods on the dashboard
- **Upload** files to **Stage 1** (their own accounts only)
- **Download** processed files from Stages 2, 3, and 4 when they are ready
- Reset their password via the forgot-password link

### Cannot do
- View other clients' data
- Upload to any stage other than Stage 1
- Manage accounts, periods, or users
- Lock or unlock periods
- Send reminders
- View logs

---

## Sending Reminders (Admin Only)

The reminder system lets the admin send a single consolidated email to every client who has pending bank statements. It is designed to send **one email per client email address**, even if that address is shared across multiple client records.

### How it works

1. On the dashboard, if any clients have pending (grey or orange) Stage 1 items in unlocked periods, a **Send Reminder** button appears in the top-right area, next to the Export .xlsx button.
2. Clicking **Send Reminder** opens a confirmation popup listing every client (and their email address) who will receive a reminder.
3. After reviewing the list, click **Send Reminders** to dispatch the emails, or click **Cancel** (or the ✕ icon) to close without sending.
4. Each email covers all pending periods and accounts for that client.

### Last Reminder column

The dashboard table includes a **Last Reminder** column (visible to admins only). It shows the date the most recent reminder was sent to each client in `mm/dd/yyyy` format. If no reminder has ever been sent, it shows `—`.

### Notes

- The **Reminder** column is hidden from Processors and Clients — they cannot see it at all.
- Locked periods are excluded — no reminder is sent for a period that has been locked.
- If the same email address belongs to more than one client record, only one email is sent covering all of them.

---

## General Rules (All Users)

- **Locked periods** — no one can upload to a locked period until an admin unlocks it
- **Re-uploading a stage clears later stages** — if you upload to Stage 1, any files already in Stages 2, 3, and 4 for that period are removed and their statuses reset to grey
- **Downloading a green stage turns it orange** — downloading files marks them as consumed
- **Account lockout** — after 3 failed login attempts, the account is locked for 1 hour
- **Password reset** — use the "Forgot password?" link on the login page if you are locked out or have forgotten your password

---

## Typical Workflow (in order)

1. **Client/Processor 0** uploads their files to Stage 1
2. **Processor 1** downloads from Stage 1 and uploads their output to Stage 2
3. **Processor 0** downloads from Stage 2 and uploads their output to Stage 3
4. **Processor 1** downloads from Stage 3 and uploads their output to Stage 4
5. **Client/Processor 0** downloads the final output from Stage 4
6. **Admin** locks the period once all stages are complete (all orange)


