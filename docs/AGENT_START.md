# Skite Ops — Agent Start

> **Every agent reads this file first, every session, before touching any code.**
> Update the "Last completed" and "Current focus" sections before ending your session.

---

Last updated by: Claude Opus 4.6 — 2026-05-25
Last commit: `84f4971 docs: update governance docs for monitoring upload overhaul completion`

---

## What this product is

Skite Ops is a field operations management system for an outdoor advertising company.
It manages green belt maintenance (watering, planting, repairs), supervisor attendance,
upload proof workflows, authority representative access to approved photos, and
advertisement campaign/task tracking — all on a shared PHP/MySQL/XAMPP stack.

10 roles. Single-page application frontend (vanilla JS, hash routing). No framework.
Runs on shared hosting — no background jobs, no queues, no external services.

---

## Current development phase

**Post-launch improvement.** The product owner uses the real running app, reports
observations and requests, and agents implement changes. No automated test runner —
validation is browser testing by the product owner + agent browser automation.

Agent testing (T01–T70 QA pass) is **complete**. That phase is archived in
`tests/TEST_RESULTS.md`. Do not update that file for new work.

---

## What was recently completed

| Commit | What |
|---|---|
| *(uncommitted)* | Initialized Antigravity Superpowers profile at `.agent` using portable Node.js v20.15.0 and ran validation test checks successfully (82/82 PASS). |
| `65112ac` | QA batch: 8 targeted fixes (upload/serve scope, IS/RQ codes, settings, cycle auto-close, SPA refresh, form validation, belt dropdown, HTML badges) |
| `4ff3080` | Authority View v1: gallery, belt-name filters, date range, group-by, JSZip bulk download, mobile swipe, photo preview modal |
| `f392038` | Authority View UX polish: collapsible filters, compact stat cards, card layout, Refresh moved, sticky group headers, keyboard nav, swipe gestures, per-belt photo count, auto-swap dates |
| `1ab21c1` | Shared components: `UI.panel(collapsible)`, `UI.statGrid()`, `openPhotoGallery()`, `.photo-thumb` CSS — wired to Upload Review, Client Media Library, Task Progress |
| `d35967c` | Supervisor + Outsourced Upload: work type chips, mobile camera picker, thumbnail preview, XHR progress bar, success card with My Uploads link |
| `ea9d580` | My Uploads: gallery cards, photo preview (openPhotoGallery 1-of-27), 5-min self-delete with live countdown, "Window closed" badge |
| `35226b3` | My Uploads: belt filter + date grouping (Group by Date/Belt) |
| `b58f770` | RBAC fix: OUTSOURCED_MAINTAINER missing `green_belt.my_uploads` scope — seed migration bug, patched in 001 + added migration 004 |
| `156231e` | My Uploads: 4 date chips (Today/Yesterday/Last 5/Last 7), belt hidden if 1 belt, group-by auto-applies, no date pickers/type/Apply |
| *(uncommitted)* | Monitoring Upload: mobile camera picker, site search dropdown, discovery toggle (Regular Visit / Free Media Discovery), photo preview, recent uploads strip, XHR progress |
| *(uncommitted)* | Monitoring History: gallery cards, date chips, category chips, discovery chips, photo preview modal, self-delete on own uploads |
| *(uncommitted)* | Bug fixes: free media auto-assign to default site, upload scope for monitoring, photo URL fix |
| *(uncommitted)* | Media Discovery design spec: `docs/superpowers/specs/2026-05-24-media-discovery-design.md` |
| *(uncommitted)* | Media Discovery implementation plan: `docs/superpowers/plans/2026-05-24-media-discovery.md` |
| *(uncommitted)* | Media Discovery feature implemented: new `monitoring.discovery` module, MediaDiscoveryController/Service, SiteRepository + FreeMediaRepository extensions, migration 005, discovery toggle removed from monitoring.upload, FREE_MEDIA_DEFAULT_SITE_ID retired |
| `683375b`–`c6a84cd` | **Monitoring Upload Overhaul** (15 commits): migration 006 (board dims, creative_upload_id, last_monitored, site_condition, monitoring_shifts table), enriched site cards API, shift lifecycle, condition tags + quick issue, GPS sorting, tabs/cards/search frontend, history card enrichment, plan completion filter, cache bump + data flow docs |

---

## Current focus

**Monitoring Upload Overhaul — COMPLETE** (Tasks 1–15 all committed)

- `monitoring.upload` ✅ Fully redesigned: tabs (Planned/Unplanned), GPS-sorted card list, shift bar, condition chips, search, auto-advance
- `monitoring.history` ✅ Enriched with client_name, board_size, site_condition badge
- `monitoring.plan` ✅ Completed/Missed filter chips added
- `monitoring.discovery` ✅ Implemented in prior session (committed)

**Next up:**
- Head Supervisor pages (`green_belt.watering_oversight` and related)
- Phase 2 of Media Discovery (planner confirm/merge/dismiss flows)

GREEN_BELT_SUPERVISOR ✅ complete. OUTSOURCED_MAINTAINER ✅ complete. AUTHORITY_REPRESENTATIVE ✅ complete.

---

## What NOT to touch right now

