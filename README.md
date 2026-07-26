# Moodle Question Bank Outcome Mapping

`qbank_outcomemap` adds accessible question-bank interfaces for mapping exact Moodle question versions to governed learning outcomes. It is a thin companion to [`local_outcomemap`](https://github.com/dta121/moodle-local_outcomemap), which remains the system of record for definitions, mappings, evidence, calculations, audit, privacy, backup/restore, and reports.

Current release: **0.8.0 beta** (`2026072800`).

## Compatibility and installation

- Minimum Moodle: 4.5 (`2024100700`)
- Compatibility target: Moodle 4.5 through 5.2/5.3
- Installed validation reference: Moodle 5.2 with PHP 8.3
- Source syntax baseline: PHP 8.1-compatible
- Install path: `<moodleroot>/question/bank/outcomemap`
- Component: `qbank_outcomemap`
- Required dependency: `local_outcomemap` `2026072704` or later

Install or upgrade the required local plugin first, then run Moodle's normal upgrade and enable the qbank plugin. Moodle dependency resolution blocks installation against an older local release.

## Features and invariants

- Outcome column with bounded, memoized bulk mapping loads for every visible exact question version
- Outcome-code, role, status, mapped/unmapped, invalid-weight, and copied-pending filters through the public `question_mapping_filters` API
- Per-question editor bound to the displayed exact question version, with inline draft editing and POST review/delete actions
- Copy-to-new-version behavior that always creates drafts, with an explicit review preview
- Bulk mapping through a preview-then-confirm flow committed atomically by the public API
- Explicit read, mapping, and Moodle question-capability enforcement, failing closed when `local_outcomemap` is absent
- Mode-aware governance terminology selected through the public `workflow` facade
- Null Privacy API provider and no plugin-owned schema or backup payload

All governed reads and mutations use public `local_outcomemap` services. This plugin must never query or write `local_outcomemap_*` tables directly.

Disabling or removing this plugin hides its qbank UI without deleting local-owned mappings or interrupting approved calculations, audit, reports, privacy processing, or restore behavior.

## Documentation

- [Implementation plan](docs/QBANK_IMPLEMENTATION_PLAN.md)
- [Operations guide](docs/OPERATIONS.md)
- [Release checklist](docs/RELEASE_CHECKLIST.md)
- [Canonical cross-plugin specification](https://github.com/dta121/moodle-local_outcomemap/blob/main/docs/OUTCOME_MAPPING_SPEC.md)
- [Local plugin operations guide](https://github.com/dta121/moodle-local_outcomemap/blob/main/docs/OPERATIONS.md)

The operations guide covers administrator, question-author, reviewer, read-only, and student boundaries; exact-version and weight workflows; operation while qbank is absent; privacy/backup ownership; upgrades; rollback; and troubleshooting.
