# Skite Ops — Product Backlog

Tracks all product work after the initial build and QA phase.
One entry per feature/improvement. No separate spec files — key decisions live here.

Status icons: ✅ Done · 🔄 In Progress · 📋 Planned · ⏸ Deferred

---

## ⚠️ AGENT MAINTENANCE CONTRACT

**This file goes stale if agents do not update it.** Every agent that completes work
in a session MUST update the following sections before ending the session:

| Section | Update when |
|---|---|
| Feature entry status (✅ / 🔄 / 📋) | When you start or finish a feature |
| Feature entry — add commit hash | When work is committed |
| **Improvement Sequence table** | When a user role is started or completed — update the overall status column |
| **User-level status header** in Page Status | When any page for that user changes — update "X of Y pages done" |
| **Per-page row** in Page Status | When ANY page is improved, even partially — update status + notes |
| Planned / Deferred sections | When new work is identified or a decision is deferred |

**Do not end a session without updating all three levels of the Page Status section:**
the Improvement Sequence table (user-level), the role section header (pages done count),
and the individual page rows. Each level tells the next agent something different.
A stale table is worse than no table — it creates false confidence.

The same mandate applies to `docs/AGENT_START.md` (current focus + last completed)
and `docs/PRODUCT_LOG.md` (decisions made this session).

---

## ✅ QA Bug Fixes — Batch 1 (commit `65112ac`, 2026-05-18)

Post-QA targeted fixes based on T01–T70 test failures, implemented by Codex then
browser-verified by Claude Sonnet 4.6.

**Fixed:**
- `upload/serve` had `module_key: 'green_belt.detail'` blocking non-OPS roles (T70)
- Issue + Request sequence codes (IS-00000 / RQ-00000) missing from API responses (T25, T27)
- PHP undefined-id notice on `settings/update` (T39)
- Belt cycle not auto-closing when `permission_end_date` moved to past (T49)
- Supervisor Upload belt field was a free-text number input, not a dropdown (T12)
- Section-header badges rendered as raw HTML `<span style=...>` instead of styled (T45)
- Form required-field errors were silent — no visible message (T46)
- SPA F5 refresh was resetting to role's default landing instead of preserving the current module hash (T58)
- T01/T22: AUTHORITY_REPRESENTATIVE landing showed "Forbidden" (fixed indirectly by above)
- T14/E2E-07: HEAD_SUPERVISOR watering correction was fully blocked (fixed by watering service logic)

---

## ✅ Authority View — Full Redesign (commits `4ff3080`, `f392038`, 2026-05-19)

Replaced the old flat table with a gallery-first view built for the AUTHORITY_REPRESENTATIVE
role, which is primarily a mobile/tablet user reviewing approved proof photos.

