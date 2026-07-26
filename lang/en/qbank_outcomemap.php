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
$string['filtertitle'] = 'Outcome or framework';
$string['mappedfiltertitle'] = 'Has active outcome mappings';
$string['rolefiltertitle'] = 'Outcome mapping role';
$string['statusfiltertitle'] = 'Outcome mapping status';
$string['invalidweightfiltertitle'] = 'Has invalid assessed-weight total';
$string['copiedfiltertitle'] = 'Has copied mappings awaiting finalization';
$string['nomappings'] = 'No outcome mappings';
$string['managemappings'] = 'Manage outcome mappings';
$string['addmapping'] = 'Add draft mapping';
$string['editmapping'] = 'Edit draft mapping';
$string['savedraft'] = 'Save draft';
$string['reviewmessage'] = 'Review message';
$string['reviewmessage_help'] = 'Optional audit message recorded when this draft is updated or submitted for review.';
$string['reviewmessage_finalization'] = 'Finalization message';
$string['reviewmessage_finalization_help'] = 'Optional audit message recorded when this draft is updated or finalized.';
$string['deletemapping'] = 'Delete draft mapping';
$string['confirmdeletemapping'] = 'Delete this draft mapping? This action cannot be undone.';
$string['deletedfromeditor'] = 'Deleted from the question-bank mapping editor.';
$string['outcome'] = 'Outcome (exact approved version)';
$string['outcome_finalization'] = 'Outcome (exact finalized version)';
$string['assessedweight'] = 'Assessed weight';
$string['assessedweight_finalization'] = 'Assessed weight';
$string['assessedweight_help'] = 'Required for assesses mappings. All approved assessed weights for one question version must total exactly 1.0000000000. Weights are never inferred, and other roles must not carry a weight.';
$string['assessedweight_finalization_help'] = 'Required for assesses mappings. All finalized assessed weights for one question version must total exactly 1.0000000000. Weights are never inferred, and other roles must not carry a weight.';
$string['alignmentnote'] = 'Alignment, teaches, and practices mappings do not generate student attainment evidence. Only approved assesses mappings with governed weights contribute to outcome results.';
$string['alignmentnote_finalization'] = 'Alignment, teaches, and practices mappings do not generate student attainment evidence. Only finalized assesses mappings with governed weights contribute to outcome results.';
$string['approvedtotal'] = 'Approved assessed total: {$a}';
$string['finalizedtotal'] = 'Finalized assessed total: {$a}';
$string['combinedtotal'] = 'Including pending drafts: {$a}';
$string['weighttotalinvalid'] = 'The assessed weights are not currently valid for approval.';
$string['weighttotalinvalid_finalization'] = 'The assessed weights are not currently valid for finalization.';
$string['mappingcreated'] = 'Draft mapping created.';
$string['mappingupdated'] = 'Draft mapping updated.';
$string['invalidmappingedit'] = 'The submitted draft no longer matches the exact-version mapping selected for editing. Reload the page and try again.';
$string['mappingdeleted'] = 'Draft mapping deleted.';
$string['mappingsubmitted'] = 'Mapping submitted for review.';
$string['mappingfinalized'] = 'Mapping finalized.';
$string['mappingscopied'] = '{$a} mapping(s) copied from the previous version as drafts.';
$string['copiedprovenance'] = 'Copied from question version {$a->questionversion}, source mapping {$a->mappinguuid} v{$a->mappingversion}';
$string['copiedfromqbank'] = 'Explicitly copied from the prior question version in the question-bank mapping editor.';
$string['copyfromprevious'] = 'Copy these mappings as drafts';
$string['copypreviewheading'] = 'Eligible mappings from question version {$a}';
$string['copypreviewnote'] = '{$a} approved mapping(s) can be copied. Copies are new drafts with source provenance and must be reviewed before approval.';
$string['copypreviewnote_finalization'] = '{$a} finalized mapping(s) can be copied. Copies are new drafts with source provenance and must be explicitly finalized.';
$string['copyalreadycomplete'] = 'Every eligible mapping from the immediately preceding version is already represented on this exact version.';
$string['copynothingeligible'] = 'No eligible mappings remain on the immediately preceding version. Nothing was copied.';
$string['mappingtablecaption'] = 'Outcome mappings for {$a}';
$string['copypreviewtablecaption'] = 'Mappings eligible to copy from question version {$a}';
$string['backtoquestionbank'] = 'Back to the question bank';

