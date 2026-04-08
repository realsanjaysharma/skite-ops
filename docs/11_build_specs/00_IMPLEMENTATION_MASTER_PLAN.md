# Implementation Master Plan

## Purpose

This file defines the implementation sequence for building the recovered product without drifting back into the trimmed legacy version.

## Authoritative Inputs

Use these before implementation planning:

1. `docs/10_recovered_product/00_FINAL_PRODUCT_BEHAVIOR_MODEL.md`
2. `docs/10_recovered_product/01_ROLE_AND_ACCESS_MODEL.md`
3. `docs/10_recovered_product/02_DOMAIN_AND_ENTITY_MODEL.md`
4. `docs/10_recovered_product/03_WORKFLOWS_AND_LIFECYCLES.md`
5. `docs/10_recovered_product/04_PAGE_AND_MODULE_MODEL.md`
6. `docs/10_recovered_product/06_REPORT_AND_EXPORT_MODEL.md`
7. `docs/10_recovered_product/07_AUTHORITY_SHARE_AND_SUMMARY_MODEL.md`

## Build Phases

### Phase 0 - Doc And Contract Alignment

- complete `docs/11_build_specs/`
- rewrite critical legacy docs
- lock schema roadmap before domain migrations

### Phase 1 - Platform Foundation And RBAC

- auth and session alignment
- role and permission-group model
- landing-page routing
- user lifecycle rules

### Phase 2 - Green Belt Core

- green belt master
- belt assignments
- authority assignments
- maintenance cycles

### Phase 3 - Green Belt Field Operations

- supervisor upload
- watering
- attendance
- labour
- issue capture and review
- outsourced flow

### Phase 4 - Request, Task, And Fabrication Execution

- task requests
- task management
- fabrication workers
- worker daily entries
- fabrication-only task-worker assignment

### Phase 5 - Advertisement, Site, And Monitoring Domain

- advertisement site master
- campaigns
- campaign-site links
- monitoring upload
- monitoring monthly due-date scheduling
- free media tracking

### Phase 6 - Authority And Commercial Read Models

- authority view
- authority summary/share behavior
- assigned task progress pages
- client/proof read surfaces

### Phase 7 - Reports, Alerts, And System Settings

- reports and CSV export
- dashboard formulas
- alert calculations
- system settings

### Phase 8 - Hardening And Deployment

- upload safety
- pagination
- transaction coverage
- deployment and recovery checks

## Dependency Rule

Do not implement a module until:

- its schema shape is locked in `02_CANONICAL_SCHEMA_ROADMAP.md`
- its page behavior is locked in `04_PAGE_FIELD_AND_ACTION_SPEC.md`
- its lifecycle rules are locked in `05_WORKFLOW_STATE_MACHINE_SPEC.md`

## Acceptance Gate Rule

No phase is complete until the relevant checklist in `09_MODULE_ACCEPTANCE_CHECKLISTS.md` is satisfied.