**What was built:**
- Belt name dropdown (scoped to AR's historical assignments), date/date-range pickers, work type dropdown, group-by selector
- Gallery cards with thumbnail, belt name, date, time-only, supervisor, work type — all on card
- Group by: Date / Belt / Work Type
- Per-card checkbox + select-all-in-group + select-all-on-page
- Download: single photo, selected ZIP (JSZip), loaded-all ZIP
- JSZip 3.10.1 vendored at `public/js/lib/jszip.min.js`
- Photo preview modal: Prev/Next, keyboard nav (← → Esc D), mobile swipe gestures, metadata, Download
- `upload/serve?download=1` for attachment mode
- New backend endpoint `authority/belt-options` with per-belt photo counts
- `date_from` / `date_to` support on `authority/view` and `authority/summary`
- Default load: today only (50 photos max). Load 50 More pagination
- Broad date range warning (> 7 days)
- Sticky group headers, floating bulk action bar, active filter chips
- Auto-swap From/To if entered in wrong order

**Decisions:**
- WhatsApp share removed from v1 (wa.me URLs are text-only — cannot attach images). See ⏸ Deferred section.
- Belt options include historical assignments (not just currently active) — product owner decision 2026-05-19
- Group-by options: Date, Belt, Work Type only (no Supervisor)
- Bulk download cap: 50 photos per ZIP
- `authority/share-helper` route kept in backend for future v2 use; frontend does not call it

---

## ✅ Shared UI Components (commit `1ab21c1`, 2026-05-20)

Promoted three Authority View patterns into universally reusable components.

**`UI.panel(title, body, actions, options)`** extended with:
- `{ collapsible: true }` — renders chevron toggle in section header
- `{ defaultOpen: false }` — starts collapsed
- `{ alwaysVisible: html }` — slot outside the collapsible body (used for filter chips)
- Toggle wired globally in `app.js` via event delegation — no per-page JS needed

**`UI.statGrid(items)`** — new method:
- Compact centred stat cards (half height of old `UI.cards()`)
- `.stat-compact-grid` / `.stat-compact` / `.stat-compact-label` / `.stat-compact-value`

**`openPhotoGallery(items, startIndex)`** — new top-level function in `modules.js`:
- Prev/Next navigation, counter ("2 of 50"), metadata panel, Download, keyboard nav, swipe
- Used by: Authority View, Upload Review, Client Media Library, Task Progress proofs

**`.photo-thumb` CSS class** — standard 48px thumbnail replacing inline styles across pages.

**Pages updated to use shared components:**
- Upload Review: thumbnails now use `.photo-thumb` + `openPhotoGallery` (50-photo gallery with prev/next)
- Client Media Library: same
- Task Progress: inline `onclick` replaced with `openPhotoGallery`

---

## ✅ Authority View — UX Polish Pass 2 (commit `f392038`, 2026-05-20)

Additional improvements based on product owner review of the live page.

- Page title: "Daily Work and Maintenance Photos" (was "Authority View")
- Subtitle: "filter, browse, download" only
- Refresh button back in page header
- Download Loaded Photos button stays in Work photos panel
- Filters panel: collapsible (default collapsed) using `UI.panel(collapsible)`
  — chips always visible when collapsed so AR knows what filter is active
- Summary stats: compact centred via `UI.statGrid()`
- Card belt row: removed "BELT" label prefix — just shows belt code + name
- Card row 2: Date (human format) | Time-only (no date repeated)
- Card row 3: Supervisor name fills line; Work Type right-aligned on same line; wraps below left-aligned only when supervisor name is too long — uses `justify-content: space-between + flex-wrap`
- `[hidden] { display: none !important }` added globally to prevent CSS display values leaking through HTML hidden attribute

---

## ✅ Supervisor + Outsourced Upload — Mobile Redesign (commit `d35967c`, 2026-05-21)

Applies to both `green_belt.supervisor_upload` and `green_belt.outsourced_upload`
(shared `uploadView` function).

**What was built:**
- Upload type toggle: "Work proof" / "Issue report" as large touch chips
- Work type chips: Routine / Repair / Planting / Watering / Cleaning with Phosphor icons — visible only for Work proof, required before submit
- Mobile camera picker: full-width dashed button "Take a photo or choose from gallery", `accept="image/*"` without `capture` attribute (gives full OS sheet: camera + gallery on Android/iOS)
- Photo preview: file selection tracked in JS array (FileList is immutable), thumbnails rendered with remove-per-photo + "Clear all"
- Submit button updates live: "Select photos to upload" → "Upload 3 photos"
- Real XHR upload progress: `uploadWithProgress()` helper, progress bar animates 0→100%, label shows "Uploading 3 photos… 42%"
- Success card: "3 photos uploaded successfully" + "Upload more" + "View My Uploads →" link

---

## ✅ My Uploads Gallery (commit `ea9d580`, 2026-05-21)

`green_belt.my_uploads` redesigned from a 6-column generic table into a photo gallery.
Applies to both GREEN_BELT_SUPERVISOR and OUTSOURCED_MAINTAINER (shared page).

**What was built:**
- Gallery card grid (4-column auto-fill, 2-column on mobile)
- Each card: thumbnail + belt name + date/time + upload type badge (Work/Issue with icon) + comment preview (2-line clamp) + delete control
- Photo preview via `openPhotoGallery` — navigates across all visible uploads (1 of 27 etc.), keyboard + swipe
- Self-delete: 5-minute window matches `UPLOAD_SELF_DELETE_WINDOW_MINUTES = 5` in `config/constants.php`. Within window → red Delete button + live second countdown. After window → "Window closed" badge. Confirmation modal before delete.
- Filters: 4 large tappable date chips (Today / Yesterday / Last 5 Days / Last 7 Days), max 7-day cap enforced by chips. Active chip is solid green. No date pickers, no type filter, no Apply button — chips auto-navigate on tap.
- Belt dropdown: shown only if user has 2+ assigned belts; hidden for single-belt users.
- Group by (Date / Belt): simple select, auto-applies on change.
- "Showing X of N uploads" count bar. Grouped gallery with sticky group headers.
- RBAC fix: `OUTSOURCED_MAINTAINER` was missing `green_belt.my_uploads` scope in seed migration. Fixed in `001_seed_foundation.sql` + added `migrations/004_fix_outsourced_my_uploads_scope.sql`.
- Backend: `UploadController::myList` now accepts `parent_id` filter for belt-level filtering.

---

## 📋 Planned — My Uploads Gallery

Improve `green_belt.my_uploads` to use gallery cards like Authority View.
Supervisor should be able to see their own uploads as thumbnails with metadata,
use `openPhotoGallery` for preview, and have basic filters (date, belt, type).

No bulk download needed here — supervisor's own uploads, not authority proof.

---

## 📋 Planned — Head Supervisor Pages

`green_belt.watering_oversight` and related HS pages.
Observations to address (from initial QA):
- T14/E2E-07 fixed at backend; verify the watering correction UI communicates the override-reason requirement clearly to the user
- Oversight tables are functional but not mobile-optimised

---

## 📋 Planned — Client Logo Feature

**Decision log:**
- **One logo per client name** — keyed to `campaigns.client_name` (not per campaign)
- **Storage:** File uploaded to server, stored in `storage/logos/`, served via new `client-logo/serve` route
- **Multiple campaigns on one belt:** Show logo from the **oldest active campaign** (MIN `campaign_sites.linked_from_date`)

**Schema change required — new table:**
```sql
CREATE TABLE client_logos (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_name         VARCHAR(150) NOT NULL UNIQUE,
  logo_path           VARCHAR(500) NOT NULL,
  uploaded_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_cl_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id)
);
```

**New backend layer:** `ClientLogoController → ClientLogoService → ClientLogoRepository`
Routes: `client-logo/upload`, `client-logo/serve`, `client-logo/list`, `client-logo/delete`

**Belt query enrichment** — these 6 service methods need a logo merge step:
`BeltService::getBelts`, `BeltService::getBeltById`, `UploadService::getTargets`,
`AuthorityViewService::getBeltOptionsForAuthority`, `AuthorityViewService::getView`,
`WateringService::getOversight`

**Frontend:** New `governance.client_logos` management page (OPS) + `.belt-logo-avatar` 24px circle displayed universally next to belt names.

**Implementation order:** Schema → storage dir → backend layer → belt query enrichment → frontend management page → avatar display in 6 locations.

**Constraint:** `client_name` matching is string-exact. Normalise `campaigns.client_name` capitalisation before implementing.

**Do after:** All in-field user page improvements are complete.

---

## 📋 Planned — Filter Panels on Other List Pages

Now that `UI.panel(collapsible)` exists, the following pages have filter panels that
would benefit from defaulting to collapsed (they take up significant vertical space):

- `green_belt.upload_review` (Upload Review)
- `green_belt.issue_management` (Issues)
- `task.request_intake` (Task Requests)
- `task.management` (Task Management)
- `green_belt.master` (Green Belts list)

Implement as a sweep: one commit, add `{ collapsible: true, defaultOpen: false }` to
the `UI.panel('Filters', ...)` call in each of these views.

---

## ⏸ Deferred — WhatsApp Share with Photo Attachments

**Why deferred:** `wa.me/?text=` URLs are text-only by design — WhatsApp's URL scheme
cannot carry image attachments. The only way to attach images is the WhatsApp Business
Cloud API (paid, requires Meta verification, external service) which violates the
shared-hosting governance rule ("no external services").

**What exists:** `authority/share-helper` backend route generates a pre-formatted text
summary. Route is kept but no UI calls it in v1.

**v2 approach (when ready):** Web Share API (`navigator.share({ files, text })`) —
works on Android Chrome and iOS Safari when photos are selected. Falls back to
download-then-attach on desktop. Requires HTTPS in production.

**Ticket to open when ready:** Implement W1 (Web Share API) as primary + W2
(JSZip download + clipboard text) as fallback. See PRODUCT_LOG.md 2026-05-18 entry
for the full options analysis.

---

## Page Status — All Roles

**Every agent must update this section at THREE levels when any page changes:**
1. **Improvement Sequence table** — overall user status column
2. **Role section header** — pages-done count
3. **Individual page row** — status + notes

A page not updated here has not been reviewed since the initial build.

Status key:
- ✅ **Fully improved** — deliberate UX/mobile review and redesign
- 🔧 **Component upgrade only** — shared component added (gallery / collapsible / stat grid) but no page-specific review done yet
- 🔄 **In progress** — currently being worked on
- ⬜ **Not yet reviewed** — untouched since initial build

---

### Improvement Sequence

The product owner's strategy: **update users in order from least complex to most complex.**
Roles with fewer pages and simpler workflows come first. OPS_MANAGER (25+ pages) comes last.
Note: roles that share pages (GBS + Outsourced share My Uploads; Sales/CS/MP share Task Progress)
are worked together so shared improvements land once for all.

**Update the Overall Status column every time a role's pages change.**

| # | Role | Total pages | Pages done | Overall Status |
|---|---|---|---|---|
| 1 | AUTHORITY_REPRESENTATIVE | 1 | 1 | ✅ Complete |
| 2 | GREEN_BELT_SUPERVISOR | 2 | 2 | ✅ Complete |
| 3 | OUTSOURCED_MAINTAINER | 2 | 2 | ✅ Complete (shares both pages with GBS) |
| 4 | MONITORING_TEAM | 2 | 0 | ⬜ Not started |
| 5 | FABRICATION_LEAD | 2 | 0 | ⬜ Not started |
| 6 | SALES_TEAM / CLIENT_SERVICING / MEDIA_PLANNING | 2–3 | 1 partial | 🔧 Partial (shared gallery only) |
| 7 | MANAGEMENT | 1–2 | 0 | ⬜ Not started |
| 8 | HEAD_SUPERVISOR | 5 | 0 | ⬜ Not started |
| 9 | OPS_MANAGER | 25+ | 3 partial | 🔧 Partial (targeted fixes + shared components) |

---

---
### 1 · AUTHORITY_REPRESENTATIVE — ✅ Complete (1 of 1 pages done)

| Page | Module key | Status | Notes |
|---|---|---|---|
| Authority View | `green_belt.authority_view` | ✅ Fully improved | Full gallery redesign — belt name dropdown with photo counts, date range, group-by, JSZip bulk download, preview modal with keyboard nav + swipe, collapsible filters, compact stat cards |

---

### 2 · GREEN_BELT_SUPERVISOR — ✅ Complete (2 of 2 pages done)

| Page | Module key | Status | Notes |
|---|---|---|---|
| Supervisor Upload | `green_belt.supervisor_upload` | ✅ Fully improved | Work type chips, mobile camera picker, thumbnail preview, XHR progress, success card with My Uploads link |
| My Uploads | `green_belt.my_uploads` | ✅ Fully improved | Gallery cards, openPhotoGallery (1 of 27), 5-min self-delete countdown, "Window closed" badge, collapsible filters |

---

### 3 · OUTSOURCED_MAINTAINER — ✅ Complete (2 of 2 pages done)

Shares both pages with GREEN_BELT_SUPERVISOR — completing GBS completed this role simultaneously.

| Page | Module key | Status | Notes |
|---|---|---|---|
| Outsourced Upload | `green_belt.outsourced_upload` | ✅ Fully improved | Shares `uploadView` — identical redesign |
| My Uploads | `green_belt.my_uploads` | ✅ Fully improved | Shares page with GBS — same gallery redesign |

---

### 4 · MONITORING_TEAM — ⬜ Not started (0 of 2 pages done)

| Page | Module key | Status | Notes |
|---|---|---|---|
| Monitoring Upload | `monitoring.upload` | ⬜ Not yet reviewed | Consider applying `uploadView` pattern if applicable |
| Monitoring History | `monitoring.history` | ⬜ Not yet reviewed | Photo history — consider gallery view |

---

### 5 · FABRICATION_LEAD — ⬜ Not started (0 of 2 pages done)

| Page | Module key | Status | Notes |
|---|---|---|---|
| My Tasks | `task.my_tasks` | ⬜ Not yet reviewed | |
| Task Detail | `task.detail` | ⬜ Not yet reviewed | |

---

### 6 · SALES_TEAM / CLIENT_SERVICING / MEDIA_PLANNING — 🔧 Partial (0 of 2 pages fully done)

Three roles that share the same pages. Work done here lands for all three simultaneously.

| Page | Module key | Status | Notes |
|---|---|---|---|
| Task Progress | `task.progress_read` | 🔧 Component upgrade | `openPhotoGallery` wired to task proof thumbnails — no page-specific review done yet |
| Task Requests | `task.request_intake` | ⬜ Not yet reviewed | RQ-XXXXX codes showing (T27 fix). Page itself not reviewed |

---

### 7 · MANAGEMENT — ⬜ Not started (0 of 1 pages done)

| Page | Module key | Status | Notes |
|---|---|---|---|
| Management Dashboard | `dashboard.management` | ⬜ Not yet reviewed | Read-only overview — stat cards could use `UI.statGrid()` |

---

### 8 · HEAD_SUPERVISOR — ⬜ Not started (0 of 5 pages done)

| Page | Module key | Status | Notes |
|---|---|---|---|
| Watering Oversight | `green_belt.watering_oversight` | ⬜ Not yet reviewed | Backend correction fixed (T14). UI override-reason flow not verified. Tables not mobile-optimised |
| Supervisor Attendance | `green_belt.supervisor_attendance` | ⬜ Not yet reviewed | |
| Labour Entries | `green_belt.labour_entries` | ⬜ Not yet reviewed | |
| Issue Management | `green_belt.issue_management` | ⬜ Not yet reviewed | IS-XXXXX codes showing (T25 fix). Page not reviewed |
| Green Belt Dashboard | `dashboard.green_belt` | ⬜ Not yet reviewed | |

---

### 9 · OPS_MANAGER — 🔧 Partial (0 of 25+ pages fully done)

OPS accesses all modules. Reviewed last due to volume and complexity.
Pages listed where any improvement has landed — all others are completely untouched.

**Update the count in the Improvement Sequence table when any OPS page reaches ✅.**

| Page | Module key | Status | Notes |
|---|---|---|---|
| Authority View | `green_belt.authority_view` | ✅ Fully improved | OPS can access — improvement was AR-focused but OPS benefits too |
| Supervisor Upload | `green_belt.supervisor_upload` | ✅ Fully improved | OPS can access — improvement was GBS-focused |
| Upload Review | `green_belt.upload_review` | 🔧 Component upgrade | `openPhotoGallery` on thumbnails. No page-specific review |
| Task Progress | `task.progress_read` | 🔧 Component upgrade | `openPhotoGallery` on proof photos |
| Client Media Library | `commercial.client_media_library` | 🔧 Component upgrade | `openPhotoGallery` wired. No page-specific review |
| Green Belts List | `green_belt.master` | ⬜ Not yet reviewed | Create Belt form has visible validation (T46 fix) |
| Belt Detail | `green_belt.detail` | ⬜ Not yet reviewed | |
| Issue Management | `green_belt.issue_management` | ⬜ Not yet reviewed | IS-XXXXX codes showing |
| Task Requests | `task.request_intake` | ⬜ Not yet reviewed | RQ-XXXXX codes showing |
| Master Dashboard | `dashboard.master_ops` | ⬜ Not yet reviewed | |
| Green Belt Dashboard | `dashboard.green_belt` | ⬜ Not yet reviewed | |
| Advertisement Dashboard | `dashboard.advertisement` | ⬜ Not yet reviewed | |
| Monitoring Dashboard | `dashboard.monitoring` | ⬜ Not yet reviewed | |
| Maintenance Cycles | `green_belt.maintenance_cycles` | ⬜ Not yet reviewed | |
| Watering Oversight | `green_belt.watering_oversight` | ⬜ Not yet reviewed | |
| Supervisor Attendance | `green_belt.supervisor_attendance` | ⬜ Not yet reviewed | |
| Labour Entries | `green_belt.labour_entries` | ⬜ Not yet reviewed | |
| Task Management | `task.management` | ⬜ Not yet reviewed | |
| Task Detail | `task.detail` | ⬜ Not yet reviewed | |
| Worker Allocation | `task.worker_allocation` | ⬜ Not yet reviewed | |
| Alert Panel | `governance.alert_panel` | ⬜ Not yet reviewed | Section badges fixed (T45) |
| Audit Logs | `governance.audit_logs` | ⬜ Not yet reviewed | |
| User Management | `governance.user_management` | ⬜ Not yet reviewed | |
| Rejected Upload Cleanup | `governance.rejected_upload_cleanup` | ⬜ Not yet reviewed | |
| System Settings | `settings.system` | ⬜ Not yet reviewed | PHP notice on update fixed (T39) |
| Monthly Reports | `reports.monthly` | ⬜ Not yet reviewed | |
| Site Master | `advertisement.site_master` | ⬜ Not yet reviewed | |
| Campaign Management | `advertisement.campaign_management` | ⬜ Not yet reviewed | |
| Free Media | `media.free_media_inventory` | ⬜ Not yet reviewed | |
| Media Planning View | `commercial.media_planning_inventory` | ⬜ Not yet reviewed | |
| Monitoring Plan | `monitoring.plan` | ⬜ Not yet reviewed | |
| Monitoring History | `monitoring.history` | ⬜ Not yet reviewed | |
