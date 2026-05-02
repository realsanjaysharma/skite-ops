# Skite Ops — Master Test Plan

## How to use this file
Each test case is self-contained. An AI agent runs ONE section per turn:
1. Read the case
2. Execute the steps via browser automation or API
3. Record PASS/FAIL against each assertion
4. Stop — do not bleed into the next case

**Test credentials** (all passwords: `TestPass123!`)

| Role | Email |
|---|---|
| OPS_MANAGER | ops.test.phase2@skite.local |
| HEAD_SUPERVISOR | headsupervisor.phase2@skite.local |
| GREEN_BELT_SUPERVISOR | test.supervisor.p2@skite.local |
| OUTSOURCED_MAINTAINER | test.outsourced.p2@skite.local |
| FABRICATION_LEAD | lead.upload.foundation@skite.local |
| MONITORING_TEAM | monitor.phase3@skite.local |
| AUTHORITY_REPRESENTATIVE | test.authority.p2@skite.local |
| SALES_TEAM | test.sales@skite.local |
| CLIENT_SERVICING | test.clientservicing@skite.local |
| MEDIA_PLANNING | test.mediaplanning@skite.local |

**Base URL:** `http://localhost/skite/public/`
**API base:** `http://localhost/skite/index.php?route=`

---

## BLOCK 1 — Authentication and RBAC

### T01 — Login and landing per role
For each role, login and verify the landing page is correct.

| Role | Expected landing page title |
|---|---|
| OPS_MANAGER | Master Dashboard |
| HEAD_SUPERVISOR | Watering Oversight |
| GREEN_BELT_SUPERVISOR | Supervisor Upload |
| OUTSOURCED_MAINTAINER | Outsourced Upload |
| FABRICATION_LEAD | My Tasks |
| MONITORING_TEAM | Monitoring Upload |
| AUTHORITY_REPRESENTATIVE | Authority View |
| SALES_TEAM | Task Progress |
| CLIENT_SERVICING | Task Progress |
| MEDIA_PLANNING | Task Progress |

**Assertions:**
- Page loads without error panel
- Sidebar shows only role-appropriate sections
- User name and role badge visible in top bar
- Logout works and redirects to login screen

### T02 — Sidebar scope per role
Login as each role. Confirm sidebar items match expected modules.

| Role | Should see | Should NOT see |
|---|---|---|
| GREEN_BELT_SUPERVISOR | Supervisor Upload, My Uploads | Issues list, Labour, Attendance, Users, Tasks |
| HEAD_SUPERVISOR | Watering Oversight, Attendance, Labour, Issues, Maintenance Cycles | Campaigns, Sites, Tasks, Users |
| FABRICATION_LEAD | My Tasks, Worker Daily Entry, Workers | Users, Belts, Issues, Campaigns |
| MONITORING_TEAM | Monitoring Upload, Monitoring History | Green Belts, Tasks, Users, Authority View |
| AUTHORITY_REPRESENTATIVE | Authority View | Everything else |
| SALES_TEAM | Task Progress, Task Requests, Client Media Library | Green Belts, Users, Upload Review |

### T03 — Direct URL access blocked
While logged in as GREEN_BELT_SUPERVISOR, manually navigate to a module outside their scope (e.g. User Management).

**Assertions:**
- System shows "not available for your role" toast OR does not navigate
- API call to that module's backend route returns 403

### T04 — Session expiry and re-login
Login, wait for session to expire (or manually clear PHP session), then attempt a page navigation.

**Assertions:**
- App redirects to login screen
- No data from previous session leaks

---

## BLOCK 2 — Green Belt Core

### T05 — Create a Green Belt (Ops)
Login as OPS_MANAGER. Open Green Belts → New Belt.

Fields: belt_code=GB-TEST-01, common_name=Test Belt Sector18, zone=Sector18, maintenance_mode=MAINTAINED, permission_status=AGREEMENT_SIGNED, is_hidden=No

**Assertions:**
- Belt appears in list with correct values
- belt_code is immutable (edit form does not show belt_code field)
- `belt/list` API returns the new belt

### T06 — Assign Supervisor to Belt (Ops)
Open the belt created in T05. Click Assign Supervisor.
Enter the user_id for GREEN_BELT_SUPERVISOR (id=9).

