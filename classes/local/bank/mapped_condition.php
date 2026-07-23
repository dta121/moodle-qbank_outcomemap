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

/**
 * Yes/no filter for questions whose exact version carries outcome mappings.
 *
 * Selecting "No" surfaces unmapped questions for curriculum-coverage review.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mapped_condition extends condition {
    /**
     * Constructor tolerating views whose filters are not initialised.
     *
     * @param view|null $qbank Question bank view.
     */
    public function __construct(?view $qbank = null) {
        if ($qbank !== null && $qbank->get_pagevars('filter') !== null) {
            parent::__construct($qbank);
        }
    }

    /**
     * Return the stable condition key.
     *
     * @return string
     */
    public static function get_condition_key() {
        return 'outcomemapmapped';
    }

    /**
     * Return the filter title.
     *
     * @return string
     */
    public function get_title() {
        return get_string('mappedfiltertitle', 'qbank_outcomemap');
    }

    /**
     * Use the core yes/no filter UI.
     *
     * @return string
     */
    public function get_filter_class() {
        return 'core/datafilter/filtertypes/binary';
    }

    /**
     * A yes/no filter takes exactly one value.
     *
     * @return bool
     */
    public function allow_multiple() {
        return false;
    }

    /**
     * The binary options are fixed.
     *
     * @return bool
     */
    public function allow_custom() {
        return false;
    }

    /**
     * Build the WHERE fragment for the yes/no selection.
     *
     * @param array $filter Filter properties including values.
     * @return array WHERE SQL and named parameters.
     */
    public static function build_query_from_filter(array $filter): array {
        $values = $filter['values'] ?? [];
        if ($values === []) {
            return ['', []];
        }
        $exists = 'EXISTS (SELECT 1 FROM {local_outcomemap_qmap} qbommap
                            WHERE qbommap.questionversionid = qv.id)';
        return [(int) reset($values) === 1 ? $exists : 'NOT ' . $exists, []];
    }
}
