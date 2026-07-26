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
 * Filter exact question versions by one or more mapping roles.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class role_condition extends mapping_condition {
    /** Canonical values indexed by the integer values required by Moodle's generic data filter. */
    private const VALUES = [
        'teaches',
        'practices',
        'assesses',
        'alignment_only',
        'remediates',
    ];

    protected static function criterion(): string {
        return question_mapping_filters::ROLE;
    }

    protected static function normalize_filter(array $filter): array {
        $values = [];
        foreach ($filter['values'] ?? [] as $value) {
            if ((is_int($value) || ctype_digit((string) $value))
                    && array_key_exists((int) $value, self::VALUES)) {
                $values[] = self::VALUES[(int) $value];
            } else if (in_array($value, self::VALUES, true)) {
                // Preserve compatibility with canonical values in saved URLs and server-side callers.
                $values[] = $value;
            }
        }
        $filter['values'] = $values;
        return $filter;
    }

    public static function get_condition_key() {
        return 'outcomemaprole';
    }

    public function get_title() {
        return get_string('rolefiltertitle', 'qbank_outcomemap');
    }

    public function get_filter_class() {
        return null;
    }

    public function allow_custom() {
        return false;
    }

    public function get_initial_values() {
        $values = [];
        foreach (self::VALUES as $index => $role) {
            $values[] = (object) [
                'value' => $index,
                'title' => get_string('mappingrole_' . $role, 'local_outcomemap'),
                'selected' => in_array($index, $this->selectedvalues, true)
                    || in_array((string) $index, $this->selectedvalues, true)
                    || in_array($role, $this->selectedvalues, true),
            ];
        }
        return $values;
    }
}
