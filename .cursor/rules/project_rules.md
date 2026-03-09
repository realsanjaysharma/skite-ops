\# Skyte Ops — Cursor Development Rules



This repository uses a documentation-driven architecture.



Before generating or modifying code, always read the documentation hierarchy.



Reading order:



1\. docs/00\_governance

2\. docs/01\_structure

3\. docs/02\_interface

4\. docs/03\_context

5\. docs/04\_operations

6\. docs/06\_schema



---



\# Architecture Status



Architecture: Frozen  

Schema: v1 Locked  



Structural redesign is not allowed.



---



\# Hard Rules



Cursor must NOT:



• introduce new database tables  

• redesign schema structure  

• modify lifecycle logic  

• ignore month-lock rules  

• invent entities not defined in schema  



---



\# Database Authority



Database schema file:



docs/06\_schema/schema\_v1\_full.sql



If generated code conflicts with schema:



\*\*Schema always wins.\*\*



---



\# Governance Documents



Core system behavior is defined in:



docs/01\_structure/05\_DATA\_AND\_FLOW\_NOTES\_FINAL.md  

docs/01\_structure/06\_DECISIONS\_LOG\_FINAL\_LOCKED.md  

docs/00\_governance/NON\_NEGOTIABLES\_V3.md  



Implementation must follow these documents.



---



\# Code Architecture Rules



Controllers:



• Handle HTTP requests only  

• Perform role validation  

• Call service layer  

• Must NOT contain SQL queries  



Services:



• Contain all business logic  

• Contain database queries  

• Enforce governance rules  



Views:



• Display only  

• No business logic  

• No database queries

