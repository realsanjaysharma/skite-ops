# Migration Bootstrap Notes

The old trimmed-era migration chain was removed on purpose.

Use this bootstrap order for local validation and first-run setup:

1. Run [docs/06_schema/schema_v1_full.sql](C:/xampp/htdocs/skite/docs/06_schema/schema_v1_full.sql)
2. Run [001_seed_foundation.sql](C:/xampp/htdocs/skite/migrations/001_seed_foundation.sql)
3. Optionally create the first Ops user from [002_bootstrap_ops_user.template.sql](C:/xampp/htdocs/skite/migrations/002_bootstrap_ops_user.template.sql)

Important rules:

- `docs/06_schema/schema_v1_full.sql` is the canonical executable DDL baseline
- there is no `system_meta` or `schema_migrations` truth in the recovered product
- do not recreate the old `001/002/003` trimmed schema chain
- seed files are idempotent where practical and are meant to be re-runnable on clean local databases
