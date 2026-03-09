# 01_REQUIREMENTS_CONTEXT.md

Version: Architecture Freeze v1 Status: Ground Reality Reference
Document

------------------------------------------------------------------------

## 1. PURPOSE OF THIS DOCUMENT

This document captures how operations actually work on the ground.

It is intentionally practical and descriptive.

It does NOT describe the ideal system. It describes real operational
behavior, constraints, habits, and limitations.

This document prevents: - Over-engineering - Unrealistic enforcement -
Artificial workflows - Design drift

All system design decisions must respect this document.

------------------------------------------------------------------------

## 2. COMPANY OPERATIONS STRUCTURE

The company operates in Outdoor Advertising and Green Belt Maintenance
simultaneously.

Operations Head manages both verticals.

Verticals:

1.  Green Belt Maintenance
2.  Advertisement Operations
3.  Monitoring (cross-supporting advertisement)

These verticals overlap in workforce and coordination but remain
logically distinct.

------------------------------------------------------------------------

## 3. GREEN BELT GROUND REALITY

### 3.1 Team Structure

-   6 Green Belt Supervisors
-   1 Head Supervisor
-   1 Gardener
-   20--40 daily labourers (not individually tracked)
-   2 night guards

Labourers are not system users. Supervisors are responsible for
execution and proof.

------------------------------------------------------------------------

### 3.2 Belt Assignment Behavior

-   Supervisors are assigned fixed belts (\~95% stable).
-   Temporary reassignment happens only:
    -   During absence
    -   For urgent work
-   Supervisors rotate belts in circular pattern: belt1 → belt2 → belt3
    → belt4 → belt1

Cycle return period typically 10--15 days.

------------------------------------------------------------------------

### 3.3 Types of Green Belt Work

Green belt work falls into three categories:

1.  Routine / Cycle-Based:
    -   Watering (daily / alternate)
    -   Cleaning (2--3 times per month)
    -   Grass cutting (2 times per month)
2.  Need-Based:
    -   Seasonal plantation
    -   Pot installation
    -   Tree trimming
    -   Stand adjustments
3.  Damage / Incident-Based:
    -   Theft
    -   Accidental damage
    -   Fence breakage
    -   Authority complaint

More than 90% of work fits into these categories.

------------------------------------------------------------------------

### 3.4 Supervisor Work Pattern

Supervisors usually:

-   Work one belt fully before moving to next.
-   Do not switch belts mid-day for non-watering tasks.
-   Water multiple belts in same day if required.
-   Sometimes only perform watering and no other visible maintenance.

Watering may be the only activity done on certain days.

------------------------------------------------------------------------

### 3.5 Upload Behavior

Supervisors:

-   Upload 80--100 photos per belt per visit (average).
-   Upload during or after work completion.
-   Do not tag work categories intentionally.
-   Do not plan work in system.
-   Do not create tasks.
-   Do not approve anything.

Uploads serve two purposes: - Internal proof - Authority reporting

Supervisors may upload only watering photos if no other work required.

------------------------------------------------------------------------

### 3.6 What Defines "Properly Maintained"

In practical terms, a belt is properly maintained if:

-   No visible garbage
-   Grass trimmed appropriately
-   Plants healthy
-   No visible damage
-   No active issues

Neglect signs:

-   Visible garbage
-   Overgrown grass
-   Dry plants (watering missed)
-   Authority complaints

------------------------------------------------------------------------

## 4. HEAD SUPERVISOR REALITY

Head Supervisor:

-   Oversees supervisors.
-   Marks attendance.
-   May step in operationally when needed.
-   May upload photos.
-   May start and close maintenance cycles.

Head Supervisor does NOT:

-   Approve uploads for authority.
-   Close issues finally.
-   Override system locks without Ops.

------------------------------------------------------------------------

## 5. OUTSOURCED BELTS REALITY

Some green belts:

-   Are legally under company permission.
-   But maintenance outsourced to third party.

For outsourced belts:

-   No watering compliance enforced.
-   No labour tracking.
-   No attendance expectation.
-   Uploads allowed.
-   Issues allowed.
-   Oversight only.

------------------------------------------------------------------------

## 6. ADVERTISEMENT OPERATIONS REALITY

### 6.1 Fabrication & Installation Team

-   5--6 workers
-   1 lead
-   Some workers also perform monitoring

Workers: - Low literacy - No system login required - Work assigned
verbally by lead

Lead: - Receives tasks from Ops. - Assigns work internally. - Reports
progress.

------------------------------------------------------------------------

### 6.2 Task Sources

Tasks originate from:

-   Ops
-   Management
-   Sales
-   Monitoring findings
-   Client demands

Current problem: No structured central queue.

------------------------------------------------------------------------

### 6.3 Daily Work Tracking Reality

Currently maintained in Excel:

-   Worker name
-   Date
-   Activity type
-   Work plan
-   Work update
-   Remarks

Ops wants:

-   Monthly per-worker visibility
-   Skill utilization insight
-   Who is free today
-   Who did what in month

Workers do not self-enter system data. Ops or lead records entries.

------------------------------------------------------------------------

## 7. MONITORING REALITY

Monitoring zones:

-   Green Belt Sites (mostly lit, daily)
-   City Sites (weekly / fixed date)
-   Highway Sites (periodic route-based)

Monitoring may overlap with fabrication workers.

Monitoring is currently reactive and client-driven.

System goal: Make monitoring assisted, not enforced rigidly.

------------------------------------------------------------------------

## 8. AUTHORITY REPORTING REALITY

-   Authority representatives assigned per belt.
-   Reporting currently done manually via WhatsApp.
-   Supervisors may upload blurry or duplicate photos.
-   Ops filters before authority visibility.

System requirement: Authority sees only approved uploads. No duplicate
copies created.

------------------------------------------------------------------------

## 9. MANAGEMENT EXPECTATIONS

Management wants:

-   Monthly belt-wise activity visibility
-   Supervisor-wise movement tracking
-   Evidence that belts are periodically maintained
-   Worker productivity visibility
-   Clean dashboards without operational complexity

------------------------------------------------------------------------

## 10. OPERATIONAL CONSTRAINTS

System must respect:

-   Mobile-first field usage
-   Low literacy users
-   No forced tagging complexity
-   Minimal mandatory fields
-   No artificial scheduling engine
-   Manual archive only
-   Calendar month reporting
-   No auto task creation from uploads
-   No hidden automation

------------------------------------------------------------------------

## 11. DESIGN BOUNDARY PRINCIPLE

The system assists and structures operations.

It does NOT:

-   Replace human judgment
-   Force rigid scheduling
-   Automate beyond control
-   Introduce artificial compliance logic

It reflects reality, then governs it.

------------------------------------------------------------------------

STATUS: Ground reality locked under Architecture Freeze v1.
