# Test Results — Live Tracker

_Each agent turn updates this file. Read it first to find where to continue._

Last updated by: Claude Sonnet 4.6 — 2026-05-09 (BLOCK 14)
Current status: IN PROGRESS

---

## Block Status

| Block | Status | PASS | FAIL | BLOCKED |
|---|---|---|---|---|
| BLOCK 1 — Auth & RBAC | DONE | 3 | 1 | 0 |
| BLOCK 2 — Green Belt Core | DONE | 4 | 0 | 0 |
| BLOCK 3 — Field Ops (Supervisor) | DONE | 3 | 1 | 0 |
| BLOCK 4 — Head Supervisor | DONE | 4 | 1 | 0 |
| BLOCK 5 — Upload Review | DONE | 4 | 0 | 0 |
| BLOCK 6 — Authority View | DONE | 2 | 1 | 0 |
| BLOCK 7 — Issue Lifecycle | DONE | 1 | 1 | 0 |
| BLOCK 8 — Request→Task→Execution | DONE | 1 | 3 | 0 |
| BLOCK 9 — Outsourced Flow | DONE | 1 | 0 | 0 |
| BLOCK 10 — Monitoring & Free Media | DONE | 3 | 0 | 0 |
| BLOCK 11 — Dashboards & Reports | DONE | 3 | 0 | 0 |
| BLOCK 12 — Governance & Settings | DONE | 3 | 0 | 0 |
| BLOCK 13 — Edge Cases & Safety | DONE | 4 | 0 | 0 |
| BLOCK 14 — Alert Panel | DONE | 0 | 1 | 0 |
| BLOCK 15 — E2E Chains | DONE | 6 | 1 | 0 |
| BLOCK 16 — Data Integrity | PENDING | — | — | — |
| BLOCK 17 — User & Role Lifecycle | PENDING | — | — | — |
| BLOCK 18 — SPA & Empty States | PENDING | — | — | — |
| BLOCK 19 — Mobile Responsiveness | PENDING | — | — | — |
| BLOCK 20 — Security Basics | PENDING | — | — | — |

---

## Per-Test Results

