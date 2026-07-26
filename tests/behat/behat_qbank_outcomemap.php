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
 * Behat fixtures and navigation for critical qbank outcome-mapping workflows.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_qbank_outcomemap extends behat_base {
    /**
     * Create approved institution outcomes without coupling scenarios to governance UI setup.
     *
     * @Given /^the governed qbank outcomes "([^"]+)" exist$/
     * @param string $codes Comma-separated outcome codes.
     */
    public function the_governed_qbank_outcomes_exist(string $codes): void {
        $generator = behat_util::get_data_generator()->get_plugin_generator('local_outcomemap');
        $generator->create_approved_outcomes(array_filter(array_map('trim', explode(',', $codes))));
    }

    /**
     * Seed one approved source mapping for copy/filter scenarios.
     *
     * @Given /^question "([^"]+)" has an approved "([^"]+)" mapping to outcome "([^"]+)"$/
     * @param string $questionname Question name.
     * @param string $role Human-readable or canonical role.
     * @param string $outcomecode Outcome code.
     */
    public function question_has_an_approved_mapping(
        string $questionname,
        string $role,
        string $outcomecode
    ): void {
        $question = $this->latest_question($questionname);
        $generator = behat_util::get_data_generator()->get_plugin_generator('local_outcomemap');
        $generator->create_approved_question_mapping(
            (int) $question->versionid,
            (int) $question->id,
            $outcomecode,
            $this->normalize_role($role)
        );
    }

    /**
     * Create a minimal next core question version for copy-page behavior.
     *
     * @Given /^question "([^"]+)" has a new version named "([^"]+)"$/
     * @param string $questionname Existing question name.
     * @param string $newname New version question name.
     */
    public function question_has_a_new_version_named(string $questionname, string $newname): void {
        $source = $this->latest_question($questionname);
        $questiondata = \question_bank::load_question_data((int) $source->id);
        $generator = behat_util::get_data_generator()->get_plugin_generator('core_question');
        $generator->update_question($questiondata, null, ['name' => $newname]);
    }

    /**
     * Open the real exact-version editor for a named question.
     *
     * @When /^I open outcome mappings for question "([^"]+)"$/
     * @param string $questionname Question name.
     */
    public function i_open_outcome_mappings_for_question(string $questionname): void {
        $question = $this->latest_question($questionname);
        $this->getSession()->visit((new moodle_url('/question/bank/outcomemap/edit.php', [
            'id' => (int) $question->id,
        ]))->out(false));
    }

    /**
     * Open the course-context question bank on every supported Moodle version.
     *
     * @When /^I open the question bank for course "([^"]+)"$/
     * @param string $courseshortname Course shortname.
     */
    public function i_open_the_question_bank_for_course(string $courseshortname): void {
        global $DB;
        $courseid = (int) $DB->get_field('course', 'id', ['shortname' => $courseshortname], MUST_EXIST);
        $this->getSession()->visit((new moodle_url(
            '/question/edit.php',
            $this->question_bank_route_params($courseid)
        ))->out(false));
    }

    /**
     * Open the real bulk page for named selected questions.
     *
     * @When /^I open bulk outcome mappings for questions "([^"]+)" in course "([^"]+)"$/
     * @param string $questionnames Comma-separated question names.
     * @param string $courseshortname Course shortname.
     */
    public function i_open_bulk_outcome_mappings_for_questions(
        string $questionnames,
        string $courseshortname
    ): void {
        global $DB;
        $courseid = (int) $DB->get_field('course', 'id', ['shortname' => $courseshortname], MUST_EXIST);
        $ids = [];
        foreach (array_filter(array_map('trim', explode(',', $questionnames))) as $name) {
            $ids[] = (int) $this->latest_question($name)->id;
        }
        $params = $this->question_bank_route_params($courseid);
        $params['questionids'] = implode(',', $ids);
        $this->getSession()->visit((new moodle_url(
            '/question/bank/outcomemap/bulk.php',
            $params
        ))->out(false));
    }

    /** Return the version-appropriate route to a course's question bank. */
    private function question_bank_route_params(int $courseid): array {
        global $DB;
        $qbankmoduleid = $DB->get_field('modules', 'id', ['name' => 'qbank']);
        if ($qbankmoduleid) {
            $cmid = $DB->get_field('course_modules', 'id', [
                'course' => $courseid,
                'module' => $qbankmoduleid,
            ], IGNORE_MULTIPLE);
            if (!$cmid) {
                throw new RuntimeException('No question bank activity exists for course ID ' . $courseid);
            }
            return ['cmid' => (int) $cmid];
        }
        return ['courseid' => $courseid];
    }

    /** Return the newest question row/version for a display name. */
    private function latest_question(string $name): \stdClass {
        global $DB;
        $records = $DB->get_records_sql(
            'SELECT q.*, qv.id AS versionid, qv.version AS questionversion
               FROM {question} q
               JOIN {question_versions} qv ON qv.questionid = q.id
              WHERE q.name = :name
           ORDER BY qv.version DESC',
            ['name' => $name],
            0,
            1
        );
        if (!$records) {
            throw new RuntimeException('Question not found: ' . $name);
        }
        return reset($records);
    }

    /**
     * Replace the editor's hidden target with another question's draft mapping.
     *
     * @When /^I replace the selected draft mapping ID with the draft for question "([^"]+)"$/
     * @param string $questionname Target question name.
     */
    public function i_replace_the_selected_draft_mapping_id(string $questionname): void {
        global $DB;
        $question = $this->latest_question($questionname);
        $mappingid = (int) $DB->get_field('local_outcomemap_qmap', 'id', [
            'questionversionid' => (int) $question->versionid,
            'status' => 'draft',
        ], MUST_EXIST);
        $field = $this->getSession()->getPage()->find('css', 'input[name="mappingid"]');
        if (!$field) {
            throw new RuntimeException('The hidden mappingid field was not found.');
        }
        $field->setValue((string) $mappingid);
    }

    /**
     * Assert an exact-version draft retained its role and notes.
     *
     * @Then /^question "([^"]+)" has a draft "([^"]+)" mapping with notes "([^"]*)"$/
     * @param string $questionname Question name.
     * @param string $role Human-readable or canonical role.
     * @param string $notes Expected notes.
     */
    public function question_has_a_draft_mapping_with_notes(
        string $questionname,
        string $role,
        string $notes
    ): void {
        global $DB;
        $question = $this->latest_question($questionname);
        $records = $DB->get_records('local_outcomemap_qmap', [
            'questionversionid' => (int) $question->versionid,
            'status' => 'draft',
        ]);
        if (count($records) !== 1) {
            throw new RuntimeException('Expected exactly one draft mapping for ' . $questionname . '.');
        }
        $mapping = reset($records);
        $expectedrole = $this->normalize_role($role);
        if ($mapping->role !== $expectedrole || (string) $mapping->notes !== $notes) {
            throw new RuntimeException(sprintf(
                'Draft for %s was %s with notes "%s"; expected %s with notes "%s".',
                $questionname,
                $mapping->role,
                (string) $mapping->notes,
                $expectedrole,
                $notes
            ));
        }
    }

    /** Normalize a Behat role label. */
    private function normalize_role(string $role): string {
        $normalized = strtolower(str_replace([' ', '-'], '_', trim($role)));
        $roles = [
            'alignment_only' => 'alignment_only',
            'teaches' => 'teaches',
            'practices' => 'practices',
            'assesses' => 'assesses',
            'remediates' => 'remediates',
        ];
        if (!isset($roles[$normalized])) {
            throw new InvalidArgumentException('Unknown mapping role: ' . $role);
        }
        return $roles[$normalized];
    }
}
