# qbank_outcomemap 0.8.x release checklist

Complete this together with the [`local_outcomemap` release checklist](https://github.com/dta121/moodle-local_outcomemap/blob/main/docs/RELEASE_CHECKLIST.md). The local plugin is the system of record and must be released first.

## 1. Release identity and dependency

- [ ] `version.php` contains the intended qbank component, version, release, maturity, and Moodle minimum.
- [ ] `$plugin->dependencies['local_outcomemap']` points to the exact minimum public API/schema release used by this code.
- [ ] The target local release declares the public question-mapping API version expected by the qbank plugin.
- [ ] Installation is blocked when the required local version is absent.
- [ ] README, implementation plan, operations guide, and this checklist describe the same compatibility and dependency.
- [ ] No schema, governed data ownership, backup payload, or direct `local_outcomemap_*` table access has been added.

## 2. Moodle/API compatibility

- [ ] Column, filter, per-question action, and bulk-action signatures match Moodle 4.5.
- [ ] The same code is checked against the installed Moodle 5.2 question-bank core and enabled qbank plugins.
- [ ] Nullable-view filter/bulk registration uses the supported active-page-context fallback.
- [ ] Exact question context and Moodle question capabilities are resolved using installed public APIs.
- [ ] No Moodle core files are changed and no invented/deprecated API is required.

## 3. Automated validation

- [ ] Every PHP file passes `php -l` on the PHP 8.1 syntax baseline and validation PHP version.
- [ ] `git diff --check` and Moodle coding-style/static checks pass.
- [ ] `vendor/bin/phpunit --testsuite qbank_outcomemap_testsuite` passes in a generated Moodle test environment with the matching local plugin installed.
- [ ] `php admin/tool/behat/cli/run.php --tags=@qbank_outcomemap` passes.
- [ ] Privacy null-provider tests pass.
- [ ] Moodle 4.5 and 5.2 compatibility smoke tests pass without plugin-attributable deprecation warnings.

## 4. Authorization and workflow

- [ ] Column and filters are hidden without `local/outcomemap:viewdefinitions`.
- [ ] Editor and bulk controls require `viewdefinitions`, `mapquestions`, and applicable Moodle question capabilities.
- [ ] Direct editor/bulk requests and public filter boundaries reject unauthorized users.
- [ ] Every bulk-selected question is re-authorized in its own exact question-bank context.
- [ ] Question mappings bind to `question_versions.id`; older mappings remain historical.
- [ ] New-version copies are drafts and require explicit review.
- [ ] Create/update, submit, delete, copy, and bulk commit use POST and sesskeys; deletion requires confirmation.
- [ ] Posted outcome UUIDs are revalidated against the authoritative context and effective timestamp.
- [ ] The local approval queue enforces creator/reviewer separation when configured.

## 5. Weight and bulk safety

- [ ] `assesses` mappings require explicit positive weights and exact approved total `1.0000000000`.
- [ ] Non-assessed roles reject evidence weights.
- [ ] Multi-outcome weights are never inferred or assigned equally.
- [ ] Bulk assessed mappings require explicit per-question/per-mapping weights and a successful atomic preview.
- [ ] Bulk batches are bounded at 1,000 IDs and reject/skip unauthorized or invalid questions safely.

## 6. Performance and ownership boundary

- [ ] Visible question mappings are loaded in bounded page batches, never once per row.
- [ ] Bulk core metadata and existing mappings use a fixed query budget; the 25-question regression remains within the approved bound.
- [ ] Filters use parameterized SQL returned through the public local API.
- [ ] Free-text filters bound the number and length of terms before expanding SQL predicates.
- [ ] A repository search confirms no direct governed-table access.
- [ ] The qbank plugin owns no mapping/evidence/result state and its Privacy API provider remains null.

## 7. Accessibility

- [ ] The first content heading has the correct hierarchy and the mapping table has an exact-version caption.
- [ ] Submit and Delete are keyboard-operable native buttons with visible focus; Delete confirmation is announced.
- [ ] Outcome/version, role, weight, status, and action meaning is available without relying on color or title attributes.
- [ ] The table works in a narrow viewport.
- [ ] Read-only/unauthorized UI and mapping workflow Behat coverage passes with supported browsers.

## 8. Upgrade, absence, and rollback smoke tests

- [ ] Upgrade `local_outcomemap` first, then qbank, and confirm Moodle dependency resolution.
- [ ] With qbank enabled, test column, filters, editor, bulk action, review, and version-copy behavior.
- [ ] Disable/remove qbank and verify local-owned mappings, calculations, reports, privacy, audit, and restore still operate.
- [ ] Re-enable a compatible qbank release and verify the same mappings reappear.
- [ ] Pre-upgrade backups and a paired local/qbank rollback procedure are recorded; no in-place downgrade is promised.

## 9. Packaging and sign-off

- [ ] Package root is exactly `question/bank/outcomemap` and contains no `.git`, secrets, generated runner configuration, or unrelated artifacts.
- [ ] Release commit/tag and package checksum are recorded.
- [ ] Matching local commit/tag, public API version, and package checksum are recorded.
- [ ] Moodle/PHP/database/browser matrix and PHPUnit/Behat/static/performance/accessibility evidence are attached.
- [ ] Question authors, independent reviewers, Moodle administrators, privacy/security owners, and release manager approve production deployment.
