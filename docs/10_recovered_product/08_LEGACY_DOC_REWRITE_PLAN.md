# Legacy Doc Rewrite Plan

## Purpose

This file defines how the current legacy docs should be rewritten against the recovered canon.

The rule is:

- do not patch the trimmed product back into relevance
- do not silently mix recovered truth and legacy truth
- rewrite from the recovered canon outward

## Canonical Read Order

When rewriting any legacy doc, use this order:

1. `00_FINAL_PRODUCT_BEHAVIOR_MODEL.md`
2. `01_ROLE_AND_ACCESS_MODEL.md`
3. `02_DOMAIN_AND_ENTITY_MODEL.md`
4. `03_WORKFLOWS_AND_LIFECYCLES.md`
5. `04_PAGE_AND_MODULE_MODEL.md`
6. `06_REPORT_AND_EXPORT_MODEL.md`
7. `07_AUTHORITY_SHARE_AND_SUMMARY_MODEL.md`
8. `05_RECOVERY_LOCKS_AND_OPEN_DECISIONS.md`

## Rewrite Phases

### Phase 1: Context And Product Identity

Rewrite these first because they still describe the trimmed version of the product:

- `docs/03_context/00_PROJECT_OVERVIEW_FINAL.md`
- `docs/03_context/01_REQUIREMENTS_CONTEXT_FINAL.md`
- `docs/03_context/MASTER_CONTEXT_V3.md`
- `docs/03_context/PRODUCT_BEHAVIOR_SPEC_V3.md`

### What These Rewrites Must Absorb

- full multi-domain product scope
- recovered authority model
- monitoring planning and monthly due-date scheduling model
- request-to-task model
- campaign and free-media scope
- worker model
- reporting and export scope

### Phase 2: Interface Truth

Rewrite these next because roles and pages are where the trimmed behavior leaks most:

- `docs/02_interface/02_ROLES_AND_ACCESS_FINAL.md`
- `docs/02_interface/04_PAGE_CATALOG_FINAL_V4_LOCKED.md`

### What These Rewrites Must Absorb

- all recovered roles including Client Servicing and Outsourced Maintainer behavior
- role-based landing pages
- Authority View download/share/summary behavior
- Monitoring Plan with monthly due-date scheduling
- Assigned Task Progress page for Sales, Client Servicing, and Media Planning
- Rejected Uploads Cleanup
- reporting surfaces and report domain placement

### Phase 3: Structural And Flow Truth

Rewrite next:

- `docs/01_structure/05_DATA_AND_FLOW_NOTES_FINAL.md`

Review carefully before changing:

- `docs/01_structure/06_DECISIONS_LOG_FINAL_LOCKED.md`
- `docs/01_structure/07_EXECUTION_BEHAVIOR_LOCK_V1.md`

### What These Rewrites Must Absorb

- recovered entity boundaries
- authority visibility and summary behavior
- worker_daily_entries plus fabrication-only task_worker_assignment
- monitoring monthly due-date scheduling and bulk plan-copy behavior
- rejected-upload cleanup and purge rules
- request-to-task conversion flow
- end-of-day authority summary behavior

## Schema Rewrite Phase

Only after the above are aligned should these be rewritten:

- `docs/06_schema/11_SCHEMA_BASELINE_v1_FINAL_WITH_DDL.md`
- `docs/06_schema/12_SCHEMA_SPECIFICATION_v1.md`

### Schema Rewrite Must Follow

- recovered entity model first
- then page/workflow needs
- then implementation constraints

Do not let the current trimmed schema define the product again.

## Legacy Docs That Can Mostly Stay

These remain useful as foundation/governance references, but should be reviewed for conflicting scope language:

- `docs/00_governance/*`
- `docs/04_operations/*`
- auth and middleware security notes already aligned earlier

## Suggested Rewrite Order

1. `00_PROJECT_OVERVIEW_FINAL.md`
2. `02_ROLES_AND_ACCESS_FINAL.md`
3. `04_PAGE_CATALOG_FINAL_V4_LOCKED.md`
4. `05_DATA_AND_FLOW_NOTES_FINAL.md`
5. `11_SCHEMA_BASELINE_v1_FINAL_WITH_DDL.md`
6. `12_SCHEMA_SPECIFICATION_v1.md`
7. then remaining context files for cleanup and consistency

## Rewrite Output Rule

Each rewritten legacy file should:

- clearly match the recovered canon
- remove trimmed-product assumptions
- avoid contradicting the recovered role, page, and workflow models
- stay implementation-aware without shrinking the product back down
