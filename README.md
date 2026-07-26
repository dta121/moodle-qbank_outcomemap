# Moodle Question Bank Outcome Mapping

`qbank_outcomemap` adds question-bank interfaces for mapping exact question versions to governed learning outcomes.

It is a companion to `local_outcomemap` and must use that plugin's public services as the system of record.

Read [docs/QBANK_IMPLEMENTATION_PLAN.md](docs/QBANK_IMPLEMENTATION_PLAN.md) before implementation. The canonical cross-plugin requirements are maintained in the sibling repository at `moodle-local_outcomemap/docs/OUTCOME_MAPPING_SPEC.md`.

## Repository and installation locations

- Repository: `D:\wamp64\www\moodle-qbank_outcomemap`
- Minimum supported Moodle version: 4.5 (`2024100700`); compatible through Moodle 5.2/5.3
- Moodle 5.2 validation target: `D:\wamp64\www\moodle502\public\question\bank\outcomemap`
- Component name: `qbank_outcomemap`
- Required dependency: `local_outcomemap` (2026072703 or later)

The plugin intentionally declares no qbank-owned capabilities or persistence. Every read and mutation is authorized by both the applicable Moodle question capability and the public `local_outcomemap` service/capability boundary. Mapping records, audit history, calculations, and evidence remain owned by `local_outcomemap`.

Implementation follows the eight milestones in [docs/QBANK_IMPLEMENTATION_PLAN.md](docs/QBANK_IMPLEMENTATION_PLAN.md).