**Assertions:**
- Assignment appears in Supervisors panel with start_date
- `supervisorassignment/list?belt_id=X` returns the assignment

### T07 — Assign Authority Rep to Belt (Ops)
Same belt. Assign AUTHORITY_REPRESENTATIVE (id=5).

**Assertions:**
- Assignment appears in Authorities panel
- `authorityassignment/list?belt_id=X` returns the assignment

### T08 — Start Maintenance Cycle (Ops)
Open belt T05. Click Start Cycle. Enter start_date = today.

**Assertions:**
- Cycle appears in Maintenance Cycles panel with status ACTIVE
- Cannot start a second cycle while one is active (should show error)

---

## BLOCK 3 — Field Operations (Green Belt Supervisor)

### T09 — Supervisor Upload (work photo)
Login as GREEN_BELT_SUPERVISOR. Open Supervisor Upload.

Select the belt assigned in T06. Upload a test image as upload_type=WORK.

**Assertions:**
- Upload appears in My Uploads
- authority_visibility is NOT shown in My Uploads (field should be hidden from supervisor view)
- `upload/my-list` returns the upload
- Upload is visible to Ops in Upload Review queue

### T10 — Supervisor self-delete within window
Immediately after T09, open My Uploads. Click delete on the just-uploaded item.

**Assertions:**
- Delete succeeds (within 5-minute window)
- Upload disappears from list

### T11 — Supervisor self-delete outside window
Upload a photo (repeat T09). Wait 5+ minutes or simulate expired window. Attempt delete.

**Assertions:**
- Delete is blocked with an error message
- Upload remains in list

### T12 — Supervisor cannot upload to unassigned belt
Login as GREEN_BELT_SUPERVISOR. In Supervisor Upload, check the belt dropdown.

**Assertions:**
- Only assigned belts appear in the belt selection
- Submitting with a belt_id not in assignments returns 403 or validation error

---

## BLOCK 4 — Head Supervisor Operations

### T13 — Mark Watering (Head Supervisor)
Login as HEAD_SUPERVISOR. Open Watering Oversight. Set date = today.

Click Mark Watering. Select any maintained belt. Status = DONE.

**Assertions:**
- Watering record appears in the oversight table with status DONE
- `watering/list?date=today` returns the record
- `PENDING` is derived (belts without a record show as pending, not stored)

### T14 — Watering correction requires override_reason
On the same belt+date from T13, try to mark again with status = NOT_REQUIRED (no override_reason).

**Assertions:**
- System rejects with "Correction requires an override reason"

Retry with override_reason filled.

**Assertions:**
- Record updates to NOT_REQUIRED
- override_by_user_id is set in the record

### T15 — Mark Supervisor Attendance
Open Attendance page. Mark GREEN_BELT_SUPERVISOR as PRESENT for today.

**Assertions:**
- Attendance record appears in list
- `attendance/list?date=today` returns record

### T16 — Enter Labour Counts
Open Labour Entries. Enter for any belt: labour_count=5, gardener_count=1, night_guard_count=2.

**Assertions:**
- Record appears with correct counts
- Fields are labour_count, gardener_count, night_guard_count (NOT male/female)

### T17 — Move Issue to In Progress
If any OPEN issue exists, Head Supervisor opens Issues and moves it to IN_PROGRESS.

**Assertions:**
- Status changes to IN_PROGRESS
- Head Supervisor cannot close the issue (Close button absent or blocked)

---

## BLOCK 5 — Upload Review (Ops)

### T18 — Ops approves a work upload
Login as OPS_MANAGER. Open Upload Review. Find the upload from T09 (if not deleted) or upload a new photo as supervisor first.

Click Approve on a WORK upload.

**Assertions:**
- authority_visibility changes to APPROVED
- Upload disappears from "HIDDEN/PENDING" filter, appears in "APPROVED" filter
- `upload/list?authority_visibility=APPROVED` includes this upload

### T19 — Ops cannot approve an ISSUE upload
In Upload Review, find or create an upload with upload_type=ISSUE.

**Assertions:**
- Approve button is disabled or absent for ISSUE uploads
- API call to approve an ISSUE upload returns 400 or 403

### T20 — Bulk approve
Select multiple WORK uploads via checkboxes. Click Bulk Approve.

**Assertions:**
- All selected uploads become APPROVED
- ISSUE uploads and already-reviewed uploads are not selectable