$string['bulkmaptitle'] = 'Bulk outcome mapping';
$string['bulkmapnote'] = 'Review every selected exact question version before committing. Assessed mappings require explicit weights; weights are never inferred or split equally.';
$string['bulkmapnote_finalization'] = 'Check every selected exact question version before committing. Assessed mappings require explicit weights; weights are never inferred or split equally.';
$string['bulkmapcount'] = 'Bulk operation for {$a} selected question(s)';
$string['bulkmapresult'] = 'Committed {$a->affected} mapping change(s) across {$a->questions} selected question(s) in one transaction.';
$string['bulknoquestions'] = 'No questions were selected.';
$string['bulkoperation'] = 'Bulk operation';
$string['bulkoperation_add'] = 'Add one outcome mapping';
$string['bulkoperation_change_role'] = 'Change the role of selected drafts';
$string['bulkoperation_delete_drafts'] = 'Delete selected drafts';
$string['bulkoperation_submit_drafts'] = 'Submit selected drafts for review';
$string['bulkoperation_finalize_drafts'] = 'Finalize selected drafts';
$string['bulkquestionweights'] = 'Explicit per-question assessed weights';
$string['bulkquestionweights_help'] = 'Used only when adding an assesses mapping. Enter one explicit weight for every selected question; the resulting assessed total for each exact version must be 1.0000000000.';
$string['bulkquestionweight'] = '{$a->name}, version {$a->version}';
$string['bulkdraftselection'] = 'Selected draft mappings';
$string['bulkdraftlabel'] = '{$a->question}, version {$a->version}: {$a->outcome} — {$a->role}';
$string['bulkmappingweight'] = '{$a->question}, version {$a->version}: explicit assessed weight for {$a->outcome} ({$a->role})';
$string['bulkmappingrequired'] = 'Select at least one draft mapping for this operation.';
$string['bulknodrafts'] = 'The selected questions have no draft mappings available for role changes, deletion, or submission.';
$string['bulknodrafts_finalization'] = 'The selected questions have no draft mappings available for role changes, deletion, or finalization.';
$string['bulkpreview'] = 'Validate and preview';
$string['bulkpreviewheading'] = 'Validation preview';
$string['bulkpreviewtablecaption'] = 'Validated changes for each selected exact question version';
$string['bulkpreviewhaserrors'] = 'The preview contains validation errors. Nothing was committed.';
$string['bulkpreviewvalid'] = 'All selected changes are valid. Confirm to commit the complete operation atomically.';
$string['bulkconfirmnote'] = 'This commits every listed change in one transaction. If any validation check fails, no mapping is changed.';
$string['bulkcommit'] = 'Confirm and commit all changes';
$string['bulkexactversion'] = 'Exact question version';
$string['bulkproposedaction'] = 'Proposed action';
$string['bulkversionnumber'] = 'Version {$a}';
$string['bulkpreview_add'] = 'Add {$a->outcome} as {$a->role}; weight {$a->weight}';
$string['bulkpreview_change_role'] = 'Change mapping {$a->id} ({$a->outcome}) to {$a->role}; weight {$a->weight}';
$string['bulkpreview_delete_drafts'] = 'Delete draft mapping {$a->id} ({$a->outcome})';
$string['bulkpreview_submit_drafts'] = 'Submit draft mapping {$a->id} ({$a->outcome}, {$a->role})';
$string['bulkpreview_finalize_drafts'] = 'Finalize draft mapping {$a->id} ({$a->outcome}, {$a->role})';
