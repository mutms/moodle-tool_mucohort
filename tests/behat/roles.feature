@tool @tool_mucohort @javascript @MuTMS
Feature: Roles of advanced cohort members
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | user1    | User      | 1        | user1@example.com |
    And the following "roles" exist:
      | shortname | name     | archetype |
      | reviewer  | Reviewer | manager   |
    And I change window size to "large"
    And I log in as "admin"
    And I navigate to "Users > Accounts > Cohort management" in site administration
    And I press "Create cohort"
    And I click on "Continue" "button" in the "dialog[open]" "css_element"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Name | Curated 1 |
    And I click on "Create cohort" "button" in the "dialog[open]" "css_element"

  Scenario: Admin assigns role to cohort members and removes it
    When I click on "Roles" "link" in the ".secondary-navigation" "css_element"
    Then I should see "No roles are assigned to cohort members."
    And I press "Add role"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Context | System   |
      | Role    | Reviewer |
    And I click on "Add role" "button" in the "dialog[open]" "css_element"
    And the following should exist in the "tool_mucohort-roles" table:
      | Role     | Context | Members with role |
      | Reviewer | System  | 0                 |

    When I press "Add role"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Context | System   |
      | Role    | Reviewer |
    And I click on "Add role" "button" in the "dialog[open]" "css_element"
    Then I should see "This role is already assigned to cohort members in this context." in the "dialog[open]" "css_element"
    And I click on "Cancel" "button" in the "dialog[open]" "css_element"

    When I click on "Members" "link" in the ".secondary-navigation" "css_element"
    And I press "Add members"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Users | User 1 |
    And I click on "Add members" "button" in the "dialog[open]" "css_element"
    And I click on "Roles" "link" in the ".secondary-navigation" "css_element"
    Then the following should exist in the "tool_mucohort-roles" table:
      | Role     | Members with role |
      | Reviewer | 1                 |

    When I click on "Remove role" "link" in the "Reviewer" "table_row"
    And I should see "role assignments to be removed: 1" in the "dialog[open]" "css_element"
    And I click on "Remove role" "button" in the "dialog[open]" "css_element"
    Then I should see "No roles are assigned to cohort members."
    And I press "Add role"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Role | Reviewer |
    And I click on "Add role" "button" in the "dialog[open]" "css_element"
    And the following should exist in the "tool_mucohort-roles" table:
      | Role     | Context                |
      | Reviewer | Cohort context: System |