### T21 — Reject upload with reason
Click Reject on a WORK upload. Enter rejection reason.

**Assertions:**
- authority_visibility changes to REJECTED
- Upload appears in Rejected Cleanup view after threshold days

---

## BLOCK 6 — Authority View

### T22 — Authority Rep sees only assigned belts
Login as AUTHORITY_REPRESENTATIVE. Open Authority View.

**Assertions:**
- Only belts assigned to this authority rep appear
- No HIDDEN, REJECTED, or NOT_ELIGIBLE uploads visible
- No ISSUE uploads visible
- Only APPROVED WORK uploads visible

### T23 — Authority Rep filters work correctly
Apply filters: date range, belt selection, work type.

**Assertions:**
- Results match filter criteria
- Download button produces a file with only filtered results
- WhatsApp helper button generates share text (if setting enabled)

### T24 — Authority Rep cannot modify anything
Check that no approve/reject/edit/delete buttons exist anywhere in the Authority View.

---

## BLOCK 7 — Issue Lifecycle

### T25 — Create issue (field or Ops)
Login as OPS_MANAGER. Open Issues → create a new DAMAGE issue on any belt with MEDIUM priority.

**Assertions:**
- Issue appears with IS-XXXXX sequence ID
- Status is OPEN

### T26 — Full issue lifecycle
Move the issue through: OPEN → IN_PROGRESS (Head Supervisor or Ops) → Link Task → Close (Ops only).

**Assertions:**
- Status transitions happen correctly
- Closing issue does NOT auto-close the linked task
- Head Supervisor cannot close the issue directly

---

## BLOCK 8 — Request → Task → Execution

### T27 — Commercial role raises a request
Login as SALES_TEAM. Open Task Requests. Submit a new request:
request_type = FABRICATION, description = "New board install at Sector 18"

**Assertions:**
- Request appears with RQ-XXXXX sequence ID, status SUBMITTED
- Ops can see it in their request queue

### T28 — Ops approves and converts to task
Login as OPS_MANAGER. Open Task Requests. Find T27 request. Approve it.
Then open Task Management → Create Task from the approved request.

**Assertions:**
- Request status becomes CONVERTED
- Task appears with linked request_id
- Task status is OPEN

### T29 — Fabrication Lead starts and completes task
Login as FABRICATION_LEAD. Open My Tasks. Find the task from T28.

1. Click Start → status should become RUNNING
2. Click Update Progress → set to 50%
3. Upload an AFTER_WORK photo (browser upload)
4. Click Mark Done

**Assertions:**
- Start changes status to RUNNING
- Progress updates are saved
- Mark Done blocked if no AFTER_WORK photo exists
- Mark Done succeeds after AFTER_WORK upload
- Ops sees task as WORK_DONE and can final-approve

### T30 — Commercial role sees task progress (read-only)
Login as SALES_TEAM. Open Task Progress. Find the task from T28.

**Assertions:**
- Task is visible with progress_percent
- No Start, Assign, Approve, or Edit buttons visible
- Cannot navigate to task execution controls

---

## BLOCK 9 — Outsourced Flow

### T31 — Outsourced Maintainer scope
Login as OUTSOURCED_MAINTAINER.

**Assertions:**
- Only outsourced-assigned belts appear in the upload surface
- No watering, attendance, or labour controls visible
- Upload goes through correctly for assigned outsourced belt
- Upload does NOT appear in authority-visible proof

---

## BLOCK 10 — Monitoring and Free Media

### T32 — Monitoring team uploads site proof
Login as MONITORING_TEAM. Open Monitoring Upload. Select a site. Upload a photo.

**Assertions:**
- Upload appears in Monitoring History
- Sales/Client Servicing can see it in Client Media Library

### T33 — Free media discovery
Monitoring team uploads with discovery_mode = ON (check for toggle).

**Assertions:**
- A free_media_record is created with status DISCOVERED
- Ops can see it in Free Media Inventory
- Media Planning can see it

### T34 — Ops confirms free media
Login as OPS_MANAGER. Open Free Media Inventory. Find DISCOVERED record. Confirm Active.

**Assertions:**
- Status changes to CONFIRMED_ACTIVE
- Campaign ending does NOT auto-create free media (Ops must confirm manually)

---

## BLOCK 11 — Dashboards and Reports

