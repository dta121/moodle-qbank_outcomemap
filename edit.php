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

/** Per-question exact-version outcome mapping editor. */

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

use local_outcomemap\api\context_resolver;
use local_outcomemap\api\outcome_search;
use local_outcomemap\api\question_mappings;
use local_outcomemap\api\validation_exception;
use local_outcomemap\api\workflow;
use qbank_outcomemap\form\mapping_form;
use qbank_outcomemap\local\bank\outcome_column;

$questionid = required_param('id', PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$action = optional_param('action', '', PARAM_ALPHA);
$mappingid = optional_param('mappingid', 0, PARAM_INT);
$editmappingid = optional_param('editmappingid', 0, PARAM_INT);
$confirmed = optional_param('confirm', 0, PARAM_BOOL);

$questionversion = $DB->get_record('question_versions', ['questionid' => $questionid], '*', MUST_EXIST);
$question = $DB->get_record('question', ['id' => $questionid], 'id,name', MUST_EXIST);
$context = context_resolver::for_question_version((int) $questionversion->id);

// Initialise course/module page state before rendering navigation for qbank module contexts.
if ($context instanceof context_module) {
    $cm = get_coursemodule_from_id('', (int) $context->instanceid, 0, false, MUST_EXIST);
    require_login((int) $cm->course, false, $cm);
} else if ($context instanceof context_course) {
    require_login((int) $context->instanceid);
} else {
    require_login();
}
\core_question\local\bank\helper::require_plugin_enabled('qbank_outcomemap');

$pageurl = new moodle_url('/question/bank/outcomemap/edit.php', ['id' => $questionid]);
if ($returnurl !== '') {
    $pageurl->param('returnurl', $returnurl);
}
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('managemappings', 'qbank_outcomemap'));
$PAGE->set_heading(get_string('managemappings', 'qbank_outcomemap'));

require_capability('local/outcomemap:mapquestions', $context);
if (!question_has_capability_on($questionid, 'edit')) {
    throw new required_capability_exception($context, 'moodle/question:editall', 'nopermissions', '');
}

// Load once and constrain every row action to this exact question version.
$grouped = question_mappings::get_for_question_versions([(int) $questionversion->id]);
$mappings = $grouped[(int) $questionversion->id] ?? [];
$mappingsbyid = [];
foreach ($mappings as $mapping) {
    $mappingsbyid[$mapping->id] = $mapping;
}
if ($action !== '' && $mappingid && !isset($mappingsbyid[$mappingid])) {
    throw new invalid_parameter_exception('The mapping does not belong to this exact question version.');
}
if ($editmappingid && (!isset($mappingsbyid[$editmappingid])
        || $mappingsbyid[$editmappingid]->status !== workflow::DRAFT)) {
    throw new invalid_parameter_exception('Only a draft mapping from this exact question version can be edited.');
}

$copypreview = null;
if ((int) $questionversion->version > 1) {
    $copypreview = question_mappings::preview_copy_to_version((int) $questionversion->id);
}

if ($action === 'deletedraft' && $mappingid && confirm_sesskey() && !$confirmed) {
    $yesurl = new moodle_url($pageurl, [
        'action' => 'deletedraft',
        'mappingid' => $mappingid,
        'confirm' => 1,
        'sesskey' => sesskey(),
    ]);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('deletemapping', 'qbank_outcomemap'));
    echo $OUTPUT->confirm(get_string('confirmdeletemapping', 'qbank_outcomemap'), $yesurl, $pageurl);
    echo $OUTPUT->footer();
    exit;
}

