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
 * Cross-version binary selector for mapping filters.
 *
 * @module     qbank_outcomemap/datafilter/filtertypes/binary
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Binary from 'core/datafilter/filtertypes/binary';

export default class extends Binary {
    /**
     * Add the value selector, normalizing Moodle 4.5's missing initial values.
     *
     * @param {Array} initialValues The default value for the filter.
     * @return {Promise}
     */
    async addValueSelector(initialValues = []) {
        return super.addValueSelector(initialValues);
    }
}
