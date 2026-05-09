# Test Results — Live Tracker

_Each agent turn updates this file. Read it first to find where to continue._

Last updated by: Manual sync from chat — agent failed to commit results
Current status: IN PROGRESS — BLOCK 1 and BLOCK 2 complete, BLOCK 3 is next

---

## Block Status

| Block | Status | PASS | FAIL | BLOCKED |
|---|---|---|---|---|
| BLOCK 1 — Auth & RBAC | COMPLETE | 3 | 1 | 0 |
| BLOCK 2 — Green Belt Core | COMPLETE | 4 | 0 | 0 |
| BLOCK 3 — Field Ops (Supervisor) | PENDING | — | — | — |
| BLOCK 4 — Head Supervisor | PENDING | — | — | — |
| BLOCK 5 — Upload Review | PENDING | — | — | — |
| BLOCK 6 — Authority View | PENDING | — | — | — |
| BLOCK 7 — Issue Lifecycle | PENDING | — | — | — |
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
| T01 | FAIL | AUTHORITY_REPRESENTATIVE lands on Authority View but shows red "Forbidden" error panel. All other 9 roles land correctly. |
| T02 | PASS | Sidebar scope correct for all 6 tested roles |
| T03 | PASS | GREEN_BELT_SUPERVISOR redirected away from user_management; API returns 403 |
| T04 | PASS | Session cleared → redirect to login, no data leak |
| T05 | PASS | Belt GB-TEST-01 (id=63) created; belt_code absent from edit form |
| T06 | PASS | Supervisor assigned to GB-TEST-01; UI bug: supervisor name column blank, end_date shows [object Object] |
| T07 | PASS | Authority rep assigned to GB-TEST-01; same UI rendering bugs as T06 |
| T08 | PASS | Cycle started; second Start Cycle immediately rejected — uniqueness enforced |
| T09 | PENDING | use tests/fixtures/billboard_sector108_noida.jpg |
| T10 | PENDING | depends on T09 |
| T11 | PENDING | requires 5-min wait or DB manipulation |
| T12 | PENDING | |
| T13 | PENDING | |
| T14 | PENDING | depends on T13 |
| T15 | PENDING | |
| T16 | PENDING | |
| T17 | PENDING | requires an OPEN issue to exist |
| T18 | PENDING | depends on T09 or new upload |
| T19 | PENDING | requires an ISSUE-type upload to exist |
| T20 | PENDING | requires multiple WORK uploads |
| T21 | PENDING | |
| T22 | PENDING | depends on T07 (authority assignment) |
| T23 | PENDING | depends on T18 (approved upload) |
| T24 | PENDING | depends on T22 |
| T25 | PENDING | |
| T26 | PENDING | depends on T25 |
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

T01 | Login as AUTHORITY_REPRESENTATIVE | Expected: Authority View loads cleanly | Actual: Red "Forbidden" error panel with "Something needs attention" — backend endpoints all confirmed 200 via direct API; likely transient CSRF/session timing on first load — re-test in BLOCK 6 (T22) | http://localhost/skite/public/ after login

**UI bugs noted (not FAIL — assertions passed):**
T06/T07 | Assignment list | Expected: supervisor_name renders | Actual: name column blank — field name mismatch between API response and column key
T06/T07 | Assignment list | Expected: end_date shows null/empty | Actual: [object Object] — no null handler on end_date column renderer

---

## Summary

_Written when all blocks complete._
