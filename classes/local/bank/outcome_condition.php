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

use local_outcomemap\api\question_mapping_filters;

/**
 * Filter exact question versions by governed outcome or framework text.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class outcome_condition extends mapping_condition {
    /** Maximum keyword clauses accepted from one filter URL. */
    private const MAX_TERMS = 20;
    /** Maximum length of one plain-text keyword. */
    private const MAX_TERM_LENGTH = 100;
    /**
     * Return the public filter criterion.
     *
     * @return string
     */
    protected static function criterion(): string {
        return question_mapping_filters::OUTCOME;
    }

    /**
     * Bound free-text filter input before it expands into SQL LIKE clauses.
     *
     * @param array $filter Core filter data.
     * @return array Normalized, bounded filter data.
     */
    protected static function normalize_filter(array $filter): array {
        $values = [];
        $submitted = array_slice((array) ($filter['values'] ?? []), 0, self::MAX_TERMS);
        foreach ($submitted as $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $value = \core_text::substr((string) $value, 0, self::MAX_TERM_LENGTH);
            $value = trim(clean_param($value, PARAM_TEXT));
            if ($value === '') {
                continue;
            }
            $value = \core_text::substr($value, 0, self::MAX_TERM_LENGTH);
            $values[$value] = $value;
            if (count($values) === self::MAX_TERMS) {
                break;
            }
        }
        $filter['values'] = array_values($values);
        return $filter;
    }

    /**
     * Return the stable core condition key.
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
     * Return the core keyword filter class.
     *
     * @return string
     */
    public function get_filter_class() {
        return 'core/datafilter/filtertypes/keyword';
    }

    /**
     * Return normalized values for the filter UI.
     *
     * @return \stdClass[]
     */
    public function get_initial_values() {
        $values = [];
        foreach ($this->selectedvalues as $value) {
            $values[] = (object) [
                'value' => $value,
                'title' => $value,
                'selected' => true,
            ];
        }
        return $values;
    }
}
