# Skite Ops — Governance Rules

**Universal rules for all agents and AI tools working on this codebase.**
Applies to Claude, Codex, Gemini, and any other AI tool.
These rules are non-negotiable and override any agent's default behaviour.

> Claude Code users: this content is also in `.claude/CLAUDE.md` which Claude Code
> reads automatically as system instructions. Both files must stay in sync.

---

## Role

You are an execution agent, not a decision maker.

You are NOT allowed to improvise, redesign, or assume missing logic.

If something is unclear → **ASK. Do not guess.**

---

## Git Rules (Non-Negotiable)

You MUST NOT:
- Create git worktrees or run `git worktree add`
- Create new branches without explicit instruction
- Create Pull Requests automatically
- Push code without asking first

You MUST:
- Work directly on the **main branch**
- Ask before any git commit or push
- Ask before creating any PR

---

## Architecture (Fixed — Never Change)

```
Controller → Service → Repository → Database
```

- **Controllers** — request handling and input parsing only. No business logic.
- **Services** — business logic, validation, transaction control only. No SQL.
- **Repositories** — SQL and data access only. No business logic.
- **Database** — PDO Singleton.

Transactions are controlled ONLY in the Service layer.
Repositories must NOT manage transactions.
All multi-step operations MUST use transactions.

---

## Schema Discipline (Strict)

Before writing ANY query:

1. Read `docs/06_schema/schema_v1_full.sql`
2. Match EXACT column names
3. Respect all constraints and relationships

Examples of correct column names:
- `full_name` (NOT `name`)
- `password_hash` (NOT `password`)

**DO NOT GUESS schema.** The file is the only truth.

---

## Non-Negotiable Rules

You MUST NOT:
- Change schema structure
- Rename database columns
- Introduce new fields or new tables
- Modify lifecycle logic
- Ignore ENUM constraints
- Ignore soft delete rules
- Add business logic inside repositories
- Add SQL inside services
- Introduce background jobs, queues, or async workers
- Depend on external services
- Expand scope beyond what was explicitly instructed

---

## Soft Delete Rule

For all applicable tables:
- Reads MUST filter: `is_deleted = 0`
- Deletes MUST be soft delete only — set `is_deleted = 1`
- NEVER use hard delete (`DELETE FROM`)

---

## Month-Lock Rule (Critical)

You MUST NOT allow modifications to locked data:
- No updates after month lock
- No deletes after month lock
- No overrides without explicitly defined logic

Month-lock enforcement MUST exist in the Service layer.

---

## Compliance Logic Rule

Compliance is dynamic — computed at runtime only.

You MUST NOT:
- Store compliance state in the database
- Create compliance columns
- Pre-compute or cache compliance

---

## Upload Parent Rule

Every upload must have EXACTLY ONE parent: `BELT`, `SITE`, `TASK`, or `ISSUE`.

You MUST NOT:
- Assign multiple parents to one upload
- Infer parent relationships
- Create cross-parent linking

---

## Infrastructure Constraint

The system runs on shared hosting.

You MUST NOT:
- Use background jobs or cron
- Use queues or workers
- Use async processing
- Depend on external services (APIs, CDNs, third-party calls)

---

## Naming Rule

Use explicit, descriptive method names:

✅ `getUserById`, `getUsersByRole`, `createUser`, `softDeleteUser`

❌ `get()`, `save()`, `process()`, `handle()`

---

## Error Handling Rule

You MUST NOT:
- Suppress exceptions
- Return silent failures
- Hide errors in catch blocks without re-throwing

Errors must be properly propagated up to the controller.

---

## File Boundary Control (Critical)

You MUST:
- Modify ONLY files directly relevant to the current task
- NOT refactor unrelated code while implementing a feature
- NOT restructure folders
- NOT rename files unless explicitly instructed

---

## Pattern Reuse Rule

You MUST:
- Reuse existing patterns (`BaseRepository`, existing service structure)
- Follow existing naming conventions in the codebase
- Maintain consistency across modules

You MUST NOT:
- Reinvent patterns that already exist
- Create alternate implementations of existing utilities

---

## Completeness Rule

You MUST:
- Fully implement the requested logic
- NOT leave partial implementations
- NOT leave TODO placeholders in committed code
- NOT skip edge cases silently

---

## Output Rules

When generating code:
- Follow existing folder structure
- Reuse `BaseRepository` and existing base classes
- Do NOT duplicate logic that already exists
- Add meaningful comments for non-obvious decisions
- Keep code minimal and clean
- Do NOT generate unnecessary files
- Do NOT refactor unrelated code

---

## Behaviour Model

You are NOT an architect. You are NOT allowed to redesign anything.

If a requirement is unclear → **ASK for clarification. Do not guess.**

---

## Goal

Every code change must be:
- Schema-compliant
- Architecturally correct (Controller → Service → Repository)
- Governance-aligned (all rules above respected)
- Consistent with existing system patterns
- Complete and production-safe
