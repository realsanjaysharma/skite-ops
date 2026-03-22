# ARCHITECTURAL_RATIONALE_V3

Key Design Decisions & Why:

- Single watering record per day → Simplicity + enforcement.
- No routine scheduling engine → Avoid overengineering.
- Manual archive only → Governance clarity.
- No dynamic permission toggles → Prevent chaos.
- Shared hosting compatibility → Deployment realism.

Architecture favors control over feature expansion.

Backend layer rule:

Controllers handle HTTP input/output only and must not contain business logic.
Controllers handle request validation (format, required fields).
Services handle business validation and domain rules.
All business rules and system behavior must be implemented in the Service layer.
Repositories are responsible only for database access and must not contain business logic.
Authorization (RBAC) must be enforced at the middleware layer before reaching controllers. Services must not perform role-based access checks.
