# 00_PROJECT_FILE_MAP_FINAL_V2.md

Skyte Ops -- Constitutional Document Hierarchy & Authority Map (10/10
Locked)

------------------------------------------------------------------------

## PURPOSE OF THIS DOCUMENT

This file is the constitutional authority of the entire Skyte Ops
documentation system.

It defines:

-   Document hierarchy
-   Conflict resolution rules
-   Implementation authority
-   Schema supremacy rule
-   Git authority rule
-   Phase discipline enforcement
-   Documentation--Implementation synchronization rule

If ambiguity exists anywhere, this file resolves it.

------------------------------------------------------------------------

# SECTION 1 --- AUTHORITY LEVELS

Higher level = stronger authority.

------------------------------------------------------------------------

## LEVEL 0 --- ENFORCEMENT LAYER (ABSOLUTE CONTROL)

Files: - NON_NEGOTIABLES_V3.md - CURRENT_PHASE.md -
SYSTEM_BOOT_PROMPT_V3.md

Purpose: - Freeze architecture - Prevent redesign drift - Control
allowed actions per phase

Rules: - Level 0 overrides all other levels. - Level 0 cannot redefine
schema structure directly. - Level 0 controls execution discipline, not
database structure.

------------------------------------------------------------------------

## LEVEL 1 --- STRUCTURAL EXECUTION AUTHORITY (CORE TRUTH)

Files: - 11_SCHEMA_PREPARATION_FINAL_LOCKED_V2.md -
05_DATA_AND_FLOW_NOTES_FINAL.md - 06_DECISIONS_LOG_FINAL_LOCKED.md

Purpose: - Define database entities - Define relationships - Define
lifecycle logic - Lock structural decisions

Authority Order Within Level 1: Schema Preparation \> Data & Flow \>
Decisions Log

### SCHEMA SUPREMACY RULE

Once database schema is implemented: The actual database structure
becomes the final executable source of truth.

If documentation conflicts with implemented schema: - Documentation must
be updated immediately. - Silent divergence is forbidden.

Schema is the operational constitution.

------------------------------------------------------------------------

## LEVEL 2 --- STRUCTURAL INTERFACE LAYER

Files: - 04_PAGE_CATALOG_FINAL.md - 02_ROLES_AND_ACCESS_FINAL.md -
03_DESIGN_PRINCIPLES_FINAL.md

Purpose: - Define UI organization - Define role boundaries - Define
interaction rules

Constraint: Level 2 cannot alter Level 1 structure.

------------------------------------------------------------------------

## LEVEL 3 --- STRATEGIC CONTEXT LAYER

Files: - 00_PROJECT_OVERVIEW_FINAL.md -
01_REQUIREMENTS_CONTEXT_FINAL.md - MASTER_CONTEXT_V3.md -
ARCHITECTURAL_RATIONALE_V3.md - PRODUCT_BEHAVIOR_SPEC_V3.md

Purpose: - Explain why system exists - Preserve long-term philosophy -
Provide business grounding

Constraint: Cannot modify structure or override execution authority.

------------------------------------------------------------------------

## LEVEL 4 --- OPERATIONAL GOVERNANCE LAYER

Files: - 08_SECURITY_AND_DEPLOYMENT_FINAL.md -
09_BACKUP_AND_RECOVERY_FINAL_LOCKED.md -
10_GIT_WORKFLOW_FINAL_LOCKED_V2.md - DEV_NOTES_FINAL_LOCKED_V3.md

Purpose: - Deployment discipline - Backup protection - Git control -
Coding rules

### GIT AUTHORITY RULE

Only code merged into the main branch is considered official
implementation.

Local experiments, feature branches, or unmerged code are not
authoritative.

------------------------------------------------------------------------

## LEVEL 5 --- CONTROLLED FUTURE SCOPE

File: - 07_OPEN_QUESTIONS_AND_FUTURE_IDEAS_FINAL.md

Purpose: - Postponed evolution only - No structural redesign - No
override authority

Future scope never overrides active architecture.

------------------------------------------------------------------------

# SECTION 2 --- CONFLICT RESOLUTION MATRIX

If conflict exists:

1.  Level 0 wins.
2.  Within Level 1: Schema \> Data & Flow \> Decisions.
3.  Level 1 overrides Level 2.
4.  Level 2 overrides Level 3.
5.  Level 3 cannot override any structural authority.
6.  Level 4 governs deployment, not structure.
7.  Level 5 never overrides any active level.

------------------------------------------------------------------------

# SECTION 3 --- PHASE DISCIPLINE RULE

CURRENT_PHASE controls:

-   Whether redesign is allowed
-   Whether new entities can be introduced
-   Whether fields can be added
-   Whether lifecycle logic can change

If CURRENT_PHASE states: Redesign Allowed = No

Then: - No entity addition - No field addition - No relationship
modification - No lifecycle alteration - No implicit scope expansion

Without explicit phase update.

------------------------------------------------------------------------

# SECTION 4 --- IMPLEMENTATION SYNCHRONIZATION RULE

After schema implementation:

-   Database must match Level 1 documents.
-   If deviation occurs: → Update documentation immediately. → Commit
    change via Git. → Log revision in Decisions Log.

Documentation and implementation must remain synchronized.

Drift is prohibited.

------------------------------------------------------------------------

# SECTION 5 --- READING ORDER FOR NEW EXECUTION CHAT

Mandatory reading order:

1.  CURRENT_PHASE.md
2.  NON_NEGOTIABLES_V3.md
3.  00_PROJECT_FILE_MAP_FINAL_V2.md
4.  11_SCHEMA_PREPARATION_FINAL_LOCKED_V2.md
5.  05_DATA_AND_FLOW_NOTES_FINAL.md
6.  06_DECISIONS_LOG_FINAL_LOCKED.md
7.  04_PAGE_CATALOG_FINAL.md
8.  02_ROLES_AND_ACCESS_FINAL.md

Only after that: Context and philosophy files.

------------------------------------------------------------------------

# SECTION 6 --- ARCHITECTURAL FREEZE DECLARATION

Architecture Status: Frozen\
Active Phase: Schema Implementation\
Redesign Allowed: No

All development must align with:

-   Single watering record model
-   Attendance independence logic (Green Belt)
-   Attendance dependency logic (Fabrication/Monitoring)
-   Manual archive policy
-   Shared hosting compatibility
-   Explicit lifecycle transitions
-   Audit-logged overrides

------------------------------------------------------------------------

# SECTION 7 --- WHY THIS FILE EXISTS

Without this file: - Authority becomes ambiguous - Drift becomes
invisible - Schema errors become permanent

With this file: - Structure is protected - Implementation is
disciplined - Governance remains intact - Future expansion is controlled

------------------------------------------------------------------------

Document Status: FINAL LOCKED 10/10
