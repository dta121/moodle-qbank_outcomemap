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

namespace qbank_outcomemap;

use core_question\local\bank\question_edit_contexts;
use core_question\local\bank\view;
use local_outcomemap\api\question_mappings;
use local_outcomemap\local\service\framework_service;
use local_outcomemap\local\service\outcome_service;
use local_outcomemap\local\service\question_mapping_service;
use qbank_outcomemap\local\bank\bulk_map_action;
use qbank_outcomemap\local\bank\outcome_column;
use qbank_outcomemap\local\bank\outcome_condition;
use qbank_outcomemap\local\bank\outcome_map_action;

/**
 * Tests for the outcome mapping question bank integration.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class qbank_outcomemap_test extends \advanced_testcase {
    /** @var int Outcome effective start shared across fixtures. */
    private const EFFECTIVEFROM = 1704067200;

    /**
     * Creates a question bank view like core qbank plugin tests do.
     *
     * @param \stdClass $course Course record.
     * @param \stdClass $qbank Question bank module record.
     * @return view
     */
    private function create_view(\stdClass $course, \stdClass $qbank): view {
        $cm = get_coursemodule_from_id('qbank', $qbank->cmid);
        return new view(
            new question_edit_contexts(\context_module::instance($qbank->cmid)),
            new \moodle_url('/question/edit.php', ['cmid' => $qbank->cmid]),
            $course,
            $cm
        );
    }

    /**
     * Creates an approved outcome version via the local governance services.
     *
     * @param string $code Outcome code.
     * @return array{0:int,1:string} Outcome-version ID and UUID.
     */
    private function create_approved_outcome(string $code): array {
        global $DB;
        $this->setAdminUser();
        $reviewer = $this->getDataGenerator()->create_user();
        $managerroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $reviewer->id, \context_system::instance()->id);

        $frameworkid = framework_service::create([
            'code' => 'QBFW' . $code,
            'name' => 'Question bank outcomes',
            'ownertype' => framework_service::OWNER_INSTITUTION,
        ]);
        framework_service::submit_for_review($frameworkid);
        $this->setUser($reviewer);
        framework_service::approve($frameworkid);
        $this->setAdminUser();
        $itemid = outcome_service::create([
            'frameworkid' => $frameworkid,
            'code' => $code,
            'statement' => 'Outcome ' . $code,
            'effectivefrom' => self::EFFECTIVEFROM,
        ]);
        $itemver = $DB->get_record_select('local_outcomemap_itemver', 'itemid = :itemid',
            ['itemid' => $itemid], '*', MUST_EXIST);
        outcome_service::submit_for_review((int) $itemver->id);
        $this->setUser($reviewer);
        outcome_service::approve((int) $itemver->id);
        $this->setAdminUser();
        return [(int) $itemver->id, $itemver->uuid];
    }

    /**
     * Creates a course, question bank module, category, and one question.
     *
     * @return array{0:\stdClass,1:\stdClass,2:\stdClass} Course, qbank, and question.
     */
    private function create_question_scope(): array {
        $course = $this->getDataGenerator()->create_course();
        $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category([
            'contextid' => \context_module::instance($qbank->cmid)->id,
        ]);
        $question = $generator->create_question('shortanswer', null, ['category' => $category->id]);
        return [$course, $qbank, $question];
    }

    /**
     * Tests that the plugin feature registers all view components.
     */
    public function test_plugin_feature_registers_components(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$course, $qbank] = $this->create_question_scope();
        $view = $this->create_view($course, $qbank);
        $feature = new plugin_feature();

        $columns = $feature->get_question_columns($view);
        $this->assertCount(1, $columns);
        $this->assertInstanceOf(outcome_column::class, $columns[0]);

        $actions = $feature->get_question_actions($view);
        $this->assertCount(1, $actions);
        $this->assertInstanceOf(outcome_map_action::class, $actions[0]);

        $filters = $feature->get_question_filters($view);
        $this->assertCount(1, $filters);
        $this->assertInstanceOf(outcome_condition::class, $filters[0]);

        $bulk = $feature->get_bulk_actions($view);
        $this->assertCount(1, $bulk);
        $this->assertInstanceOf(bulk_map_action::class, $bulk[0]);
        $this->assertSame('outcomemapbulk', $bulk[0]->get_key());
        // Moodle 4.5 instantiates bulk actions without a view.
        $this->assertInstanceOf(bulk_map_action::class, new bulk_map_action());
    }

    /**
     * Tests the column bulk load and accessible text rendering.
     */
    public function test_column_bulk_load_and_display(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$course, $qbank, $question] = $this->create_question_scope();
        [$itemverid] = $this->create_approved_outcome('CLO1');

        question_mapping_service::create([
            'questionversionid' => $question->versionid,
            'itemverid' => $itemverid,
            'role' => 'assesses',
            'weight' => '1.0',
            'effectivefrom' => self::EFFECTIVEFROM,
        ]);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $DB->get_record('question_categories', [
            'id' => $DB->get_field_sql(
                'SELECT qbe.questioncategoryid FROM {question_versions} qv
                   JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  WHERE qv.id = :id', ['id' => $question->versionid]
            ),
        ], '*', MUST_EXIST);
        $unmapped = $generator->create_question('shortanswer', null, ['category' => $category->id]);

        $view = $this->create_view($course, $qbank);
        $column = new outcome_column($view);
        $rows = [];
        foreach ([$question, $unmapped] as $q) {
            $record = $DB->get_record('question', ['id' => $q->id], 'id,createdby', MUST_EXIST);
            $rows[$q->id] = (object) [
                'id' => (int) $q->id,
                'versionid' => (int) $q->versionid,
                'contextid' => (int) $category->contextid,
                'createdby' => (int) $record->createdby,
            ];
        }
        $column->load_additional_data($rows);

        $display = function (\stdClass $row) use ($column): string {
            $method = new \ReflectionMethod($column, 'display_content');
            ob_start();
            $method->invoke($column, $row, '');
            return ob_get_clean();
        };
        $mappedhtml = $display($rows[$question->id]);
        $this->assertStringContainsString('CLO1', $mappedhtml);
        $this->assertStringContainsString(get_string('status_draft', 'local_outcomemap'), $mappedhtml);
        $this->assertStringContainsString(get_string('managemappings', 'qbank_outcomemap'), $mappedhtml);

        $unmappedhtml = $display($rows[$unmapped->id]);
        $this->assertStringContainsString(get_string('nomappings', 'qbank_outcomemap'), $unmappedhtml);
    }

    /**
     * Tests the outcome filter condition SQL for match and negation joins.
     */
    public function test_filter_condition_query(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [, , $question] = $this->create_question_scope();
        [$itemverid] = $this->create_approved_outcome('CLO7');
        question_mapping_service::create([
            'questionversionid' => $question->versionid,
            'itemverid' => $itemverid,
            'role' => 'alignment_only',
            'effectivefrom' => self::EFFECTIVEFROM,
        ]);

        $run = function (array $filter) use ($DB): array {
            [$where, $params] = outcome_condition::build_query_from_filter($filter);
            $sql = 'SELECT q.id FROM {question} q
                      JOIN {question_versions} qv ON qv.questionid = q.id';
            if ($where !== '') {
                $sql .= ' WHERE ' . $where;
            }
            return array_keys($DB->get_records_sql($sql, $params));
        };

        $matched = $run(['values' => ['CLO7'], 'jointype' => \core\output\datafilter::JOINTYPE_ANY]);
        $this->assertContains((int) $question->id, $matched);
        $this->assertCount(1, $matched);

        $excluded = $run(['values' => ['CLO7'], 'jointype' => \core\output\datafilter::JOINTYPE_NONE]);
        $this->assertNotContains((int) $question->id, $excluded);

        $nomatch = $run(['values' => ['NOSUCHCODE'], 'jointype' => \core\output\datafilter::JOINTYPE_ANY]);
        $this->assertNotContains((int) $question->id, $nomatch);

        $this->assertSame(['', []], outcome_condition::build_query_from_filter(['values' => []]));
    }

    /**
     * Tests that the public facade round-trips a bulk alignment draft by UUID.
     */
    public function test_bulk_alignment_creation_by_uuid(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [, , $question] = $this->create_question_scope();
        [, $versionuuid] = $this->create_approved_outcome('CLO9');

        $mappingid = question_mappings::create_draft((int) $question->versionid, $versionuuid, 'alignment_only');
        $this->assertGreaterThan(0, $mappingid);
        $grouped = question_mappings::get_for_question_versions([(int) $question->versionid]);
        $this->assertCount(1, $grouped[(int) $question->versionid]);
        $dto = $grouped[(int) $question->versionid][0];
        $this->assertSame('alignment_only', $dto->role);
        $this->assertSame($versionuuid, $dto->outcomeversionuuid);
        $this->assertNull($dto->weight);
    }
}
