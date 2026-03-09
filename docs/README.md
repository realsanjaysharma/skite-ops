# Skyte Ops Documentation System

This repository contains a governance-first operations system.

The documentation is structured as a hierarchical authority system.

Not all documents have equal authority.

The reading order and authority levels must be respected during development.

---

# Documentation Hierarchy

The documentation is divided into levels.

Higher levels override lower levels.

LEVEL 0 — GOVERNANCE (Highest Authority)

These files control architecture boundaries and system rules.

docs/00_governance/

Files:

NON_NEGOTIABLES_V3.md  
CURRENT_PHASE.md  
SYSTEM_BOOT_PROMPT_V3.md  
00_PROJECT_FILE_MAP_FINAL_V2.md

These files define:

• Architecture freeze status  
• System boundaries  
• Development discipline  
• Documentation reading order

If any file conflicts with these, these files take precedence.

---

LEVEL 1 — STRUCTURAL AUTHORITY

docs/01_structure/

Files:

05_DATA_AND_FLOW_NOTES_FINAL.md  
06_DECISIONS_LOG_FINAL_LOCKED.md  

These files define:

• System entities  
• Lifecycle rules  
• Compliance logic  
• Governance decisions

All backend implementation must align with these documents.

---

LEVEL 2 — SYSTEM INTERFACE

docs/02_interface/

Files:

04_PAGE_CATALOG_FINAL_V4_LOCKED.md  
02_ROLES_AND_ACCESS_FINAL.md  
03_DESIGN_PRINCIPLES_FINAL.md  

These define:

• Page structure
• Role-based access model
• UI architecture
• Development principles

---

LEVEL 3 — CONTEXT & PRODUCT INTENT

docs/03_context/

Files:

00_PROJECT_OVERVIEW_FINAL.md  
01_REQUIREMENTS_CONTEXT_FINAL.md  
MASTER_CONTEXT_V3.md  
ARCHITECTURAL_RATIONALE_V3.md  
PRODUCT_BEHAVIOR_SPEC_V3.md  

These explain the operational purpose and system philosophy.

---

LEVEL 4 — OPERATIONAL GOVERNANCE

docs/04_operations/

Files:

08_SECURITY_AND_DEPLOYMENT_FINAL.md  
09_BACKUP_AND_RECOVERY_FINAL_LOCKED.md  
10_GIT_WORKFLOW_FINAL_LOCKED_V2.md  
DEV_NOTES_FINAL_LOCKED_V3.md  

These define development workflow and operational safety rules.

---

LEVEL 5 — FUTURE IDEAS

docs/05_future/

Files:

07_OPEN_QUESTIONS_AND_FUTURE_IDEAS_FINAL.md  

This file contains potential future enhancements and ideas.

These are NOT part of v1 implementation.

---

# Schema Documentation

docs/schema/

Files:

schema_v1_full.sql  
11_SCHEMA_BASELINE_V1.md  
12_SCHEMA_SPECIFICATION.md  

The schema is frozen at version v1.

If documentation conflicts with the SQL schema, the schema file takes precedence.

Schema changes require:

• Migration file  
• Decisions Log update  
• Documentation update

---

# Architecture Status

Architecture: Frozen  
Schema: v1 Locked  
Active Phase: Backend Implementation

Redesign is not allowed unless CURRENT_PHASE explicitly changes.

---

# Development Philosophy

Governance > Convenience  
Auditability > Automation  
Clarity > Cleverness

All code must follow the rules defined in the documentation hierarchy.