<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Preview and atomically apply bulk exact-version outcome mappings.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// __DIR__ resolves symlinks, so when this plugin directory is symlinked into a
// Moodle install (a common dev setup) it points at the checkout rather than at
// question/bank/outcomemap, and the relative path above lands outside the site.
// SCRIPT_FILENAME keeps the unresolved request path, so it still finds config.
$configfile = __DIR__ . '/../../../config.php';
if (!file_exists($configfile) && !empty($_SERVER['SCRIPT_FILENAME'])) {
    $fallback = dirname($_SERVER['SCRIPT_FILENAME'], 4) . '/config.php';
    if (file_exists($fallback)) {
        $configfile = $fallback;
    }
}
require_once($configfile);
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/editlib.php');

use local_outcomemap\api\outcome_search;
use local_outcomemap\api\question_mappings;
use local_outcomemap\api\validation_exception;
use local_outcomemap\api\workflow;
use qbank_outcomemap\form\bulk_map_form;

$cmid = optional_param('cmid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$questionids = optional_param('questionids', '', PARAM_SEQUENCE);
$confirmed = optional_param('confirmed', 0, PARAM_BOOL);

\core_question\local\bank\helper::require_plugin_enabled('qbank_outcomemap');

if ($cmid) {
    [$module, $cm] = get_module_from_cmid($cmid);
    require_login($cm->course, false, $cm);
    $thiscontext = context_module::instance($cmid);
} else if ($courseid) {
    require_login($courseid, false);
    $thiscontext = context_course::instance($courseid);
} else {
    throw new moodle_exception('missingcourseorcmid', 'question');
}
require_capability('local/outcomemap:mapquestions', $thiscontext);

// The question bank posts one q<id> parameter per selected question.
if ($questionids === '') {
    $selected = [];
    foreach ((array) data_submitted() as $key => $value) {
        if (preg_match('/^q(\d+)$/', $key, $matches)) {
            $selected[] = (int) $matches[1];
        }
    }
    $questionids = implode(',', $selected);
}
$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $questionids)))));
sort($ids);

$PAGE->set_context($thiscontext);
$PAGE->set_url(new moodle_url('/question/bank/outcomemap/bulk.php', $cmid
    ? ['cmid' => $cmid]
    : ['courseid' => $courseid]));
$PAGE->set_title(get_string('bulkmaptitle', 'qbank_outcomemap'));
$PAGE->set_heading(get_string('bulkmaptitle', 'qbank_outcomemap'));

$backurl = $returnurl !== ''
    ? new moodle_url($returnurl)
    : new moodle_url('/question/edit.php', $cmid ? ['cmid' => $cmid] : ['courseid' => $courseid]);
if (!$ids) {
    redirect($backurl, get_string('bulknoquestions', 'qbank_outcomemap'), null,
        \core\output\notification::NOTIFY_WARNING);
}

// This call resolves exact question versions, verifies every selected row, and
// bulk-loads the available draft inventory without exposing local table access.
$inventory = question_mappings::preview_bulk($ids, [
    'action' => question_mappings::BULK_INSPECT,
]);

$outcomeoptions = ['' => get_string('choosedots')];
foreach (outcome_search::search($thiscontext, '', null, 200) as $outcome) {
    $label = $outcome->frameworkcode . '.' . $outcome->code . ' v' . $outcome->version;
    $label .= ' — ' . ($outcome->shortstatement ?: shorten_text($outcome->statement, 80));
    $outcomeoptions[$outcome->versionuuid] = $label;
}

$customdata = [
    'questionids' => implode(',', $ids),
    'cmid' => $cmid,
    'courseid' => $courseid,
    'returnurl' => $returnurl,
    'outcomes' => $outcomeoptions,
    'questions' => $inventory->questions,
];

/** Build the public operation array from a normal preview form submission. */
$operationfromform = static function (\stdClass $data) use ($inventory): array {
    $operation = [
        'action' => (string) $data->operation,
        'role' => (string) ($data->role ?? ''),
        'outcomeversionuuid' => (string) ($data->outcomeversionuuid ?? ''),
        'notes' => trim((string) ($data->notes ?? '')),
        'reason' => trim((string) ($data->reason ?? '')),
        'mappingids' => [],
        'weights' => [],
    ];
    foreach ($inventory->questions as $question) {
        $questionweight = 'questionweight_' . (int) $question->questionid;
        if (isset($data->{$questionweight}) && trim((string) $data->{$questionweight}) !== '') {
            $operation['weights'][(int) $question->questionid] = trim((string) $data->{$questionweight});
        }
        foreach ($question->drafts as $draft) {
            $mappingfield = 'mapping_' . (int) $draft->id;
            if (!empty($data->{$mappingfield})) {
                $operation['mappingids'][] = (int) $draft->id;
                $weightfield = 'mappingweight_' . (int) $draft->id;
                if (isset($data->{$weightfield}) && trim((string) $data->{$weightfield}) !== '') {
                    $operation['weights'][(int) $draft->id] = trim((string) $data->{$weightfield});
                }
            }
        }
    }
    return $operation;
};

