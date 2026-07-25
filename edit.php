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
 * Per-question outcome mapping editor.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

use local_outcomemap\api\context_resolver;
use local_outcomemap\api\outcome_search;
use local_outcomemap\api\question_mappings;
use local_outcomemap\api\validation_exception;
use qbank_outcomemap\form\mapping_form;
use qbank_outcomemap\local\bank\outcome_column;

$questionid = required_param('id', PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$action = optional_param('action', '', PARAM_ALPHA);
$mappingid = optional_param('mappingid', 0, PARAM_INT);
$confirmed = optional_param('confirm', 0, PARAM_BOOL);

require_login();
\core_question\local\bank\helper::require_plugin_enabled('qbank_outcomemap');

$questionversion = $DB->get_record('question_versions', ['questionid' => $questionid], '*', MUST_EXIST);
$question = $DB->get_record('question', ['id' => $questionid], 'id,name', MUST_EXIST);
$context = context_resolver::for_question_version((int) $questionversion->id);

$pageurl = new moodle_url('/question/bank/outcomemap/edit.php', ['id' => $questionid]);
if ($returnurl !== '') {
    $pageurl->param('returnurl', $returnurl);
}
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('managemappings', 'qbank_outcomemap'));
$PAGE->set_heading(get_string('managemappings', 'qbank_outcomemap'));

require_capability('local/outcomemap:viewdefinitions', $context);
require_capability('local/outcomemap:mapquestions', $context);
if (!question_has_capability_on($questionid, 'edit')) {
    throw new required_capability_exception($context, 'moodle/question:editall', 'nopermissions', '');
}

// Sesskey-protected row actions delegated to the system of record.
if ($action !== '') {
    require_sesskey();
    if ($action === 'deletedraft' && $mappingid && !$confirmed) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($question->name)
            . ' (v' . (int) $questionversion->version . ')', 2);
        echo $OUTPUT->confirm(
            get_string('confirmdeletedraft', 'qbank_outcomemap'),
            new moodle_url($pageurl, [
                'action' => 'deletedraft',
                'mappingid' => $mappingid,
                'confirm' => 1,
                'sesskey' => sesskey(),
            ]),
            $pageurl
        );
        echo $OUTPUT->footer();
        exit;
    }
    try {
        switch ($action) {
            case 'deletedraft':
                question_mappings::delete_draft($mappingid);
                redirect($pageurl, get_string('mappingdeleted', 'qbank_outcomemap'));
            case 'submitreview':
                question_mappings::submit_for_review($mappingid);
                redirect($pageurl, get_string('mappingsubmitted', 'qbank_outcomemap'));
            case 'copyprevious':
                $created = question_mappings::copy_to_version((int) $questionversion->id);
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

$form = new mapping_form($pageurl->out(false), [
    'questionid' => $questionid,
    'returnurl' => $returnurl,
    'outcomes' => $outcomeoptions,
]);
if ($data = $form->get_data()) {
    try {
        question_mappings::create_draft(
            (int) $questionversion->id,
            (string) $data->outcomeversionuuid,
            (string) $data->role,
            trim((string) ($data->weight ?? '')) === '' ? null : trim((string) $data->weight),
            trim((string) ($data->notes ?? '')) === '' ? null : (string) $data->notes
        );
        redirect($pageurl, get_string('mappingcreated', 'qbank_outcomemap'));
    } catch (validation_exception $e) {
        redirect($pageurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($question->name)
    . ' (v' . (int) $questionversion->version . ')', 2);

// Assessed-weight validity summary.
$report = question_mappings::validate_assessed_weights((int) $questionversion->id);
$reportitems = [
    get_string('approvedtotal', 'qbank_outcomemap', $report->approvedtotal),
    get_string('combinedtotal', 'qbank_outcomemap', $report->combinedtotal),
];
$reporttype = \core\output\notification::NOTIFY_INFO;
if (!$report->approvedvalid || $report->missingweight) {
    $reportitems[] = get_string('weighttotalinvalid', 'qbank_outcomemap');
    $reporttype = \core\output\notification::NOTIFY_WARNING;
}
echo $OUTPUT->notification(implode(' · ', $reportitems), $reporttype, false);

$mappings = question_mappings::get_for_question_versions([(int) $questionversion->id]);
$mappings = $mappings[(int) $questionversion->id] ?? [];
if ($mappings) {
    $table = new html_table();
    $table->caption = get_string('mappingtablecaption', 'qbank_outcomemap', (object) [
        'question' => format_string($question->name),
        'version' => (int) $questionversion->version,
    ]);
    $table->head = [
        get_string('outcome', 'qbank_outcomemap'),
        get_string('mappingrole', 'local_outcomemap'),
        get_string('assessedweight', 'qbank_outcomemap'),
        get_string('status', 'local_outcomemap'),
        get_string('actions', 'local_outcomemap'),
    ];
    $table->attributes['class'] = 'generaltable';
    foreach ($mappings as $mapping) {
        $actions = [];
        if ($mapping->status === 'draft') {
            $actions[] = $OUTPUT->single_button(new moodle_url($pageurl, [
                'action' => 'submitreview', 'mappingid' => $mapping->id,
            ]), get_string('submitreview', 'local_outcomemap'), 'post');
            $actions[] = $OUTPUT->single_button(new moodle_url($pageurl, [
                'action' => 'deletedraft', 'mappingid' => $mapping->id,
            ]), get_string('delete'), 'post');
        }
        $table->data[] = [
            s($mapping->frameworkcode . '.' . $mapping->outcomecode . ' v' . $mapping->outcomeversion)
                . '<br><small>' . s($mapping->outcomeshortstatement ?? shorten_text($mapping->outcomestatement, 80))
                . '</small>',
            get_string('mappingrole_' . $mapping->role, 'local_outcomemap'),
            $mapping->weight === null ? '—' : s(outcome_column::format_weight($mapping->weight)),
            get_string('status_' . $mapping->status, 'local_outcomemap'),
            html_writer::div(implode(' ', $actions), 'd-flex flex-wrap align-items-center gap-2'),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'table-responsive');
} else {
    echo $OUTPUT->notification(get_string('nomappings', 'qbank_outcomemap'),
        \core\output\notification::NOTIFY_INFO, false);
}

if ((int) $questionversion->version > 1) {
    echo $OUTPUT->single_button(new moodle_url($pageurl, [
        'action' => 'copyprevious', 'sesskey' => sesskey(),
    ]), get_string('copyfromprevious', 'qbank_outcomemap'), 'post');
}

$form->display();

if ($returnurl !== '') {
    echo html_writer::div(html_writer::link(new moodle_url($returnurl),
        get_string('backtoquestionbank', 'qbank_outcomemap')), 'mt-3');
}
echo $OUTPUT->footer();
