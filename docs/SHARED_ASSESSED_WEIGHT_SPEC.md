# Shared assessed weight for bulk question mapping

Status: Implementation specification
Primary component: `qbank_outcomemap`
Local plugin changes: none — `local_outcomemap\api\question_mappings` v1.1 is unchanged
Target Moodle branch: 5.2 (`2026042000.00`), source kept 4.5-compatible

## 1. Problem

`bulk_map_form` requires an explicit weight for **every** selected question when the
operation is `add` with role `assesses`, and for every selected draft when the
operation is `change_role` to `assesses`:

- per-question fields: `classes/form/bulk_map_form.php:90-98`
- per-mapping fields: `classes/form/bulk_map_form.php:115-129`
- presence validation: `classes/form/bulk_map_form.php:158-165` and `:181-188`

The dominant real case is a bank of single-outcome questions where every weight is
`1.0000000000`. Mapping 50 such questions means typing into 50 boxes. Roles without a
weight requirement (`teaches`, `practices`, `alignment_only`, `remediates`) are already
one-click, so the friction is specific to `assesses`.

## 2. Governance constraint

`AGENTS.md:26` — *"Do not silently assign weights to multi-outcome assessment items."*

This feature stays inside that rule and must be reviewed against it:

1. The shared weight is **operator-typed**, never defaulted or inferred. No prefilled
   `1.0000000000`, no "assume single outcome" shortcut.
2. The resulting weight for every question is rendered in the preview table before commit
   (`bulk.php:243-247` already prints `weight` for each `add` action).
3. Per-question hypothetical assessed totals still gate the commit
   (`local_outcomemap` `question_mapping_service.php:1149-1164` →
   `bulk_hypothetical_assessed_total()` at `:1248`). A question that already carries an
   effective `assesses` mapping fails loudly with `assessedweighttotalinvalid` instead of
   silently over-weighting.

Consequence to state in the UI help text: a shared weight of `1.0000000000` succeeds for
questions whose only assessed mapping is the one being added, and fails — visibly, per
question, in the preview — for any question where the total would not reach exactly
`1.0000000000`.

## 3. Design

### 3.1 Expansion happens in the page layer, not the API

`bulk_preview_token()` (`question_mapping_service.php:1401-1421`) HMACs the operation array
verbatim, so preview and commit must produce byte-identical `weights` maps. The
confirmation stage already round-trips a fully expanded map through `weightsjson`
(`bulk.php:148-151`, `bulk_map_form.php:218`).

Therefore the shared weight is expanded into the existing per-target `weights` map **before**
`question_mappings::preview_bulk()` is called, and never reaches the public API as a
distinct concept. The public operation shape, `API_VERSION` `1.1`, and the token contract
are all untouched.

### 3.2 New class: `qbank_outcomemap\local\weight_resolver`

Expansion must be unit-testable, so it does not live inline in `bulk.php`.

```php
namespace qbank_outcomemap\local;

/** Resolves the effective assessed weight for each bulk target. */
final class weight_resolver {
    /**
     * @param int[] $targetids Question IDs (add) or draft mapping IDs (change_role).
     * @param string|null $shared Shared weight as typed, or null/'' when unused.
     * @param array<int,string> $overrides Per-target weights as typed, blanks removed.
     * @return array<int,string> Weight per target ID, ascending, blanks removed.
     */
    public static function resolve(array $targetids, ?string $shared, array $overrides): array;
}
```

Rules:

- An override wins over the shared value for that target.
- A blank or absent override falls back to the trimmed shared value.
- A blank shared value with a blank override yields **no key** for that target, so the
  existing "missing weight" validation still fires.
- Output is `ksort`ed and string-typed to match `question_mappings::normalize_bulk_operation()`
  (`question_mappings.php:129-139`), which trims, drops blanks and `ksort`s again — so
  double normalisation is a no-op rather than a token mismatch.
- No decimal parsing here. Canonical-form checking stays in
  `question_mapping_service::validate_role_weight()` so format errors continue to surface
  per question in the preview table.

### 3.3 Form changes — `classes/form/bulk_map_form.php`

Add one element, positioned immediately after `role` so it reads as a modifier of the role
choice:

```php
$mform->addElement('text', 'sharedweight',
    get_string('bulksharedweight', 'qbank_outcomemap'));
$mform->setType('sharedweight', PARAM_RAW_TRIMMED);
$mform->addHelpButton('sharedweight', 'bulksharedweight', 'qbank_outcomemap');
$mform->hideIf('sharedweight', 'role', 'neq', 'assesses');
```

`hideIf` conditions are stored per dependency (`lib/formslib.php:2994-3009`) and each is
applied independently by the dependency manager, so stacked calls hide on **any** match —
i.e. the element shows only when every condition is false. `sharedweight` is relevant to
both `add` and `change_role`, so it is gated on `role` alone and needs no operation
condition; it is simply ignored for `delete_drafts` and `submit_drafts`.

Also tighten the existing weight fields, which currently show for non-assessed roles:

