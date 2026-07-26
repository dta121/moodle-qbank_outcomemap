# qbank_outcomemap operations guide

`qbank_outcomemap` is a thin question-bank integration for governed outcome mappings. It owns no outcome, mapping, evidence, result, audit, privacy, or backup tables. All reads and mutations use the public `local_outcomemap` service boundary, and every mapping remains bound to an exact Moodle question version.

## Requirements and installation

- Minimum Moodle version: 4.5 (`2024100700`).
- Compatibility target: Moodle 4.5 through 5.2/5.3; the installed validation reference is Moodle 5.2.
- Install as `<moodleroot>/question/bank/outcomemap`.
- Install/upgrade the required `local_outcomemap` dependency first. Moodle blocks an incompatible dependency version.
- Enable the Outcome mapping question-bank plugin through Moodle's question-bank plugin management interface, then purge caches.

Do not install this plugin as a substitute for `local_outcomemap`; it cannot operate without the system of record.

## Audience and access

| Audience | Access and workflow |
| --- | --- |
| Administrator | Installs/enables the plugin, validates the local dependency, assigns capabilities, and monitors compatibility and tests. |
| Question author/instructor | Needs `local/outcomemap:viewdefinitions`, `local/outcomemap:mapquestions`, and the applicable Moodle question-bank view/edit capability in the resolved question context. Editing teachers receive the local capabilities by default, subject to overrides. |
| Reviewer | Reviews submitted question mappings in the `local_outcomemap` approval queue. Independent approval should use a different account from the creator. |
| Read-only staff | May see the outcome column and filters only when `viewdefinitions` and the applicable Moodle qbank read capability are present. Mapping actions and bulk controls also require `mapquestions` and relevant Moodle edit access. |
| Student | Receives no qbank mapping UI. Student-facing results are rendered by `local_outcomemap` under its release and access rules. |

Presentation checks do not replace service authorization. Direct requests to `edit.php` or `bulk.php`, bulk preload/mutation services, columns, actions, and public filter queries repeat capability/context enforcement.

## Question-author workflow

1. Open the Moodle question bank. The **Outcomes** column bulk-loads mappings for the visible exact question versions.
2. Use **Outcome** and **Has outcome mappings** filters to find questions. Filter SQL is supplied by the public local API; this plugin never queries governed local tables directly.
3. Choose **Manage outcome mappings** for one question.
4. Select an exact approved outcome version and a mapping role.
5. For `assesses`, enter an explicit positive decimal weight. All approved assessed mappings for that exact question version must total exactly `1.0000000000`.
6. Use `alignment_only`, `teaches`, or `practices` when the relationship must not generate attainment evidence. Do not add an assessed weight to those roles.
7. Submit the draft for review. Submit and Delete are POST actions; draft deletion requires confirmation.
8. If Moodle creates a new question version, review any copied mappings. Copies are drafts, remain attached to the new `question_versions.id`, and never become approved silently.

The bulk action adds one `alignment_only` draft to selected questions. It cannot create assessed mappings or assign weights. Selections are processed in bounded batches and existing mappings are preloaded rather than queried once per question.

## Reviewer workflow

Reviewers work in **Site administration > Plugins > Learning outcome mapping > Approval queue** in the local plugin.

Before approval, verify:

- the question and exact question-version identity;
- the exact outcome wording/version and effective dates;
- the intended role;
- every assessed weight and an exact total of `1.0000000000` for the version;
- that non-assessed roles carry no evidence weight; and
- that copied mappings remain drafts until explicitly accepted.

Approved mappings are immutable. Correct a later version through a new governed draft rather than editing history.

## Operation when this plugin is disabled or absent

Disabling or uninstalling `qbank_outcomemap` removes only its question-bank UI integration and null Privacy API declaration.

- Existing mappings remain in `local_outcomemap`.
- Approved calculations, evidence lineage, audit, reports, privacy processing, and local backup/restore continue to use those mappings.
- The qbank outcome column, filters, editor, and bulk action disappear.
- Reinstalling a compatible qbank version exposes the same local-owned records again, subject to current capabilities.

Do not delete local mapping rows to remove the UI. Disable this qbank plugin instead. Do not uninstall `local_outcomemap` while this plugin is installed because the declared dependency is mandatory.

## Privacy, backup, and restore

The plugin declares a Moodle null privacy provider because it stores no personal data. `local_outcomemap` owns and processes any user-linked governance, evidence, results, audit, and frozen-subject data.

The plugin owns no database schema and adds no duplicate backup payload. Local backup/restore support remaps exact question versions and restores mappings as drafts requiring review. After restoring a question bank or course, verify each mapping's target version and status before calculation.

## Upgrade and rollback

1. Read both release checklists and take verified database, moodledata, and code backups.
2. Deploy and upgrade `local_outcomemap` first.
3. Deploy this plugin only after the required local version is available.
4. From the Moodle root, run the normal upgrade and purge caches:
   ```sh
   php admin/cli/upgrade.php --non-interactive
   ```
5. Verify the plugin is enabled and test the column, both filters, per-question editor, POST submit/delete confirmation, bulk alignment-only action, version-copy-as-draft behavior, and local approval queue.
6. Record the paired local/qbank commit IDs and test evidence.

There is no supported in-place downgrade. Roll back the Moodle database, code, and moodledata together. Because mapping data is local-owned, rolling back only this qbank code can expose an incompatible API; always respect the declared dependency.

## Troubleshooting

- **Plugin cannot install or upgrade:** install the required `local_outcomemap` version first and rerun Moodle upgrade.
- **Column and filters are absent:** verify the qbank plugin is enabled, caches are purged, the current context resolves correctly, and the user has `viewdefinitions` plus Moodle qbank read access.
- **Actions or bulk mapping are absent:** also verify `mapquestions` and the applicable Moodle question edit capability, including context overrides.
- **A mapping appears on an older question only:** mappings are exact-version records. Copy/recreate it as a draft for the new version and review it.
- **An assessed draft cannot be approved:** supply explicit positive weights whose exact approved total is `1.0000000000`; no equal-weight fallback exists.
- **Bulk mapping skips questions:** the question may already have the requested exact-version mapping, be outside the authorized context, or fail core question edit checks.
- **UI works but calculations do not change:** only effective approved `assesses` mappings contribute evidence. Check local policies, grading, cron, and reconciliation.

Never bypass the local public API or repair mappings with direct SQL.
