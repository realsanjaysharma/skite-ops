# 00_PROJECT_OVERVIEW.md

Version: Architecture Freeze v1 Status: Foundational Governance Document

------------------------------------------------------------------------

## 1. PROJECT IDENTITY

Working Name: Integrated Operations & Monitoring System

Nature: Internal Operations Governance Platform

Scope: Multi-vertical operational management system for: - Green Belt
Maintenance - Advertisement Operations - Monitoring Management - Media
Planning Support

This system is NOT public-facing. This system is NOT a client portal.
This system is NOT a billing or ERP solution.

------------------------------------------------------------------------
SCHEMA FREEZE DECLARATION (v1)
------------------------------------------------------------------------

Schema Version: v1 (Finalized)
Total Tables: 17
Maintenance Cycle: Single-active enforced at service layer
Supervisor Assignment: Historical model via belt_supervisor_assignments
Compliance Model: Dynamic (no stored alert flags)

No structural redesign allowed without:
- Migration file
- Decisions Log update
- Documentation synchronization

Schema v1 is constitutionally locked.

------------------------------------------------------------------------

------------------------------------------------------------------------

## 2. WHY THIS SYSTEM EXISTS

The company currently operates using:

-   WhatsApp communication
-   Excel tracking sheets
-   Verbal coordination
-   Fragmented status updates

This creates:

-   No single source of truth
-   No audit traceability
-   No compliance enforcement
-   Manual authority reporting effort
-   No structured monthly analysis
-   No operational health visibility

This system replaces chaos with structured governance.

------------------------------------------------------------------------

## 3. STRATEGIC OBJECTIVES

1.  Create a single source of truth.
2.  Enforce role-based governance.
3.  Provide audit-safe operational logging.
4.  Enable structured compliance (watering, attendance, labour).
5.  Separate governance from execution.
6.  Enable monthly performance visibility.
7.  Preserve historical records permanently.
8.  Ensure system extensibility without structural breakage.

------------------------------------------------------------------------

## 4. VERTICAL STRUCTURE

The system contains three operational domains:

### 4.1 Green Belt Vertical

Manages: - Legal permission lifecycle - Maintenance cycles - Watering
compliance - Supervisor attendance - Labour tracking - Work & Issue
uploads - Authority visibility control - Monthly belt health reporting

Green Belt also contains advertisement boards physically, but those
boards are treated as advertisement assets, not as green belt entities.

------------------------------------------------------------------------

### 4.2 Advertisement Operations Vertical

Manages: - Fabrication tasks - Installation tasks - Maintenance tasks -
Removal tasks - Task lifecycle governance - Worker daily work entry -
Task archiving (manual only) - Monthly worker productivity reporting

------------------------------------------------------------------------

### 4.3 Monitoring Vertical

Manages: - Site-based monitoring uploads - Monitoring attendance -
Monitoring frequency compliance - Free/available media discovery -
Planning visibility support

Monitoring and Fabrication share worker model but remain logically
separate verticals.

------------------------------------------------------------------------

## 5. GOVERNANCE MODEL

Operations Manager (Ops) holds ultimate authority.

Principles: - Least privilege enforcement - No silent data mutation -
All overrides logged - All compliance calculated dynamically - Immutable
parent-child associations - Calendar-month reporting only

------------------------------------------------------------------------

## 6. CORE SYSTEM ENGINES

The system contains structured engines:

1.  Watering Compliance Engine
2.  Maintenance Cycle Engine
3.  Attendance Engine
4.  Labour Tracking Engine
5.  Upload Classification Engine
6.  Issue Lifecycle Engine
7.  Task Lifecycle Engine
8.  Alert Calculation Engine
9.  Monthly Reporting Engine

Each engine is defined in 05_DATA_AND_FLOW_NOTES.md.

------------------------------------------------------------------------

## 7. ARCHITECTURAL PHILOSOPHY

This system is built as:

-   Governance-first
-   Audit-first
-   Mobile-first for field users
-   Controller-enforced security
-   Schema-driven integrity
-   Manual archive (no automatic data hiding)
-   Explicit lifecycle transitions only

No automation will alter operational data silently.

------------------------------------------------------------------------

## 8. COMPLIANCE BOUNDARIES

Compliance applies only when:

-   Belt maintenance_mode = MAINTAINED
-   Permission_status != EXPIRED
-   Belt not hidden

Outsourced belts: - No watering compliance - No labour compliance - No
attendance compliance - Upload & issue allowed

------------------------------------------------------------------------

## 9. REPORTING FRAMEWORK

Reports are monthly only.

System supports:

-   Belt Health Summary (Monthly)
-   Supervisor Activity Report (Monthly)
-   Worker Activity Report (Monthly)
-   Advertisement Monthly Report

CSV export required for all reports.

------------------------------------------------------------------------

## 10. DATA RETENTION POLICY

-   Approved uploads: permanent
-   Issue uploads: permanent (unless deleted within 5-minute window)
-   Rejected uploads: manual purge only
-   Self-deleted uploads: auto purge after 30 days
-   Tasks: manual archive only
-   Archived tasks remain in reports

------------------------------------------------------------------------

## 11. TECHNICAL DEPLOYMENT TARGET

Designed for:

-   PHP + MySQL environment
-   Shared hosting compatibility
-   Git-based version control
-   Controller-level permission enforcement

No dependency on background cron jobs for core logic.

------------------------------------------------------------------------

## 12. NON-GOALS (V1 EXCLUSIONS)

The system does NOT include:

-   Billing
-   Payroll
-   GPS tracking
-   Automated WhatsApp sending
-   AI automation
-   Financial accounting
-   Client login portals

------------------------------------------------------------------------

## 13. EXTENSIBILITY INTENT

The architecture must allow:

-   Addition of new verticals
-   Addition of new roles
-   Addition of new report types
-   UI enhancements
-   Map view (Phase 2)
-   VPS deployment upgrade

All structural change must update MD documentation first.

------------------------------------------------------------------------

## 14. DOCUMENTATION DISCIPLINE RULE

This file is governance-level framing only.

Structural definitions are maintained in:

05_DATA_AND_FLOW_NOTES.md

Any architectural change requires:

-   Update to this file
-   Update to Decisions Log
-   Version increment

------------------------------------------------------------------------

STATUS: Authoritative Overview aligned with Architecture Freeze v1.
