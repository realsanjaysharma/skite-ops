# Skite Ops — Agent Start

> **Every agent reads this file first, every session, before touching any code.**
> Update the "Last completed" and "Current focus" sections before ending your session.

---

Last updated by: Claude Sonnet 4.6 — 2026-05-21
Last commit: `d35967c feat(supervisor-upload): mobile-first redesign`

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
| `65112ac` | QA batch: 8 targeted fixes (upload/serve scope, IS/RQ codes, settings, cycle auto-close, SPA refresh, form validation, belt dropdown, HTML badges) |
| `4ff3080` | Authority View v1: gallery, belt-name filters, date range, group-by, JSZip bulk download, mobile swipe, photo preview modal |
| `f392038` | Authority View UX polish: collapsible filters, compact stat cards, card layout, Refresh moved, sticky group headers, keyboard nav, swipe gestures, per-belt photo count, auto-swap dates |
| `1ab21c1` | Shared components: `UI.panel(collapsible)`, `UI.statGrid()`, `openPhotoGallery()`, `.photo-thumb` CSS — wired to Upload Review, Client Media Library, Task Progress |
| `d35967c` | Supervisor + Outsourced Upload: work type chips, mobile camera picker, thumbnail preview, XHR progress bar, success card with My Uploads link |

---

## Current focus

**In-field user pages — Green Belt Supervisor role.**

Pages in scope: `green_belt.supervisor_upload` ✅ done, `green_belt.my_uploads` ← next.

After that: Head Supervisor pages (`green_belt.watering_oversight` and related).

---

## What NOT to touch right now

- **Authority View** — stable after multiple polish passes. Do not refactor.
- **`uploadView` shared function** — just redesigned. Do not change without instruction.
- **`openPhotoGallery()`** — shared function, used by 4 pages. Changes affect all.
- **`UI.panel()` in `ui.js`** — extended with `collapsible` option. Test any changes across pages.
- **`tests/TEST_RESULTS.md`** — QA phase is archived. Do not add new test results there.

---

## Known open issues

| Issue | Page | Severity | Notes |
|---|---|---|---|
| `green_belt.my_uploads` is a basic table | Supervisor | Medium | Next improvement target — needs gallery view like Authority View |
| `[hidden]` CSS fix applied globally | All | Low | Added `[hidden] { display:none !important }` to fix upload form — verify no regressions on other pages |

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
- [ ] Page Status table — update status of every page you touched (even 🔧 partial)
- [ ] Planned section — add any new items identified during your session
- [ ] Deferred section — add anything explicitly decided not to do yet

### `docs/PRODUCT_LOG.md`
- [ ] Append a dated entry for every significant decision made this session
- [ ] Include: what was observed → what was decided → what was deferred and why
- [ ] Keep it short — 3–6 bullet points per decision is enough

---

## Key document pointers

| Need | File |
|---|---|
| Full governance rules | `.claude/CLAUDE.md` |
| Planned / done / deferred features | `docs/PRODUCT_BACKLOG.md` |
| Why decisions were made | `docs/PRODUCT_LOG.md` |
| Codebase pitfalls and gotchas | `docs/AI_TOOL_HANDOFF_GUIDE.md` |
| Schema source of truth | `docs/06_schema/schema_v1_full.sql` |
| Historical QA results | `tests/TEST_RESULTS.md` (read-only) |
| Original pre-build specs (archived) | `docs/11_build_specs/` (do not update) |
