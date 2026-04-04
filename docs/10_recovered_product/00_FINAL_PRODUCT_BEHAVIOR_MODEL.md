# Recovered Final Product Behavior Model

## Purpose

This folder captures the recovered product truth from the transcript files in:

- `chat_gpt_chats/part 1-Operation management system for skite - chat gpt chat transcript.txt`
- `chat_gpt_chats/part 2-Operation management system for skite - chat gpt chat transcript.txt`
- `chat_gpt_chats/part 3-Operation management system for skite - chat gpt chat transcript.txt`

This is the canonical target product definition during the recovery phase.

The current legacy docs elsewhere in `docs/` remain useful for foundation, governance, and already-implemented behavior, but they do not fully represent the intended product scope.

## Product Identity

Skite Ops is a governance-first internal operations platform for Skite.

It is intended to unify:

- green belt operations
- advertisement operations
- monitoring
- fabrication and installation execution
- media planning support
- authority-facing proof access
- client-facing proof reuse
- management visibility
- Ops control, review, audit, and exception handling

The system replaces WhatsApp-based reporting, spreadsheet coordination, manual proof forwarding, and memory-driven operations.

## Core Product Shape

One shared system supports multiple teams and workflows:

- field teams create evidence
- support and commercial teams raise requests
- execution teams complete assigned work
- Ops governs review, approval, assignment, closure, and overrides
- authority, commercial, and management users consume controlled views

This is not only a green belt tracker, a proof portal, or a task board.

## Main Domains

The intended product includes all of these as real scope:

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

## Core Roles

The intended role set is:

- Ops / Operations Manager
- Head Supervisor
- Green Belt Supervisor
- Outsourced Maintainer / agency login
- Monitoring Team Member
- Fabrication / Installation Lead
- Fabrication / Installation Workers via read-only link
- Sales Team
- Client Servicing Team
- Media Planning Team
- Authority Representative / Authorized Person
- Management

The system also allows controlled future role creation through predefined permission groups.
This is not free-form permission design.

Real-world actors discussed but not fully finalized as system roles:

- gardener
- night guards
- daily labour

## Core Entity Model

The intended product is built around these core entities:

- users and roles
- permission groups
- role-permission mappings
- green belts
- belt-supervisor assignments
- belt-authority assignments
- maintenance cycles
- watering records
- supervisor attendance
- labour entries
- fabrication workers
- task-worker assignments
- advertisement sites and assets
- campaigns
- campaign-site links
- free media state and history
- uploads and proof
- issues
- task requests
- tasks
- audit records
- system settings and thresholds

Important modeling boundaries:

- Green Belt and Advertisement Site are separate entities
- Upload is not the same as Issue
- Issue is not the same as Task
- Request is not the same as Task
- authority-facing visibility is governed access, not duplicate data storage
- dashboard state, alerts, and compliance are mostly derived

## Main Workflows

The intended product behavior includes:

- green belt work proof flow
- watering flow
- maintenance cycle flow
- authority-proof review and visibility flow
- authority summary flow
- issue review and closure flow
- request-to-task conversion flow
- task execution and final Ops review flow
- worker allocation and availability flow
- monitoring proof flow
- free media discovery flow
- campaign-end to free-media transition flow
- outsourced belt oversight flow
- role-based landing flow after login

## Product Surfaces

The intended product includes these major surfaces:

- Master Operations Dashboard
- Green Belt Dashboard
- Advertisement Dashboard
- Monitoring Dashboard
- Green Belt Master
- Green Belt Detail
- Supervisor Upload
- Supervisor My Uploads
- Outsourced Upload
- Watering and Watering Status
- Maintenance Cycle Controls
- Supervisor Attendance
- Labour Entry
- Upload Review
- Issue Management
- Task Management
- Task Detail
- Fabrication Lead My Tasks
- Site and Asset Master
- Campaign Management
- Monitoring Upload
- Monitoring Plan
- Monitoring History
- Free and Available Media Page
- Client Media Library
- Raise Request Page
- Media Planning Inventory and Request View
- Authority View
- Management Dashboard
- User Management
- Access and Mapping Control
- Audit Log Viewer
- Rejected Uploads Cleanup
- Notification and Alert Panel
- Reports
- System Settings

## Non-Negotiable Product Rules

- Ops is the final governance authority
- evidence is imperfect
- compliance is best-effort inference, not fake certainty
- field workflows must stay low-friction
- authority-facing proof must be filtered and governed
- approved authority visibility is the in-system truth; external sharing happens outside the system
- outsourced belts must stay outside normal internal compliance logic
- requesters cannot bypass Ops and create execution truth directly
- dynamic roles are allowed only through predefined permission groups
- no hidden lifecycle magic
- no hidden approval shortcuts
- auditability matters
- the system must remain practical for PHP, MySQL, and shared hosting

## Recovery Use

Use this document as the high-level target product definition.

All future repo comparison, doc rewrite, schema redesign, and module planning should be checked against this recovered model first.