| Test | Status | Notes |
|---|---|---|
| T01 | FAIL | AUTHORITY_REPRESENTATIVE landing page shows "Forbidden" error panel; all other 9 roles PASS (correct landing, badge, no error) |
| T02 | PASS | Sidebar scope correct for all 6 specified roles: GBS, HS, FL, MT, AR, ST |
| T03 | PASS | URL nav to #governance.user_management stayed on Supervisor Upload; API user/list returned 403 |
| T04 | PASS | After PHP session files cleared, app redirects to login screen; no previous session data visible |
| T05 | PASS | Belt GB-TEST-01 created (id=63); appears in list with correct values; belt_code absent from edit form; belt/list API confirms |
| T06 | PASS | Supervisor user_id=4 (Test Supervisor P2) assigned; start_date=2026-05-08 in panel; supervisorassignment/list confirms. UI note: SUPERVISOR column and END DATE render as blank/[object Object] |
| T07 | PASS | Authority user_id=5 (Test Authority P2) assigned; start_date=2026-05-08 in panel; authorityassignment/list confirms. Same UI rendering note |
| T08 | PASS | Cycle id=22 created with start_date=2026-05-08; second Start Cycle rejected with "An active cycle already exists for this belt." |
| T09 | PASS | Upload id=110 created (WORK/GREEN_BELT/parent_id=63); in upload/my-list; authority_visibility absent from supervisor response; visible to Ops as HIDDEN. Upload id=109 used for T10 |
| T10 | PASS | upload/delete succeeded within 2-min window; upload 109 removed from my-list; browser UI confirmed |
| T11 | PASS | Upload id=110 backdated 10 min; delete blocked with HTTP 400 "Upload is outside the self-delete window."; upload remains in my-list |
| T12 | FAIL | Belt selection is a free-text number input (not a dropdown restricted to assigned belts). Backend correctly returns HTTP 403 "You are not currently assigned to this green belt." for unassigned belt_id=1 |
| T13 | PASS | watering/mark DONE for belt_id=1 (record id=2, date=2026-05-08); watering/list confirms watering_status=DONE; 36 other belts show PENDING derived (0 PENDING rows in DB) |
| T14 | FAIL | HEAD_SUPERVISOR cannot correct watering at all — got "Only Ops can correct watering status once it is marked." (not the expected "Correction requires an override reason"). OPS CAN correct: without override_reason → expected error; with override_reason → NOT_REQUIRED, override_by_user_id=3 set ✓ |
| T15 | PASS | attendance/mark created record id=5 (supervisor_user_id=4, status=PRESENT, date=2026-05-08); attendance/list confirms. UI note: overview shows UNKNOWN before filter applied |
| T16 | PASS | labour/mark created record (labour_count=5, gardener_count=1, night_guard_count=2); fields are correct (no male/female); browser UI confirms in Section 3 Labour Entry |
| T17 | PASS | issue/in-progress moved issue 13 to IN_PROGRESS; issue/close as HEAD_SUPERVISOR rejected HTTP 403 "Only Ops can close an issue." |
| T18 | PASS | upload/review APPROVED upload 110 (WORK/GREEN_BELT/63); DB=APPROVED; in upload/list?authority_visibility=APPROVED; absent from HIDDEN filter |
| T19 | PASS | Created ISSUE upload id=111 (auto-visibility=NOT_ELIGIBLE); upload/review APPROVED → HTTP 400 "Only authority-eligible work uploads can be reviewed."; DB still NOT_ELIGIBLE |
| T20 | PASS | Bulk-approved uploads 13 and 15 in single call; both DB=APPROVED; ISSUE upload 111 untouched (NOT_ELIGIBLE) |
| T21 | PASS | upload/review REJECTED upload 112 with comment; DB=REJECTED; cleanup-list empty (expected — threshold days not yet passed) |
| T22 | FAIL | Browser shows same "Forbidden" error as T01 — APPROVED WORK uploads not visible in UI. API correct: authority/view returns only upload 110 (APPROVED WORK, belt 63 assigned); no HIDDEN/REJECTED/NOT_ELIGIBLE/ISSUE uploads returned |
| T23 | PASS | Date/belt filters work via API; unassigned belt returns 0 items; authority/share-helper returns message_text + whatsapp_url; authority/summary returns correct stats |
| T24 | PASS | Only green_belt.authority_view accessible; upload/review + upload/delete + issue/in-progress all return HTTP 403; no action fields in authority/view response; browser shows no modify buttons (only Forbidden panel) |
| T25 | FAIL | Issue id=21 created (status=OPEN, priority=MEDIUM, belt_id=1) ✓; IS-XXXXX sequence ID NOT present — no such field in API response, issue/list, or anywhere in codebase. Browser UI confirmed: Issues table shows plain integer ID column only (screenshot ss_5502v2hol) |
| T26 | PASS | OPEN→IN_PROGRESS (HS) ✓; link-task stored (tasks.linked_issue_id=21 on task 3) ✓; HS close blocked HTTP 403 "Only Ops can close an issue." ✓; OPS closed issue (status=CLOSED, closed_by=3) ✓; task 3 still OPEN after issue closed ✓ |
| T27 | FAIL | Request id=15 created (FABRICATION, SUBMITTED, belt_id=63) ✓; Ops sees it ✓; RQ-XXXXX sequence ID NOT present — no such field in API or codebase. Browser UI confirmed: My Submitted Requests table shows plain integer ID=15 (screenshot ss_7621c26xc) |
| T28 | FAIL | Request approved (APPROVED) ✓; task id=41 created (OPEN, request_id=15) ✓; assigned to FL id=11 ✓; BUT request status stayed APPROVED — did NOT become CONVERTED after task creation |
| T29 | FAIL | Start→RUNNING ✓ (browser: task 41 shows Progress/Mark Done/Detail buttons, OPEN tasks show Start button — ss_84945we32); progress 50% saved ✓; work-done blocked without proof ✓; BUT TASK surface only allows upload_type=WORK — AFTER_WORK type rejected ("Invalid upload_type"); system requires AFTER_WORK proof but cannot upload it. Task stuck at RUNNING/50% |
| T30 | PASS | task 41 visible in taskprogress/list (status=RUNNING, progress_percent=50) ✓; task/start + task/work-done + task/update + task/progress all return HTTP 403 ✓; allowed modules confirm task.progress_read only ✓; browser UI confirmed: no Start/Assign/Approve/Edit buttons in table — only data columns (screenshot ss_9795wo32n) |
| T31 | PASS | Active outsourced assignments for user 6: belt_id=13 and belt_id=54; upload to belt_id=13 → id=114 (authority_visibility=NOT_ELIGIBLE) ✓; unassigned belt_id=63 → HTTP 403 "not assigned to this outsourced belt" ✓; watering/attendance/labour all HTTP 403 ✓; NOT_ELIGIBLE cannot be APPROVED (HTTP 400) ✓; browser UI confirmed: sidebar shows ONLY "Outsourced Upload" — no Watering, Attendance, Labour, Issues, or any other controls (screenshot ss_29983jg7l) |
| T32 | PASS | Upload id=115 (WORK/SITE/parent_id=2) created; in monitoring/history (upload_id=115, site P3-SITE-1776595179) ✓; CLIENT_SERVICING can see it in monitoring/history ✓; browser confirms: Monitoring Upload form + History sidebar, no other controls (ss_48377dx4q) |
| T33 | PASS | Upload id=116 with discovery_mode=1; free_media_record id=24 created (MONITORING_DISCOVERY, DISCOVERED, site_id=2, source_reference_id=116) ✓; freemedia/list returns it for Ops ✓; Media Planning sees it (DISCOVERED) ✓; browser confirms "Mark as Free Media Discovery" checkbox visible (ss_48377dx4q) |
| T34 | PASS | freemedia/confirm (record_id=24, confirmed_date=2026-05-09) → status=CONFIRMED_ACTIVE, confirmed_by=3 ✓; CAMPAIGN_END auto-creates DISCOVERED records (not auto-CONFIRMED_ACTIVE), requiring Ops manual confirmation ✓; browser UI: Free Media Inventory shows CONFIRMED ACTIVE badge with Raise Request action (ss_7910quoz6) |
| T35 | PASS | Master Ops Dashboard shows: Operational Belts=41, Open Tasks=18, Open Issues=3, Free Media=4 (all non-zero ✓); clicking Open Tasks card navigated to Task Management ✓; no "Something needs attention" error on any card ✓ (ss_24718tcsj) |
| T36 | PASS | Green Belt Dashboard: Active Cycles=8 ✓, Same-Day Watering Pending=34 ✓, Open Belt Issues=2 ✓; Belts Needing Attention table shows real belt data (GB-001, GB-009, GB-TEST-01 etc with NO ACTIVE CYCLE badges) ✓ (ss_9262n21cd, ss_7124au83v) |
| T37 | PASS | Monthly Analytics page loads with May 2026 pre-selected and Download CSV button ✓; report/belt-health?format=csv returns Content-Type:text/csv with headers (belt_id,belt_code,common_name,...,health_status) and 51 belt rows ✓; future month 2099-01 returns success with no error ✓ (ss_7014xwr0i) |
| T38 | PASS | audit/list returns 562 entries; FREE_MEDIA_CONFIRMED (actor=3, entity=free_media_records, entity_id=24) has old={status:DISCOVERED} new={status:CONFIRMED_ACTIVE} ✓; UPDATE_SETTING entries also verified; browser UI shows Audit Logs page with TIMESTAMP/ACTOR/ACTION/ENTITY/ID columns (ss_69147rbti) |
| T39 | PASS | settings/list: authority_whatsapp_helper_enabled=true (current); toggled to false → saved confirmed ✓; audit log shows UPDATE_SETTING (old_value=1, new_value=0) ✓; restored to true; WhatsApp UI assertion unverifiable (Authority View Forbidden bug from T01/T22); Note: PHP Warning "Undefined array key 'id'" in SystemSettingsService.php:79 — does not prevent save |
| T40 | PASS | Cleanup threshold=30 days (rejected_upload_cleanup_days setting); upload 112 (REJECTED) appeared in cleanup-list after backdating reviewed_at to 31 days ago ✓; purge removed file (file_path=NULL, is_purged=1, purged_at set) while retaining metadata row ✓; purged upload absent from cleanup-list ✓ |
| T41 | PASS | Watering mark for 2026-04-01 → HTTP 403 "Head Supervisors can only mark same-day watering." ✓; Attendance for 2026-04-01 → HTTP 403 "Head Supervisors can only mark same-day attendance." ✓; backdated changes blocked at service layer |
| T42 | PASS | Belt update with belt_code="CHANGED-CODE" in payload: belt_code silently ignored, DB still shows GB-TEST-01 ✓; belt_code field absent from Edit Belt modal confirmed in T05 browser UI ✓ |
| T43 | PASS | INFORMATION_SCHEMA query: 0 columns named compliance, compliance_percent, compliance_pct in any table ✓; compliance is computed at runtime from watering_records (0 DONE records → 0% compliance visible in dashboard) |
| T44 | PASS | Upload 109 (deleted in T10): DB shows is_deleted=1, deleted_at=2026-05-08 12:28:03 ✓; absent from upload/my-list API response ✓; users table has is_deleted column; watering_records uses overwrite semantics (no is_deleted) |
| T45 | FAIL | API: high_priority_tasks=5 (HIGH/OPEN/RUNNING) ✓; expiry_warnings=0 (no belts near threshold — correct empty state); belt click → Green Belts (belt master) ✓ (ss_8500uqqoy); task row click → Task #14 detail ✓ (ss_90201kzyo); BUT section headers render raw HTML span tags as text (e.g. "High Priority Tasks <span style=...>5</span>" displayed verbatim) — badge count formatting broken in all 6 sections |
| T46 | PENDING | |
| T47 | PENDING | |
| T48 | PENDING | depends on T08 |
| T49 | PENDING | requires direct DB manipulation |
| T50 | PENDING | |
| T51 | PENDING | requires direct DB query |
| T52 | PENDING | requires direct DB query |
| T53 | PENDING | idempotency: check test.newuser@skite.local before creating |
| T54 | PENDING | depends on T53 |
| T55 | PENDING | depends on T54 |
| T56 | PENDING | depends on T53 |
| T57 | PENDING | |
| T58 | PENDING | |
| T59 | PENDING | |
| T60 | PENDING | requires enough records for pagination |
| T61 | PENDING | |
| E2E-01 | PASS | Green Belt proof pipeline verified via API: supervisor WORK upload 136 hidden from My Uploads status, HIDDEN in Ops review, absent from Authority before approval, APPROVED after Ops review, visible in Authority View as upload_id=136. Authority response uses upload_id, not id. |
| E2E-02 | PASS | Request-to-task pipeline verified: request 19 SUBMITTED → APPROVED → CONVERTED, task 45 visible to Fabrication Lead, RUNNING after start, Sales saw 0% then 75%, AFTER_WORK proof accepted using upload_type=WORK + photo_label=AFTER_WORK, task completed and Sales saw COMPLETED. |
| E2E-03 | PASS | Authority isolation verified: ISSUE upload 138 could not be approved and stayed absent from Authority View; WORK upload 139 was approved and became visible; ISSUE upload remained absent. |
| E2E-04 | PASS | Outsourced isolation verified: outsourced upload 140 visible in outsourced My Uploads, NOT_ELIGIBLE in Ops list, absent from supervisor My Uploads, and Ops approval blocked. |
| E2E-05 | PASS | Monitoring-to-commercial chain verified: monitoring upload 141 visible to Sales and Media Planning through monitoring history; absent from Authority View. |
| E2E-06 | PASS | Free media discovery chain verified: discovery upload 142 created free_media_record 26 as DISCOVERED, visible to Media Planning, Ops confirmed to CONFIRMED_ACTIVE, Media Planning saw active record. |
| E2E-07 | FAIL | Green Belt Supervisor watering step failed with Forbidden on watering/mark; Head Supervisor correction path can update to NOT_REQUIRED, but audit action is UPDATE, not expected WATERING_OVERRIDE. |
| T62 | PENDING | resize browser to 375x812 |
| T63 | PENDING | mobile viewport, supervisor upload |
| T64 | PENDING | mobile viewport, OPS dashboard |
| T65 | PENDING | no session cookie required |
| T66 | PENDING | omit X-CSRF-Token header |
| T67 | PENDING | copy supervisor session, call ops route |
| T68 | PENDING | SQL injection in zone param |
| T69 | PENDING | authority/share-helper + GPS field check |
| T70 | PENDING | upload/serve scope: hidden→403, unassigned belt→403, assigned+approved→200 |

