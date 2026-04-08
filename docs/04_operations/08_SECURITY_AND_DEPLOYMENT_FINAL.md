# Skyte Ops Security And Deployment

## Purpose

This file records the active security and deployment posture for the current Skite Ops documentation model.
It supports the recovered canon and build-spec layer. It does not define product scope.

Primary references:

- `docs/README.md`
- `docs/11_build_specs/03_API_AND_ROUTE_CONTRACT.md`
- `docs/11_build_specs/06_UPLOAD_STORAGE_RETENTION_SPEC.md`
- `docs/11_build_specs/08_SYSTEM_SETTINGS_AND_EXTERNAL_ACTIONS.md`

## 1. Authentication Model

- PHP session-based authentication
- `session_regenerate_id(true)` on successful login
- full session destruction on logout
- user identity and role context stored server-side only
- RBAC enforced before controllers through middleware
- inactivity timeout enforced through deployment-aware session policy

### Session Cookie Rules

- `HttpOnly` enabled in production
- `Secure` enabled when HTTPS is active
- `SameSite=Strict` unless deployment constraints require an explicit documented exception

## 2. Password And Login Policy

- password storage must use `password_hash()`
- minimum password length: 8
- no email self-service reset in v1
- Ops can trigger manual reset
- forced password reset can block protected routes until the reset is completed
- login throttling and lock thresholds must be enforced server-side

Threshold values belong in deployment config or approved auth configuration, not scattered through controller code.

## 3. CSRF Protection

- CSRF token generated per session
- token required for authenticated mutating requests
- invalid token causes immediate rejection
- route behavior must stay aligned with `docs/11_build_specs/03_API_AND_ROUTE_CONTRACT.md`

## 4. Database Security Rules

- use prepared statements only
- do not concatenate user input into SQL
- validate server-side before persistence
- escape output at the view layer

## 5. File Upload Security

### Core Rules

- validate MIME type server-side
- validate extension and MIME together
- reject executable or double-extension style inputs
- enforce hard upload-size ceilings in config
- generate collision-safe server filenames
- store relative path in the database, not public URL

### Storage Contract

Relative upload paths must follow the canonical pattern:

```text
uploads/<parent_type>/<YYYY>/<MM>/<generated_file_name>.<ext>
```

Examples:

```text
uploads/green_belt/2026/04/gb_4812_01f3c8a2.jpg
uploads/site/2026/04/site_9044_0c91f7ab.webp
uploads/task/2026/04/task_2210_9a22c001.jpg
```

The physical upload root should remain deployment-configured and protected from direct unauthenticated file browsing.

## 6. Error Handling

### Local

- debug display may be enabled for development only

### Production

- no raw PHP errors shown to users
- error logging enabled
- detailed internal diagnostics written to protected logs

## 7. Web Server Hardening

- force HTTPS in production
- disable directory listing
- protect upload and log directories
- set security headers such as:
  - `X-Frame-Options: SAMEORIGIN`
  - `X-Content-Type-Options: nosniff`

## 8. Environment Strategy

- separate local, staging, and production configuration
- do not commit secrets
- keep separate DB credentials per environment
- enable debug only in local development unless explicitly justified

## 9. Deployment Strategy

- private Git-based workflow
- no direct production edits
- deploy only from reviewed, intentional releases
- shared-hosting compatibility remains a hard constraint

## 10. Shared Hosting Boundaries

- no required background workers for core correctness
- no queue dependency for product truth
- no root-level assumptions
- avoid operational designs that require infrastructure the target environment does not provide

## 11. Input Validation Policy

- validate all GET and POST inputs server-side
- cast numeric values explicitly
- validate enums against the allowed vocabulary
- validate dates for format and range
- never rely on client-side validation for business correctness

## Active Status

This file remains an active operational reference.
If it conflicts with the build-spec layer on implementation detail, the build-spec layer wins.
