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
use qbank_outcomemap\local\bank\copied_condition;
use qbank_outcomemap\local\bank\invalid_weight_condition;
use qbank_outcomemap\local\bank\mapped_condition;
use qbank_outcomemap\local\bank\outcome_column;
use qbank_outcomemap\local\bank\outcome_condition;
use qbank_outcomemap\local\bank\outcome_map_action;
use qbank_outcomemap\local\bank\role_condition;
use qbank_outcomemap\local\bank\status_condition;
use qbank_outcomemap\local\access;
/**
 * Tests for the outcome mapping question bank integration.
 *
 * @coversNothing
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
     * @param \stdClass|null $qbank Optional Moodle 5.2 question-bank module.
     * @return view
     */
    private function create_view(\stdClass $course, ?\stdClass $qbank = null): view {
        $cm = $qbank ? get_coursemodule_from_id('qbank', $qbank->cmid) : null;
        $context = $qbank
            ? \context_module::instance($qbank->cmid)
            : \context_course::instance($course->id);
        $url = $qbank
            ? new \moodle_url('/question/edit.php', ['cmid' => $qbank->cmid])
            : new \moodle_url('/question/edit.php', ['courseid' => $course->id]);
        return new view(
            new question_edit_contexts($context),
            $url,
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
        if (\local_outcomemap\api\workflow::requires_independent_approval()) {
            $this->setUser($reviewer);
            framework_service::approve($frameworkid);
        }
        $this->setAdminUser();
        $itemid = outcome_service::create([
            'frameworkid' => $frameworkid,
            'code' => $code,
            'statement' => 'Outcome ' . $code,
            'effectivefrom' => self::EFFECTIVEFROM,
        ]);
        $itemver = $DB->get_record_select(
            'local_outcomemap_itemver',
            'itemid = :itemid',
            ['itemid' => $itemid],
            '*',
            MUST_EXIST
        );
        outcome_service::submit_for_review((int) $itemver->id);
        if (\local_outcomemap\api\workflow::requires_independent_approval()) {
            $this->setUser($reviewer);
            outcome_service::approve((int) $itemver->id);
        }
        $this->setAdminUser();
        return [(int) $itemver->id, $itemver->uuid];
    }

    /**
     * Creates a course-context question bank category and one question.
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
        $context = \context_module::instance($qbank->cmid);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category([
            'contextid' => $context->id,
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
        $this->assertCount(6, $filters);
        $this->assertInstanceOf(outcome_condition::class, $filters[0]);
        $this->assertInstanceOf(role_condition::class, $filters[1]);
        $rolevalues = array_map(
            static fn(\stdClass $value): int => $value->value,
            $filters[1]->get_initial_values()
        );
        $this->assertSame([0, 1, 2, 3, 4], $rolevalues);
        [, $roleparams] = role_condition::build_query_from_filter([
            'values' => [4],
            'jointype' => \core\output\datafilter::JOINTYPE_ANY,
        ]);
        $this->assertContains('remediates', $roleparams);
        $this->assertInstanceOf(status_condition::class, $filters[2]);
        $statusvalues = array_map(
            static fn(\stdClass $value): int => $value->value,
            $filters[2]->get_initial_values()
        );
        $this->assertSame([0, 1, 2, 3], $statusvalues);
        [, $statusparams] = status_condition::build_query_from_filter([
            'values' => [1],
            'jointype' => \core\output\datafilter::JOINTYPE_ANY,
        ]);
        $this->assertContains('needs_review', $statusparams);
        $this->assertInstanceOf(mapped_condition::class, $filters[3]);
        $this->assertSame(
            'qbank_outcomemap/datafilter/filtertypes/binary',
            $filters[3]->get_filter_class()
        );
        $this->assertInstanceOf(invalid_weight_condition::class, $filters[4]);
        $this->assertInstanceOf(copied_condition::class, $filters[5]);

        $bulk = $feature->get_bulk_actions($view);
        $this->assertCount(1, $bulk);
        $this->assertInstanceOf(bulk_map_action::class, $bulk[0]);
        $this->assertSame('outcomemapbulk', $bulk[0]->get_key());
        // Moodle 4.5 instantiates bulk actions without a view.
        $this->assertInstanceOf(bulk_map_action::class, new bulk_map_action());
    }

    /**
     * Tests that every extension point fails closed when the required companion is unavailable.
     */
    public function test_plugin_feature_registers_nothing_without_dependency(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$course, $qbank] = $this->create_question_scope();
        $view = $this->create_view($course, $qbank);
        $feature = new class extends plugin_feature {
            /**
             * Simulate a missing or incomplete required local_outcomemap installation.
             *
             * @return bool
             */
            protected function dependency_available(): bool {
                return false;
            }
        };

        $this->assertSame([], $feature->get_question_columns($view));
        $this->assertSame([], $feature->get_question_actions($view));
        $this->assertSame([], $feature->get_question_filters($view));
        $this->assertSame([], $feature->get_question_filters());
        $this->assertSame([], $feature->get_bulk_actions($view));
        $this->assertSame([], $feature->get_bulk_actions());
    }

    /**
     * Tests the dependency-only skeleton and privacy ownership contract.
     */
    public function test_companion_plugin_owns_no_personal_data_or_capabilities(): void {
        $this->assertTrue(is_subclass_of(
            \qbank_outcomemap\privacy\provider::class,
            \core_privacy\local\metadata\null_provider::class
        ));
        $this->assertSame('privacy:metadata', \qbank_outcomemap\privacy\provider::get_reason());
        $this->assertFileDoesNotExist(__DIR__ . '/../db/install.xml');
        $this->assertFileDoesNotExist(__DIR__ . '/../db/access.php');
        $version = file_get_contents(__DIR__ . '/../version.php');
        $this->assertStringContainsString("'local_outcomemap' => 2026081700", $version);
        $this->assertTrue(
            class_exists(question_mappings::class),
            'The pinned public local_outcomemap service dependency must be available.'
        );
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
                  WHERE qv.id = :id',
                ['id' => $question->versionid]
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

        // A caller without the local view capability must not receive or render mapping details.
        $restricteduser = $this->getDataGenerator()->create_user();
        $this->setUser($restricteduser);
        $restrictedcolumn = new outcome_column($view);
        $restrictedcolumn->load_additional_data($rows);
        $restricteddisplay = function (\stdClass $row) use ($restrictedcolumn): string {
            $method = new \ReflectionMethod($restrictedcolumn, 'display_content');
            ob_start();
            $method->invoke($restrictedcolumn, $row, '');
            return ob_get_clean();
        };
        $restrictedhtml = $restricteddisplay($rows[$question->id]);
        $this->assertStringNotContainsString('CLO1', $restrictedhtml);
        $this->assertStringNotContainsString(get_string('managemappings', 'qbank_outcomemap'), $restrictedhtml);
        $this->assertStringContainsString(get_string('nomappings', 'qbank_outcomemap'), $restrictedhtml);
    }

    /**
     * Tests accessible labels, complete role round-tripping, and workflow-specific terminology.
     */
    public function test_mapping_forms_render_accessible_labels(): void {
        global $PAGE;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('requireapproval', 1, 'local_outcomemap');
        $PAGE->set_url(new \moodle_url('/question/bank/outcomemap/edit.php'));

        $mappingform = new \qbank_outcomemap\form\mapping_form(null, [
            'questionid' => 11,
            'mappingid' => 0,
            'returnurl' => '',
            'outcomes' => [
                '' => get_string('choosedots'), 'outcome-uuid' => 'QBFW.CLO1 v1', 'unsafe-uuid' => '<img src=x onerror=alert(1)>',
            ],
        ]);
        ob_start();
        $mappingform->display();
        $mappinghtml = ob_get_clean();
        $this->assertStringContainsString('for="id_outcomeversionuuid"', $mappinghtml);
        $this->assertStringContainsString(get_string('outcome', 'qbank_outcomemap'), $mappinghtml);
        $this->assertStringContainsString('for="id_role"', $mappinghtml);
        $this->assertStringContainsString(get_string('mappingrole', 'local_outcomemap'), $mappinghtml);
        $this->assertStringContainsString('value="remediates"', $mappinghtml);
        $this->assertStringContainsString(get_string('mappingrole_remediates', 'local_outcomemap'), $mappinghtml);
        $this->assertStringContainsString('for="id_weight"', $mappinghtml);
        $this->assertStringContainsString(get_string('assessedweight', 'qbank_outcomemap'), $mappinghtml);
        $this->assertStringContainsString(get_string('reviewmessage', 'qbank_outcomemap'), $mappinghtml);
        $this->assertStringContainsString(get_string('savedraft', 'qbank_outcomemap'), $mappinghtml);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $mappinghtml);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $mappinghtml);
        $question = (object) [
            'questionid' => 11,
            'name' => 'Accessible question',
            'questionversion' => 1,
            'drafts' => [(object) [
                'id' => 22,
                'outcome' => '<img src=x onerror=alert(2)>',
                'role' => 'alignment_only',
            ]],
        ];
        $bulkform = new \qbank_outcomemap\form\bulk_map_form(null, [
            'questionids' => '11',
            'cmid' => 0,
            'courseid' => 2,
            'returnurl' => '',
            'outcomes' => [
                '' => get_string('choosedots'), 'outcome-uuid' => 'QBFW.CLO1 v1', 'unsafe-uuid' => '<svg onload=alert(1)>',
            ], 'questions' => [$question],
        ]);
        ob_start();
        $bulkform->display();
        $bulkhtml = ob_get_clean();
        $this->assertStringContainsString('for="id_operation"', $bulkhtml);
        $this->assertStringContainsString(get_string('bulkoperation', 'qbank_outcomemap'), $bulkhtml);
        $this->assertStringContainsString('for="id_questionweight_11"', $bulkhtml);
        $this->assertStringContainsString('Accessible question, version 1', $bulkhtml);
        $this->assertStringContainsString(get_string('bulkpreview', 'qbank_outcomemap'), $bulkhtml);
        $this->assertStringNotContainsString('<svg onload=alert(1)>', $bulkhtml);
        $this->assertStringContainsString('&lt;svg onload=alert(1)&gt;', $bulkhtml);
        $this->assertStringNotContainsString('<img src=x onerror=alert(2)>', $bulkhtml);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(2)&gt;', $bulkhtml);
        // A hidden draft ID must remain bound to the server-selected exact-version draft.
        $editform = new \qbank_outcomemap\form\mapping_form(null, [
            'questionid' => 11,
            'mappingid' => 22,
            'returnurl' => '',
            'outcomes' => ['' => get_string('choosedots'), 'outcome-uuid' => 'QBFW.CLO1 v1'],
        ]);
        $errors = $editform->validation([
            'id' => 11,
            'mappingid' => 23,
            'outcomeversionuuid' => 'outcome-uuid',
            'role' => 'remediates',
            'weight' => '',
            'notes' => 'Unrelated edit',
            'reviewmessage' => '',
        ], []);
        $this->assertSame(get_string('invalidmappingedit', 'qbank_outcomemap'), $errors['outcomeversionuuid']);
        $invalidroleerrors = $editform->validation([
            'id' => 11,
            'mappingid' => 22,
            'outcomeversionuuid' => 'outcome-uuid',
            'role' => '<img src=x onerror=alert(1)>',
            'weight' => '1',
        ], []);
        $this->assertSame(get_string('invalidmappingrole', 'local_outcomemap'), $invalidroleerrors['role']);
        $this->assertStringNotContainsString('<img', implode(' ', $invalidroleerrors));
        // Approval-disabled forms must use finalization terminology exclusively.
        set_config('requireapproval', 0, 'local_outcomemap');
        $finalmappingform = new \qbank_outcomemap\form\mapping_form(null, [
            'questionid' => 11,
            'mappingid' => 0,
            'returnurl' => '',
            'outcomes' => ['' => get_string('choosedots'), 'outcome-uuid' => 'QBFW.CLO1 v1'],
        ]);
        ob_start();
        $finalmappingform->display();
        $finalmappinghtml = ob_get_clean();

        $question->drafts = [];
        $finalbulkform = new \qbank_outcomemap\form\bulk_map_form(null, [
            'questionids' => '11',
            'cmid' => 0,
            'courseid' => 2,
            'returnurl' => '',
            'outcomes' => ['' => get_string('choosedots'), 'outcome-uuid' => 'QBFW.CLO1 v1'],
            'questions' => [$question],
        ]);
        ob_start();
        $finalbulkform->display();
        $finalbulkhtml = ob_get_clean();
        $finalhtml = $finalmappinghtml . ' ' . $finalbulkhtml;
        $this->assertStringContainsString(get_string('outcome_finalization', 'qbank_outcomemap'), $finalhtml);
        $this->assertStringContainsString(
            get_string('reviewmessage_finalization', 'qbank_outcomemap'),
            $finalhtml
        );
        $this->assertStringContainsString(get_string('bulknodrafts_finalization', 'qbank_outcomemap'), $finalhtml);
        $finaltext = html_entity_decode(strip_tags($finalhtml));
        $this->assertDoesNotMatchRegularExpression(
            '/\b(?:approved|approval|review|submission)\b/i',
            $finaltext
        );
    }

    /**
     * Guards mapped visible-page loading against per-row query growth.
     */
    public function test_large_visible_page_uses_constant_mapping_queries(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$course, $qbank, $first] = $this->create_question_scope();
        [$itemverid] = $this->create_approved_outcome('PERF');
        $categoryid = (int) $DB->get_field_sql(
            'SELECT qbe.questioncategoryid
               FROM {question_versions} qv
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              WHERE qv.id = :id',
            ['id' => $first->versionid]
        );
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $questions = [$first];
        for ($index = 2; $index <= 50; $index++) {
            $questions[] = $generator->create_question('shortanswer', null, [
                'category' => $categoryid,
                'name' => 'Performance question ' . $index,
            ]);
        }
        $contextid = (int) $DB->get_field('question_categories', 'contextid', ['id' => $categoryid], MUST_EXIST);
        $rows = [];
        foreach ($questions as $question) {
            question_mapping_service::create([
                'questionversionid' => $question->versionid,
                'itemverid' => $itemverid,
                'role' => 'alignment_only',
                'effectivefrom' => self::EFFECTIVEFROM,
            ]);
            $record = $DB->get_record('question', ['id' => $question->id], 'id,createdby', MUST_EXIST);
            $rows[$question->id] = (object) [
                'id' => (int) $question->id,
                'versionid' => (int) $question->versionid,
                'contextid' => $contextid,
                'createdby' => (int) $record->createdby,
            ];
        }

        if (!method_exists($DB, 'perf_get_reads')) {
            $this->markTestSkipped('The database driver does not expose the Moodle query counter.');
        }
        $view = $this->create_view($course, $qbank);
        $firstrow = [$first->id => $rows[$first->id]];

        // Warm context and capability caches before comparing equivalent calls.
        (new outcome_column($view))->load_additional_data($firstrow);

        $before = $DB->perf_get_reads();
        (new outcome_column($view))->load_additional_data($firstrow);
        $smallreads = $DB->perf_get_reads() - $before;

        $before = $DB->perf_get_reads();
        (new outcome_column($view))->load_additional_data($rows);
        $largereads = $DB->perf_get_reads() - $before;

        $this->assertGreaterThan(0, $smallreads);
        $this->assertLessThanOrEqual(
            $smallreads + 1,
            $largereads,
            'Loading 50 mapped rows must use the same query shape as loading one mapped row.'
        );
        $this->assertLessThanOrEqual(
            8,
            $largereads,
            'Mapping retrieval must remain bounded for a 50-question visible page.'
        );
    }

    /**
     * Tests that feature registration fails closed for an unauthorized viewer.
     */
    public function test_feature_registration_hidden_without_local_capabilities(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$course, $qbank] = $this->create_question_scope();
        $view = $this->create_view($course, $qbank);
        $this->setUser($this->getDataGenerator()->create_user());
        $feature = new plugin_feature();

        $this->assertSame([], $feature->get_question_columns($view));
        $this->assertSame([], $feature->get_question_actions($view));
        $this->assertSame([], $feature->get_question_filters($view));
        $this->assertSame([], $feature->get_bulk_actions($view));
    }

    /**
     * Tests that the maximum supported page is split into safe public API batches.
     */
    public function test_column_chunks_maximum_question_bank_page(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$course, $qbank] = $this->create_question_scope();
        $view = $this->create_view($course, $qbank);
        $column = new class ($view) extends outcome_column {
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

        $this->assertCount(6, $filters);
        $this->assertInstanceOf(outcome_condition::class, $filters[0]);
        $this->assertInstanceOf(role_condition::class, $filters[1]);
        $this->assertInstanceOf(status_condition::class, $filters[2]);
        $this->assertInstanceOf(mapped_condition::class, $filters[3]);
        $this->assertInstanceOf(invalid_weight_condition::class, $filters[4]);
        $this->assertInstanceOf(copied_condition::class, $filters[5]);
    }

    /**
     * Tests the outcome filter condition SQL for match and negation joins.
     */
    public function test_filter_condition_query(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [, , $question] = $this->create_question_scope();
        [, , $unmapped] = $this->create_question_scope();
        [$itemverid] = $this->create_approved_outcome('CLO7');
        $alignmentid = question_mapping_service::create([
            'questionversionid' => $question->versionid,
            'itemverid' => $itemverid,
            'role' => 'alignment_only',
            'effectivefrom' => self::EFFECTIVEFROM,
        ]);
        question_mapping_service::create([
            'questionversionid' => $question->versionid,
            'itemverid' => $itemverid,
            'role' => 'assesses',
            'weight' => '0.5000000000',
            'effectivefrom' => self::EFFECTIVEFROM,
        ]);
        question_mapping_service::create([
            'questionversionid' => $question->versionid,
            'itemverid' => $itemverid,
            'role' => 'remediates',
            'effectivefrom' => self::EFFECTIVEFROM,
        ]);
        // Mark the alignment draft as copied provenance for the copied-pending filter.
        $DB->set_field('local_outcomemap_qmap', 'sourceqmapid', $alignmentid, ['id' => $alignmentid]);
        $DB->set_field(
            'local_outcomemap_qmap',
            'sourcequestionversionid',
            $question->versionid,
            ['id' => $alignmentid]
        );

        $run = function (string $condition, array $filter) use ($DB): array {
            [$where, $params] = $condition::build_query_from_filter($filter);
            $sql = 'SELECT q.id FROM {question} q
                      JOIN {question_versions} qv ON qv.questionid = q.id';
            if ($where !== '') {
                $sql .= ' WHERE ' . $where;
            }
            return array_map('intval', array_keys($DB->get_records_sql($sql, $params)));
        };

        $any = \core\output\datafilter::JOINTYPE_ANY;
        $none = \core\output\datafilter::JOINTYPE_NONE;
        $this->assertContains(
            (int) $question->id,
            $run(outcome_condition::class, ['values' => ['CLO7'], 'jointype' => $any])
        );
        $this->assertContains(
            (int) $question->id,
            $run(outcome_condition::class, ['values' => ['Question bank outcomes'], 'jointype' => $any])
        );
        $this->assertNotContains(
            (int) $question->id,
            $run(outcome_condition::class, ['values' => ['CLO7'], 'jointype' => $none])
        );
        $this->assertNotContains(
            (int) $question->id,
            $run(outcome_condition::class, ['values' => ['NOSUCHCODE'], 'jointype' => $any])
        );
        $this->assertContains(
            (int) $question->id,
            $run(role_condition::class, ['values' => ['assesses'], 'jointype' => $any])
        );
        $this->assertContains(
            (int) $question->id,
            $run(role_condition::class, ['values' => ['remediates'], 'jointype' => $any])
        );
        $this->assertContains(
            (int) $question->id,
            $run(status_condition::class, ['values' => ['draft'], 'jointype' => $any])
        );
        $this->assertContains((int) $question->id, $run(mapped_condition::class, ['values' => [1]]));
        $this->assertNotContains((int) $question->id, $run(mapped_condition::class, ['values' => [0]]));
        $this->assertContains((int) $unmapped->id, $run(mapped_condition::class, ['values' => [0]]));
        $this->assertNotContains((int) $unmapped->id, $run(mapped_condition::class, ['values' => [1]]));
        $this->assertContains((int) $question->id, $run(invalid_weight_condition::class, ['values' => [1]]));
        $this->assertContains((int) $question->id, $run(copied_condition::class, ['values' => [1]]));

        foreach (
            [
            outcome_condition::class,
            role_condition::class,
            status_condition::class,
            mapped_condition::class,
            invalid_weight_condition::class,
            copied_condition::class,
            ] as $condition
        ) {
            $this->assertSame(['', []], $condition::build_query_from_filter(['values' => []]));
        }

        $longterms = [];
        for ($index = 0; $index < 25; $index++) {
            $longterms[] = str_pad((string) $index, 150, 'x');
        }
        [, $boundedparams] = outcome_condition::build_query_from_filter([
            'values' => $longterms, 'jointype' => $any,
        ]);
        $this->assertCount(100, $boundedparams, 'Twenty terms expand to five bounded LIKE parameters each.');
        foreach ($boundedparams as $value) {
            $this->assertLessThanOrEqual(102, \core_text::strlen($value));
        }
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame(['1 = 0', []], mapped_condition::build_query_from_filter(['values' => [1]]));
    }

    /**
     * Tests role filtering remains scoped to independently authorized question contexts.
     */
    public function test_role_filter_excludes_denied_question_context(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [, , $allowedquestion] = $this->create_question_scope();
        [, , $deniedquestion] = $this->create_question_scope();
        [$itemverid] = $this->create_approved_outcome('REMEDIATE');
        foreach ([$allowedquestion, $deniedquestion] as $question) {
            question_mapping_service::create([
                'questionversionid' => $question->versionid,
                'itemverid' => $itemverid,
                'role' => 'remediates',
                'effectivefrom' => self::EFFECTIVEFROM,
            ]);
        }
        $allowedcontext = \local_outcomemap\api\context_resolver::for_question_version(
            (int) $allowedquestion->versionid
        );
        $deniedcontext = \local_outcomemap\api\context_resolver::for_question_version(
            (int) $deniedquestion->versionid
        );
        $user = $this->getDataGenerator()->create_user();
        $roleid = create_role('Scoped outcome viewer', 'scopedoutcomeviewer', '');
        assign_capability(
            'local/outcomemap:viewdefinitions',
            CAP_ALLOW,
            $roleid,
            $allowedcontext->id,
            true
        );
        role_assign($roleid, $user->id, $allowedcontext->id);
        $this->setUser($user);

        [$where, $params] = \local_outcomemap\api\question_mapping_filters::build(
            \local_outcomemap\api\question_mapping_filters::ROLE,
            ['values' => ['remediates'], 'jointype' => \core\output\datafilter::JOINTYPE_ANY],
            [$allowedcontext, $deniedcontext]
        );
        $records = $DB->get_records_sql(
            'SELECT q.id
               FROM {question} q
               JOIN {question_versions} qv ON qv.questionid = q.id
              WHERE ' . $where,
            $params
        );
        $questionids = array_map('intval', array_keys($records));
        $this->assertContains((int) $allowedquestion->id, $questionids);
        $this->assertNotContains((int) $deniedquestion->id, $questionids);
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
     * Tests the companion-facing bulk preview contract and exact-version commit.
     */
    public function test_public_bulk_preview_and_commit_contract(): void {
        global $DB, $PAGE;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [, , $question] = $this->create_question_scope();
        [, $versionuuid] = $this->create_approved_outcome('CLO11');
        $question->name = '<script>alert("unsafe")</script> & quoted';
        $DB->set_field('question', 'name', $question->name, ['id' => $question->id]);

        $preview = question_mappings::preview_bulk([(int) $question->id], [
            'action' => question_mappings::BULK_ADD,
            'outcomeversionuuid' => $versionuuid,
            'role' => 'alignment_only',
        ]);
        $this->assertTrue($preview->valid);
        $this->assertSame($versionuuid, $preview->operation['outcomeversionuuid']);
        $this->assertArrayNotHasKey('itemverid', $preview->operation);
        $this->assertFalse(property_exists($preview->questions[0], 'contextid'));
        $this->assertFalse(property_exists($preview->questions[0], 'createdby'));
        $this->assertSame((int) $question->versionid, $preview->questions[0]->questionversionid);
        $questioncontext = \local_outcomemap\api\context_resolver::for_question_version(
            (int) $question->versionid
        );
        $this->assertSame(
            format_string($question->name, true, ['context' => $questioncontext]),
            $preview->questions[0]->name
        );
        $this->assertStringNotContainsString('<script>', $preview->questions[0]->name);
        $this->assertStringContainsString('&amp; quoted', $preview->questions[0]->name);

        $PAGE->set_url(new \moodle_url('/question/bank/outcomemap/bulk.php'));
        $form = new \qbank_outcomemap\form\bulk_map_form(null, [
            'questionids' => (string) $question->id,
            'cmid' => 0,
            'courseid' => 2,
            'returnurl' => '',
            'outcomes' => ['' => get_string('choosedots'), $versionuuid => 'QBFW.CLO11 v1'],
            'questions' => $preview->questions,
        ]);
        ob_start();
        $form->display();
        $formhtml = ob_get_clean();
        $this->assertStringContainsString($preview->questions[0]->name . ', version 1', $formhtml);
        $this->assertStringNotContainsString('&amp;amp; quoted', $formhtml);

        $result = question_mappings::commit_bulk(
            [(int) $question->id],
            $preview->operation,
            $preview->previewtoken
        );
        $this->assertSame(1, $result->affected);
        $this->assertTrue($DB->record_exists('local_outcomemap_qmap', [
            'questionversionid' => $question->versionid,
            'role' => 'alignment_only',
        ]));
    }

    /**
     * Tests prior-version eligibility, explicit copy provenance, and finalization.
     */
    public function test_prior_version_copy_preview_and_finalization(): void {
        $this->resetAfterTest(true);
        set_config('requireapproval', 0, 'local_outcomemap');
        set_config('autocopyquestionmappings', 0, 'local_outcomemap');
        $this->setAdminUser();
        [, , $question] = $this->create_question_scope();
        [, $versionuuid] = $this->create_approved_outcome('CLO12');
        $sourceid = question_mappings::create_draft(
            (int) $question->versionid,
            $versionuuid,
            'alignment_only'
        );
        question_mappings::submit_for_review($sourceid);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $newversion = $generator->update_question(clone $question, null, ['name' => 'Copied version']);
        $preview = question_mappings::preview_copy_to_version((int) $newversion->versionid);
        $this->assertSame(1, $preview->eligiblecount);
        $this->assertSame(1, $preview->sourceversion);
        $this->assertStringContainsString('CLO12', $preview->mappings[0]->outcome);

        $newids = question_mappings::copy_to_version((int) $newversion->versionid);
        $this->assertCount(1, $newids);
        $grouped = question_mappings::get_for_question_versions([(int) $newversion->versionid]);
        $copy = $grouped[(int) $newversion->versionid][0];
        $this->assertSame('draft', $copy->status);
        $this->assertSame((int) $question->versionid, $copy->sourcequestionversionid);
        $this->assertSame(1, $copy->sourcequestionversion);
        $this->assertNotNull($copy->sourcemappinguuid);

        question_mappings::submit_for_review($copy->id);
        $grouped = question_mappings::get_for_question_versions([(int) $newversion->versionid]);
        $this->assertSame('approved', $grouped[(int) $newversion->versionid][0]->status);
    }

    /**
     * Tests the editor's public draft update and explicit submission boundary.
     */
    public function test_public_draft_update_and_submit(): void {
        $this->resetAfterTest(true);
        set_config('requireapproval', 1, 'local_outcomemap');
        $this->setAdminUser();
        [, , $question] = $this->create_question_scope();
        [, $versionuuid] = $this->create_approved_outcome('CLO10');

        $mappingid = question_mappings::create_draft(
            (int) $question->versionid,
            $versionuuid,
            'alignment_only'
        );
        question_mappings::update_draft($mappingid, [
            'role' => 'teaches',
            'notes' => 'Edited in the qbank form.',
            'reason' => 'Ready for review.',
        ]);

        $grouped = question_mappings::get_for_question_versions([(int) $question->versionid]);
        $dto = $grouped[(int) $question->versionid][0];
        $this->assertSame('teaches', $dto->role);
        $this->assertSame('Edited in the qbank form.', $dto->notes);
        $this->assertSame('draft', $dto->status);

        question_mappings::submit_for_review($mappingid, 'Ready for review.');
        $grouped = question_mappings::get_for_question_versions([(int) $question->versionid]);
        $this->assertSame('needs_review', $grouped[(int) $question->versionid][0]->status);
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
     * Tests direct endpoint guards require read, map, and core question access for every selection.
     */
    public function test_direct_access_guards_fail_closed_for_tampered_contexts(): void {
        $this->resetAfterTest(true);
        [, $allowedqbank, $allowedquestion] = $this->create_question_scope();
        [, , $deniedquestion] = $this->create_question_scope();
        $allowedcontext = \context_module::instance($allowedqbank->cmid);
        $user = $this->getDataGenerator()->create_user();
        $roleid = create_role('Direct question mapper', 'directquestionmapper', '');
        foreach (['local/outcomemap:mapquestions', 'moodle/question:editall'] as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $allowedcontext->id, true);
        }
        role_assign($roleid, $user->id, $allowedcontext->id);
        $this->setUser($user);
        $denied = false;
        try {
            access::require_question_edit_access($allowedcontext, (int) $allowedquestion->id);
        } catch (\required_capability_exception $e) {
            $denied = true;
        }
        $this->assertTrue($denied, 'Mapping permission alone must not expose governed definitions.');
        assign_capability('local/outcomemap:viewdefinitions', CAP_ALLOW, $roleid, $allowedcontext->id, true);
        $this->setUser($user);
        access::require_question_edit_access($allowedcontext, (int) $allowedquestion->id);
        access::require_bulk_question_access([(int) $allowedquestion->id]);
        $denied = false;
        try {
            access::require_bulk_question_access([
                (int) $allowedquestion->id, (int) $deniedquestion->id,
            ]);
        } catch (\required_capability_exception $e) {
            $denied = true;
        }
        $this->assertTrue($denied, 'A tampered bulk selection must be checked in every question context.');
    }

    /**
     * Tests write actions cannot be triggered by a GET request carrying a valid sesskey.
     */
    public function test_write_action_guard_requires_post_data(): void {
        $originalpost = $_POST;
        try {
            $_POST = [];
            $denied = false;
            try {
                access::require_post_action();
            } catch (\moodle_exception $e) {
                $denied = $e->errorcode === 'postrequired';
            }
            $this->assertTrue($denied);
            $_POST = ['sesskey' => sesskey()];
            access::require_post_action();
            $this->addToAssertionCount(1);
        } finally {
            $_POST = $originalpost;
        }
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
        $this->assertCount(6, $feature->get_question_filters($view));
        $this->assertCount(1, $feature->get_bulk_actions($view));
    }
}
