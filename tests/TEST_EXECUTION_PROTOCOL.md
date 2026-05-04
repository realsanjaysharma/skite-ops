# Test Execution Protocol

## Reusable Prompt (use this every turn, never change it)

```
Read docs/AI_TOOL_HANDOFF_GUIDE.md (Codebase Pitfalls and Safety Rules section only).
Read tests/TEST_EXECUTION_PROTOCOL.md.
Read tests/TEST_RESULTS.md — find the first block with status PENDING.
Execute that block from tests/TEST_PLAN.md using browser automation and/or API calls.
Update tests/TEST_RESULTS.md: set block status, record per-test PASS/FAIL/BLOCKED, append to Bug Log for any failures.
Stop after one block. Do not fix bugs. Do not run the next block.
```

---

## Pre-Flight (run once before any block)

```bash
bash tests/http_role_coverage_test.sh
```

If any role fails pre-flight, stop. Fix RBAC before testing.

Also confirm MySQL and Apache are running before starting.

---

## Agent Start Sequence (every turn)

1. Read `tests/TEST_RESULTS.md` → find first block with status **PENDING**
2. Read only that block from `tests/TEST_PLAN.md`
3. Execute every step — browser automation for UI, curl/API for state checks
4. Write results to `tests/TEST_RESULTS.md`
5. **Stop** — do not continue into the next block

---

## One Block Per Turn

- One agent turn = one block only
- Never combine two blocks even if both are small
- Never skip a block even if it seems trivial

---

## Test Data

Two credential sets exist:

**Integration test users** (primary — use these for all testing):
All passwords: `TestPass123!`
See credentials table in `tests/TEST_PLAN.md`

**Seed data users** (alice@skyte.com etc / password123):
Created by `migrations/004_seed_test_data.sql`. Use these when tests need
pre-existing belts, assignments, uploads, tasks in known states.
Run seed: `C:\xampp\mysql\bin\mysql.exe -u root skite_ops < migrations/004_seed_test_data.sql`

**Idempotency:** Create tests (T05, T25, T27, T53) use unique identifiers
(belt_code=GB-TEST-01, etc.). Before creating, check if the record already
exists to avoid duplicate key errors on second run.

---

## On PASS

Mark `PASS` in TEST_RESULTS.md per-test table. No other action.

---

## On FAIL

1. Mark `FAIL` in per-test table
2. Add one line to Bug Log:
   `T## | Step N | Expected: X | Actual: Y | Error message if any`
3. Continue remaining tests in the block — do not stop early
4. **Do NOT fix the bug during testing**

---

## On BLOCKED

A test is BLOCKED when its required state (from a prior test) does not exist.

Example: E2E-01 Step 4 (Authority View) is BLOCKED if T07 (Assign Authority) FAILed and left no assignment.

Mark `BLOCKED` in per-test table. Note which earlier test caused it.
Move on to next test.

---

## On ERROR (unexpected crash / 500 / JS exception)

Record exact error message + URL/action. Mark `FAIL` with label `[ERROR]`.

---

## On RETRY

If a test fails due to a network timeout or resource loading error (not a
functional failure), retry once before marking FAIL.

---

## Independent Blocks (can run in any order)

These blocks have no upstream dependencies:

- BLOCK 11 — Dashboards and Reports
- BLOCK 12 — Governance and Settings
- BLOCK 14 — Alert Panel
- BLOCK 16 — Data Integrity
- BLOCK 18 — SPA and Empty States

If an upstream block is BLOCKED, skip ahead to an independent block.

---

## Screenshot on Failure

When a test FAILs, capture a screenshot of the current page state if using
browser automation. Include the filename in the Bug Log entry.

---

## When All Blocks Are Done

Write a Summary line in TEST_RESULTS.md:

```
## Summary
Completed: YYYY-MM-DD
PASS: X | FAIL: Y | BLOCKED: Z
See Bug Log below for all failures.
```

Testing phase is complete. Hand the Bug Log to the developer for triage.
Do not commit code fixes during testing.

---

## What Agents Must NOT Do During Testing

- Fix bugs found during testing
- Change test plan assertions to match broken behavior
- Skip a failing test to keep moving
- Run more than one block per turn
- Modify any source files (PHP, JS, SQL)
