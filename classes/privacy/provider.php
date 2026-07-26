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

namespace qbank_outcomemap\privacy;

use core_privacy\local\metadata\null_provider;

/**
 * Privacy provider for the presentation-only question-bank companion.
 *
 * Mapping records, governance actors, and audit history are owned by
 * local_outcomemap. This plugin stores no data of its own.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements null_provider {
    /**
     * Explain why this component has no privacy metadata of its own.
     *
     * @return string Language-string identifier.
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
