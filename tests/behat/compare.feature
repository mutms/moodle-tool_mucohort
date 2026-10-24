@tool @tool_mucohort @javascript @MuTMS
Feature: Compare cohort membership with other cohorts
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | user1    | User      | 1        | user1@example.com |
      | user2    | User      | 2        | user2@example.com |
      | user3    | User      | 3        | user3@example.com |
    And the following "cohorts" exist:
      | name     | idnumber | contextlevel | reference |
      | Target   | TRG      | System       |           |
      | Source 1 | SRC1     | System       |           |
      | Source 2 | SRC2     | System       |           |
    And the following "cohort members" exist:
      | user  | cohort |
      | user1 | TRG    |
      | user2 | TRG    |
      | user2 | SRC1   |
      | user3 | SRC2   |

  Scenario: Admin compares a cohort and replaces its members
    Given I change window size to "large"
    And I log in as "admin"
    And I visit "/admin/tool/mucohort/index.php"
    And I click on "Target" "link" in the "reportbuilder-table" "table"
    And I click on "Members" "link" in the ".secondary-navigation" "css_element"
    And I click on "Cohort actions" "button"
    And I click on "Compare with cohorts" "link"
    When I set the following muform fields:
      | Other cohorts | Source 1, Source 2 |
    And I press "Compare"
    Then I should see "Missing (1)"
    And I should see "Shared (1)"
    And I should see "Extra (1)"
    And I should see "User 3" in the "reportbuilder-table" "table"

    When I press "Add all missing"
    Then I should see "Users to be added to this cohort: 1" in the "dialog[open]" "css_element"
    And I click on "Add all missing" "button" in the "dialog[open]" "css_element"
    And I should see "Missing (0)"
    And I should see "Shared (2)"

    When I press "Remove all extra"
    Then I should see "Users to be removed from this cohort: 1" in the "dialog[open]" "css_element"
    And I click on "Remove all extra" "button" in the "dialog[open]" "css_element"
    And I should see "Extra (0)"
    And I should see "Shared (2)"
