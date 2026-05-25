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

---

## 2026-05-22 — My Uploads filter simplification for field users

**Why chips instead of date pickers:**
Supervisors and outsourced maintainers are low-literacy field users submitting photos between tasks on mobile. Free-text date inputs, upload type selectors, and Apply buttons create unnecessary friction. A row of 4 large tappable chips (Today / Yesterday / Last 5 Days / Last 7 Days) covers 95%+ of real use cases with zero cognitive load.

**Why max 7 days:**
Supervisors do not need to audit old history — that is an OPS/HS task. 7 days is enough to check "did my upload from last week go through?" without opening up the full archive.

**Belt dropdown visibility rule:**
Hide the belt dropdown when the supervisor is assigned to only one belt — they have no choice to make. Show it only for 2+ belts. Reduces clutter for the common single-belt case.

**OUTSOURCED_MAINTAINER RBAC bug:**
Seed migration `001_seed_foundation.sql` gave the outsourced role only `green_belt.outsourced_upload`. `green_belt.my_uploads` was missing, so outsourced users could submit uploads but had no way to review them. Fixed in the seed and patched via `migrations/004`. `navigation.js` already had the outsourced role in the `my_uploads` roles array — the bug was purely in the DB scope.

**Default chip: Today (not Today+Yesterday):**
First instinct was to default to Yesterday+Today so recent uploads are visible. Changed to Today-only after discussion — supervisor's primary need is to check what they submitted today. Yesterday is one tap away. Keeps the default view focused.

---

## 2026-05-24 — Monitoring Upload + History UX improvements

**Monitoring Upload page redesigned** with mobile camera picker, site search dropdown, discovery toggle (Regular Visit / Free Media Discovery), photo preview grid, XHR progress bar, and recent uploads horizontal strip. Discovery mode auto-assigns uploads to `FREE_MEDIA_DEFAULT_SITE_ID = 38`.

**Monitoring History page redesigned** with gallery cards, date chips (Today/Yesterday/Last 5/Last 7), site category filter chips, discovery filter chips, photo preview modal via `openPhotoGallery`, and self-delete with live countdown on own uploads.

**Three bugs fixed:** (1) Free media auto-assign wasn't linking to default site correctly, (2) upload scope check was blocking monitoring surface, (3) photo URL construction was broken for monitoring uploads.

---

## 2026-05-24 — Media Discovery: separate page designed

**Problem identified:** All free media discoveries sharing one default site ID (38) would overwrite each other's `free_media_records` — only the last discovery's metadata would survive. Each discovery needs its own site.

**Approach B chosen (over A):** Dedicated `MediaDiscoveryService` + reuse `UploadService` for file storage. Approach A was modifying UploadService itself — rejected because discovery has different intent (reporting opportunities vs routine proof), different inputs (no site selection needed), and different downstream handling (site creation + free media record).

**Separate page from monitoring.upload:** Product owner confirmed discovery should be its own page (`monitoring.discovery`), not a toggle on the upload page. The toggle will be removed from monitoring.upload when discovery page is built.

**Per-discovery placeholder sites:** Each discovery creates a site with `site_code = 'DISC-YYYYMMDD-NNN'`, `is_active = 0`. This makes discovery sites invisible to all regular dropdowns (which filter `WHERE is_active = 1`) while keeping them in the sites table for proper FK relationships.

**Distinguishing inactive site types:** Four patterns:
- Normal active: regular codes, `is_active = 1`
- Deactivated: regular codes, `is_active = 0`
- Discovery placeholder: `DISC-*`, `is_active = 0`
- Merged (absorbed): `MERGED-*`, `is_active = 0`

**GPS proximity dedup (50m, Haversine):** When a new discovery has GPS, check for existing `DISC-*` sites within 50m that also have a pending `free_media_records` with `status = 'DISCOVERED'`. If match, add photos to existing discovery instead of creating new site. Important: only matches pending discoveries — if site was already confirmed/expired/consumed, a new discovery is created (situation may have changed).

**Dual GPS strategy:** Browser `navigator.geolocation` (requested on submit click, not page load) takes priority over photo EXIF GPS. Browser GPS = person is physically at the spot. EXIF GPS = backup for gallery uploads. Both are stored: site record gets best available, upload record keeps EXIF values.

**discovered_date preservation on dedup:** When adding photos to an existing discovery via dedup match, the original `discovered_date` is preserved (not overwritten) — the first sighting date is what matters for stale alert calculations.

**Transaction architecture:** MediaDiscoveryService does NOT start its own transaction because `UploadService::createUploadsForSurface()` already calls `$this->uploadRepository->beginTransaction()` internally. MySQL/PDO throws an exception on nested `beginTransaction()`. Site creation auto-commits, then UploadService handles its own transaction for uploads + free_media_record.

