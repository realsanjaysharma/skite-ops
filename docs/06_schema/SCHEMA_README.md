# Skyte Ops — Database Schema Documentation

This directory contains the database schema definition for the Skyte Ops system.

Schema Status: **Frozen (Version v1)**

The schema defines the structural backbone of the system and must remain consistent with governance documentation.

---

# Files in this Directory

## schema_v1_full.sql

Executable SQL schema used to create the database.

This file represents the **authoritative structural definition** of the system.

If documentation conflicts with SQL structure, **the SQL schema takes precedence**.

---

## 11_SCHEMA_BASELINE_V1.md

High-level overview of the schema.

Defines:

• Tables included  
• Structural guarantees  
• Governance rules  
• Schema authority model  

This document explains **what exists in the schema**, not column-level detail.

---

## 12_SCHEMA_SPECIFICATION.md

Detailed explanation of every table.

Includes:

• Real-world mapping  
• Column purpose  
• Constraints  
• Lifecycle implications  
• Governance enforcement

This document is primarily used for:

• Developer onboarding  
• Maintenance understanding  
• Architecture explanation in interviews

---

# Schema Version Control

Schema version is tracked in the database table:

system_meta

Example row:

id = 1  
schema_version = 1

Future schema changes must increment this value.

---

# Schema Authority Rule

The SQL schema is the **executable truth of the system**.

If documentation conflicts with SQL structure:

**SQL takes precedence.**

Schema modifications require:

1. Migration file  
2. Decisions Log update  
3. Documentation synchronization  

---

# Design Philosophy

The schema prioritizes governance, auditability, and operational realism.

Key examples:

• Supervisor ownership modeled historically  
• Compliance calculated dynamically  
• Alerts not stored in database  
• Parent-child relationships immutable  

Principles:

Governance > Convenience  
Auditability > Automation  
Clarity > Cleverness