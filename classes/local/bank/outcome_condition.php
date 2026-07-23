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

use core\output\datafilter;
use core_question\local\bank\condition;
use core_question\local\bank\view;

/**
 * Filter questions by governed outcome codes mapped to the exact version.
 *
 * Values are outcome-code fragments such as `CLO1` or `MBA.CLO1`. The filter
 * reads the system-of-record table through parameterized SQL only; all
 * mutations stay behind the local_outcomemap services.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class outcome_condition extends condition {
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
        return 'outcomemap';
    }

    /**
     * Return the filter title.
     *
     * @return string
     */
    public function get_title() {
        return get_string('filtertitle', 'qbank_outcomemap');
    }

    /**
     * Use the core keyword filter UI; Moodle 4.5 requires an explicit class.
     *
     * @return string
     */
    public function get_filter_class() {
        return 'core/datafilter/filtertypes/keyword';
    }

    /**
     * Build the WHERE fragment for the selected outcome-code fragments.
     *
     * @param array $filter Filter properties including values and jointype.
     * @return array WHERE SQL and named parameters.
     */
    public static function build_query_from_filter(array $filter): array {
        global $DB;
        $values = [];
        foreach ($filter['values'] ?? [] as $value) {
            $value = trim(clean_param((string) $value, PARAM_TEXT));
            if ($value !== '') {
                $values[] = $value;
            }
        }
        if (!$values) {
            return ['', []];
        }
        $jointype = (int) ($filter['jointype'] ?? datafilter::JOINTYPE_ANY);
        $fullcode = $DB->sql_concat('qbomf.code', "'.'", 'qbomi.code');
        $fragments = [];
        $params = [];
        foreach (array_values($values) as $index => $value) {
            $codeparam = 'qbomcode' . $index;
            $fullparam = 'qbomfull' . $index;
            $params[$codeparam] = '%' . $DB->sql_like_escape($value) . '%';
            $params[$fullparam] = '%' . $DB->sql_like_escape($value) . '%';
            $exists = 'EXISTS (SELECT 1
                                 FROM {local_outcomemap_qmap} qbomm
                                 JOIN {local_outcomemap_itemver} qbomv ON qbomv.id = qbomm.itemverid
                                 JOIN {local_outcomemap_item} qbomi ON qbomi.id = qbomv.itemid
                                 JOIN {local_outcomemap_fw} qbomf ON qbomf.id = qbomi.frameworkid
                                WHERE qbomm.questionversionid = qv.id
                                  AND (' . $DB->sql_like('qbomi.code', ':' . $codeparam, false)
                                  . ' OR ' . $DB->sql_like($fullcode, ':' . $fullparam, false) . '))';
            $fragments[] = $jointype === datafilter::JOINTYPE_NONE ? 'NOT ' . $exists : $exists;
        }
        $glue = $jointype === datafilter::JOINTYPE_ANY ? ' OR ' : ' AND ';
        return ['(' . implode($glue, $fragments) . ')', $params];
    }
}
