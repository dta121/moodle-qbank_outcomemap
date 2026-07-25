@qbank @qbank_outcomemap
Feature: Question authors map governed outcomes to exact question versions
  In order to generate governed attainment evidence
  As a question author and reviewer
  I need draft outcome mappings visible and editable from the question bank

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | reviewer | Outcome   | Reviewer | reviewer@example.com |
      | readonly | Read      | Only     | readonly@example.com |
    And the following "role assigns" exist:
      | user     | role    | contextlevel | reference |
      | reviewer | manager | System       |           |
    And the following "courses" exist:
      | fullname             | shortname | category |
      | Strategic Leadership | MBA614    | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | readonly | MBA614 | editingteacher |
    And the following "permission overrides" exist:
      | capability                           | permission | role           | contextlevel | reference |
      | local/outcomemap:viewdefinitions     | Prevent    | editingteacher | Course       | MBA614    |
    And the following "activities" exist:
      | activity | name         | course | idnumber |
      | qbank    | MBA614 bank  | MBA614 | qbank1   |
    And the following "question categories" exist:
      | contextlevel    | reference | name           |
      | Activity module | qbank1    | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name       | questiontext        |
      | Test questions   | truefalse | Question 1 | Answer the question |

  Scenario: Question-bank controls are hidden without definition-read access
    Given I log in as "readonly"
    When I am on the "MBA614 bank" "core_question > question bank" page
    Then I should not see "Manage outcome mappings"
    And I should not see "Has outcome mappings"
    And I should not see "Add alignment outcome to selected questions"

  Scenario: The outcome column shows unmapped questions and the editor creates a draft
    Given I log in as "admin"
    And I navigate to "Plugins > Learning outcome mapping > Frameworks and outcomes" in site administration
    And I click on "Add framework" "link"
    And I set the following fields to these values:
      | Code       | MBA614-FW       |
      | Name       | MBA614 outcomes |
      | Owner type | Institution     |
    And I press "Save changes"
    And I click on "Submit for review" "link" in the "MBA614-FW" "table_row"
    And I log out
    And I log in as "reviewer"
    And I navigate to "Plugins > Learning outcome mapping > Approval queue" in site administration
    And I click on "Approve" "link" in the "MBA614-FW" "table_row"
    And I press "Continue"
    And I log out
    And I log in as "admin"
    And I navigate to "Plugins > Learning outcome mapping > Frameworks and outcomes" in site administration
    And I click on "Add outcome" "link"
    And I set the following fields to these values:
      | Framework      | MBA614-FW                        |
      | Code           | CLO1                             |
      | Statement      | Evaluate strategic alternatives. |
      | Effective from | 1 January 2026, 00:00            |
    And I press "Save changes"
    And I click on "Submit for review" "link" in the "CLO1" "table_row"
    And I log out
    And I log in as "reviewer"
    And I navigate to "Plugins > Learning outcome mapping > Approval queue" in site administration
    And I click on "Approve" "link" in the "CLO1" "table_row"
    And I press "Continue"
    And I log out
    When I log in as "admin"
    And I am on the "MBA614 bank" "core_question > question bank" page
    Then I should see "No outcome mappings"
    When I click on "Manage outcome mappings" "link" in the "Question 1" "table_row"
    And I set the following fields to these values:
      | Outcome (exact approved version) | MBA614-FW.CLO1 v1 — Evaluate strategic alternatives. |
      | Mapping role                     | Alignment only                                        |
    And I press "Add draft mapping"
    Then I should see "Draft mapping created."
    And I should see "MBA614-FW.CLO1 v1"
    And I should see "Alignment only"
    And "Submit for review" "button" should exist
    And "Submit for review" "link" should not exist
    And "Delete" "button" should exist
    And I should see "Outcome mappings for Question 1, exact question version 1"
    When I click on "Delete" "button"
    Then I should see "Delete this draft outcome mapping? This cannot be undone."
    When I press "Cancel"
    Then I should see "MBA614-FW.CLO1 v1"
    When I click on "Submit for review" "button"
    Then I should see "Mapping submitted for review."
    And I log out
    And I log in as "reviewer"
    And I navigate to "Plugins > Learning outcome mapping > Approval queue" in site administration
    And I click on "Approve" "link" in the "MBA614-FW.CLO1 / alignment_only" "table_row"
    And I press "Continue"
    Then I should see "The record was approved."
