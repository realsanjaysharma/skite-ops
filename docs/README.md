# Skyte Ops Documentation System

This repository now uses a two-layer documentation model:

- `docs/10_recovered_product` = canonical product truth
- `docs/11_build_specs` = canonical implementation-spec layer

Older docs outside those layers remain useful as references, but many are legacy interpretation docs from the trimmed-system phase.

## Reading Order

Use this order during active planning and implementation:

1. `docs/10_recovered_product/00_FINAL_PRODUCT_BEHAVIOR_MODEL.md`
2. `docs/10_recovered_product/01_ROLE_AND_ACCESS_MODEL.md`
3. `docs/10_recovered_product/02_DOMAIN_AND_ENTITY_MODEL.md`
4. `docs/10_recovered_product/03_WORKFLOWS_AND_LIFECYCLES.md`
5. `docs/10_recovered_product/04_PAGE_AND_MODULE_MODEL.md`
6. `docs/10_recovered_product/06_REPORT_AND_EXPORT_MODEL.md`
7. `docs/10_recovered_product/07_AUTHORITY_SHARE_AND_SUMMARY_MODEL.md`
8. `docs/11_build_specs/*`

Only after that should rewritten legacy docs be treated as synchronized repo-facing references.

## Documentation Layers

### Product Truth

`docs/10_recovered_product/`

This folder captures the recovered intended product from the original transcripts.
It is the canonical source for:

- scope
- roles
- entities
- workflows
- pages
- reporting
- authority-sharing behavior

### Build Specs

`docs/11_build_specs/`

This folder captures implementation-level truth.
It is the canonical source for:

- build order
- RBAC details
- schema roadmap
- route contracts
- page field behavior
- workflow state machines
- upload retention and storage rules
- formulas
- system settings
- acceptance checklists

### Legacy References

Legacy folders still exist:

- `docs/01_structure`
- `docs/02_interface`
- `docs/03_context`
- `docs/04_operations`
- `docs/06_schema`

These folders are not all equal in value anymore.
Some files should be kept, some rewritten, some merged, and some archived.

See:

- `docs/10_recovered_product/08_LEGACY_DOC_REWRITE_PLAN.md`
- `docs/10_recovered_product/09_LEGACY_DOC_CLASSIFICATION.md`

## Document Authority Rules

Use this precedence rule everywhere:

1. `docs/10_recovered_product/*` wins on product meaning, scope, roles, entities, workflows, pages, and reporting intent
2. `docs/11_build_specs/*` wins on implementation behavior, schema design, routes, fields, state transitions, formulas, settings, and acceptance criteria
3. rewritten legacy docs are repo-facing mirrors only and must not override either canonical layer
4. operational support docs help environment and workflow decisions, but they do not redefine product scope or implementation contracts

If two documents appear to conflict:

- use `docs/10_recovered_product/*` to settle what the system is supposed to be
- use `docs/11_build_specs/*` to settle how that behavior must be implemented
- update mirror docs to match; do not treat drift as intentional truth

## Active Rule

When a legacy doc conflicts with recovered product truth:

- recovered product docs win on product intent
- build-spec docs win on implementation contract
- legacy docs must be rewritten, merged, or archived

## Still Useful Existing Folders

These remain useful support layers and should be realigned gradually:

- `docs/04_operations`
- `docs/06_schema`

## Backend Layer Rule

Controllers handle HTTP input/output only and must not contain business logic.
Controllers handle request validation for format and required fields.
Services handle business validation and domain rules.
Repositories handle database access only.
RBAC must be enforced at middleware before controllers.

## Development Philosophy

Governance > Convenience
Auditability > Automation
Clarity > Cleverness

## Repo-Facing Legacy Mirrors

The following legacy files are now rewritten mirrors of the canon rather than independent product authorities:

- `docs/03_context/00_PROJECT_OVERVIEW_FINAL.md`
- `docs/03_context/01_REQUIREMENTS_CONTEXT_FINAL.md`
- `docs/02_interface/02_ROLES_AND_ACCESS_FINAL.md`
- `docs/02_interface/04_PAGE_CATALOG_FINAL_V4_LOCKED.md`
- `docs/01_structure/05_DATA_AND_FLOW_NOTES_FINAL.md`
- `docs/01_structure/06_DECISIONS_LOG_FINAL_LOCKED.md`
- `docs/06_schema/11_SCHEMA_BASELINE_v1_FINAL_WITH_DDL.md`
- `docs/06_schema/12_SCHEMA_SPECIFICATION_v1.md`
- `docs/06_schema/schema_v1_full.sql`

These files should help repo navigation and onboarding, but they should stay synchronized to the canon rather than redefine it.

For executable first-run DB setup:

1. run `docs/06_schema/schema_v1_full.sql`
2. run `migrations/001_seed_foundation.sql`
3. optionally create the first Ops user from `migrations/002_bootstrap_ops_user.template.sql`
