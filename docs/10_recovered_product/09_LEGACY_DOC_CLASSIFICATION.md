# Legacy Doc Classification

## Purpose

This file classifies every pre-recovery document currently in `docs/` into one of four actions:

- keep
- rewrite
- merge
- archive

This classification applies to legacy documents outside `docs/10_recovered_product/`.

Recovered canon files are not part of this list.

## Category Meanings

- `keep` = still useful with only minor future cleanup
- `rewrite` = must be rewritten against the recovered canon
- `merge` = useful content should be absorbed into stronger docs, then the source should be archived
- `archive` = no longer strong enough to remain active truth

## Root Docs

- `docs/README.md` - `keep`
  The README now reflects the two-layer model and should continue as the top-level docs index.

- `docs/.gitkeep` - `archive`
  This is only a placeholder and is no longer needed once the docs tree is populated.

## 00_governance

- `docs/00_governance/00_PROJECT_FILE_MAP_FINAL_V2.md` - `merge`
  Useful hierarchy language remains, but the authority model is now superseded by the recovered canon and build-spec layer.

- `docs/00_governance/CURRENT_PHASE.md` - `merge`
  This is a small status artifact that should be absorbed into the implementation master plan.

- `docs/00_governance/NON_NEGOTIABLES_V3.md` - `merge`
  Some rules remain useful, but this is now too small and too tied to the trimmed v1 freeze.

- `docs/00_governance/SYSTEM_BOOT_PROMPT_V3.md` - `archive`
  This is an old AI boot artifact, not a durable repo truth document.

## 01_structure

- `docs/01_structure/05_DATA_AND_FLOW_NOTES_FINAL.md` - `rewrite`
  It carries real structural value, but it still reflects the trimmed product and must be rewritten from the recovered workflow and entity model.

- `docs/01_structure/06_DECISIONS_LOG_FINAL_LOCKED.md` - `rewrite`
  It remains important, but decision entries must be realigned to recovered product truth and the upcoming build specs.

- `docs/01_structure/07_EXECUTION_BEHAVIOR_LOCK_V1.md` - `merge`
  Useful execution rules belong in the workflow state-machine spec and implementation master plan rather than as a standalone frozen v1 behavior file.

## 02_interface

- `docs/02_interface/02_ROLES_AND_ACCESS_FINAL.md` - `rewrite`
  This is one of the most important rewrite targets because the old version still carries trimmed role behavior.

- `docs/02_interface/03_DESIGN_PRINCIPLES_FINAL.md` - `merge`
  Its durable engineering rules should move into the build-spec layer and top-level docs governance.

- `docs/02_interface/04_PAGE_CATALOG_FINAL_V4_LOCKED.md` - `rewrite`
  This is a major rewrite target because the recovered product has broader page/module scope and different landing behavior.

## 03_context

- `docs/03_context/00_PROJECT_OVERVIEW_FINAL.md` - `rewrite`
  The product identity here is materially narrower than the recovered product.

- `docs/03_context/01_REQUIREMENTS_CONTEXT_FINAL.md` - `rewrite`
  This still reflects the reduced interpretation and must be rebuilt from recovered intent.

- `docs/03_context/ARCHITECTURAL_RATIONALE_V3.md` - `merge`
  A few rationale points remain useful, but this should not remain a standalone active authority doc.

- `docs/03_context/MASTER_CONTEXT_V3.md` - `archive`
  This is now a weak summary duplicate of stronger recovered docs.

- `docs/03_context/PRODUCT_BEHAVIOR_SPEC_V3.md` - `archive`
  This is a thin trimmed-product summary and should not remain active truth.

## 04_operations

- `docs/04_operations/08_SECURITY_AND_DEPLOYMENT_FINAL.md` - `keep`
  This still appears useful as operational governance and is not obviously in conflict with recovered product scope.

- `docs/04_operations/09_BACKUP_AND_RECOVERY_FINAL_LOCKED.md` - `keep`
  This is still useful operationally and not part of the trimmed-product distortion.

- `docs/04_operations/10_GIT_WORKFLOW_FINAL_LOCKED_V2.md` - `keep`
  This remains useful process guidance.

- `docs/04_operations/DEV_NOTES_FINAL_LOCKED_V3.md` - `merge`
  Useful dev discipline exists here, but much of it overlaps with older boot/governance files and should be consolidated into the build-spec layer.

## 05_future

- `docs/05_future/07_OPEN_QUESTIONS_AND_FUTURE_IDEAS_FINAL.md` - `archive`
  This future-scope list is tied to the old trimmed model and should not remain active planning truth.

## 06_schema

- `docs/06_schema/11_SCHEMA_BASELINE_v1_FINAL_WITH_DDL.md` - `rewrite`
  This must eventually reflect the recovered entity model and canonical schema roadmap.

- `docs/06_schema/12_SCHEMA_SPECIFICATION_v1.md` - `rewrite`
  This is still useful structurally, but it must be realigned after the build-spec schema roadmap is created.

- `docs/06_schema/SCHEMA_README.md` - `merge`
  This is a glue doc whose useful rules should be absorbed into the canonical schema roadmap and top-level README.

- `docs/06_schema/schema_v1_full.sql` - `rewrite`
  This is not just a document; it is the old executable schema artifact.
  It must be treated carefully, but it still represents the trimmed model and should eventually be rebuilt from the canonical schema roadmap.

## architecture

- `docs/architecture/skite_ops_architecture_roadmap.pdf` - `archive`
  This is a visual artifact from the old trimmed architecture phase and should not remain active truth without regeneration.

## Recommended Action Order

1. Fill `docs/11_build_specs/`
2. Rewrite:
   - `docs/03_context/00_PROJECT_OVERVIEW_FINAL.md`
   - `docs/02_interface/02_ROLES_AND_ACCESS_FINAL.md`
   - `docs/02_interface/04_PAGE_CATALOG_FINAL_V4_LOCKED.md`
   - `docs/01_structure/05_DATA_AND_FLOW_NOTES_FINAL.md`
3. Rewrite schema docs and SQL after the canonical schema roadmap is ready
4. Merge the useful small governance/dev docs into stronger docs
5. Archive the weak summary and boot artifacts
