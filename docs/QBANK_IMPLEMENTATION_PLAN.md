# `qbank_outcomemap` Implementation Plan

Status: Companion-plugin plan  
Target: Moodle 4.5 (`2024100700`) through 5.2/5.3; PHP 8.1-compatible source  
Validation environment: installed Moodle 5.2 tree on PHP 8.3  
Dependency: `local_outcomemap`

## 1. Responsibility

Provide an efficient Moodle question-bank interface for reading and editing outcome mappings owned by `local_outcomemap`.

Do not create duplicate outcome or mapping tables. Do not calculate attainment in this plugin.

## 2. Required features

### 2.1 Question-bank column

Display a compact outcome summary for the exact question version shown in the bank.

The column must:

- Bulk-load all mappings for the visible question-version IDs.
- Distinguish approved, draft, needs-review, and retired mappings.
- Distinguish assessed outcomes from alignment-only outcomes.
- Show an accessible label and not rely on color alone.
- Link authorized users to the mapping editor.
- Avoid exposing mappings to users without the required capabilities.

### 2.2 Filter

Support filtering by:

- Outcome or framework
- Mapping role
- Approval state
- Unmapped questions
- Invalid assessed-weight totals
- Mappings copied from an earlier version and awaiting review

Use the installed Moodle 5.2 qbank filter APIs and parameterized SQL/DML patterns.

### 2.3 Per-question mapping action

Open a form or modal showing:

- Question-bank entry and exact version
- Existing mappings and their status
- Context-scoped outcome search
- Mapping role
- Assessed weight
- Notes and optional review message
- Save draft and submit-for-review actions

The UI must state that alignment mappings do not generate student attainment evidence.

### 2.4 Bulk mapping action

Allow authorized users to:

- Add one alignment outcome to several question versions
- Add or change a common mapping role
- Remove only selected draft mappings
- Submit selected drafts for review

Bulk assessed mappings require explicit weight handling. Do not apply an assessed mapping if doing so makes a question's approved weight total invalid. Show a validation preview before committing.

### 2.5 Version workflow

When a new question version is created or first viewed:

- Detect mappings on the prior version.
- Offer an explicit copy action.
- Copy them as draft/needs-review through a `local_outcomemap` service.
- Preserve prior-version mappings.
- Record provenance from the source mapping/version.

Do not automatically approve copied mappings.

## 3. Service dependency

Use public `local_outcomemap` services for:

- Context-scoped outcome search
- Bulk mapping retrieval
- Draft create/update/delete
- Weight validation
- Submit-for-review
- Copy-to-new-version
- Capability and context resolution

Do not write `local_outcomemap` database tables directly.

## 4. Capabilities

Require both:

1. The appropriate Moodle capability to view or edit the question in its question-bank context.
2. The appropriate outcomemap capability, such as `local/outcomemap:mapquestions` or `local/outcomemap:approve`.

Server-side capability checks are mandatory even when the UI action is hidden.

## 5. Expected repository structure

Confirm exact class names against the installed Moodle 5.2 qbank implementations during Milestone 0. The likely structure includes:

```text
classes/
    plugin_feature.php
    column/
    filter/
    bulk_action/
    form/
    external/
db/
    access.php
lang/en/
    qbank_outcomemap.php
tests/
version.php
```

Add AMD/ESM JavaScript and templates only where the selected Moodle 5.2 UI pattern requires them.

## 6. Performance

- Query mappings once per visible page, not once per question row.
- Cache stable outcome display data through the local plugin cache definitions.
- Invalidate after mapping approval, retirement, or version-copy operations.
- Keep filter joins bounded and indexed.
- Add performance-oriented tests for pages containing many questions.

## 7. Accessibility

- Keyboard-accessible actions and dialogs
- Proper labels for outcome pickers and weights
- Text status alongside icons or colors
- Clear validation summaries and field-level errors
- Focus returned to the invoking action after modal completion

## 8. Tests

### PHPUnit

- Bulk retrieval uses exact version IDs
- Context and capability enforcement
- Filter query conditions
- Draft mapping creation and deletion through local services
- Weight-total validation
- Prior-version copy creates draft mappings only
- Missing or disabled local dependency fails safely

### Behat

- View mapped outcomes in the bank
- Filter to a selected outcome
- Filter to unmapped or needs-review questions
- Map one question to several outcomes
- Bulk add an alignment-only outcome
- Reject invalid bulk assessed weights
- Copy mappings to a new version and submit them for review
- Confirm an unauthorized user cannot view or change mappings

## 9. Milestones

1. API spike against installed Moodle 5.2 qbank plugins.
2. Installable dependency skeleton and capabilities.
3. Read-only column with bulk mapping retrieval.
4. Filter implementation.
5. Per-question mapping editor.
6. Bulk mapping workflow and validation preview.
7. Question-version copy/review workflow.
8. Accessibility, performance, PHPUnit, and Behat hardening.

## 10. Definition of done

- The plugin installs only when the compatible `local_outcomemap` dependency is available.
- The visible question-bank page loads mappings in bulk.
- Every mapping is bound to an exact question version.
- Multi-outcome assessed weights cannot be approved unless valid.
- Version-copy operations create reviewable drafts and preserve history.
- All mutations go through local services and pass context/capability checks.
- Critical workflows have automated tests.
- No Moodle core files are changed.
