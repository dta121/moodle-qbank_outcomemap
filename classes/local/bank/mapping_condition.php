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

namespace qbank_outcomemap\local\bank;

use core_question\local\bank\condition;
use core_question\local\bank\view;
use local_outcomemap\api\question_mapping_filters;

/**
 * Common capability-aware adapter from Moodle filters to the local public API.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class mapping_condition extends condition {
    /** @var array Values currently selected in the filter UI. */
    protected $selectedvalues = [];

    /** Return the public API criterion handled by this condition. */
    abstract protected static function criterion(): string;

    /**
     * Build through the public service using authoritative view contexts.
     *
     * @param view|null $qbank Question-bank view, or null for class discovery.
     */
    public function __construct(?view $qbank = null) {
        if ($qbank === null) {
            return;
        }
        $filters = (array) ($qbank->get_pagevars('filter') ?? []);
        $this->filter = static::get_filter_from_list($filters);
        $this->selectedvalues = $this->filter['values'] ?? [];
        if ($this->filter) {
            [$this->where, $this->params] = question_mapping_filters::build(
                static::criterion(),
                static::normalize_filter($this->filter),
                $qbank->contexts->all()
            );
        }
    }

    /**
     * Translate UI-safe values to the canonical values owned by local_outcomemap.
     *
     * Moodle's generic data-filter control converts option values to integers.
     * Finite string-valued filters override this method to restore their canonical values.
     */
    protected static function normalize_filter(array $filter): array {
        return $filter;
    }

    /**
     * Support core's static filter resolution while failing closed for callers
     * without the system-level read capability.
     *
     * Interactive qbank views use the authoritative contexts above.
     *
     * @param array $filter Core filter data.
     * @return array SQL predicate and named parameters.
     */
    public static function build_query_from_filter(array $filter): array {
        return question_mapping_filters::build(
            static::criterion(),
            static::normalize_filter($filter),
            [\context_system::instance()]
        );
    }

    /** Explicit compatibility default required by the accepted ADR. */
    public function get_initial_values() {
        return [];
    }

    /** Explicit compatibility default required by the accepted ADR. */
    public function get_filteroptions(): \stdClass {
        return (object) [];
    }
}
