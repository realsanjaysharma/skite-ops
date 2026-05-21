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
| Page Status table | When ANY page is improved, even partially |
| Planned / Deferred sections | When new work is identified or a decision is deferred |

**Do not end a session without updating the Page Status table.** That table is how the
next agent knows which pages have been improved and which have never been touched.
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

## 🔄 In Progress — Green Belt Supervisor Pages

**`green_belt.my_uploads`** — next improvement target.
Current state: basic data table. Needs gallery view consistent with Authority View style.

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

**Every agent must update this table when any page is changed, even partially.**
A page not in this table has not been reviewed since the initial build.

Status key:
- ✅ **Fully improved** — deliberate UX/mobile review and redesign
- 🔧 **Component upgrade only** — shared component added (gallery / collapsible / stat grid) but no page-specific review done yet
- 🔄 **In progress** — currently being worked on
- ⬜ **Not yet reviewed** — untouched since initial build

---

### AUTHORITY_REPRESENTATIVE

| Page | Module key | Status | Notes |
|---|---|---|---|
| Authority View | `green_belt.authority_view` | ✅ Fully improved | Full gallery redesign — belt dropdown, date range, group-by, JSZip, photo preview modal, mobile swipe, work type chips in filters, stat cards, collapsible filters |

---

### GREEN_BELT_SUPERVISOR

| Page | Module key | Status | Notes |
|---|---|---|---|
| Supervisor Upload | `green_belt.supervisor_upload` | ✅ Fully improved | Work type chips, mobile camera picker, photo preview, XHR progress, success card |
| My Uploads | `green_belt.my_uploads` | 🔄 In progress | Currently a basic table — gallery view next |

---

### OUTSOURCED_MAINTAINER

| Page | Module key | Status | Notes |
|---|---|---|---|
| Outsourced Upload | `green_belt.outsourced_upload` | ✅ Fully improved | Shares `uploadView` with Supervisor Upload — same redesign applies |
| My Uploads | `green_belt.my_uploads` | 🔄 In progress | Same as Supervisor |

---

### HEAD_SUPERVISOR

| Page | Module key | Status | Notes |
|---|---|---|---|
| Watering Oversight | `green_belt.watering_oversight` | ⬜ Not yet reviewed | Backend watering correction fixed (T14). UI override-reason UX not verified. Tables not mobile-optimised |
| Supervisor Attendance | `green_belt.supervisor_attendance` | ⬜ Not yet reviewed | |
| Labour Entries | `green_belt.labour_entries` | ⬜ Not yet reviewed | |
| Issue Management | `green_belt.issue_management` | ⬜ Not yet reviewed | IS-XXXXX codes now showing (T25 fix) |
| Green Belt Dashboard | `dashboard.green_belt` | ⬜ Not yet reviewed | |

---

### MONITORING_TEAM

| Page | Module key | Status | Notes |
|---|---|---|---|
| Monitoring Upload | `monitoring.upload` | ⬜ Not yet reviewed | |
| Monitoring History | `monitoring.history` | ⬜ Not yet reviewed | |

---

### FABRICATION_LEAD

| Page | Module key | Status | Notes |
|---|---|---|---|
| My Tasks | `task.my_tasks` | ⬜ Not yet reviewed | |
| Task Detail | `task.detail` | ⬜ Not yet reviewed | |

---

### SALES_TEAM / CLIENT_SERVICING / MEDIA_PLANNING

These three roles share the same pages.

| Page | Module key | Status | Notes |
|---|---|---|---|
| Task Progress | `task.progress_read` | 🔧 Component upgrade | `openPhotoGallery` wired to task proof thumbnails — no page-specific review done |
| Task Requests | `task.request_intake` | ⬜ Not yet reviewed | RQ-XXXXX codes now showing (T27 fix) |

---

### MANAGEMENT

| Page | Module key | Status | Notes |
|---|---|---|---|
| Management Dashboard | `dashboard.management` | ⬜ Not yet reviewed | |

---

### OPS_MANAGER (key pages — full list is large)

OPS has access to all modules. Pages that have been improved are noted; all others are untouched.

| Page | Module key | Status | Notes |
|---|---|---|---|
| Master Dashboard | `dashboard.master_ops` | ⬜ Not yet reviewed | |
| Green Belt Dashboard | `dashboard.green_belt` | ⬜ Not yet reviewed | |
| Advertisement Dashboard | `dashboard.advertisement` | ⬜ Not yet reviewed | |
| Monitoring Dashboard | `dashboard.monitoring` | ⬜ Not yet reviewed | |
| Green Belts List | `green_belt.master` | ⬜ Not yet reviewed | Create Belt form has visible validation (T46 fix) |
| Belt Detail | `green_belt.detail` | ⬜ Not yet reviewed | |
| Upload Review | `green_belt.upload_review` | 🔧 Component upgrade | `openPhotoGallery` wired to thumbnails (prev/next across 50 photos). No other page review done |
| Issue Management | `green_belt.issue_management` | ⬜ Not yet reviewed | IS-XXXXX codes showing (T25 fix) |
| Maintenance Cycles | `green_belt.maintenance_cycles` | ⬜ Not yet reviewed | |
| Watering Oversight | `green_belt.watering_oversight` | ⬜ Not yet reviewed | |
| Supervisor Attendance | `green_belt.supervisor_attendance` | ⬜ Not yet reviewed | |
| Labour Entries | `green_belt.labour_entries` | ⬜ Not yet reviewed | |
| Authority View | `green_belt.authority_view` | ✅ Fully improved | Full redesign — OPS can access but it is primarily the AR's page |
| Task Management | `task.management` | ⬜ Not yet reviewed | |
| Task Requests | `task.request_intake` | ⬜ Not yet reviewed | RQ-XXXXX codes showing (T27 fix) |
| Task Progress | `task.progress_read` | 🔧 Component upgrade | `openPhotoGallery` on proof photos |
| Task Detail | `task.detail` | ⬜ Not yet reviewed | |
| Worker Allocation | `task.worker_allocation` | ⬜ Not yet reviewed | |
| Alert Panel | `governance.alert_panel` | ⬜ Not yet reviewed | Section badges fixed (T45 fix) |
| Audit Logs | `governance.audit_logs` | ⬜ Not yet reviewed | |
| User Management | `governance.user_management` | ⬜ Not yet reviewed | |
| Rejected Upload Cleanup | `governance.rejected_upload_cleanup` | ⬜ Not yet reviewed | |
| System Settings | `settings.system` | ⬜ Not yet reviewed | PHP notice on update fixed (T39 fix) |
| Monthly Reports | `reports.monthly` | ⬜ Not yet reviewed | |
| Site Master | `advertisement.site_master` | ⬜ Not yet reviewed | |
| Campaign Management | `advertisement.campaign_management` | ⬜ Not yet reviewed | |
| Free Media | `media.free_media_inventory` | ⬜ Not yet reviewed | |
| Client Media Library | `commercial.client_media_library` | 🔧 Component upgrade | `openPhotoGallery` wired. No page-specific review |
| Media Planning View | `commercial.media_planning_inventory` | ⬜ Not yet reviewed | |
| Monitoring Plan | `monitoring.plan` | ⬜ Not yet reviewed | |
| Monitoring History | `monitoring.history` | ⬜ Not yet reviewed | |
