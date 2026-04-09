# Legacy Doc Rewrite Plan

## Authority Note

- Purpose: Canonical recovered product support document.
- Authority Level: Product-support truth.
- If Conflict: `docs/10_recovered_product/*` controls product meaning and `docs/11_build_specs/*` controls implementation behavior. This file supports migration planning and must not override either layer.

## Purpose

This file records how the legacy layer was realigned against the recovered canon and what the repo should treat as finished versus still-active cleanup work.

## Canonical Read Order Used For Rewrite

The rewrite followed this order:

1. `00_FINAL_PRODUCT_BEHAVIOR_MODEL.md`
2. `01_ROLE_AND_ACCESS_MODEL.md`
3. `02_DOMAIN_AND_ENTITY_MODEL.md`
4. `03_WORKFLOWS_AND_LIFECYCLES.md`
5. `04_PAGE_AND_MODULE_MODEL.md`
6. `06_REPORT_AND_EXPORT_MODEL.md`
7. `07_AUTHORITY_SHARE_AND_SUMMARY_MODEL.md`
8. `docs/11_build_specs/*`

## Completed Rewrite Outputs

These repo-facing legacy mirrors have been rewritten and should now stay synchronized to the canon:

- `docs/03_context/00_PROJECT_OVERVIEW_FINAL.md`
- `docs/03_context/01_REQUIREMENTS_CONTEXT_FINAL.md`
- `docs/02_interface/02_ROLES_AND_ACCESS_FINAL.md`
- `docs/02_interface/04_PAGE_CATALOG_FINAL_V4_LOCKED.md`
- `docs/01_structure/05_DATA_AND_FLOW_NOTES_FINAL.md`
- `docs/01_structure/06_DECISIONS_LOG_FINAL_LOCKED.md`
- `docs/06_schema/11_SCHEMA_BASELINE_v1_FINAL_WITH_DDL.md`
- `docs/06_schema/12_SCHEMA_SPECIFICATION_v1.md`
- `docs/06_schema/schema_v1_full.sql`

## Completed Merge And Cleanup

Useful content from the old clutter layer was absorbed into:

- `docs/README.md`
- `docs/11_build_specs/00_IMPLEMENTATION_MASTER_PLAN.md`
- the recovered canon
- the build-spec layer

The old duplicate summary, boot, and merge-source docs were then removed.

## Remaining Rule

From this point forward:

- do not rebuild product truth inside legacy docs
- keep legacy docs concise and repo-facing
- make product-scope changes in `docs/10_recovered_product`
- make implementation-contract changes in `docs/11_build_specs`
- update legacy mirrors only after the canon changes

## Next Use

The next major doc work should focus on:

- keeping surviving legacy mirrors synchronized when the canon changes
- extending build specs only when a new implementation contract is truly needed
- avoiding recreation of thin duplicate summary docs