### T35 — Master Ops Dashboard shows real data
Login as OPS_MANAGER. Open Master Dashboard.

**Assertions:**
- Cards show non-zero numbers where data exists
- Clicking a card navigates to the relevant module
- No "Something needs attention" error on any card

### T36 — Green Belt Dashboard
Login as OPS_MANAGER. Open Green Belt Dashboard.

**Assertions:**
- Active cycle count shown
- Watering pending count shown
- Belts needing attention table shows belt data

### T37 — Monthly Reports export
Login as OPS_MANAGER. Open Monthly Reports. Select current month. Download Belt Health CSV.

**Assertions:**
- CSV downloads successfully
- Headers match expected format
- Empty month exports headers only, no error

---

## BLOCK 12 — Governance and Settings

### T38 — Audit log captures mutations
After running any governed mutation (approve upload, mark watering with override, close issue), open Audit Logs.

**Assertions:**
- Relevant action appears with actor, entity_type, entity_id, timestamp
- Old values and new values shown in detail modal

### T39 — System Settings edit
Login as OPS_MANAGER. Open Settings. Edit one setting (e.g. toggle authority_whatsapp_helper_enabled).

**Assertions:**
- Setting saves successfully
- Audit log records the change
- WhatsApp helper button in Authority View appears/disappears based on toggle

### T40 — Rejected Upload Cleanup
After rejecting an upload and exceeding the cleanup threshold (or adjusting config for test), open Rejected Cleanup.

**Assertions:**
- Rejected upload appears in cleanup list
- Purge removes the file but retains a metadata row
- Purged upload does not appear as a broken link in UI

---

## BLOCK 13 — Edge Cases and Safety

### T41 — Month-lock prevents backdated changes
Attempt to submit a watering record or attendance record for a date in a prior locked month without Ops override.

**Assertions:**
- System blocks the submission with an appropriate error

### T42 — belt_code is immutable
Open belt edit form for any belt.

**Assertions:**
- belt_code field is absent from the edit form
- API call to belt/update with a changed belt_code is ignored or rejected

### T43 — Compliance is derived, never stored
Query the database directly: confirm no `compliance` or `compliance_percent` column exists in any table.

**Assertions:**
- Compliance figures shown in dashboards match what would be computed from watering_records
- No compliance value is persisted

### T44 — Soft delete — no hard deletes
After any delete action in the UI, confirm the row remains in the database with is_deleted=1 and deleted_at set.

**Assertions:**
- `SELECT * FROM [table] WHERE is_deleted=1` shows the row
- The row no longer appears in any UI list

---

## BLOCK 14 — Alert Panel

### T45 — Alert panel shows actionable items
Login as OPS_MANAGER. Open Alert Panel.

**Assertions:**
- Permission expiry warnings show belts whose permission_end_date is within threshold
- High priority tasks section shows OPEN/RUNNING tasks with HIGH or CRITICAL priority
- Clicking a belt row navigates to belt master
- Clicking a task row navigates to task detail

---

## BLOCK 15 — Cross-Role State Verification (E2E Chains)

These tests run sequentially across multiple roles in one pass. Each chain verifies that a state change made by Role A is immediately visible to Role B (who should see it) and NOT visible to Role C (who should not). Use API shortcuts between role switches to keep tests fast.

### E2E-01 — Green Belt Proof Pipeline (3 roles)
Step 1 — GREEN_BELT_SUPERVISOR uploads WORK photo for assigned belt
- Assert: appears in My Uploads; authority_visibility column absent from view

Step 2 — Switch to OPS_MANAGER
- Assert: upload in Upload Review with visibility=HIDDEN
- Assert: AUTHORITY_REPRESENTATIVE authority/view does NOT yet include it

Step 3 — OPS_MANAGER approves the upload
- Assert: authority_visibility = APPROVED in DB

Step 4 — Switch to AUTHORITY_REPRESENTATIVE
- Assert: approved upload NOW in Authority View with correct belt, supervisor, work_type
- Assert: no ISSUE / REJECTED / HIDDEN uploads visible alongside it

---

### E2E-02 — Request → Task → Proof Pipeline (4 roles)
Step 1 — SALES_TEAM raises request
- Assert: status = SUBMITTED; NOT accessible to FABRICATION_LEAD (403)

