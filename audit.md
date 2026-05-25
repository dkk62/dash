# Work Progress Dashboard — Comprehensive Codebase Audit
**Author:** AI Audit Agent  
**Date:** May 25, 2026  
**Target Codebase:** Work Progress Dashboard (PHP MVC/Procedural Application)

---

## 1. Executive Summary

A comprehensive technical audit of the codebase located at `c:\xampp\htdocs\dash` was conducted to identify security vulnerabilities, logical bugs, navigation/flow inconsistencies, and potential UI/UX experience flaws. 

The audit revealed **one critical security vulnerability**, **two functional/logical bugs**, and **several major UX/UI navigational flow issues**. 

### Severity Matrix Summary

| ID | Finding / Issue | Severity | Component | Impact |
|:---|:---|:---|:---|:---|
| **01** | [CSRF Bypass in Log Clearing](#01-csrf-bypass-in-log-clearing) | **CRITICAL** | Security / Auth | Allows attackers to wipe entire system activity logs via malicious cross-site POST requests. |
| **02** | [Hardcoded Domain Names in Automated Emails](#02-hardcoded-domain-names-in-automated-emails) | **HIGH** | Config / Welcome Flow | Renders links in client welcome emails and entity invitation emails completely broken on non-production (e.g. local XAMPP) environments. |
| **03** | [Ignored Existing Client Check & Password-Reset Welcomes](#03-ignored-existing-client-check--password-reset-welcomes) | **HIGH** | Welcome Flow / Client Logic | Resets password flow for all existing entities when a multi-entity client is assigned a new organization. Disregards ready-made new entity email logic. |
| **04** | [Criss-Cross Circular Redirection Navigational Bug](#04-criss-cross-circular-redirection-navigational-bug) | **MEDIUM** | UX / Navigation | Creates a disorienting navigation experience where adding a Period jumps to Accounts, and adding an Account jumps to Periods. |
| **05** | [Unhandled DB Unique Constraint PDOException (Duplicate User Email)](#05-unhandled-db-unique-constraint-pdoexception-duplicate-user-email) | **MEDIUM** | Robustness / User Admin | Causes a database crash screen (500 Error) when an administrator creates a user with a duplicate email address. |
| **06** | [Inconsistent Log Filtering UI Options](#06-inconsistent-log-filtering-ui-options) | **LOW** | UX / Admin Logs | Admins are unable to filter activity logs by `file_delete` or `upload_single` actions in the user interface. |
| **07** | [Case-Sensitive AJAX Request Headers Check](#07-case-sensitive-ajax-request-headers-check) | **LOW** | Browser Compatibility | Case-sensitive string matching against Request headers (`'XMLHttpRequest'`) may fail on certain browsers or proxy set-ups. |

---

## 2. Comprehensive Audit Findings & Recommended Fixes

### 01: CSRF Bypass in Log Clearing
* **Severity:** **CRITICAL**
* **File Affected:** [LogController.php](file:///c:/xampp/htdocs/dash/controllers/LogController.php#L8) (Line 8)
* **Status:** Vulnerable

#### Description
In `controllers/LogController.php`, line 8 handles the CSRF validation during log deletion/clearing:
```php
if ($action === 'clear_logs') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('?action=logs');
    }
    verifyCsrf();
    LogModel::clearAll();
    ...
```
However, the shared helper function `verifyCsrf()` defined in `helpers/functions.php` **merely returns a boolean** (`true`/`false`) rather than halting execution or throwing an exception:
```php
function verifyCsrf(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
```
Because the return value of `verifyCsrf()` is ignored, there is **no CSRF protection** on the log clearing endpoint. Any authenticated admin user visiting a malicious site can be coerced into triggering a background POST request to `/dash/?action=clear_logs`, completely wiping out all audit and operational logs of the application.

#### Recommended Fix
Modify the call to use `requireCsrf()` instead, which correctly halts execution with a `403 Forbidden` response upon validation failure.

```diff
-     verifyCsrf();
+     requireCsrf();
      LogModel::clearAll();
```

---

### 02: Hardcoded Domain Names in Automated Emails
* **Severity:** **HIGH**
* **File Affected:** [ClientController.php](file:///c:/xampp/htdocs/dash/controllers/ClientController.php#L59) (Lines 59, 120)
* **Status:** Bug

#### Description
In `controllers/ClientController.php`, the automated welcome email (`sendClientWelcomeEmail`) and the new organization email (`sendNewEntityEmail`) have the base URL hardcoded:
```php
$baseUrl  = 'https://dashboard.taxcheapo.com';
```
This entirely bypasses the environment configurations (`.env`'s `APP_BASE_URL` or `APP_FULL_URL`) and breaks core functionality when the system is run in developers' local workspaces (e.g. `c:\xampp\htdocs\dash`), staging systems, or custom domain setups. Any clients receiving the welcome email will click links pointing to the production portal instead of the environment they were created in.

#### Recommended Fix
Update these methods to dynamically resolve the origin URL, matching the secure and robust fallback logic already successfully implemented inside `controllers/PasswordResetController.php`:

```diff
-     $baseUrl  = 'https://dashboard.taxcheapo.com';
+     $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
+     $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
+     $baseUrl = (defined('APP_FULL_URL') && APP_FULL_URL !== '')
+         ? rtrim(APP_FULL_URL, '/')
+         : $scheme . '://' . $host;
+     $baseUrl .= basePath(); // Appends subpath, e.g. /dash
```

---

### 03: Ignored Existing Client Check & Password-Reset Welcomes
* **Severity:** **HIGH**
* **File Affected:** [ClientController.php](file:///c:/xampp/htdocs/dash/controllers/ClientController.php#L37) (Lines 37, 43-44)
* **Status:** Logic Bug

#### Description
The system is built to support multi-entity clients (where one user email address is associated with multiple corporate entities). When a new client record is saved under an already existing email:
1. `ClientController.php` correctly computes `$isExistingEmail = Client::emailExists($email);` (line 37).
2. However, this flag is **completely ignored**. The controller always calls `sendClientWelcomeEmail($name, $email);` (line 44) which forces the client to trigger password reset procedures.
3. The helper function `sendNewEntityEmail($name, $email)` designed specifically to handle adding new organizations/entities to existing users is **completely unused and never called in the entire codebase**.

This leads to a confusing UX flow where a client who already has active portals gets their login credential flow reset and receives a "Set Your Password" email rather than a "New Organization Added" notification.

#### Recommended Fix
Integrate the `$isExistingEmail` check to selectively send the correct transactional email:

```diff
             // Create new - set default password (override via CLIENT_DEFAULT_PASSWORD in .env)
             $defaultPassword = defined('CLIENT_DEFAULT_PASSWORD') ? CLIENT_DEFAULT_PASSWORD : 'Password#2026';
             $newClientId = Client::create($name, $email, $phone, $cycleType, $defaultPassword, $processor0Id, $processor1Id);
 
-            // Send full welcome email
-            sendClientWelcomeEmail($name, $email);
+            // Check if this is a secondary entity for an existing client email
+            if ($isExistingEmail) {
+                sendNewEntityEmail($name, $email);
+            } else {
+                sendClientWelcomeEmail($name, $email);
+            }
```

---

### 04: Criss-Cross Circular Redirection Navigational Bug
* **Severity:** **MEDIUM**
* **Files Affected:** [PeriodController.php](file:///c:/xampp/htdocs/dash/controllers/PeriodController.php) & [AccountController.php](file:///c:/xampp/htdocs/dash/controllers/AccountController.php)
* **Status:** Navigation UX Bug

#### Description
A confusing navigational redirect circle exists across the period and account management screens:
* When an administrator goes to **Manage Periods** for Client X and clicks *Add/Generate Period*, a successful creation redirects them to the **Accounts** screen (`redirect('?action=accounts&client_id=' . $clientId);`).
* When an administrator goes to **Accounts** for Client X and clicks *Add Account*, a successful creation redirects them to the **Periods** screen (`redirect('?action=periods&client_id=' . $clientId);`).

This circular redirection disorients administrators who want to stay on the screen they are currently configuring to verify addition records or perform batch entries.

#### Recommended Fix
Adjust both controllers to redirect to their respective parent views on success:

**In `controllers/PeriodController.php` (Lines 59, 93, 114):**
```diff
- redirect('?action=accounts&client_id=' . $clientId);
+ redirect('?action=periods&client_id=' . $clientId);
```

**In `controllers/AccountController.php` (Line 27):**
```diff
         Account::create($clientId, $name, $bankFeedMode);
-        setFlash('success', 'Account created. Now add periods for this client.');
-        redirect('?action=periods&client_id=' . $clientId);
+        setFlash('success', 'Account created.');
+        redirect('?action=accounts&client_id=' . $clientId);
```

---

### 05: Unhandled DB Unique Constraint PDOException (Duplicate User Email)
* **Severity:** **MEDIUM**
* **File Affected:** [UserController.php](file:///c:/xampp/htdocs/dash/controllers/UserController.php#L44) (Line 44)
* **Status:** Robustness Bug

#### Description
When an admin creates a new user, `UserController.php` directly executes `User::create(...)` without verifying whether the email is already in use. Because the `users` table email column is marked as `UNIQUE`, MariaDB/MySQL throws a `23000 Duplicate Key` error. 

Since `UserController.php` lacks try-catch blocks or pre-checks, this triggers a **fatal database exception** (visible to the admin as a raw 500 error screen) rather than a elegant validation message. (Note: The `ClientController.php` has a try-catch for `RuntimeException` to handle duplicates, but `UserController.php` completely missed this).

#### Recommended Fix
Pre-validate that the email does not exist in the `users` table before calling `User::create`:

```diff
         // Create new user — password required
         if ($password === '') {
             setFlash('danger', 'Password is required when creating a new user.');
             redirect('?action=users');
         }
+        if (User::findByEmail($email)) {
+            setFlash('danger', 'A user with this email address already exists.');
+            redirect('?action=users');
+        }
         User::create($name, $email, $password, $role);
         setFlash('success', 'User created.');
```

---

### 06: Inconsistent Log Filtering UI Options
* **Severity:** **LOW**
* **File Affected:** [logs.php](file:///c:/xampp/htdocs/dash/views/logs.php#L22) (Line 22)
* **Status:** Inconsistency

#### Description
The activity logs system supports logging the actions `'file_delete'` and `'upload_single'`. These entries successfully insert and display in the log list. However, in the admin logs view (`views/logs.php`), the search filters only list a subset of actions:
```php
<?php foreach (['upload','download','reupload','reminder_sent','period_locked','period_unlocked','login'] as $a): ?>
```
Admins are therefore unable to filter logs to see which files were single-uploaded or deleted, which is highly relevant for auditing file-loss issues.

#### Recommended Fix
Include `'file_delete'` and `'upload_single'` in the filter select options array:

```diff
- <?php foreach (['upload','download','reupload','reminder_sent','period_locked','period_unlocked','login'] as $a): ?>
+ <?php foreach (['upload','download','reupload','reminder_sent','period_locked','period_unlocked','login','file_delete','upload_single'] as $a): ?>
```

---

### 07: Case-Sensitive AJAX Request Headers Check
* **Severity:** **LOW**
* **File Affected:** [LockController.php](file:///c:/xampp/htdocs/dash/controllers/LockController.php#L6) (Line 6)
* **Status:** Browser Compatibility Inconsistency

#### Description
In `controllers/LockController.php`, the controller tests for AJAX requests using a strict case-sensitive match:
```php
$isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
```
However, in `controllers/StageController.php` (line 54) and `controllers/DocumentController.php` (line 74), the codebase uses a more robust case-insensitive check:
```php
$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
```
Strict comparison against `'XMLHttpRequest'` can fail in certain browsers, mobile webviews, or backend proxies that normalize HTTP headers, causing the application to return full-page redirects instead of neat JSON replies on lock/unlock actions.

#### Recommended Fix
Align header detection in `LockController.php` with the rest of the application:

```diff
- $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
+ $isAjax = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest');
```

---

## 3. General UI/UX & Quality-of-Life Recommendations

The overall design relies on Bootstrap 5 + Bootstrap Icons, which is functional. However, implementing the following changes would elevate the user experience:

1. **Integrated File Drag-and-Drop Zones:**
   * **Current State:** Users click small cloud icons which trigger a hidden native file upload input.
   * **Suggestion:** Implement a stylized dropzone dashboard section (dashed borders, hover effects) with visual progress feedback. This reduces visual clutter and makes statements ingestion significantly easier.

2. **Dashboard Interactive Animation Feedback:**
   * **Current State:** Clicking a stage LED opens a files modal instantly without transition, and the hover states of dashboard items are static.
   * **Suggestion:** Add subtle micro-animations (e.g., scale transitions on hover, color fades for the status LEDs) and loading placeholders (skeleton loaders) to make the UI feel modern and premium.

3. **Combined Document View for Admins:**
   * **Current State:** Admins click a folder icon to see client documents by date, requiring drilling down into specific dates to find new uploads.
   * **Suggestion:** Add an "All Uploads" chronological feed option so admins can see all documents uploaded across all clients in one unified list, sorted by date, rather than checking folder-by-folder.

---

*This audit document has been successfully created in the project root directory as requested. No files in the codebase have been altered.*
