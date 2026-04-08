# Skyte Ops Page Catalog

## Purpose

This file is the repo-facing page and module inventory.
It mirrors:

- `docs/10_recovered_product/04_PAGE_AND_MODULE_MODEL.md`
- `docs/11_build_specs/04_PAGE_FIELD_AND_ACTION_SPEC.md`

Use this file for page-level orientation.
Use the build spec for exact fields, filters, actions, and validations.

## Global Page Rules

- dashboards are summary and navigation surfaces, not overloaded action pages
- real work happens on dedicated pages
- role landing after login is specific, not generic
- record scope applies before a page can show sensitive data
- field users must get low-friction pages
- supervisor-facing pages must not leak authority review status

## 1. Dashboard Surfaces

### Master Operations Dashboard

- Access: Ops
- Landing for Ops
- Purpose: cross-domain operational command view

### Green Belt Dashboard

- Access: Ops, Head Supervisor
- Purpose: maintained-belt daily status, watering pressure, cycles, uploads, and issues

### Advertisement Dashboard

- Access: Ops, Management
- Purpose: advertisement execution, campaigns, issues, and free-media pressure

### Monitoring Dashboard

- Access: Ops, Management
- Purpose: due today, completed today, overdue monitoring, and discovery activity

### Management Dashboard

- Access: Management
- Landing for Management
- Purpose: read-only cross-domain visibility

## 2. Green Belt Domain Pages

### Green Belt Master

- Access: Ops
- Purpose: legal and operational source of truth for belts

### Green Belt Detail

- Access: Ops, Head Supervisor in limited form
- Purpose: belt drill-down with assignments, cycles, watering, uploads, and issues

### Supervisor Upload

- Access: Green Belt Supervisor
- Landing for Green Belt Supervisor
- Purpose: low-friction work and issue proof submission for assigned belts
- Notes: work uploads may carry an optional stored work type for later authority filtering

### Supervisor My Uploads

- Access: Green Belt Supervisor
- Purpose: own recent uploads and limited self-delete window

### Outsourced Upload

- Access: Outsourced Maintainer
- Landing for Outsourced Maintainer
- Purpose: separate upload flow for outsourced belts
- Notes: available belts come from explicit outsourced-belt assignment, not internal supervisor mapping

### Supervisor Attendance And Watering Oversight

- Access: Head Supervisor, Ops
- Landing for Head Supervisor
- Purpose: same-day attendance, watering, labour, and quick exception handling

### Maintenance Cycle Controls

- Access: Head Supervisor, Ops
- Purpose: cycle start and close behavior for maintained belts

### Upload Review

- Access: Ops
- Purpose: authority-visibility governance for eligible green-belt proof

### Issue Management

- Access: Ops, Head Supervisor with limited transitions
- Purpose: governed issue lifecycle and task linkage

### Authority View

- Access: Authority Representative
- Landing for Authority Representative
- Purpose: approved proof access, filtered download, WhatsApp helper sharing, and authority summary output
- Notes: work-type filtering must use stored upload metadata, not comment parsing

### Rejected Uploads Cleanup

- Access: Ops
- Purpose: bulk cleanup of eligible rejected uploads while retaining minimal metadata and purge markers

## 3. Task, Execution, And Worker Pages

### Raise Request Page

- Access: Sales, Client Servicing, Media Planning
- Purpose: request intake for Ops-governed work

### Task Management

- Access: Ops
- Purpose: request conversion, task creation, assignment, and Ops control

### Task Detail

- Access: Ops, assigned Fabrication Lead
- Purpose: task metadata, proof, worker allocation, progress, remarks, and completion handoff

### Fabrication Lead My Tasks

- Access: Fabrication Lead
- Landing for Fabrication Lead
- Purpose: assigned task workspace

### Assigned Task Progress Page

- Access: Sales, Client Servicing, Media Planning
- Landing for those roles
- Purpose: read-only progress visibility for tasks linked to their requests, clients, campaigns, or planning work

### Worker Allocation And Daily Work Pages

- Access: Ops, Fabrication Lead where allowed
- Purpose: fabrication worker resource tracking, daily entries, and availability logic

## 4. Advertisement And Monitoring Pages

### Site And Asset Master

- Access: Ops
- Purpose: advertisement site and asset source of truth

### Campaign Management

- Access: Ops
- Purpose: campaign lifecycle, site linkage, and governed campaign-end handling

### Monitoring Upload

- Access: Monitoring Team Member
- Landing for Monitoring Team
- Purpose: monitoring and discovery proof submission
- Notes: discovery mode must create or refresh governed discovered free-media state

### Monitoring Plan

- Access: Ops
- Purpose: monthly due-date planning by site, with copy-forward and bulk-copy behavior

### Monitoring History

- Access: Ops, Monitoring Team, scoped read consumers
- Purpose: proof retrieval and completion history

### Free And Available Media Page

- Access: Ops, Media Planning, optional scoped commercial read later
- Purpose: discovered and confirmed free-media visibility

## 5. Governance, Reporting, And Settings

### User Management

- Access: Ops
- Purpose: user lifecycle and role assignment

### Access And Mapping Control

- Access: Ops
- Purpose: belt-supervisor, belt-authority, belt-outsourced, and role-module scope management

### Audit Log Viewer

- Access: Ops
- Purpose: view sensitive changes, overrides, approvals, and closures

### Reports

- Access: Ops, Management
- Purpose: monthly CSV reporting across belts, supervisors, workers, and advertisement operations

### System Settings

- Access: Ops
- Purpose: controlled operational settings such as Ops phone number, cleanup thresholds, and helper toggles

## 6. Page Placement Rules

- Green Belt Master and Site And Asset Master remain separate pages
- monitoring planning belongs in its own page, not hidden inside uploads
- request intake belongs to requester roles, not to the execution flow
- authority view remains separate from upload review
- rejected-upload cleanup remains an admin page, not a hidden data action

## 7. Canonical Detail Rule

This file names the pages and their purpose.

For exact implementation behavior, use:

- `docs/11_build_specs/04_PAGE_FIELD_AND_ACTION_SPEC.md`
- `docs/11_build_specs/03_API_AND_ROUTE_CONTRACT.md`
