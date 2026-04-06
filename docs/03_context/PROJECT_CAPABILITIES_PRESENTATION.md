# Skite Ops Capabilities Presentation

## Slide 1 — What is Skite Ops?

**Skite Ops** is a governance-first internal operations platform designed to unify green belt operations, advertisement operations, monitoring, fabrication/installation execution, authority proof access, client proof reuse, and management visibility in one controlled system.  
It replaces fragmented workflows such as WhatsApp reporting, spreadsheets, and manual proof forwarding with auditable, role-governed digital workflows.

---

## Slide 2 — Core Product Vision

Skite Ops is built as a **single shared platform** where:
- Field teams produce evidence and status updates.
- Support/commercial teams raise requests.
- Execution teams fulfill assigned tasks.
- Ops governs review, approvals, assignment, closure, and overrides.
- Authority/commercial/management stakeholders consume filtered, role-safe visibility.

**Positioning:** Not just a task board or proof portal—an integrated operational governance system.

---

## Slide 3 — End-to-End Domain Coverage

Skite Ops covers these major capability domains:
1. Green Belt Operations
2. Advertisement Site & Asset Operations
3. Monitoring
4. Campaign Management
5. Free/Available Media Tracking
6. Request-to-Task Workflow
7. Authority Visibility & Proof Governance
8. Commercial/Support Read Portals
9. Reports, Alerts, and Dashboards
10. User, Access, Audit, and System Governance

---

## Slide 4 — Role-Based Operational Model

The target role model includes:
- Ops / Operations Manager
- Head Supervisor
- Green Belt Supervisor
- Outsourced Maintainer / Agency login
- Monitoring Team Member
- Fabrication / Installation Lead
- Fabrication / Installation Workers (read-only link flow)
- Sales Team
- Client Servicing Team
- Media Planning Team
- Authority Representative
- Management

Capabilities are constrained through predefined permission groups to keep role expansion controlled and auditable.

---

## Slide 5 — Dashboard Suite

Skite Ops provides specialized dashboards:
- **Master Operations Dashboard**: cross-domain KPIs and alert aggregation.
- **Green Belt Dashboard**: maintained vs outsourced activity and compliance views.
- **Advertisement Dashboard**: task pipeline, priority load, attendance/entry exceptions.
- **Monitoring Dashboard**: due/overdue site tracking and upload/attendance status.

All dashboards are role-scoped and primarily navigational to protect governance boundaries.

---

## Slide 6 — Green Belt Operations Capabilities

Key green belt features:
- Green Belt Master and Detail lifecycle management.
- Maintenance cycle control (open/close/backdate with constraints).
- Watering status tracking and compliance with override controls.
- Supervisor attendance management with lock/override rules.
- Labour entry controls with monthly locking behavior.
- Supervisor/Head upload flows (work vs issue uploads).
- Ops Upload Review with bulk approve/reject and governance rules.

Notable rule: issue uploads are governed differently from work proof and cannot be approved as normal work evidence.

---

## Slide 7 — Issue, Task, Fabrication & Monitoring Capabilities

Execution and monitoring modules include:
- Issue lifecycle management (open/in-progress/closed, priority, task linkage).
- Task list and task detail management (creation, assignment, cancellation, archive).
- Worker profile management and daily operational attendance.
- Worker daily entries with attendance dependency and exception alerts.
- Monitoring site management (site lifecycle).
- Monitoring upload flow (photo + optional comment).

This supports a full request-to-execution trail and proof-backed closure behavior.

---

## Slide 8 — Advertisement, Campaign, and Media Capabilities

Planned/defined capabilities include:
- Advertisement site and asset operations.
- Campaign-to-site linkage and campaign operations.
- Free/available media state tracking and history.
- Client media library and media planning request visibility.
- Campaign-end to free-media transition workflow.

These capabilities connect commercial planning with operational ground truth.

---

## Slide 9 — Authority, Reporting, and Visibility Features

Skite Ops supports governed external/internal visibility through:
- **Authority Dashboard**: assigned belts + approved uploads only, filtered access.
- **Reports**: monthly belt health, supervisor activity, worker activity, advertisement summary.
- **Export**: CSV in v1, including archived task contribution.
- **Notification Panel**: global alert routing for operational exceptions.

Principle: authority-facing proof is filtered governance output, not raw internal data access.

---

## Slide 10 — Governance, Security, and Auditability

Platform safeguards include:
- Ops as final governance authority.
- Role-based access control enforced via middleware.
- Lock + override model for sensitive historical records.
- Mandatory override reasons with audit logging.
- Separation of concerns: Upload ≠ Issue ≠ Task ≠ Request.
- Password reset enforcement flows.
- Comprehensive audit trail strategy for critical actions and status changes.

This enables compliance-oriented operations without overburdening field users.

---

## Slide 11 — Technical Architecture Strengths

Backend architecture follows a strict layered model:
**Controller → Service → Repository → Database**

Current codebase strengths already implemented include:
- Session-backed authentication with login/logout/password reset routes.
- Failed login tracking and lockout handling.
- User lifecycle management (create/update/list/get/delete/activate/deactivate/restore).
- Middleware authorization gate for non-login routes.
- Repository-driven data access and service-level transaction handling.

This creates a maintainable foundation for progressive module rollout.

---

## Slide 12 — Business Value Summary

Skite Ops delivers value by:
- Replacing fragmented manual coordination with governed digital workflows.
- Improving operational transparency across field, Ops, management, and authority audiences.
- Reducing exception handling lag through alerts and dashboard visibility.
- Preserving accountability via role controls, lifecycle locks, and audit logs.
- Scaling across multiple domains (green belt, advertisement, monitoring, execution) in one platform.

**Outcome:** Higher reliability, faster decisions, and traceable operations at enterprise governance quality.

---

## Optional Presenter Close

“If you want, I can convert this into a version tailored for:
1) executive leadership, 2) operations team training, or 3) client/authority onboarding—with audience-specific wording and depth.”
