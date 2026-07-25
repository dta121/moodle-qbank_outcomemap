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
use qbank_outcomemap\local\bank\mapped_condition;
use qbank_outcomemap\local\bank\outcome_column;
use qbank_outcomemap\local\bank\outcome_condition;
use qbank_outcomemap\local\bank\outcome_map_action;
use qbank_outcomemap\local\bulk_mapping_service;

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
     * The standalone question bank module arrived in Moodle 5.0. On the declared
     * 4.5 minimum there is no mod_qbank to host a module-context question bank,
     * so these fixtures cannot be built and the test is skipped rather than
     * reported as a plugin failure.
     *
     * @return array{0:\stdClass,1:\stdClass,2:\stdClass} Course, qbank, and question.
     */
    private function create_question_scope(): array {
        if (!\core_component::get_plugin_directory('mod', 'qbank')) {
            $this->markTestSkipped(
                'Module-context question banks require mod_qbank, added in Moodle 5.0.'
            );
        }
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
        $this->assertCount(2, $filters);
        $this->assertInstanceOf(outcome_condition::class, $filters[0]);
        $this->assertInstanceOf(mapped_condition::class, $filters[1]);

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
     * Tests that the maximum supported page is split into safe public API batches.
     */
    public function test_column_chunks_maximum_question_bank_page(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$course, $qbank] = $this->create_question_scope();
        $view = $this->create_view($course, $qbank);
        $column = new class($view) extends outcome_column {
            /** @var int[][] Captured question-version ID batches. */
            public array $batches = [];

            /**
             * Capture one batch without creating thousands of question fixtures.
             *
             * @param int[] $questionversionids Question-version IDs.
             * @return array
             */
            protected function load_mapping_batch(array $questionversionids): array {
                $this->batches[] = $questionversionids;
                return [];
            }
        };
        $rows = [];
        for ($versionid = 1; $versionid <= 4000; $versionid++) {
            $rows[] = (object) ['versionid' => $versionid];
        }

        $column->load_additional_data($rows);
        // Moodle 5.2 currently invokes the column preload callback twice.
        $column->load_additional_data($rows);

        $this->assertCount(4, $column->batches);
        foreach ($column->batches as $batch) {
            $this->assertLessThanOrEqual(1000, count($batch));
        }
        $this->assertSame(range(1, 4000), array_merge(...$column->batches));
    }

    /**
     * Core can discover filter condition classes without constructing a view.
     */
    public function test_filter_classes_register_without_view(): void {
        $this->resetAfterTest(true);
        $this->setUser($this->getDataGenerator()->create_user());

        $filters = (new plugin_feature())->get_question_filters();

        $this->assertCount(2, $filters);
        $this->assertInstanceOf(outcome_condition::class, $filters[0]);
        $this->assertInstanceOf(mapped_condition::class, $filters[1]);
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

        // The yes/no mapped-state filter uses the same SQL scaffold.
        $runmapped = function (array $filter) use ($DB): array {
            [$where, $params] = mapped_condition::build_query_from_filter($filter);
            $sql = 'SELECT q.id FROM {question} q
                      JOIN {question_versions} qv ON qv.questionid = q.id';
            if ($where !== '') {
                $sql .= ' WHERE ' . $where;
            }
            return array_keys($DB->get_records_sql($sql, $params));
        };
        $this->assertContains((int) $question->id, $runmapped(['values' => [1]]));
        $this->assertNotContains((int) $question->id, $runmapped(['values' => [0]]));
        $this->assertSame(['', []], mapped_condition::build_query_from_filter(['values' => []]));
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

    /**
     * Tests that duplicate detection uses a fixed query budget for a large selection.
     */
    public function test_bulk_duplicate_preload_query_count_does_not_scale_per_question(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        [, , $firstquestion] = $this->create_question_scope();
        [$itemverid, $versionuuid] = $this->create_approved_outcome('CLOPERF');
        $categoryid = (int) $DB->get_field_sql(
            'SELECT qbe.questioncategoryid
               FROM {question_versions} qv
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              WHERE qv.id = :versionid',
            ['versionid' => $firstquestion->versionid],
            MUST_EXIST
        );
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $questions = [$firstquestion];
        for ($index = 1; $index < 25; $index++) {
            $questions[] = $generator->create_question('shortanswer', null, ['category' => $categoryid]);
        }
        foreach ($questions as $question) {
            question_mapping_service::create([
                'questionversionid' => $question->versionid,
                'itemverid' => $itemverid,
                'role' => 'alignment_only',
                'effectivefrom' => self::EFFECTIVEFROM,
            ]);
        }
        $questionids = array_map(static fn(\stdClass $question): int => (int) $question->id, $questions);

        // Warm context and capability caches so the counters cover data access, not one-time bootstrap work.
        bulk_mapping_service::add_alignment_drafts([$questionids[0]], $versionuuid);
        $before = $DB->perf_get_queries();
        $single = bulk_mapping_service::add_alignment_drafts([$questionids[0]], $versionuuid);
        $singlequeries = $DB->perf_get_queries() - $before;

        $before = $DB->perf_get_queries();
        $many = bulk_mapping_service::add_alignment_drafts($questionids, $versionuuid);
        $manyqueries = $DB->perf_get_queries() - $before;

        $this->assertSame(0, $single->created);
        $this->assertSame(1, $single->skipped);
        $this->assertSame(0, $many->created);
        $this->assertSame(count($questionids), $many->skipped);
        $this->assertLessThanOrEqual($singlequeries + 1, $manyqueries);
        $this->assertLessThanOrEqual(4, $manyqueries,
            'Bulk metadata and existing mappings must be loaded with a fixed query budget.');
    }

    /**
     * Tests that mapping metadata is not registered without definition-read access.
     */
    public function test_features_are_hidden_without_definition_read_capability(): void {
        $this->resetAfterTest(true);
        [$course, $qbank] = $this->create_question_scope();
        $context = \context_module::instance($qbank->cmid);
        $user = $this->getDataGenerator()->create_user();
        $roleid = create_role('Question mapper without definition access', 'qmappernodef', '');
        assign_capability('local/outcomemap:mapquestions', CAP_ALLOW, $roleid, $context->id, true);
        role_assign($roleid, $user->id, $context->id);
        $this->setUser($user);

        $this->assertTrue(has_capability('local/outcomemap:mapquestions', $context));
        $this->assertFalse(has_capability('local/outcomemap:viewdefinitions', $context));
        $view = $this->create_view($course, $qbank);
        $feature = new plugin_feature();
        $this->assertSame([], $feature->get_question_columns($view));
        $this->assertSame([], $feature->get_question_actions($view));
        $this->assertSame([], $feature->get_question_filters($view));
        $this->assertSame([], $feature->get_bulk_actions($view));
    }

    /**
     * Tests that public qbank filter boundaries enforce definition-read access.
     */
    public function test_public_filter_queries_require_definition_read_capability(): void {
        $this->resetAfterTest(true);
        [, $qbank] = $this->create_question_scope();
        $context = \context_module::instance($qbank->cmid);
        $this->setUser($this->getDataGenerator()->create_user());

        $deniedcalls = [
            static fn() => question_mappings::build_mapped_filter_query($context, true),
            static fn() => question_mappings::build_outcome_filter_query(
                $context,
                ['CLO1'],
                \core\output\datafilter::JOINTYPE_ANY
            ),
        ];
        foreach ($deniedcalls as $call) {
            $denied = false;
            try {
                $call();
            } catch (\required_capability_exception) {
                $denied = true;
            }
            $this->assertTrue($denied, 'The public filter boundary must reject users without definition-read access.');
        }
    }

    /**
     * Tests the editing-teacher archetype contract for question mapping workflows.
     */
    public function test_editing_teacher_has_read_and_mapping_features(): void {
        $this->resetAfterTest(true);
        [$course, $qbank] = $this->create_question_scope();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $context = \context_module::instance($qbank->cmid);
        $this->assertTrue(has_capability('local/outcomemap:viewdefinitions', $context));
        $this->assertTrue(has_capability('local/outcomemap:mapquestions', $context));
        $view = $this->create_view($course, $qbank);
        $feature = new plugin_feature();
        $this->assertCount(1, $feature->get_question_columns($view));
        $this->assertCount(1, $feature->get_question_actions($view));
        $this->assertCount(2, $feature->get_question_filters($view));
        $this->assertCount(1, $feature->get_bulk_actions($view));
    }
}
