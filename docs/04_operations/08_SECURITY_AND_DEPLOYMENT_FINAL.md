# 08_SECURITY_AND_DEPLOYMENT.md

Version: Architecture Freeze v1 (Security Hardened) Status:
Production-Grade (Shared Hosting Compatible)

  --------------------------
  1\. AUTHENTICATION MODEL
  --------------------------

Authentication Method: - PHP session-based authentication.

Session Security Rules: - session_regenerate_id(true) on successful
login. - Full session destruction on logout. - User ID and role stored
in server-side session only. - Role validation enforced at middleware
level before controller execution. - Session timeout: 30 minutes inactivity.
Session Termination Rules:
- On logout:
    - session_unset()
    - session_destroy()
    - Delete session cookie explicitly
- Prevent session fixation or reuse after logout.
Session Cookie Hardening (Production): - session.cookie_httponly =
true - session.cookie_secure = true (HTTPS required) - SameSite=Strict

  --------------------------------------------------
  2\. PASSWORD POLICY (Level B -- Internal System)
  --------------------------------------------------

-   Password stored using password_hash() (bcrypt).
-   Minimum length: 8 characters.
-   No email-based reset in v1.
-   Ops/Admin may manually reset password.
-   Force password change on first login after reset.
Login Attempt Tracking:
- failed_attempt_count stored in users table.
- last_failed_attempt_at timestamp stored in users table.
- Counter resets to 0 on successful login.
- Lock duration enforced server-side.
- All lock/unlock events logged in audit log.
Login Protection: - Maximum 5 failed login attempts. - Account locked
for 15 minutes after threshold. - Failed attempts logged in audit log.

Forced Password Reset:
- Users flagged for forced password reset may log in successfully.
- All protected routes are blocked until password reset is completed.
- Only logout and reset-password routes remain accessible during this state.

  ---------------------
  3\. CSRF PROTECTION
  ---------------------

-   CSRF token generated per session.
-   Token included as hidden field in all POST forms.
-   Token validated server-side before processing request.
-   Invalid token → request rejected immediately.
-   Required for all data mutation endpoints.

---------------------
4\. DATABASE SECURITY RULES
---------------------

- All database queries must use prepared statements (PDO or MySQLi prepared).
- No dynamic SQL concatenation with user input.
- All user inputs validated before database interaction.
- All output escaped at view layer to prevent XSS.
  --------------------------------
  5\. FILE UPLOAD SECURITY MODEL
  --------------------------------

Storage Strategy: - Uploads stored outside public_html.

Access Control: - Files served through secured PHP controller. -
Controller validates session, role, and entity visibility.

Upload Validation: - Allowed extensions: jpg, jpeg, png, webp - Validate
extension AND server-side MIME using finfo. - Reject double
extensions. - Maximum file size: 5MB. - Randomized filename
(hash-based). - Original filename never used.

Folder Permissions: - storage/uploads: 750 or 755 - Never 777.

Logical Storage Structure:

Uploads organized by domain:

- /storage/uploads/belts/
- /storage/uploads/sites/
- /storage/uploads/tasks/
- /storage/uploads/daily_work/

This ensures:
- Clean segregation
- Easier archival
- Future storage management

  ---------------------------
  6\. ERROR HANDLING POLICY
  ---------------------------

Local: - display_errors = ON

Production: - display_errors = OFF - log_errors = ON - Errors written to
secured log file.

  -----------------------------------
  7\. WEB SERVER HARDENING (cPanel)
  -----------------------------------

-   Force HTTPS.
-   Disable directory listing.
-   Protect storage directory.
-   Security headers:
    -   X-Frame-Options: SAMEORIGIN
    -   X-Content-Type-Options: nosniff

  --------------------------
  8\. ENVIRONMENT STRATEGY
  --------------------------

-   Local / Staging / Production separation.
-   .env not committed to Git.
-   Separate DB credentials per environment.
-   Debug only enabled locally.

  -----------------------------
  9\. GIT DEPLOYMENT STRATEGY
  -----------------------------

-   Private GitHub repository.
-   Feature branches → merge → tag → deploy.
-   No direct production edits.
-   Deploy only tagged releases.

  --------------------------------
  10\. SHARED HOSTING LIMITATIONS
  --------------------------------

-   No background workers.
-   No async queues.
-   No containerization.
-   No root-level access.

  --------------------------------

  11\.INPUT VALIDATION POLICY
  --------------------------------

- All POST and GET inputs must be validated server-side.
- Numeric fields cast explicitly.
- Enum fields validated against allowed list.
- Date fields validated for format and range.
- No business logic depends on client-side validation.


------------------------------------------------------------------------

STATUS

Security hardened for shared hosting deployment.
