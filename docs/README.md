# Skite Ops — Documentation Index

This file explains what every doc folder and file is for, who maintains it, and when to read it.

---

## Quick start for any agent

```
1. Read docs/AGENT_START.md          — current state, focus, what not to touch
2. Read docs/PRODUCT_BACKLOG.md      — all planned / done / deferred work + page status
3. Read docs/GOVERNANCE.md           — architecture and governance rules (all agents)
4. Read docs/AI_TOOL_HANDOFF_GUIDE.md — codebase pitfalls before writing any code
```

That is the full required reading for every session. Everything else is reference.

---

## Active documentation (read and maintain)

These files are live. Agents update them every session.

| File | Purpose | Updated by |
|---|---|---|
| `docs/AGENT_START.md` | Current product state, last work, current focus, what NOT to touch, mandatory end-of-session checklist | Every agent, end of every session |
| `docs/PRODUCT_BACKLOG.md` | All features done / in progress / planned / deferred. Page status table for all 10 roles and ~30 pages | Every agent when work is completed or planned |
| `docs/PRODUCT_LOG.md` | Append-only log of why decisions were made — never edited, only appended | Every agent, key decisions only |
| `docs/GOVERNANCE.md` | Architecture and governance rules for **all** agents (Claude, Codex, Gemini, etc.) | Only when governance rules change — keep in sync with `.claude/CLAUDE.md` |
| `docs/AI_TOOL_HANDOFF_GUIDE.md` | Codebase pitfalls, tricky patterns, and gotchas discovered during development and testing | Any agent that finds a new pitfall |
| `.claude/CLAUDE.md` | Claude Code system instructions — mirrors `docs/GOVERNANCE.md` + adds Claude-specific session reading order | Only when governance rules change — keep in sync with `docs/GOVERNANCE.md` |

---

## Reference documentation (read, do not update)

### `docs/06_schema/`

The schema is the source of truth for all database work.

| File | Purpose |
|---|---|
| `schema_v1_full.sql` | **READ BEFORE WRITING ANY QUERY.** Exact column names, types, ENUMs, FKs |
| `12_SCHEMA_SPECIFICATION_v1.md` | Schema decisions and rationale |
| `11_SCHEMA_BASELINE_v1_FINAL_WITH_DDL.md` | Baseline DDL reference |

### `docs/10_recovered_product/`

Original product intent recovered from design transcripts before the build. Useful for
understanding *why* the system works the way it does — scope, roles, entities, workflows.

**Do not update.** If the actual product has drifted from these docs, the product wins.
Record the deviation in `PRODUCT_LOG.md` if it matters.

| File | What it covers |
|---|---|
| `00_FINAL_PRODUCT_BEHAVIOR_MODEL.md` | Overall product behaviour |
| `01_ROLE_AND_ACCESS_MODEL.md` | Role definitions and what each role can do |
| `02_DOMAIN_AND_ENTITY_MODEL.md` | Core entities and relationships |
| `03_WORKFLOWS_AND_LIFECYCLES.md` | Key operational workflows |
| `04_PAGE_AND_MODULE_MODEL.md` | Page catalogue |
| `06_REPORT_AND_EXPORT_MODEL.md` | Reporting |
| `07_AUTHORITY_SHARE_AND_SUMMARY_MODEL.md` | Authority representative workflows |

### `docs/11_build_specs/`

**ARCHIVED.** Written before the product was built. Many things have been added,
changed, and improved through development, agent testing, and real-world product owner
feedback. These files reflect original design intent — they do not reflect the current
implementation. Each file has an archive notice at the top.

Use these only to understand the original reasoning behind a design decision.
For current state, use `PRODUCT_BACKLOG.md`.

**Do not update these files.**

### `docs/01_structure/`, `docs/02_interface/`, `docs/03_context/`, `docs/04_operations/`

Legacy reference folders from the pre-build phase. Variable quality and accuracy.
Some files are still useful for context; others are outdated. Not maintained.

---

## Historical records (read-only)

| File | What it is |
|---|---|
| `tests/TEST_RESULTS.md` | QA phase archive (T01–T70, E2E-01–E2E-07). Testing phase is complete. Do not add entries. |

---

## Document authority rules

When two documents conflict:

1. **`docs/06_schema/schema_v1_full.sql`** — final authority on database structure
2. **`.claude/CLAUDE.md`** — final authority on architecture and governance rules
3. **`docs/PRODUCT_BACKLOG.md`** — final authority on current product feature state
4. **`docs/PRODUCT_LOG.md`** — final authority on why decisions were made
5. **`docs/10_recovered_product/`** — authority on original product intent
6. **`docs/11_build_specs/`** — historical only, does not override anything above

---

## Development philosophy

```
Governance > Convenience
Auditability > Automation
Clarity > Cleverness
```

Architecture (non-negotiable):
```
Controller → Service → Repository → Database
```
