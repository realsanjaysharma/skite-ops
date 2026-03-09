\# Codex System Instructions — Skyte Ops



This repository follows a governance-driven architecture.



Before analyzing code or suggesting changes, read documentation in this order:



docs/00\_governance  

docs/01\_structure  

docs/02\_interface  

docs/03\_context  

docs/04\_operations  

docs/06\_schema  



---



\# Architecture Status



Architecture: Frozen  

Schema: Version v1 Locked  



Structural redesign is not allowed.



---



\# Codex Restrictions



Codex must NOT propose:



• new database tables  

• schema redesign  

• lifecycle rule changes  

• automation not documented in system rules  

• scheduling engines or background job systems  



---



\# Governance Authority



Primary behavioral definitions exist in:



docs/01\_structure/05\_DATA\_AND\_FLOW\_NOTES\_FINAL.md  

docs/01\_structure/06\_DECISIONS\_LOG\_FINAL\_LOCKED.md  

docs/00\_governance/NON\_NEGOTIABLES\_V3.md  



These documents define system behavior.



---



\# Schema Authority



Database schema:



docs/06\_schema/schema\_v1\_full.sql



If documentation conflicts with generated code suggestions:



\*\*Documentation prevails.\*\*



Codex acts only as a \*\*code assistant\*\*, not as the system architect.



Architectural authority belongs to the system owner.

