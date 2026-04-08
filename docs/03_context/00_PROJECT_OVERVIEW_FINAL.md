# Skyte Ops Project Overview

## Status

This legacy overview has been rewritten to match the recovered product canon and the active build-spec layer.
It is a repo-facing summary, not the highest authority document.

Primary sources now are:

- `docs/10_recovered_product/00_FINAL_PRODUCT_BEHAVIOR_MODEL.md`
- `docs/10_recovered_product/01_ROLE_AND_ACCESS_MODEL.md`
- `docs/10_recovered_product/03_WORKFLOWS_AND_LIFECYCLES.md`
- `docs/11_build_specs/00_IMPLEMENTATION_MASTER_PLAN.md`

## Product Identity

Skite Ops is a governance-first internal operations platform for Skite.

It unifies:

- green belt operations
- advertisement site and asset operations
- monitoring
- fabrication and installation execution
- campaign and free-media governance
- authority-facing proof access
- commercial and support visibility
- management reporting
- Ops-led review, assignment, closure, and audit

The product replaces WhatsApp-driven reporting, spreadsheet tracking, manual proof forwarding, and memory-based coordination with one controlled operational system.

## Core Mission

Capture real operational evidence once, govern it through Ops, and reuse it safely across field teams, authority-facing roles, commercial users, and management.

## Domain Scope

The intended system includes all of these domains as first-class product scope:

- Green Belt Operations
- Advertisement Site and Asset Operations
- Monitoring
- Campaign Management
- Free and Available Media Tracking
- Request-to-Task Workflow
- Authority Visibility and Proof Governance
- Commercial and Support Read Portals
- Reports, Alerts, and Dashboards
- User, Access, Audit, and System Governance

## Operating Model

One shared data layer supports several kinds of users:

- field teams create proof and same-day operational entries
- support and commercial teams raise requests
- execution teams complete assigned work
- Ops governs approvals, assignments, visibility, closures, and overrides
- authority, commercial, and management roles consume controlled read models

This is not only a green-belt tracker, a proof library, or a task board.

## Governance Model

Ops is the final governance authority.

That means:

- field users do not approve their own work
- requesters do not create final execution truth directly
- authority representatives consume approved proof but do not govern it
- management sees the system in read-only form
- overrides require reason and auditability

## Product Boundaries

The product is intentionally realistic rather than idealized.

It accepts that:

- evidence is imperfect
- no upload does not prove no work happened
- low-friction field UX matters
- some operational truth must remain review-driven
- dashboards and alerts are derived, not blindly stored

## Delivery Constraints

The build must remain practical for:

- PHP
- MySQL
- shared hosting
- explicit service-layer business logic
- mobile-practical field use

The product must not depend on hidden automation, cron-heavy correctness, or enterprise-only infrastructure assumptions.

## Canonical Reading Order

When this overview conflicts with deeper docs, use this order:

1. `docs/10_recovered_product/`
2. `docs/11_build_specs/`
3. rewritten legacy docs in `docs/01_structure`, `docs/02_interface`, `docs/03_context`, and `docs/06_schema`
4. operational support docs in `docs/04_operations`

## Repo-Facing Role Of This File

This file exists to give contributors a fast orientation to what Skite Ops actually is.
It should stay concise and aligned to the recovered canon.
