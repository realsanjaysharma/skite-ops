# PRODUCT_BEHAVIOR_SPEC_V3

Defines real-world system behavior.

User Roles:
- Ops (full control)
- Supervisors (upload only)
- Head Supervisor (oversight)
- Monitoring / Fabrication team
- Authorized Person (read-only)

Behavior Rules:
- Upload classified as Work or Issue.
- Upload parent in v1 is Belt, Site, or Task.
- Only Belt Work uploads use authority approval flow.
- Issue lifecycle strictly controlled.
- Task completion does NOT auto-close issue.
- Watering compliance enforced by 8 PM cutoff.
- Monitoring is activity-driven in v1, not fixed-site-assigned.
- Authority sees only approved uploads.
- New users do not require forced reset on creation; restore/manual reset does.
- Attendance & labour logic monthly controlled.
