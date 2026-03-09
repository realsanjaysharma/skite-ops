# 07_OPEN_QUESTIONS_AND_FUTURE_IDEAS_FINAL.md

Skyte Ops -- Controlled Future Scope

------------------------------------------------------------------------

## PURPOSE OF THIS DOCUMENT

This document defines controlled, intentional future expansion areas.

It does NOT contain: - Rejected ideas - Alternative designs -
Architecture reconsiderations - Brainstorm experiments

It contains only postponed but valid evolution paths.

Current Architecture Status: Frozen\
Current Phase: Schema Implementation\
Redesign Allowed: No

------------------------------------------------------------------------

# PHASE 2 -- PLANNED EXTENSIONS (POST STABILIZATION)

These items are planned but intentionally postponed until after:

-   Backend completion
-   Deployment
-   Stabilization period
-   Real usage validation

------------------------------------------------------------------------

## 1️⃣ Map View (Read-Only Visualization)

Purpose: - Visualize assets (Green Belts, Advertisement Sites) -
Location awareness for Ops and Planning teams

Constraints: - Read-only - No live GPS tracking - No dynamic scheduling
integration - No redesign of asset schema

------------------------------------------------------------------------

## 2️⃣ Enhanced Reporting & Analytics

Examples: - Visual charts for monthly belt health - Work distribution
graphs - Installation vs Monitoring effort ratio - Worker productivity
trends

Constraints: - No analytics-driven automation - No AI insights -
Read-only analytics layer

------------------------------------------------------------------------

## 3️⃣ Permission History Viewer

Optional transparency tool: - View historical permission lifecycle -
Renewal timeline visualization

Governance-only feature.

------------------------------------------------------------------------

## 4️⃣ Configurable Health Thresholds UI

Allow adjustment of: - Cleaning cycle expectation - Grass cutting
threshold - Health scoring weightage

Constraints: - Must not introduce scheduling engine - Must not
auto-assign work

------------------------------------------------------------------------

# PHASE 3 -- CONDITIONAL SCALE EXPANSION

Only if real usage requires.

------------------------------------------------------------------------

## 5️⃣ VPS Migration

Trigger Conditions: - High traffic - Large image storage pressure -
Performance bottlenecks - Concurrent user growth

Shared hosting remains v1 deployment model.

------------------------------------------------------------------------

## 6️⃣ Background Job Queue

Examples: - Report generation - Image compression - Export batching

Only if performance requires.

------------------------------------------------------------------------

## 7️⃣ API Layer (Controlled Exposure)

Potential future: - Internal API endpoints - Limited integration with
other internal tools

Constraints: - No public API - No client exposure in early stages

------------------------------------------------------------------------

# PHASE 4 -- POSSIBLE FUTURE VERTICAL EXPANSION

------------------------------------------------------------------------

## 8️⃣ HR Vertical Expansion

Possible Modules: - Attendance management - Worker payroll integration -
Leave tracking

Must remain separate from operations governance core.

------------------------------------------------------------------------

# EXPLICITLY NOT IN FUTURE SCOPE

The following are permanently rejected (see KNOWN_REJECTIONS):

-   Routine scheduling engine
-   Append-only watering model
-   AI automation in v1
-   Financial module in v1
-   Live GPS tracking
-   Client login system
-   Arbitrary permission builder

------------------------------------------------------------------------

# PRINCIPLE

Future evolution must:

-   Respect frozen architecture
-   Preserve governance-first design
-   Avoid complexity inflation
-   Remain auditable and maintainable

------------------------------------------------------------------------

Document Status: Locked
