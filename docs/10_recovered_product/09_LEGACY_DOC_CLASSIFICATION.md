# Legacy Doc Classification

## Authority Note

- Purpose: Canonical recovered product support document.
- Authority Level: Product-support truth.
- If Conflict: `docs/10_recovered_product/*` controls product meaning and `docs/11_build_specs/*` controls implementation behavior. This file supports migration planning and must not override either layer.

## Purpose

This file records the post-cleanup status of the legacy docs that remain outside `docs/10_recovered_product/` and `docs/11_build_specs/`.

## Current Categories

### Keep

These remain useful active docs with only normal future maintenance:

- `docs/README.md`
- `docs/04_operations/08_SECURITY_AND_DEPLOYMENT_FINAL.md`
- `docs/04_operations/09_BACKUP_AND_RECOVERY_FINAL_LOCKED.md`
- `docs/04_operations/10_GIT_WORKFLOW_FINAL_LOCKED_V2.md`

### Rewritten Legacy Mirrors

These remain in place for repo-facing orientation, but they must mirror the canon rather than define it:

- `docs/03_context/00_PROJECT_OVERVIEW_FINAL.md`
- `docs/03_context/01_REQUIREMENTS_CONTEXT_FINAL.md`
- `docs/02_interface/02_ROLES_AND_ACCESS_FINAL.md`
- `docs/02_interface/04_PAGE_CATALOG_FINAL_V4_LOCKED.md`
- `docs/01_structure/05_DATA_AND_FLOW_NOTES_FINAL.md`
- `docs/01_structure/06_DECISIONS_LOG_FINAL_LOCKED.md`
- `docs/06_schema/11_SCHEMA_BASELINE_v1_FINAL_WITH_DDL.md`
- `docs/06_schema/12_SCHEMA_SPECIFICATION_v1.md`
- `docs/06_schema/schema_v1_full.sql`

### Removed After Merge Or Archive

These were intentionally removed because their useful content was merged into stronger docs or because they were no longer trustworthy:

- old governance boot and phase-control artifacts
- old duplicate context summaries
- old design-principle and dev-note duplicates
- old future-scope placeholder file
- old schema README glue file
- old architecture PDF artifact

## Operational Rule

When a question touches product truth:

- `docs/10_recovered_product` wins

When a question touches implementation contract:

- `docs/11_build_specs` wins

When a contributor needs a repo-facing summary:

- use the surviving rewritten legacy mirrors
