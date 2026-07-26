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

namespace qbank_outcomemap\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_outcomemap\api\question_mappings;
use local_outcomemap\api\workflow;

/**
 * Bulk question-mapping operation and confirmation form.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_map_form extends \moodleform {
    /** Define the form fields. */
    protected function definition(): void {
        $mform = $this->_form;
        $finalizationmode = !workflow::requires_independent_approval();
        $this->add_routing_fields();

        if (!empty($this->_customdata['confirmed'])) {
            $this->add_confirmation_fields();
            return;
        }

        $questions = $this->_customdata['questions'] ?? [];
        $mform->addElement('static', 'bulknote', '', get_string(
            $finalizationmode ? 'bulkmapnote_finalization' : 'bulkmapnote',
            'qbank_outcomemap'
        ));
        $mform->addElement('select', 'operation', get_string('bulkoperation', 'qbank_outcomemap'), [
            question_mappings::BULK_ADD => get_string('bulkoperation_add', 'qbank_outcomemap'),
            question_mappings::BULK_CHANGE_ROLE => get_string('bulkoperation_change_role', 'qbank_outcomemap'),
            question_mappings::BULK_DELETE_DRAFTS => get_string('bulkoperation_delete_drafts', 'qbank_outcomemap'),
            question_mappings::BULK_SUBMIT_DRAFTS => get_string(
                workflow::requires_independent_approval()
                    ? 'bulkoperation_submit_drafts'
                    : 'bulkoperation_finalize_drafts',
                'qbank_outcomemap'
            ),
        ]);
        $mform->setType('operation', PARAM_ALPHAEXT);

        $mform->addElement(
            'select',
            'outcomeversionuuid',
            get_string($finalizationmode ? 'outcome_finalization' : 'outcome', 'qbank_outcomemap'),
            $this->_customdata['outcomes'] ?? []
        );
        $mform->setType('outcomeversionuuid', PARAM_ALPHANUMEXT);
        $mform->hideIf('outcomeversionuuid', 'operation', 'neq', question_mappings::BULK_ADD);

        $roles = [];
        foreach (['alignment_only', 'teaches', 'practices', 'assesses', 'remediates'] as $role) {
            $roles[$role] = get_string('mappingrole_' . $role, 'local_outcomemap');
        }
        $mform->addElement('select', 'role', get_string('mappingrole', 'local_outcomemap'), $roles);
        $mform->setType('role', PARAM_ALPHAEXT);

        $mform->addElement('textarea', 'notes', get_string('notes', 'local_outcomemap'), ['rows' => 2]);
        $mform->setType('notes', PARAM_TEXT);
        $mform->addElement('textarea', 'reason', get_string(
            $finalizationmode ? 'reviewmessage_finalization' : 'reviewmessage',
            'qbank_outcomemap'
        ), ['rows' => 2]);
        $mform->setType('reason', PARAM_TEXT);

        $mform->addElement('header', 'questionweights', get_string('bulkquestionweights', 'qbank_outcomemap'));
        $mform->addElement('static', 'questionweightsnote', '',
            get_string('bulkquestionweights_help', 'qbank_outcomemap'));
        foreach ($questions as $question) {
            $field = 'questionweight_' . (int) $question->questionid;
            $mform->addElement('text', $field, get_string('bulkquestionweight', 'qbank_outcomemap', (object) [
                'name' => $question->name,
                'version' => $question->questionversion,
            ]));
            $mform->setType($field, PARAM_RAW_TRIMMED);
            $mform->hideIf($field, 'operation', 'neq', question_mappings::BULK_ADD);
        }

        $mform->addElement('header', 'draftselection', get_string('bulkdraftselection', 'qbank_outcomemap'));
        $draftcount = 0;
        foreach ($questions as $question) {
            foreach ($question->drafts as $draft) {
                $draftcount++;
                $label = get_string('bulkdraftlabel', 'qbank_outcomemap', (object) [
                    'question' => $question->name,
                    'version' => $question->questionversion,
                    'outcome' => $draft->outcome,
                    'role' => get_string('mappingrole_' . $draft->role, 'local_outcomemap'),
                ]);
                $field = 'mapping_' . (int) $draft->id;
                $mform->addElement('advcheckbox', $field, $label);
                $mform->setType($field, PARAM_BOOL);
                $mform->hideIf($field, 'operation', 'eq', question_mappings::BULK_ADD);
                $weightfield = 'mappingweight_' . (int) $draft->id;
                $mform->addElement('text', $weightfield,
                    get_string('bulkmappingweight', 'qbank_outcomemap', (object) [
                        'question' => $question->name,
                        'version' => $question->questionversion,
                        'outcome' => $draft->outcome,
                        'role' => get_string('mappingrole_' . $draft->role, 'local_outcomemap'),
                    ]));
                $mform->setType($weightfield, PARAM_RAW_TRIMMED);
                $mform->hideIf(
                    $weightfield,
                    'operation',
                    'neq',
                    question_mappings::BULK_CHANGE_ROLE
                );
            }
        }
        if (!$draftcount) {
            $mform->addElement('static', 'nodrafts', '', get_string(
                $finalizationmode ? 'bulknodrafts_finalization' : 'bulknodrafts',
                'qbank_outcomemap'
            ));
        }

        $this->add_action_buttons(true, get_string('bulkpreview', 'qbank_outcomemap'));
    }

    /**
     * Associate missing operation inputs with their exact form controls.
     *
     * @param array $data Submitted values.
     * @param array $files Submitted files.
     * @return array Field errors.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $action = (string) ($data['operation'] ?? '');
        $role = (string) ($data['role'] ?? '');
        $questions = $this->_customdata['questions'] ?? [];
        if ($action === question_mappings::BULK_ADD) {
            if (empty($data['outcomeversionuuid'])) {
                $errors['outcomeversionuuid'] = get_string('required');
            }
            if ($role === 'assesses') {
                foreach ($questions as $question) {
                    $field = 'questionweight_' . (int) $question->questionid;
                    if (trim((string) ($data[$field] ?? '')) === '') {
                        $errors[$field] = get_string('assessedweightrequired', 'local_outcomemap');
                    }
                }
            }
            return $errors;
        }

        $selected = [];
        foreach ($questions as $question) {
            foreach ($question->drafts as $draft) {
                $field = 'mapping_' . (int) $draft->id;
                if (!empty($data[$field])) {
                    $selected[] = $draft;
                }
            }
        }
        if (!$selected) {
            $errors['operation'] = get_string('bulkmappingrequired', 'qbank_outcomemap');
        }
        if ($action === question_mappings::BULK_CHANGE_ROLE && $role === 'assesses') {
            foreach ($selected as $draft) {
                $field = 'mappingweight_' . (int) $draft->id;
                if (trim((string) ($data[$field] ?? '')) === '') {
                    $errors[$field] = get_string('assessedweightrequired', 'local_outcomemap');
                }
            }
        }
        return $errors;
    }

    /** Add hidden routing values shared by both stages. */
    private function add_routing_fields(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'questionids', $this->_customdata['questionids'] ?? '');
        $mform->setType('questionids', PARAM_SEQUENCE);
        $mform->addElement('hidden', 'cmid', $this->_customdata['cmid'] ?? 0);
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid'] ?? 0);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'returnurl', $this->_customdata['returnurl'] ?? '');
        $mform->setType('returnurl', PARAM_LOCALURL);
    }

    /** Add immutable operation values for the explicit commit stage. */
    private function add_confirmation_fields(): void {
        $mform = $this->_form;
        $preview = $this->_customdata['preview'];
        $operation = $preview->operation;
        $hidden = [
            'confirmed' => 1,
            'previewtoken' => $preview->previewtoken,
            'operation' => $operation['action'],
            'role' => $operation['role'] ?? '',
            'outcomeversionuuid' => $operation['outcomeversionuuid'] ?? '',
            'effectivefrom' => $operation['effectivefrom'] ?? 0,
            'mappingids' => implode(',', $operation['mappingids'] ?? []),
            'weightsjson' => json_encode($operation['weights'] ?? []),
            'notes' => $operation['notes'] ?? '',
            'reason' => $operation['reason'] ?? '',
        ];
        foreach ($hidden as $name => $value) {
            $mform->addElement('hidden', $name, $value);
            $mform->setType($name, in_array($name, ['notes', 'reason', 'weightsjson'], true)
                ? PARAM_RAW : PARAM_RAW_TRIMMED);
        }
        $mform->addElement('static', 'confirmnote', '', get_string('bulkconfirmnote', 'qbank_outcomemap'));
        $this->add_action_buttons(true, get_string('bulkcommit', 'qbank_outcomemap'));
    }
}
