# Test Results — Live Tracker

_Each agent turn updates this file. Read it first to find where to continue._

Last updated by: Claude Sonnet 4.6 — 2026-05-08 (BLOCK 7)
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
| BLOCK 8 — Request→Task→Execution | PENDING | — | — | — |
| BLOCK 9 — Outsourced Flow | PENDING | — | — | — |
| BLOCK 10 — Monitoring & Free Media | PENDING | — | — | — |
| BLOCK 11 — Dashboards & Reports | PENDING | — | — | — |
| BLOCK 12 — Governance & Settings | PENDING | — | — | — |
| BLOCK 13 — Edge Cases & Safety | PENDING | — | — | — |
| BLOCK 14 — Alert Panel | PENDING | — | — | — |
| BLOCK 15 — E2E Chains | PENDING | — | — | — |
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
| T25 | FAIL | Issue id=21 created (status=OPEN, priority=MEDIUM, belt_id=1) ✓; IS-XXXXX sequence ID NOT present — no such field in API response, issue/list, or anywhere in codebase |
| T26 | PASS | OPEN→IN_PROGRESS (HS) ✓; link-task stored (tasks.linked_issue_id=21 on task 3) ✓; HS close blocked HTTP 403 "Only Ops can close an issue." ✓; OPS closed issue (status=CLOSED, closed_by=3) ✓; task 3 still OPEN after issue closed ✓ |
| T27 | PENDING | |
| T28 | PENDING | depends on T27 |
| T29 | PENDING | use tests/fixtures/billboard_sector17a_greater_noida.jpg; depends on T28 |
| T30 | PENDING | depends on T28 |
| T31 | PENDING | requires outsourced belt assignment in DB |
| T32 | PENDING | use tests/fixtures/billboard_sector108_noida.jpg |
| T33 | PENDING | use tests/fixtures/billboard_sector17a_greater_noida.jpg with discovery_mode |
| T34 | PENDING | depends on T33 |
| T35 | PENDING | |
| T36 | PENDING | |
| T37 | PENDING | |
| T38 | PENDING | run after any governed mutation |
| T39 | PENDING | |
| T40 | PENDING | requires rejected upload + threshold config |
| T41 | PENDING | |
| T42 | PENDING | |
| T43 | PENDING | requires direct DB query |
| T44 | PENDING | requires direct DB query |
| T45 | PENDING | |
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
| E2E-01 | PENDING | depends on T06, T07 |
| E2E-02 | PENDING | depends on T06 |
| E2E-03 | PENDING | depends on T06, T07 |
| E2E-04 | PENDING | requires outsourced belt assignment |
| E2E-05 | PENDING | requires site with monitoring uploads |
| E2E-06 | PENDING | requires site with free media |
| E2E-07 | PENDING | depends on T06 |
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

---

## Summary

_Written when all blocks complete._