- **Authority View** — stable after multiple polish passes. Do not refactor.
- **`uploadView` shared function** — just redesigned. Do not change without instruction.
- **`openPhotoGallery()`** — shared function, used by 5+ pages. Changes affect all.
- **`UI.panel()` in `ui.js`** — extended with `collapsible` option. Test any changes across pages.
- **`green_belt.my_uploads`** — chips, gallery, delete all stable. Do not refactor.
- **`MY_UPLOADS_PRESETS` constant** — defines the 4 chip date ranges. If adding a new chip, update both this and `myUploadsActivePreset()`.
- **`monitoring.upload`** — Fully redesigned with tabs, cards, GPS sorting, shifts, conditions (committed). Do not refactor.
- **`monitoring.history`** — Enriched with client name, board size, condition badge (committed). Do not refactor.
- **`monitoring_shifts` table** — One shift per user per day (UNIQUE on user_id+shift_date). Do not create duplicate shifts.
- **`creative_upload_id` on sites** — FK to uploads with ON DELETE SET NULL. Must be non-null for active sites (service validation).
- **`last_monitored_at` on sites** — Denormalized, auto-updated by post-upload hook. Do not update manually.
- **`monitoring.discovery`** — Newly implemented (uncommitted). MediaDiscoveryService deliberately does NOT start its own transaction; it relies on UploadService for the upload+free_media_record transaction. Do not add a wrapping `beginTransaction()` — it will throw a nested-transaction error.
- **`UploadService::createUploadsForSurface()`** — manages its own PDO transaction internally. Do NOT wrap calls to this method in another transaction or PDO will throw a nested transaction error. See Media Discovery design spec §6.1.
- **`uploadWithProgress(formData, onProgress, route='upload/create')`** — generalized to accept a route. Existing callers still default to `upload/create`. Do not remove the default.
- **`tests/TEST_RESULTS.md`** — QA phase is archived. Do not add new test results there.

---

## Known open issues

| Issue | Page | Severity | Notes |
|---|---|---|---|
| `[hidden]` CSS fix applied globally | All | Low | Added `[hidden] { display:none !important }` to fix upload form — verify no regressions on other pages |
| Phase 2 of Media Discovery (planner side) | media.free_media_inventory | Low | Confirm / merge / dismiss flows for Media Planner — separate brainstorm + plan needed. |
| Route/group data missing | sites | Low | All sites have `route_or_group = NULL` in test data. Unplanned tab browse-by-route shows "No routes found" until routes are assigned. |

---

## Architecture (non-negotiable)

```
Controller → Service → Repository → Database
```

- Controllers: request handling only
- Services: business logic, validation, transaction control
- Repositories: SQL only
- Schema: READ from `docs/06_schema/schema_v1_full.sql` before writing any query

Work on **main branch** only. Ask before committing or pushing.

---

## ⚠️ Mandatory end-of-session updates

**Before ending any session, update ALL THREE of the following files.
Skipping any one of them makes the docs stale for the next agent.**

### `docs/AGENT_START.md` (this file)
- [ ] "Last updated by" line — your agent name + date
- [ ] "Last commit" — most recent commit hash + subject
- [ ] "What was recently completed" — add your session's work
- [ ] "Current focus" — update to what comes next
- [ ] "What NOT to touch" — add anything newly stabilised
- [ ] "Known open issues" — add anything discovered but not fixed

### `docs/PRODUCT_BACKLOG.md`
- [ ] Feature entry status — mark in-progress items ✅ Done with commit hash
- [ ] **Improvement Sequence table** — update Overall Status for the role you worked on
- [ ] **Role section header** — update "X of Y pages done" count
- [ ] **Individual page row** — update status + add notes on what changed
- [ ] Planned section — add any new items identified during your session
- [ ] Deferred section — add anything explicitly decided not to do yet

### `docs/PRODUCT_LOG.md`
- [ ] Append a dated entry for every significant decision made this session
- [ ] Include: what was observed → what was decided → what was deferred and why
- [ ] Keep it short — 3–6 bullet points per decision is enough

### `docs/AI_TOOL_HANDOFF_GUIDE.md` (only when relevant)
- [ ] **Codebase Pitfalls** — add any new trap discovered: wrong column name, silent failure,
  broken ENUM value, RBAC gap, CSS specificity surprise, browser incompatibility, etc.
- [ ] **Backend / Frontend Patterns** — add any new reusable pattern established
- [ ] Do NOT add task notes or progress — those go in PRODUCT_BACKLOG and PRODUCT_LOG

---

## Key document pointers

| Need | File |
|---|---|
| Governance rules (all agents) | `docs/GOVERNANCE.md` |
| Planned / done / deferred features | `docs/PRODUCT_BACKLOG.md` |
| Why decisions were made | `docs/PRODUCT_LOG.md` |
| Codebase pitfalls and gotchas | `docs/AI_TOOL_HANDOFF_GUIDE.md` |
| Schema source of truth | `docs/06_schema/schema_v1_full.sql` |
| Historical QA results | `tests/TEST_RESULTS.md` (read-only) |
| Original pre-build specs (archived) | `docs/11_build_specs/` (do not update) |
| Claude Code system instructions | `.claude/CLAUDE.md` (Claude only — mirrors GOVERNANCE.md) |