// Sesskey-protected row actions delegated to the public system-of-record API.
if ($action !== '' && confirm_sesskey()) {
    try {
        switch ($action) {
            case 'deletedraft':
                question_mappings::delete_draft($mappingid, get_string('deletedfromeditor', 'qbank_outcomemap'));
                redirect($pageurl, get_string('mappingdeleted', 'qbank_outcomemap'));
            case 'submitreview':
                question_mappings::submit_for_review($mappingid);
                redirect($pageurl, get_string(
                    workflow::requires_independent_approval() ? 'mappingsubmitted' : 'mappingfinalized',
                    'qbank_outcomemap'
                ));
            case 'copyprevious':
                if (!$copypreview || !$copypreview->eligiblecount) {
                    redirect($pageurl, get_string('copynothingeligible', 'qbank_outcomemap'), null,
                        \core\output\notification::NOTIFY_WARNING);
                }
                $created = question_mappings::copy_to_version(
                    (int) $questionversion->id,
                    (int) $copypreview->sourcequestionversionid,
                    get_string('copiedfromqbank', 'qbank_outcomemap')
                );
                redirect($pageurl, get_string('mappingscopied', 'qbank_outcomemap', count($created)));
        }
    } catch (validation_exception $e) {
        redirect($pageurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$outcomeoptions = ['' => get_string('choosedots')];
foreach (outcome_search::search($context, '', null, 200) as $outcome) {
    $label = $outcome->frameworkcode . '.' . $outcome->code . ' v' . $outcome->version;
    $label .= ' — ' . ($outcome->shortstatement ?: shorten_text($outcome->statement, 80));
    $outcomeoptions[$outcome->versionuuid] = $label;
}

$editmapping = $editmappingid ? $mappingsbyid[$editmappingid] : null;
$formurl = new moodle_url($pageurl);
if ($editmappingid) {
    $formurl->param('editmappingid', $editmappingid);
}
$form = new mapping_form($formurl->out(false), [
    'questionid' => $questionid,
    'mappingid' => $editmappingid,
    'returnurl' => $returnurl,
    'outcomes' => $outcomeoptions,
]);
if ($editmapping) {
    $form->set_data((object) [
        'id' => $questionid,
        'mappingid' => $editmapping->id,
        'returnurl' => $returnurl,
        'outcomeversionuuid' => $editmapping->outcomeversionuuid,
        'role' => $editmapping->role,
        'weight' => $editmapping->weight,
        'notes' => $editmapping->notes,
    ]);
}
if ($form->is_cancelled()) {
    redirect($pageurl);
}
if ($data = $form->get_data()) {
    try {
        $submittedmappingid = (int) ($data->mappingid ?? 0);
        if ($submittedmappingid !== $editmappingid
                || ($submittedmappingid && (!isset($mappingsbyid[$submittedmappingid])
                    || $mappingsbyid[$submittedmappingid]->status !== workflow::DRAFT))) {
            throw new invalid_parameter_exception(
                'The submitted draft does not match the exact-version mapping selected for editing.'
            );
        }
        $weight = trim((string) ($data->weight ?? ''));
        $notes = trim((string) ($data->notes ?? ''));
        $reviewmessage = trim((string) ($data->reviewmessage ?? ''));
        if (!empty($data->mappingid)) {
            question_mappings::update_draft((int) $data->mappingid, [
                'outcomeversionuuid' => (string) $data->outcomeversionuuid,
                'role' => (string) $data->role,
                'weight' => $weight === '' ? null : $weight,
                'notes' => $notes === '' ? null : $notes,
                'reason' => $reviewmessage === '' ? null : $reviewmessage,
            ]);
            $savedmappingid = (int) $data->mappingid;
            $savedmessage = get_string('mappingupdated', 'qbank_outcomemap');
        } else {
            $savedmappingid = question_mappings::create_draft(
                (int) $questionversion->id,
                (string) $data->outcomeversionuuid,
                (string) $data->role,
                $weight === '' ? null : $weight,
                $notes === '' ? null : $notes
            );
            $savedmessage = get_string('mappingcreated', 'qbank_outcomemap');
        }
        if (!empty($data->saveandsubmit)) {
            question_mappings::submit_for_review(
                $savedmappingid,
                $reviewmessage === '' ? null : $reviewmessage
            );
            $savedmessage = get_string(
                workflow::requires_independent_approval() ? 'mappingsubmitted' : 'mappingfinalized',
                'qbank_outcomemap'
            );
        }
        redirect($pageurl, $savedmessage);
    } catch (validation_exception $e) {
        redirect($pageurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($question->name, true, ['context' => $context])
    . ' (v' . (int) $questionversion->version . ')', 3);

// Assessed-weight validity summary for all current exact-version mappings.
$hasassessed = false;
foreach ($mappings as $mapping) {
    if ($mapping->role === 'assesses') {
        $hasassessed = true;
        break;
    }
}
$report = question_mappings::validate_assessed_weights((int) $questionversion->id);
$reportitems = [
    get_string(
        workflow::requires_independent_approval() ? 'approvedtotal' : 'finalizedtotal',
        'qbank_outcomemap',
        $report->approvedtotal
    ),
    get_string('combinedtotal', 'qbank_outcomemap', $report->combinedtotal),
];
$reporttype = \core\output\notification::NOTIFY_INFO;
if ($hasassessed && (!$report->combinedvalid || $report->missingweight)) {
    $reportitems[] = get_string(
        workflow::requires_independent_approval() ? 'weighttotalinvalid' : 'weighttotalinvalid_finalization',
        'qbank_outcomemap'
    );
    $reporttype = \core\output\notification::NOTIFY_WARNING;
}
echo $OUTPUT->notification(implode(' · ', $reportitems), $reporttype, false);

if ($mappings) {
    $table = new html_table();
    $table->head = [
        get_string(
            workflow::requires_independent_approval() ? 'outcome' : 'outcome_finalization',
            'qbank_outcomemap'
        ),
        get_string('mappingrole', 'local_outcomemap'),
        get_string('assessedweight', 'qbank_outcomemap'),
        get_string('status', 'local_outcomemap'),
        get_string('actions', 'local_outcomemap'),
    ];
    $table->attributes['class'] = 'generaltable';
    $table->caption = get_string('mappingtablecaption', 'qbank_outcomemap', format_string($question->name, true, ['context' => $context]));
    foreach ($mappings as $mapping) {
        $actions = [];
        if ($mapping->status === workflow::DRAFT) {
            $actions[] = html_writer::link(new moodle_url($pageurl, [
                'editmappingid' => $mapping->id,
            ]), get_string('edit'));
            $actions[] = html_writer::link(new moodle_url($pageurl, [
                'action' => 'submitreview', 'mappingid' => $mapping->id, 'sesskey' => sesskey(),
            ]), workflow::submit_action_label());
            $actions[] = html_writer::link(new moodle_url($pageurl, [
                'action' => 'deletedraft', 'mappingid' => $mapping->id, 'sesskey' => sesskey(),
            ]), get_string('delete'));
        }
        $outcomelabel = s($mapping->frameworkcode . '.' . $mapping->outcomecode
            . ' v' . $mapping->outcomeversion)
            . '<br><small>' . s($mapping->outcomeshortstatement
                ?? shorten_text($mapping->outcomestatement, 80)) . '</small>';
        if ($mapping->sourcequestionversionid !== null) {
            $outcomelabel .= '<br><small>' . s(get_string(
                'copiedprovenance',
                'qbank_outcomemap',
                (object) [
                    'questionversion' => $mapping->sourcequestionversion ?? $mapping->sourcequestionversionid,
                    'mappinguuid' => $mapping->sourcemappinguuid ?? (string) $mapping->sourceqmapid,
                    'mappingversion' => $mapping->sourcemappingversion ?? 1,
                ]
            )) . '</small>';
        }
        $table->data[] = [
            $outcomelabel,
            get_string('mappingrole_' . $mapping->role, 'local_outcomemap'),
            $mapping->weight === null ? '—' : s(outcome_column::format_weight($mapping->weight)),
            workflow::status_label($mapping->status),
            implode(' | ', $actions),
        ];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nomappings', 'qbank_outcomemap'),
        \core\output\notification::NOTIFY_INFO, false);
}

if ($copypreview && $copypreview->sourcequestionversionid !== null) {
    if ($copypreview->eligiblecount > 0) {
        echo $OUTPUT->heading(get_string('copypreviewheading', 'qbank_outcomemap',
            $copypreview->sourceversion), 4);
        echo $OUTPUT->notification(get_string(
            workflow::requires_independent_approval() ? 'copypreviewnote' : 'copypreviewnote_finalization',
            'qbank_outcomemap',
            $copypreview->eligiblecount
        ), \core\output\notification::NOTIFY_INFO, false);
        $copytable = new html_table();
        $copytable->attributes['class'] = 'generaltable';
        $copytable->caption = get_string('copypreviewtablecaption', 'qbank_outcomemap',
            $copypreview->sourceversion);
        $copytable->head = [
            get_string(
                workflow::requires_independent_approval() ? 'outcome' : 'outcome_finalization',
                'qbank_outcomemap'
            ),
            get_string('mappingrole', 'local_outcomemap'),
            get_string('assessedweight', 'qbank_outcomemap'),
        ];
        foreach ($copypreview->mappings as $mapping) {
            $copytable->data[] = [
                s($mapping->outcome),
                get_string('mappingrole_' . $mapping->role, 'local_outcomemap'),
                $mapping->weight === null ? '—' : s(outcome_column::format_weight($mapping->weight)),
            ];
        }
        echo html_writer::table($copytable);
        echo $OUTPUT->single_button(new moodle_url($pageurl, [
            'action' => 'copyprevious', 'sesskey' => sesskey(),
        ]), get_string('copyfromprevious', 'qbank_outcomemap'), 'post');
    } else if ($copypreview->duplicatecount > 0) {
        echo $OUTPUT->notification(get_string('copyalreadycomplete', 'qbank_outcomemap'),
            \core\output\notification::NOTIFY_INFO, false);
    }
}

$form->display();

if ($returnurl !== '') {
    echo html_writer::div(html_writer::link(new moodle_url($returnurl),
        get_string('backtoquestionbank', 'qbank_outcomemap')), 'mt-3');
}
echo $OUTPUT->footer();