- `questionweight_*`: add `hideIf($field, 'role', 'neq', 'assesses')` alongside the existing
  operation condition at `:97`.
- `mappingweight_*`: add the same alongside `:124-129`.

Relabel both groups as overrides via new strings (§3.5) — the label text changes, the field
names do not, so no data-shape change.

### 3.4 Validation changes — `bulk_map_form::validation()`

Replace the two "every field required" loops with: *the shared weight, or a complete set of
per-target weights.*

For `operation === add` and `role === assesses` (`:158-165`):

```php
$shared = trim((string) ($data['sharedweight'] ?? ''));
if ($shared === '') {
    foreach ($questions as $question) {
        $field = 'questionweight_' . (int) $question->questionid;
        if (trim((string) ($data[$field] ?? '')) === '') {
            $errors['sharedweight'] = get_string('bulksharedweightrequired', 'qbank_outcomemap');
            break;
        }
    }
}
```

The error is attached to `sharedweight` rather than to each empty box: with 50 questions,
50 inline "required" messages are noise, and the single actionable fix is to fill the shared
field. Apply the same shape to the `change_role` branch (`:181-188`) keyed on
`mappingweight_` + draft ID.

Deliberately **not** validated in the form: whether the weight is canonical decimal, and
whether the resulting totals reach `1.0000000000`. Both already produce precise per-question
messages in the preview, which is the better place for them.

### 3.5 Strings — `lang/en/qbank_outcomemap.php`

```php
$string['bulksharedweight'] = 'Assessed weight for all selected';
$string['bulksharedweight_help'] = 'Applies this weight to every selected question that has no individual weight below. A weight splits one question\'s marks across the outcomes it assesses, so each question\'s assessed weights must total exactly 1.0000000000. Enter 1.0000000000 when the outcome being added is the only outcome each selected question assesses — the usual case. A weight does not set how much a question counts towards the outcome overall: that comes from the question\'s maximum mark in the quiz. Questions whose totals would not reach 1.0000000000 are reported individually in the preview and block the commit.';
$string['bulksharedweightrequired'] = 'Enter an assessed weight for all selected questions, or a weight for every question individually.';
$string['bulkquestionweight_override'] = 'Override for {$a->name} (version {$a->version})';
$string['bulkmappingweight_override'] = 'Override for {$a->question} (version {$a->version}) — {$a->outcome} as {$a->role}';
```

Keep the existing `bulkquestionweight` / `bulkmappingweight` strings in place for one
release to avoid breaking any translation in progress; switch the form to the `_override`
variants.

### 3.6 Page wiring — `bulk.php`

In `$operationfromform` (`:117-144`), collect the typed overrides as today, then expand:

```php
$targets = array_map(static fn($q) => (int) $q->questionid, $inventory->questions);
// change_role keys on the selected draft mapping IDs instead.
$operation['weights'] = weight_resolver::resolve(
    $targets,
    (string) ($data->sharedweight ?? ''),
    $overrides
);
```

For `change_role`, build `$targets` from `$operation['mappingids']` after the draft
checkboxes have been read, so a shared weight applies only to selected drafts. The
confirmation branch (`$operationfromconfirmation`, `:147-165`) needs **no change** — it
already receives the expanded map.

## 4. Tests

PHPUnit — extend `tests/qbank_outcomemap_test.php`:

1. `resolve()` returns the shared value for every target when no overrides are given.
2. An override beats the shared value for its own target only.
3. A blank shared value plus partial overrides omits the untouched targets (so the missing
   weight still fails downstream).
4. Output ordering and string typing survive a round trip through
   `question_mappings::normalize_bulk_operation()` — assert the resulting `weights` array is
   identical to the one produced from equivalent per-question input, which is what keeps the
   preview token stable.
5. End-to-end via the API: preview then commit an `add`/`assesses` over three
   single-outcome questions using one shared `1.0000000000`, and assert three approved-path
   drafts exist with that weight.
6. Negative: the same shared weight over a question that already has an effective
   `assesses` mapping yields `assessedweighttotalinvalid` for that question and
   `valid === false`, while the other questions still preview cleanly.

Behat — extend `tests/behat/question_outcome_mapping.feature` with a scenario that selects
three questions, chooses **Add one outcome mapping** / `assesses`, fills only the shared
weight, and confirms the preview lists the weight against all three rows before commit.

## 5. Scope and estimate

| File | Change |
|---|---|
| `classes/local/weight_resolver.php` | new, ~40 lines |
| `classes/form/bulk_map_form.php` | one element, four `hideIf`s, two validation branches |
| `bulk.php` | expansion call in `$operationfromform` |
| `lang/en/qbank_outcomemap.php` | five strings |
| `tests/qbank_outcomemap_test.php` | six cases |
| `tests/behat/question_outcome_mapping.feature` | one scenario |

No database change, no upgrade step, no `local_outcomemap` change, no API version bump.
`version.php` gets a version bump and the release note entry per
`docs/RELEASE_CHECKLIST.md`.
