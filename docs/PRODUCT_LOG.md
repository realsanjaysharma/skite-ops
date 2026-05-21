# Skite Ops — Product Decision Log

Append-only. Never edit past entries. Each session adds a dated block.
Answers: *"Why does the app work this way?"* months from now.

---

## 2026-05-18 — Authority View: Discovered gaps, design approved

**Observed by product owner (logged in as ivan@gov.in / AUTHORITY_REPRESENTATIVE):**
- Belt and Supervisor filter inputs were free-text number IDs, not name dropdowns
- WhatsApp share button never appeared even though the setting was enabled in DB
- No gallery/thumbnail view — only a flat table with 60×60 thumbnails
- No photo selection or bulk download
- No download button anywhere (page spec explicitly says "filtered download")
- Work type filter was a select but the other filters were not

**Root cause of WhatsApp button:** Code called `settings/list` to check if WhatsApp was enabled, but AUTHORITY_REPRESENTATIVE has no `settings.system` module scope → 403 → catch swallowed error → button never rendered, even when DB had `authority_whatsapp_helper_enabled = '1'`.

**WhatsApp share — why not implemented in v1:**
`wa.me/?text=` URLs are text-only. WhatsApp's URL scheme has no image attachment parameter.
The only real option (Web Share API with `navigator.share({ files })`) requires HTTPS in
production and has inconsistent desktop support. Decision: remove the broken button
entirely for v1, keep `authority/share-helper` route in backend for future v2.

**Belt options: historical vs active-only:**
Product owner confirmed belt dropdown should include ALL belts ever assigned to the AR
(active + historical with `end_date` in past), not just today-active. This allows the AR
to browse proof from belts they were previously assigned to.

**Group-by Supervisor removed:**
Product owner confirmed Supervisor is not needed as a group-by option (Date / Belt /
Work Type only). Supervisor name still appears on each photo card as metadata.

**Bulk download mechanism — client-side JSZip chosen:**
PHP `zip` extension was OFF on this XAMPP install. Project has no Composer/vendor directory.
JSZip (browser-side ZIP) chosen: zero server pressure, immune to `max_execution_time`,
reuses existing `upload/serve` RBAC. Cap: 50 photos per bundle. See PRODUCT_BACKLOG.md
for full options analysis (A: native PHP ZIP / B: streamed ZipStream-PHP / C: JSZip / D: sequential).

---

## 2026-05-19 — Shared components decision

**Why extract `openPhotoGallery` from Authority View:**
Upload Review, Client Media Library, and Task Progress all showed photos via raw
`UI.showModal('Photo', '<img...')` with no navigation, no keyboard, no download.
Authority View had all of this. Extracting it once means all 4 pages benefit and any
future photo-showing page gets it for free by calling one function.

**Why `UI.panel(collapsible)` in ui.js rather than page-specific code:**
5+ pages have filter panels that take up vertical space on mobile. Making it a first-class
option in `UI.panel()` with global event delegation in `app.js` means any page adopts
it with one added option — no per-page toggle JS needed.

**`[hidden]` CSS fix:**
Added `[hidden] { display: none !important }` globally. Reason: CSS rules with explicit
`display: flex / grid / block` override the browser's UA stylesheet `[hidden] { display: none }`.
This was silently showing the upload success card on page load before any upload happened.
The fix is correct standard practice — the HTML `hidden` attribute should always win.

---

## 2026-05-20 — Authority View UX polish: card layout decisions

**"BELT" label removed from belt row:**
Product owner circled it in a screenshot. The label adds no value when belt code + name is
already self-explanatory. Removed.

**Row 3 layout (Supervisor | Work Type) — space-between + flex-wrap:**
Three iterations before landing on the right approach:
1. `max-width: 65%` on supervisor → caused "Test Superv…" truncation on mobile
2. `flex: 1 1 100%` on supervisor → work type always on new line (even on desktop)
3. Final: `flex: 0 0 auto; white-space: nowrap` on both + `justify-content: space-between` + `flex-wrap: wrap` on the row

With `space-between + flex-wrap`: when both fit on one line they're naturally pushed apart
(supervisor left, work type right). When supervisor is long and fills the row, work type
wraps to the next line LEFT-aligned (single item with `space-between` = left). No JS,
no media queries — pure CSS flex behaviour.

---

## 2026-05-21 — Client logo feature: decisions captured

**One logo per client name, not per campaign:**
Clients (Samsung, Nike, etc.) have one brand identity regardless of which specific campaign
is running. Storing per-client avoids logo drift when the same client runs multiple campaigns.

**Storage: file on server, not external URL:**
External URLs depend on internet connectivity. Supervisors and AR users are often in the
field with unreliable connections. Server-stored logos load via the same origin as the app.

**Multiple campaigns on one belt → oldest first:**
When a belt has two active campaigns/sites, show the oldest client logo (MIN linked_from_date).
Oldest = most established relationship = most likely the primary/anchor tenant.

**Implementation deferred:** Client logo is a "nice to have" for recognition. In-field user
pages (Supervisor, Head Supervisor) affect daily operations and take priority.

---

## 2026-05-21 — Documentation system redesign

**Why TEST_RESULTS.md is no longer updated:**
That file was a live tracker for the agent-driven QA phase (T01–T70). The QA phase is
complete. Continuing to add product observations and specs to a test results file was
creating a confused document that served no single purpose well.

**New system:**
- `AGENT_START.md` — first thing every agent reads; current state + what not to touch
- `PRODUCT_BACKLOG.md` — all features (done / planned / deferred) in one status view
- `PRODUCT_LOG.md` — this file; append-only decisions log
- `docs/11_build_specs/` — frozen as pre-build historical archive; not updated again

**Why pre-build specs are frozen, not updated:**
Those documents were written before a line of code existed. The product has since been
built, tested, changed by 3+ AI agents, and improved based on real usage. Maintaining
accuracy of 11 original spec files simultaneously is impossible and creates false confidence
("the spec says X so it must work that way"). The code + git history + PRODUCT_BACKLOG.md
is the truth. Pre-build specs remain as design-intent references only.

## 2026-05-22 — My Uploads gallery + documentation system

**My Uploads self-delete window:** 5 minutes confirmed from `config/constants.php UPLOAD_SELF_DELETE_WINDOW_MINUTES = 5`. UI checks client-side from `created_at` to show countdown vs "Window closed" badge — avoids a server round-trip for every card render. Backend still enforces the window server-side; client check is UX only.

**Authority visibility not shown on My Uploads:** The `upload/my-list` API strips `authority_visibility` per Page Spec §9 (supervisor should not see approval status). This is intentional — supervisor's job is to submit, not to track review outcomes. Design kept as-is.

**Documentation system established:** Created `AGENT_START.md`, `PRODUCT_BACKLOG.md`, `PRODUCT_LOG.md`, `GOVERNANCE.md`. Archived `docs/11_build_specs/` with notice. Rewrote `docs/README.md`. `AI_TOOL_HANDOFF_GUIDE.md` updated to point to new files. `CLAUDE.md` updated to reference `GOVERNANCE.md` as neutral path for all agents. Added Improvement Sequence table to backlog with hierarchy order (AR → GBS → Outsourced → Monitoring → Fabrication → Sales/CS/MP → Management → HS → OPS). Mandatory end-of-session update checklist added to AGENT_START.md.