Step 2 — OPS_MANAGER approves + converts to task
- Assert: request = CONVERTED; task = OPEN; task visible in FABRICATION_LEAD My Tasks

Step 3 — FABRICATION_LEAD starts task
- Assert: status = RUNNING; SALES_TEAM sees progress_percent = 0 (read-only)

Step 4 — FABRICATION_LEAD updates progress to 75%
- Assert: SALES_TEAM Task Progress shows 75%

Step 5 — FABRICATION_LEAD marks Work Done (after AFTER_WORK upload)
- Assert: task visible to OPS_MANAGER for final closure
- Assert: SALES_TEAM still sees RUNNING (not COMPLETED until Ops closes)

---

### E2E-03 — Authority Visibility Isolation (2 roles)
Step 1 — GREEN_BELT_SUPERVISOR uploads ISSUE photo for assigned belt
Step 2 — OPS_MANAGER: Approve button disabled/absent for ISSUE type
Step 3 — AUTHORITY_REPRESENTATIVE: ISSUE upload absent from Authority View under all filters
Step 4 — GREEN_BELT_SUPERVISOR uploads WORK photo, OPS approves
- Assert: WORK photo in Authority View; ISSUE photo still absent

---

### E2E-04 — Outsourced Isolation (3 roles)
Step 1 — OUTSOURCED_MAINTAINER uploads for an outsourced belt
- Assert: in their My Uploads; authority_visibility = NOT_ELIGIBLE

Step 2 — Switch to GREEN_BELT_SUPERVISOR
- Assert: outsourced belt absent from their belt dropdown
- Assert: outsourced upload absent from their My Uploads

Step 3 — Switch to OPS_MANAGER
- Assert: outsourced upload visible for oversight; cannot be approved (NOT_ELIGIBLE)

---

### E2E-05 — Monitoring → Commercial Proof Chain (3 roles)
Step 1 — MONITORING_TEAM uploads site proof
Step 2 — SALES_TEAM: visible in Client Media Library
Step 3 — MEDIA_PLANNING: visible in their inventory view
Step 4 — AUTHORITY_REPRESENTATIVE: monitoring upload NOT in Authority View (green belt only)

---

### E2E-06 — Free Media Discovery Chain (3 roles)
Step 1 — MONITORING_TEAM uploads with discovery_mode=ON
- Assert: free_media_record created with status = DISCOVERED
Step 2 — MEDIA_PLANNING sees DISCOVERED record
Step 3 — OPS_MANAGER confirms → status = CONFIRMED_ACTIVE
Step 4 — MEDIA_PLANNING sees CONFIRMED_ACTIVE

---

### E2E-07 — Watering Correction Audit Chain (3 roles)
Step 1 — GREEN_BELT_SUPERVISOR marks watering DONE
Step 2 — HEAD_SUPERVISOR corrects to NOT_REQUIRED without override_reason
- Assert: rejected with "Correction requires an override reason"
Step 3 — HEAD_SUPERVISOR retries with override_reason = "Belt flooded"
- Assert: status = NOT_REQUIRED; override_by_user_id set
Step 4 — OPS_MANAGER Audit Logs
- Assert: entry shows actor = HEAD_SUPERVISOR, action = WATERING_OVERRIDE, reason logged

---

## BLOCK 16 — Data Integrity and Form Validation

### T46 — Required field validation
On any create form (belt, task, issue), submit with required fields blank.
- Assert: form shows validation error; no API call made or API returns 400

### T47 — GPS pair validation
On belt create form, enter latitude without longitude (or vice versa).
- Assert: rejected with "Both lat and lon required or neither"

### T48 — One active cycle per belt
Start a maintenance cycle for a belt (T08). Immediately try to start another.
- Assert: rejected with appropriate error; only one ACTIVE cycle per belt exists

### T49 — Belt permission expiry auto-closes cycle
Set a belt's permission_end_date to yesterday via direct DB update. Attempt any belt update or trigger a cycle check.
- Assert: active cycle is closed with audit entry; cycle end_date is set

### T50 — belt_code immutable
Open any belt edit form.
- Assert: belt_code field is absent from the edit form
- Assert: `belt/update` API call with a changed belt_code ignores or rejects the new value

### T51 — Soft delete — no hard deletes
Delete any record through the UI (upload self-delete, user deactivate, etc.).
- Assert: `SELECT * FROM table WHERE is_deleted=1` shows the row
- Assert: row absent from all UI lists