---

## Bug Log

_Failures recorded here by agents during test runs._
_Format: Test ID | Step | Expected | Actual | Error_

T01 | Step: Login as AUTHORITY_REPRESENTATIVE (test.authority.p2@skite.local), observe landing page | Expected: Authority View loads without error panel | Actual: Page loads Authority View module but body shows red "Forbidden" error panel with subtitle "Something needs attention" | URL: http://localhost/skite/public/ after login | Screenshot: browser capture ss_7627btnv2 (authority view forbidden on landing)
T12 | Step: Open Supervisor Upload, check belt selection field | Expected: Dropdown showing only assigned belts | Actual: Field is a free-text number input (type=number) — any belt ID can be entered, no UI restriction to assigned belts | URL: http://localhost/skite/public/#green_belt.supervisor_upload | Screenshot: ss_3922ia1n5 | Note: Backend correctly blocks unassigned belts with HTTP 403
T14 | Step: Login as HEAD_SUPERVISOR, attempt watering correction on belt_id=1, date=2026-05-08 (already DONE) with status=NOT_REQUIRED | Expected: "Correction requires an override reason" | Actual: HTTP 403 "Only Ops can correct watering status once it is marked." — HEAD_SUPERVISOR cannot correct at all, even with override_reason | URL: POST http://localhost/skite/index.php?route=watering/mark | Note: Correction logic works correctly under OPS_MANAGER role
T22 | Step: Login as AUTHORITY_REPRESENTATIVE, open Authority View | Expected: Only APPROVED WORK uploads for assigned belts visible, no Forbidden error | Actual: Browser shows "Forbidden" error panel (same bug as T01); API authority/view correctly returns upload 110 only (APPROVED WORK, belt 63) | URL: http://localhost/skite/public/ (authority/view module) | Screenshot: ss_7426wb58p
T25 | Step: Create issue, check for IS-XXXXX sequence ID in response | Expected: Issue appears with IS-XXXXX sequence ID | Actual: Issue created as id=21 (integer) only; no IS-XXXXX field in issue/create response, issue/list, issue/get, or anywhere in frontend/backend codebase | URL: POST http://localhost/skite/index.php?route=issue/create
T27 | Step: Create request as SALES_TEAM, check for RQ-XXXXX sequence ID | Expected: Request appears with RQ-XXXXX sequence ID | Actual: Request created as id=15 (integer) only; no RQ-XXXXX field in request/create response or request/list | URL: POST http://localhost/skite/index.php?route=request/create
T28 | Step: Create task from approved request, check request status | Expected: Request status becomes CONVERTED | Actual: Request status remained APPROVED after task/create; no automatic CONVERTED transition | URL: POST http://localhost/skite/index.php?route=task/create with request_id=15
T29 | Step: Upload AFTER_WORK photo for task as FABRICATION_LEAD | Expected: AFTER_WORK upload accepted, then task/work-done succeeds | Actual: upload/create rejected AFTER_WORK type ("Invalid upload_type for this upload surface") — TASK surface only allows WORK type; task/work-done requires AFTER_WORK proof creating an impossible requirement | URL: POST http://localhost/skite/index.php?route=upload/create
T45 | Step: Open Alert Panel, check section header rendering | Expected: Section headers display count badges as styled pills (e.g. "High Priority Tasks [5]") | Actual: Raw HTML rendered as text — e.g. 'High Priority Tasks <span style="background:var(--bad);color:#fff;...">5</span>' displayed verbatim in all 6 section headers | URL: http://localhost/skite/public/ (governance.alert_panel) | Screenshot: ss_1546xxpbe
E2E-07 | Step 1 and Step 4 | Expected: GREEN_BELT_SUPERVISOR can mark watering DONE, then HEAD_SUPERVISOR correction creates WATERING_OVERRIDE audit entry | Actual: GREEN_BELT_SUPERVISOR POST watering/mark returned Forbidden; Head Supervisor correction records audit action as UPDATE, not WATERING_OVERRIDE | URL: POST http://127.0.0.1/skite/index.php?route=watering/mark

---

## Summary

_Written when all blocks complete._
