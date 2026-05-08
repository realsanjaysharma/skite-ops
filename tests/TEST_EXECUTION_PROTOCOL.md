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
# 1. Reset test data to clean state
C:\xampp\mysql\bin\mysql.exe -u root skite_ops < tests/cleanup_test_data.sql

# 2. Verify all RBAC boundaries still pass
bash tests/http_role_coverage_test.sh
```

If RBAC test fails, stop. Fix before testing.
If cleanup fails, check MySQL is running first.

Also confirm MySQL and Apache are running before starting.

## File Upload in Browser Tests

For tests requiring file upload (T09, T29, T32, T33, E2E-01 to E2E-05):
Use `mcp__Claude_in_Chrome__file_upload` tool with the fixture path:
```
C:\xampp\htdocs\skite\tests\fixtures\billboard_sector108_noida.jpg
```
This is fully automatable — no manual intervention needed.

---

## Agent Start Sequence (every turn)

1. Read `tests/TEST_RESULTS.md` → find first block with status **PENDING**
2. Read only that block from `tests/TEST_PLAN.md`
3. Execute every step — browser automation for UI, curl/API for state checks
4. Write results to `tests/TEST_RESULTS.md`
5. **Commit and push `tests/TEST_RESULTS.md`** so the next agent can see the state
6. **Stop** — do not continue into the next block

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
Created by `tests/seed_test_data.sql`. Use these when tests need
pre-existing belts, assignments, uploads, tasks in known states.
Run seed: `C:\xampp\mysql\bin\mysql.exe -u root skite_ops < tests/seed_test_data.sql`

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

Testing phase is complete. Proceed to the Post-Testing Workflow below.

---

## Post-Testing Workflow

Follow this after TEST_RESULTS.md shows all blocks as COMPLETE, FAIL, or BLOCKED.

### Step 1 — Categorize the Bug Log

Read every entry in the Bug Log and assign a priority:

| Priority | Definition |
|---|---|
| P1 | Blocks a role entirely, security gap, data loss, or core flow broken |
| P2 | Feature broken but a workaround exists, or wrong output |
| P3 | Cosmetic, empty state wording, minor rendering issue |

### Step 2 — Fix P1 bugs first

Switch to implementation mode. Use the standard implementation prompt (not the test prompt).
Fix one bug at a time. Commit each fix separately.
Add any new pitfall discovered to `docs/AI_TOOL_HANDOFF_GUIDE.md` — Codebase Pitfalls and Safety Rules.

### Step 3 — Re-run only affected blocks

Do not re-run all 20 blocks. Run only the blocks that contain the tests that FAILed.
Update TEST_RESULTS.md with the new results.
Run `tests/cleanup_test_data.sql` before re-running if the block creates data.

### Step 4 — Repeat for P2 bugs

Same process: fix one at a time, commit, re-run only the affected block.

### Step 5 — P3 bugs (optional before go-live)

Fix cosmetic/rendering bugs as a batch. One commit, re-run affected blocks.

### Step 6 — Final human walkthrough

After no P1 or P2 bugs remain, the developer does one manual pass through each role's main flow in a real browser. This catches things automated tests cannot — visual glitches, confusing UX, mobile layout, WhatsApp share output.

### Step 7 — Ready

When the walkthrough passes and Bug Log has no open P1/P2 items, the system is ready.

---

### Handling BLOCKED tests after fixes

If a test was BLOCKED because an upstream test FAILed, after fixing the upstream bug:
1. Re-run the upstream block to confirm it now PASSES
2. Immediately re-run the BLOCKED block
3. Update TEST_RESULTS.md for both

Do not leave long chains of BLOCKEDs unresolved — they hide real failures.

---

## What Agents Must NOT Do During Testing

- Fix bugs found during testing
- Change test plan assertions to match broken behavior
- Skip a failing test to keep moving
- Run more than one block per turn
- Modify any source files (PHP, JS, SQL)
