@tool @tool_mucohort @javascript @MuTMS
Feature: Candidate cohort, blocked and forced users are respected in curated cohort UIs
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | user1    | User      | 1        | user1@example.com |
      | user2    | User      | 2        | user2@example.com |
      | user3    | User      | 3        | user3@example.com |
      | user4    | User      | 4        | user4@example.com |
      | user5    | User      | 5        | user5@example.com |
    And the following "cohorts" exist:
      | name      | idnumber | contextlevel | reference |
      | Candidate | CAND     | System       |           |
      | Source    | SRC      | System       |           |
    And the following "cohort members" exist:
      | user  | cohort |
      | user1 | CAND   |
      | user2 | CAND   |
      | user3 | CAND   |
      | user4 | CAND   |
      | user1 | SRC    |
      | user2 | SRC    |
      | user5 | SRC    |
    And I change window size to "large"
    And I log in as "admin"
    And I visit "/admin/tool/mucohort/index.php"
    And I press "Create cohort"
    And I click on "Continue" "button" in the "dialog[open]" "css_element"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Name             | Curated 1 |
      | Candidate cohort | Candidate |
    And I click on "Create cohort" "button" in the "dialog[open]" "css_element"
    And I click on "Exceptions" "link" in the ".secondary-navigation" "css_element"
    And I press "Add exceptions"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Users     | User 2 |
      | Exception | 2      |
    And I click on "Add exceptions" "button" in the "dialog[open]" "css_element"
    And I press "Add exceptions"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Users     | User 3 |
      | Exception | 1      |
    And I click on "Add exceptions" "button" in the "dialog[open]" "css_element"

  Scenario: Members tab respects candidate cohort, blocked and forced users
    When I click on "Members" "link" in the ".secondary-navigation" "css_element"
    Then I should see "User 3" in the "reportbuilder-table" "table"
    And "Remove member" "link" should not exist in the "User 3" "table_row"
    When I press "Add members"
    And I type "User 5" into the "userids" muform search field
    Then I should see "No results" in the "dialog[open]" "css_element"
    And I type "User 2" into the "userids" muform search field
    And I should see "No results" in the "dialog[open]" "css_element"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Users | User 4 |
    And I click on "Add members" "button" in the "dialog[open]" "css_element"
    Then "Remove member" "link" should exist in the "User 4" "table_row"
    And "Remove member" "link" should not exist in the "User 3" "table_row"

  Scenario: Compare page respects candidate cohort, blocked and forced users
    When I visit "/admin/tool/mucohort/index.php"
    And I click on "Curated 1" "link" in the "reportbuilder-table" "table"
    And I click on "Members" "link" in the ".secondary-navigation" "css_element"
    And I press "Add members"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Users | User 4 |
    And I click on "Add members" "button" in the "dialog[open]" "css_element"
    And I click on "Cohort actions" "button"
    And I click on "Compare with cohorts" "link"
    And I set the following muform fields:
      | Other cohorts | Source |
    And I press "Compare"
    Then I should see "Missing (3)"
    And I should see "Extra (2)"
    And "Add to cohort" "link" should exist in the "User 1" "table_row"
    And "Add to cohort" "link" should not exist in the "User 2" "table_row"
    And "Add to cohort" "link" should not exist in the "User 5" "table_row"

    When I press "Add all missing"
    Then I should see "Missing users: 3" in the "dialog[open]" "css_element"
    And I should see "Users that cannot be added because of the candidate cohort, blocked users or tenant restrictions: 2" in the "dialog[open]" "css_element"
    And I should see "Users to be added to this cohort: 1" in the "dialog[open]" "css_element"
    And I click on "Cancel" "button" in the "dialog[open]" "css_element"

    When I click on "Extra (2)" "link"
    Then "Remove from cohort" "link" should exist in the "User 4" "table_row"
    And "Remove from cohort" "link" should not exist in the "User 3" "table_row"
    When I press "Remove all extra"
    Then I should see "Forced users that are kept: 1" in the "dialog[open]" "css_element"
    And I should see "Users to be removed from this cohort: 1" in the "dialog[open]" "css_element"
    And I click on "Cancel" "button" in the "dialog[open]" "css_element"

    When I press "Remove from cohort" action in the "User 4" report row
    And I click on "Remove from cohort" "button" in the "dialog[open]" "css_element"
    Then I should see "Extra (1)"
    When I click on "Missing (3)" "link"
    And I press "Add to cohort" action in the "User 1" report row
    And I click on "Add to cohort" "button" in the "dialog[open]" "css_element"
    Then I should see "Missing (2)"
    And I should see "Shared (1)"
