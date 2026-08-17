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
    /** Canonical mapping roles accepted by the public service. */
    private const ROLES = ['alignment_only', 'teaches', 'practices', 'assesses', 'remediates'];

    /**
     * Define the form fields.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $finalizationmode = !workflow::requires_independent_approval();
        $outcomes = array_map(
            static fn($label): string => s((string) $label),
            $this->_customdata['outcomes'] ?? []
        );
        $this->add_routing_fields();

        if (!empty($this->_customdata['confirmed'])) {
            $this->add_confirmation_fields();
            return;
        }

        $questions = $this->_customdata['questions'] ?? [];
        $this->add_operation_fields($outcomes, $finalizationmode);
        $this->add_question_weight_fields($questions);
        if ($this->add_draft_fields($questions) === 0) {
            $mform->addElement('static', 'nodrafts', '', get_string(
                $finalizationmode ? 'bulknodrafts_finalization' : 'bulknodrafts',
                'qbank_outcomemap'
            ));
        }
        $this->add_action_buttons(true, get_string('bulkpreview', 'qbank_outcomemap'));
    }

    /**
     * Add the operation, outcome, role, notes, and weight-section controls.
     *
     * @param array $outcomes Escaped outcome labels keyed by exact version UUID.
     * @param bool $finalizationmode Whether approval is disabled.
     */
    private function add_operation_fields(array $outcomes, bool $finalizationmode): void {
        $mform = $this->_form;
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
            $outcomes
        );
        $mform->setType('outcomeversionuuid', PARAM_ALPHANUMEXT);
        $mform->hideIf('outcomeversionuuid', 'operation', 'neq', question_mappings::BULK_ADD);

        $roles = [];
        foreach (self::ROLES as $role) {
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
        $mform->addElement(
            'static',
            'questionweightsnote',
            '',
            get_string('bulkquestionweights_help', 'qbank_outcomemap')
        );
    }

    /**
     * Add explicit per-question assessed-weight controls.
     *
     * @param \stdClass[] $questions Authorized exact question summaries.
     */
    private function add_question_weight_fields(array $questions): void {
        $mform = $this->_form;
        foreach ($questions as $question) {
            $field = 'questionweight_' . (int) $question->questionid;
            $mform->addElement('text', $field, get_string('bulkquestionweight', 'qbank_outcomemap', (object) [
                'name' => $question->name,
                'version' => $question->questionversion,
            ]));
            $mform->setType($field, PARAM_RAW_TRIMMED);
            $mform->hideIf($field, 'operation', 'neq', question_mappings::BULK_ADD);
        }
    }

    /**
     * Add the authorized draft selection and role-change weight controls.
     *
     * @param \stdClass[] $questions Authorized exact question summaries.
     * @return int Number of draft controls added.
     */
    private function add_draft_fields(array $questions): int {
        $mform = $this->_form;
        $mform->addElement('header', 'draftselection', get_string('bulkdraftselection', 'qbank_outcomemap'));
        $draftcount = 0;
        foreach ($questions as $question) {
            foreach ($question->drafts as $draft) {
                $draftcount++;
                $draftoutcome = s((string) $draft->outcome);
                $label = get_string('bulkdraftlabel', 'qbank_outcomemap', (object) [
                    'question' => $question->name,
                    'version' => $question->questionversion,
                    'outcome' => $draftoutcome,
                    'role' => get_string('mappingrole_' . $draft->role, 'local_outcomemap'),
                ]);
                $field = 'mapping_' . (int) $draft->id;
                $mform->addElement('advcheckbox', $field, $label);
                $mform->setType($field, PARAM_BOOL);
                $mform->hideIf($field, 'operation', 'eq', question_mappings::BULK_ADD);
                $weightfield = 'mappingweight_' . (int) $draft->id;
                $mform->addElement(
                    'text',
                    $weightfield,
                    get_string('bulkmappingweight', 'qbank_outcomemap', (object) [
                        'question' => $question->name,
                        'version' => $question->questionversion,
                        'outcome' => $draftoutcome,
                        'role' => get_string('mappingrole_' . $draft->role, 'local_outcomemap'),
                    ])
                );
                $mform->setType($weightfield, PARAM_RAW_TRIMMED);
                $mform->hideIf(
                    $weightfield,
                    'operation',
                    'neq',
                    question_mappings::BULK_CHANGE_ROLE
                );
            }
        }
        return $draftcount;
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
        $actions = [
            question_mappings::BULK_ADD,
            question_mappings::BULK_CHANGE_ROLE,
            question_mappings::BULK_DELETE_DRAFTS,
            question_mappings::BULK_SUBMIT_DRAFTS,
        ];
        if (!in_array($action, $actions, true)) {
            $errors['operation'] = get_string('invalidbulkoperation', 'qbank_outcomemap');
            return $errors;
        }
        if (
            ($action === question_mappings::BULK_ADD || $action === question_mappings::BULK_CHANGE_ROLE)
                && !in_array($role, self::ROLES, true)
        ) {
            $errors['role'] = get_string('invalidmappingrole', 'local_outcomemap');
            return $errors;
        }
        if ($action === question_mappings::BULK_ADD) {
            return $this->validate_add_operation($data, $questions, $role, $errors);
        }

        $selected = $this->selected_drafts($data, $questions);
        if (!$selected) {
            $errors['operation'] = get_string('bulkmappingrequired', 'qbank_outcomemap');
        }
        if ($action === question_mappings::BULK_CHANGE_ROLE && $role === 'assesses') {
            $errors = $this->require_draft_weights($data, $selected, $errors);
        }
        return $errors;
    }

    /**
     * Validate an add operation's required outcome and assessed weights.
     *
     * @param array $data Submitted values.
     * @param \stdClass[] $questions Authorized exact question summaries.
     * @param string $role Submitted canonical role.
     * @param array $errors Existing form errors.
     * @return array Field errors.
     */
    private function validate_add_operation(array $data, array $questions, string $role, array $errors): array {
        if (empty($data['outcomeversionuuid'])) {
            $errors['outcomeversionuuid'] = get_string('required');
        }
        if ($role !== 'assesses') {
            return $errors;
        }
        foreach ($questions as $question) {
            $field = 'questionweight_' . (int) $question->questionid;
            if (trim((string) ($data[$field] ?? '')) === '') {
                $errors[$field] = get_string('assessedweightrequired', 'local_outcomemap');
            }
        }
        return $errors;
    }

    /**
     * Return only server-supplied drafts selected by the submitted controls.
     *
     * @param array $data Submitted values.
     * @param \stdClass[] $questions Authorized exact question summaries.
     * @return \stdClass[] Selected public draft summaries.
     */
    private function selected_drafts(array $data, array $questions): array {
        $selected = [];
        foreach ($questions as $question) {
            foreach ($question->drafts as $draft) {
                if (!empty($data['mapping_' . (int) $draft->id])) {
                    $selected[] = $draft;
                }
            }
        }
        return $selected;
    }

    /**
     * Require explicit weights for selected drafts changing to assesses.
     *
     * @param array $data Submitted values.
     * @param \stdClass[] $selected Selected public draft summaries.
     * @param array $errors Existing form errors.
     * @return array Field errors.
     */
    private function require_draft_weights(array $data, array $selected, array $errors): array {
        foreach ($selected as $draft) {
            $field = 'mappingweight_' . (int) $draft->id;
            if (trim((string) ($data[$field] ?? '')) === '') {
                $errors[$field] = get_string('assessedweightrequired', 'local_outcomemap');
            }
        }
        return $errors;
    }

    /**
     * Add hidden routing values shared by both stages.
     */
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

    /**
     * Add immutable operation values for the explicit commit stage.
     */
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
        $types = [
            'confirmed' => PARAM_BOOL,
            'previewtoken' => PARAM_ALPHANUM,
            'operation' => PARAM_ALPHAEXT,
            'role' => PARAM_ALPHAEXT,
            'outcomeversionuuid' => PARAM_ALPHANUMEXT,
            'effectivefrom' => PARAM_INT,
            'mappingids' => PARAM_SEQUENCE,
            'weightsjson' => PARAM_RAW_TRIMMED,
            'notes' => PARAM_TEXT,
            'reason' => PARAM_TEXT,
        ];
        foreach ($hidden as $name => $value) {
            $mform->addElement('hidden', $name, $value);
            $mform->setType($name, $types[$name]);
        }
        $mform->addElement('static', 'confirmnote', '', get_string('bulkconfirmnote', 'qbank_outcomemap'));
        $this->add_action_buttons(true, get_string('bulkcommit', 'qbank_outcomemap'));
    }
}