### T52 — Compliance derived — never stored
Query: `SHOW COLUMNS FROM green_belts` and all related tables.
- Assert: no column named `compliance`, `compliance_pct`, or similar
- Assert: compliance figures in dashboard match what watering_records would compute

---

## BLOCK 17 — User and Role Governance Lifecycle

### T53 — Create a new user (Ops)
Login as OPS_MANAGER. Open Users → create new user:
email=test.newuser@skite.local, role=GREEN_BELT_SUPERVISOR, password=TestPass123!
- Assert: user appears in list; can login with provided credentials

### T54 — Deactivate user blocks login
Deactivate the user from T53.
- Assert: login attempt with their credentials returns "Invalid email or password"
- Assert: user shows INACTIVE in Users list

### T55 — Reactivate user restores login
Reactivate the user from T53.
- Assert: login succeeds again; correct landing page loads

### T56 — force_password_reset flag
Update user's force_password_reset = 1 via Users edit.
- Assert: next login redirects to password reset screen before reaching the app
- Assert: after reset, force_password_reset = 0 and normal app access resumes

### T57 — Create dynamic role with module scope
Login as OPS_MANAGER. Open Roles & Access → create new role:
name = TEST_ROLE, permission_group = VIEW, landing = green_belt.master,
modules = [green_belt.master, green_belt.detail]
- Assert: role appears in role list
- Assert: landing module must be within selected module scope (try setting landing to task.management → expect rejection)

---

## BLOCK 18 — SPA Navigation, Empty States, Pagination

### T58 — Browser refresh reloads current page
Navigate to any module (e.g. Green Belts). Press F5 / browser refresh.
- Assert: same module loads again (not login screen, not blank)
- Assert: no JavaScript errors in console after refresh

### T59 — Empty state rendering
Navigate to any module guaranteed to have no data (e.g. a freshly created filter with no matches, or task.progress_read with no tasks).
- Assert: panel shows the "No records" empty state message
- Assert: no blank panel, no JS error, no "undefined" text

### T60 — Pagination navigation
Any list view that has more records than one page (or reduce page limit for test). Click Next → then Prev.
- Assert: page 2 shows different records than page 1
- Assert: Prev returns to page 1 with same records
- Assert: page indicator shows correct "Page X of Y (Z total)"

### T61 — Filter state on navigation
Apply a filter in Green Belt Master (e.g. zone = Sector18). Click a belt to open detail. Press Back.
- Assert: returns to Green Belt Master with the zone filter still applied (or correctly cleared — verify consistent behavior)

---

## Shell Integration for E2E Chains

Between role switches in E2E tests, use API to avoid slow UI re-login:
```bash
CSRF=$(curl -s -X POST "http://localhost/skite/index.php?route=auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"ROLE_EMAIL","password":"TestPass123!"}' \
  -c /tmp/skite_ROLE.txt | grep -o '"csrf_token":"[^"]*"' | cut -d'"' -f4)
```

To seed specific test state before a chain (e.g. pre-create a belt with an assignment):
```bash
# Use existing test scripts or direct curl POST to relevant API routes
# with the appropriate role session before the browser test begins
bash tests/http_integration_test.sh  # confirms all endpoints live before starting
```

This lets browser tests focus on UI verification, not data setup.

---

## Test Run Instructions for AI Agents

Run blocks in order. One block per agent turn. After each block:

1. Record PASS/FAIL per assertion
2. Note any unexpected errors with the exact error message and page
3. Do NOT fix bugs during testing — record and stop
4. After all blocks, compile a failure report: test ID | step | expected | actual

**Priority order if time-limited:**
1. BLOCK 15 (E2E chains) — highest business value
2. BLOCK 1 (Auth/RBAC) — security boundary
3. BLOCK 5 (Upload Review) — core governance flow
4. BLOCK 6 (Authority View) — legal-facing output
5. BLOCK 8 (Request→Task) — full commercial chain
6. All remaining blocks

**Known items not coverable by automation:**
- Actual browser file upload (T09, T29, T32) — requires real multipart/form-data
- GPS silent capture — requires browser geolocation API permission
- WhatsApp share text content verification
- Visual layout (pill colours, table alignment, mobile drawer behaviour)
