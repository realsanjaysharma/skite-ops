# Skyte Ops Git Workflow

## Purpose

This file records the active source-control and deployment workflow for the Skite Ops project.
It supports disciplined implementation and release, but it does not define product scope.

## 1. Repository Model

- private repository
- no direct production edits
- use reviewable branch-based work

Recommended branches:

- `main` for production-ready code
- `develop` for integration if you choose to keep a two-branch flow
- `feature/*` for feature work
- `hotfix/*` for urgent production fixes

## 2. Ignore Policy

Do not commit:

- secrets
- backups
- uploaded files
- local overrides
- dependency folders that should be rebuilt from lockfiles or install commands

## 3. Commit Discipline

- keep commits small and logical
- do not mix unrelated work
- use readable messages
- keep doc, schema, and code changes synchronized when they represent one real change

## 4. Versioning

Use tagged releases for production deployments.

Typical version meaning:

- major = structural or platform-level break
- minor = feature addition
- patch = bug fix

## 5. Migration Discipline

- keep migration files in a dedicated migrations directory
- add migrations sequentially
- do not make silent production schema edits
- update canonical schema docs when schema truth changes
- test schema changes locally before staging or production rollout

Do not rely on the old trimmed-era `system_meta` pattern as documentation truth.
The active schema truth is now anchored by:

- `docs/11_build_specs/02_CANONICAL_SCHEMA_ROADMAP.md`
- `docs/06_schema/schema_v1_full.sql`

## 6. Staging Rule

- test on staging before production whenever practical
- staging should mirror production runtime constraints closely
- do not treat production as the first validation environment

## 7. Deployment Flow

Typical release flow:

1. finish work on a feature or hotfix branch
2. test locally
3. merge intentionally
4. deploy to staging
5. run release validation checks
6. deploy to production
7. monitor the release

## 8. Release Validation

Before marking a release stable:

- login works
- role access works
- uploads work
- main operational pages boot correctly
- reports generate where relevant
- no obvious runtime warnings or fatal errors appear

## 9. Rollback Rule

If deployment fails:

1. return to the previous known-good release
2. restore database or storage from backup only when required
3. validate the rollback state
4. fix forward on a fresh hotfix branch

## 10. Solo Maintainer Rules

- do not deploy untagged or unclear code to production
- do not skip backup before risky schema work
- do not leave migrations undocumented
- do not change product behavior without updating the canon and build specs when needed

## Active Status

This file remains a kept operational doc.
If the repo workflow changes materially, update it directly rather than recreating separate workflow summaries.