/** Build the immutable public operation array posted by the confirmation form. */
$operationfromconfirmation = static function (): array {
    $weights = json_decode(optional_param('weightsjson', '[]', PARAM_RAW), true);
    if (!is_array($weights)) {
        $weights = [];
    }
    return [
        'action' => required_param('operation', PARAM_ALPHAEXT),
        'role' => optional_param('role', '', PARAM_ALPHAEXT),
        'outcomeversionuuid' => optional_param('outcomeversionuuid', '', PARAM_ALPHANUMEXT),
        'effectivefrom' => optional_param('effectivefrom', 0, PARAM_INT),
        'mappingids' => array_values(array_filter(array_map(
            'intval',
            explode(',', optional_param('mappingids', '', PARAM_SEQUENCE))
        ))),
        'weights' => $weights,
        'notes' => optional_param('notes', '', PARAM_RAW),
        'reason' => optional_param('reason', '', PARAM_RAW),
    ];
};

$notification = null;
$notificationtype = \core\output\notification::NOTIFY_ERROR;
$preview = null;
$entryform = null;
$confirmform = null;

if ($confirmed) {
    if (optional_param('cancel', 0, PARAM_BOOL)) {
        redirect($backurl);
    }
    require_sesskey();
    $operation = $operationfromconfirmation();
    try {
        $result = question_mappings::commit_bulk(
            $ids,
            $operation,
            required_param('previewtoken', PARAM_ALPHANUM)
        );
        redirect($backurl, get_string('bulkmapresult', 'qbank_outcomemap', (object) [
            'affected' => $result->affected,
            'questions' => $result->questioncount,
        ]));
    } catch (validation_exception $e) {
        $notification = $e->getMessage();
        // Show a fresh preview rather than silently retrying a stale or invalid operation.
        try {
            $preview = question_mappings::preview_bulk($ids, $operation);
        } catch (validation_exception $ignored) {
            $preview = null;
        }
    }
} else {
    $entryform = new bulk_map_form(null, $customdata);
    if ($entryform->is_cancelled()) {
        redirect($backurl);
    }
    if ($data = $entryform->get_data()) {
        try {
            $preview = question_mappings::preview_bulk($ids, $operationfromform($data));
            if (!$preview->valid) {
                $notification = get_string('bulkpreviewhaserrors', 'qbank_outcomemap');
            }
        } catch (validation_exception $e) {
            $notification = $e->getMessage();
        }
    }
}

if ($preview && $preview->valid) {
    $confirmform = new bulk_map_form(null, $customdata + [
        'confirmed' => true,
        'preview' => $preview,
    ]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('bulkmapcount', 'qbank_outcomemap', count($ids)), 3);
if ($notification !== null) {
    echo $OUTPUT->notification($notification, $notificationtype, false);
}

if ($preview) {
    echo $OUTPUT->heading(get_string('bulkpreviewheading', 'qbank_outcomemap'), 4);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->caption = get_string('bulkpreviewtablecaption', 'qbank_outcomemap');
    $table->head = [
        get_string('question'),
        get_string('bulkexactversion', 'qbank_outcomemap'),
        get_string('bulkproposedaction', 'qbank_outcomemap'),
        get_string('validation', 'local_outcomemap'),
    ];
    foreach ($preview->questions as $question) {
        $actions = [];
        foreach ($question->actions as $action) {
            if ($action->operation === question_mappings::BULK_ADD) {
                $actions[] = get_string('bulkpreview_add', 'qbank_outcomemap', (object) [
                    'outcome' => $action->outcome,
                    'role' => get_string('mappingrole_' . $action->role, 'local_outcomemap'),
                    'weight' => $action->weight ?? '—',
                ]);
            } else {
                $previewkey = $action->operation;
                if ($previewkey === question_mappings::BULK_SUBMIT_DRAFTS
                        && !workflow::requires_independent_approval()) {
                    $previewkey = 'finalize_drafts';
                }
                $actions[] = get_string('bulkpreview_' . $previewkey, 'qbank_outcomemap', (object) [
                    'id' => $action->mappingid,
                    'outcome' => $action->outcome,
                    'role' => get_string('mappingrole_' . $action->role, 'local_outcomemap'),
                    'weight' => $action->weight ?? '—',
                ]);
            }
        }
        $errors = $question->errors;
        $table->data[] = [
            $question->name,
            get_string('bulkversionnumber', 'qbank_outcomemap', $question->questionversion),
            $actions ? implode(html_writer::empty_tag('br'), array_map('s', $actions)) : '—',
            $errors
                ? html_writer::alist(array_map('s', $errors), ['class' => 'text-danger'])
                : get_string('valid', 'local_outcomemap'),
        ];
    }
    echo html_writer::table($table);
    foreach ($preview->errors as $error) {
        echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR, false);
    }
}

if ($confirmform) {
    echo $OUTPUT->notification(get_string('bulkpreviewvalid', 'qbank_outcomemap'),
        \core\output\notification::NOTIFY_SUCCESS, false);
    $confirmform->display();
} else if ($entryform) {
    $entryform->display();
} else {
    $entryform = new bulk_map_form(null, $customdata);
    $entryform->display();
}
echo $OUTPUT->footer();
