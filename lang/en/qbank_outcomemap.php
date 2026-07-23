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

/**
 * English language strings for qbank_outcomemap.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Outcome mapping';
$string['privacy:metadata'] = 'The outcome mapping question bank plugin does not store any personal data. All mapping data is owned by the local_outcomemap plugin.';

$string['outcomescolumn'] = 'Outcomes';
$string['filtertitle'] = 'Outcome';
$string['mappedfiltertitle'] = 'Has outcome mappings';
$string['nomappings'] = 'No outcome mappings';
$string['managemappings'] = 'Manage outcome mappings';
$string['addmapping'] = 'Add draft mapping';
$string['outcome'] = 'Outcome (exact approved version)';
$string['assessedweight'] = 'Assessed weight';
$string['assessedweight_help'] = 'Required for assesses mappings. All approved assessed weights for one question version must total exactly 1.0000000000. Weights are never inferred, and other roles must not carry a weight.';
$string['alignmentnote'] = 'Alignment, teaches, and practices mappings do not generate student attainment evidence. Only approved assesses mappings with governed weights contribute to outcome results.';
$string['approvedtotal'] = 'Approved assessed total: {$a}';
$string['combinedtotal'] = 'Including pending drafts: {$a}';
$string['weighttotalinvalid'] = 'The assessed weights are not currently valid for approval.';
$string['mappingcreated'] = 'Draft mapping created.';
$string['mappingdeleted'] = 'Draft mapping deleted.';
$string['mappingsubmitted'] = 'Mapping submitted for review.';
$string['mappingscopied'] = '{$a} mapping(s) copied from the previous version as drafts.';
$string['copyfromprevious'] = 'Copy mappings from previous version as drafts';
$string['backtoquestionbank'] = 'Back to the question bank';

$string['bulkmaptitle'] = 'Add alignment outcome to selected questions';
$string['bulkmapnote'] = 'This adds one alignment-only draft mapping to each selected question version. Assessed mappings and weights must be managed per question and are never assigned in bulk.';
$string['bulkmapsubmit'] = 'Add alignment mappings';
$string['bulkmapcount'] = 'Adding an alignment outcome to {$a} selected question(s)';
$string['bulkmapresult'] = 'Created {$a->created} draft mapping(s); skipped {$a->skipped}.';
$string['bulknoquestions'] = 'No questions were selected.';
