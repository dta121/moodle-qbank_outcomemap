# Codex instructions

Read `docs/QBANK_IMPLEMENTATION_PLAN.md` and the sibling `moodle-local_outcomemap/docs/OUTCOME_MAPPING_SPEC.md` completely before implementing or changing this plugin.

## Scope

- This repository is the source for the Moodle component `qbank_outcomemap`.
- This plugin provides question-bank presentation and workflows only.
- `local_outcomemap` owns all governed definitions, mappings, evidence, calculations, audit history, and reports.
- Declare and enforce the required plugin dependency.
- Use public `local_outcomemap` service classes; do not access its tables directly.

## Local Moodle reference

- Minimum supported Moodle version: 4.5 (`2024100700`); local 4.5 tree: `D:\wamp64\www\moodle405`
- Compatibility target: Moodle 4.5 through 5.2/5.3
- Installed Moodle 5.2 validation source: `D:\wamp64\www\moodle502\public`
- Source syntax baseline: PHP 8.1-compatible; the local Moodle 5.2 tree runs PHP 8.3.
- Cross-version qbank API constraints are recorded in the sibling ADR 0001 (`moodle-local_outcomemap/docs/adr/0001-moodle-api-compatibility.md`).

Inspect the enabled qbank plugins in `D:\wamp64\www\moodle502\public\question\bank` and the installed question-bank core classes before implementing columns, filters, actions, or bulk operations. Do not invent APIs and do not edit Moodle core.

## Working rules

- Bulk-load mappings for the visible question-bank page; never query once per row.
- Bind mappings to exact question versions.
- Copy mappings to a new version only as draft and require review.
- Never silently assign weights to assessed multi-outcome questions.
- Require the local capability and applicable question-bank capability.
- Keep UI accessible and compatible with Moodle output conventions.
- Add PHPUnit and Behat coverage for every critical question-mapping workflow.
