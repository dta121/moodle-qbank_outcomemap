@qbank @qbank_outcomemap
Feature: Governed outcomes are managed on exact question versions
  In order to preserve traceable attainment mappings
  As an authorized question author
  I need accessible per-question, filter, bulk, and version-copy workflows

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student  | Limited   | User     | student@example.com  |
    And the following "courses" exist:
      | fullname             | shortname | category |
      | Strategic Leadership | MBA614    | 0        |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | student | MBA614 | editingteacher |
    And the following "permission overrides" exist:
      | capability                    | permission | role           | contextlevel | reference |
      | local/outcomemap:mapquestions | Prohibit   | editingteacher | Course       | MBA614    |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | MBA614    | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name       | questiontext               |
      | Test questions   | truefalse | Question 1 | Answer the first question  |
      | Test questions   | truefalse | Question 2 | Answer the second question |
    And the governed qbank outcomes "CLO1,CLO2" exist

  @javascript
  Scenario: View and filter mapped, unmapped, and needs-review questions
    Given I log in as "admin"
    And I open outcome mappings for question "Question 1"
    And I set the following fields to these values:
      | Outcome (exact approved version) | QB-BEHAT.CLO1 v1 — Governed outcome CLO1 |
      | Mapping role                     | Alignment only                           |
    And I press "Save draft"
    Then I should see "Outcome mappings for Question 1" in the "caption" "css_element"
    And "Assessed weight" "field" should exist
    And I click on "Submit for review" "link"
    When I open the question bank for course "MBA614"
    Then I should see "QB-BEHAT.CLO1 v1"
    And I should see "Needs review" in the "Question 1" "table_row"
    And I should see "No outcome mappings" in the "Question 2" "table_row"
    When I apply question bank filter "Outcome or framework" with value "CLO1"
    Then I should see "Question 1"
    And I should not see "Question 2"
    When I open the question bank for course "MBA614"
    And I apply question bank filter "Has active outcome mappings" with value "No"
    Then I should see "Question 2"
    And I should not see "Question 1"
    When I open the question bank for course "MBA614"
    And I apply question bank filter "Outcome mapping status" with value "Needs review"
    Then I should see "Question 1"
    And I should not see "Question 2"

  Scenario: Map one exact question version to several outcomes with explicit weights
    Given I log in as "admin"
    And I open outcome mappings for question "Question 1"
    And I set the following fields to these values:
      | Outcome (exact approved version) | QB-BEHAT.CLO1 v1 — Governed outcome CLO1 |
      | Mapping role                     | Assesses                                 |
      | Assessed weight                  | 0.4000000000                             |
    And I press "Save draft"
    And I set the following fields to these values:
      | Outcome (exact approved version) | QB-BEHAT.CLO2 v1 — Governed outcome CLO2 |
      | Mapping role                     | Assesses                                 |
      | Assessed weight                  | 0.6000000000                             |
    And I press "Save draft"
    Then I should see "QB-BEHAT.CLO1 v1"
    And I should see "QB-BEHAT.CLO2 v1"
    And I should see "Including pending drafts: 1.0000000000"

  Scenario: Bulk preview atomically adds an alignment mapping
    Given I log in as "admin"
    When I open bulk outcome mappings for questions "Question 1,Question 2" in course "MBA614"
    And I set the following fields to these values:
      | Bulk operation                   | Add one outcome mapping                  |
      | Outcome (exact approved version) | QB-BEHAT.CLO1 v1 — Governed outcome CLO1 |
      | Mapping role                     | Alignment only                           |
    And I press "Validate and preview"
    Then I should see "Validation preview"
    And I should see "Validated changes for each selected exact question version" in the "caption" "css_element"
    And I should see "All selected changes are valid"
    When I press "Confirm and commit all changes"
    Then I should see "Committed 2 mapping change(s) across 2 selected question(s) in one transaction."

  Scenario: Bulk preview rejects invalid assessed totals without committing
    Given I log in as "admin"
    When I open bulk outcome mappings for questions "Question 1,Question 2" in course "MBA614"
    And I set the following fields to these values:
      | Bulk operation                   | Add one outcome mapping                  |
      | Outcome (exact approved version) | QB-BEHAT.CLO1 v1 — Governed outcome CLO1 |
      | Mapping role                     | Assesses                                 |
      | Question 1, version 1            | 0.5000000000                             |
      | Question 2, version 1            | 1.0000000000                             |
    And I press "Validate and preview"
    Then I should see "The preview contains validation errors. Nothing was committed."
    And I should see "must total exactly 1.0000000000"
    And I should not see "Confirm and commit all changes"

  Scenario: Copy eligible prior-version mappings as reviewable drafts
    Given question "Question 1" has an approved "alignment only" mapping to outcome "CLO1"
    And question "Question 1" has a new version named "Question 1 v2"
    And I log in as "admin"
    When I open outcome mappings for question "Question 1 v2"
    Then I should see "Eligible mappings from question version 1"
    And I should see "Mappings eligible to copy from question version 1" in the "caption" "css_element"
    And I should see "QB-BEHAT.CLO1 v1"
    When I press "Copy these mappings as drafts"
    Then I should see "1 mapping(s) copied from the previous version as drafts."
    And I should see "Copied from question version 1, source mapping"
    And I should see "Draft"
    When I click on "Submit for review" "link"
    Then I should see "Mapping submitted for review."
    And I should see "Needs review"

  Scenario: Remediation drafts round-trip and cannot be retargeted by hidden-field tampering
    Given I log in as "admin"
    And I open outcome mappings for question "Question 1"
    And I set the following fields to these values:
      | Outcome (exact approved version) | QB-BEHAT.CLO1 v1 — Governed outcome CLO1 |
      | Mapping role                     | Remediates                               |
      | Notes                            | Question one original                    |
    And I press "Save draft"
    When I click on "Edit" "link"
    And I set the field "Notes" to "Question one updated"
    And I press "Save draft"
    Then I should see "Draft mapping updated."
    And question "Question 1" has a draft "remediates" mapping with notes "Question one updated"
    When I open outcome mappings for question "Question 2"
    And I set the following fields to these values:
      | Outcome (exact approved version) | QB-BEHAT.CLO2 v1 — Governed outcome CLO2 |
      | Mapping role                     | Teaches                                  |
      | Notes                            | Question two original                    |
    And I press "Save draft"
    And I open outcome mappings for question "Question 1"
    And I click on "Edit" "link"
    And I set the field "Notes" to "Tampered notes"
    And I replace the selected draft mapping ID with the draft for question "Question 2"
    And I press "Save draft"
    Then I should see "The submitted draft no longer matches the exact-version mapping selected for editing."
    And question "Question 1" has a draft "remediates" mapping with notes "Question one updated"
    And question "Question 2" has a draft "teaches" mapping with notes "Question two original"

  Scenario: An unauthorized user cannot view or change mappings
    Given I log in as "student"
    When I open the question bank for course "MBA614"
    Then I should see "Question 1"
    And I should not see "Manage outcome mappings"
    And I should not see "Bulk outcome mapping"