**Media Planner notifications:** Computed at runtime (no background jobs per governance). Dashboard counter of pending discoveries, stale alert for discoveries older than `DISCOVERY_PENDING_ALERT_DAYS = 7`, age indicators on each item.

**Merge/dismiss flows:** Merge moves all uploads to keep-site, expires discard's free_media_record, renames discard site_code to `MERGED-*`. Dismiss sets `is_deleted = 1` on uploads (30-day purge via existing mechanism) and expires the free_media_record. Site stays `is_active = 0` (already invisible).

**Deferred to Phase 2:** Media Planner actions (confirm/merge/dismiss in Free Media Inventory module) — requires separate plan.

**Deferred separately:** Monitoring Upload UX improvements (site search changes, remove site IDs, show site names with client names) — requires separate brainstorming.

---

## 2026-05-25 — Media Discovery Phase 1 implemented

Plan `docs/superpowers/plans/2026-05-24-media-discovery.md` executed end-to-end with four approved deviations (logged below). All PHP syntax + `test_frontend_route_map.php` (213/213) + `test_upload_review_safety.php` (4/4) green. Migration 005 applied locally. Browser smoke test not run from this session — manual checklist sits in AGENT_START.md.

**Deviation 1 — Migration renumbered 004 → 005.** Plan said `004_media_discovery_module.sql`; that filename was already taken by the OUTSOURCED `my_uploads` scope fix from commit `b58f770`. Renumbered to keep migration history append-only.

**Deviation 2 — FreeMediaRepository got one method, not three.** Plan asked for `createDiscoveredRecord()`, `findDiscoveredBySiteId()`, `refreshSourceReference()`. UploadRepository already owns the write path (`findDiscoveryFreeMediaBySiteId`, `createDiscoveryFreeMediaRecord`, `refreshDiscoveryFreeMediaRecord`), called from `UploadService::createOrRefreshDiscoveryRecord()`. Adding the two write methods to FreeMediaRepository would have been dead code — MediaDiscoveryService delegates the write to UploadService anyway. Only `findDiscoveredBySiteId()` was added, used purely for the post-delegate read to fetch the new record_id for the audit/response.

**Deviation 3 — `Api.postFormData` dropped.** `Api.upload(formData)` already exists for multipart uploads, and we needed XHR progress events for parity with monitoring.upload. Generalised the existing `uploadWithProgress(formData, onProgress, route='upload/create')` helper to accept a route argument (default preserves the two existing callers). The new discovery view calls it with `route='discovery/submit'`.

**Deviation 4 — `MAX_UPLOAD_FILES_PER_SUBMISSION` is PHP-only.** Plan referenced it as a JS global. Introduced a `DISCOVERY_MAX_FILES = 10` JS constant with a comment pointing at `config/constants.php` as the source of truth. Future drift would require updating both — flagged in AGENT_START "what NOT to touch".

**Site placeholder strategy preserved as designed.** `DISC-YYYYMMDD-NNN`, `is_active = 0`, `site_category = 'CITY'`, `lighting_type = 'NON_LIT'`. Three retries on `Duplicate entry` for race-condition safety against the UNIQUE constraint on `site_code`. Orphan placeholder sites (when the subsequent upload step fails) are intentionally tolerated — they're invisible to all `WHERE is_active = 1` queries and the next dedup pass within 50m will reuse them.

**Transaction architecture honoured.** MediaDiscoveryService does NOT begin its own transaction. UploadService::createUploadsForSurface() already manages one internally for the uploads + the `createOrRefreshDiscoveryRecord()` write. Nested `beginTransaction()` on the singleton PDO would throw immediately. Site row write auto-commits before the upload begins.

**GPS handling.** Browser geo (5s timeout) is requested **on submit click**, not on page load — keeps the permission prompt user-initiated. EXIF GPS is parsed in JS from the first selected file's first 128 KB (covers the EXIF segment in any real-world JPEG). EXIF coords are also kept and stored on the upload row even when browser geo wins, for forensic purposes (gallery uploads where the photographer was not at the spot at submit time).

**discovery toggle on `monitoring.upload`.** Removed completely: Visit type chips, hidden `js-mon-fmd-auto-site` section, `js-mon-discovery-note`, `discoveryInput` / `discoveryNote` / `siteSection_` / `fmdAutoSite` refs, `FREE_MEDIA_DEFAULT_SITE_ID` JS const, and the `getParentId()` branch that auto-assigned site `38`. `getParentId()` now simply prefers the plan-site dropdown then falls back to the ad-hoc search.

**Phase 2 still untouched.** Media Planner confirm / merge / dismiss flows in Free Media Inventory remain in design only — separate plan needed.
