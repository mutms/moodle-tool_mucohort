@tool @tool_mucohort @javascript @MuTMS
Feature: Management of curated cohorts
  Background:
    Given the following "categories" exist:
      | name  | category | idnumber |
      | Cat 1 | 0        | CAT1     |
    And the following "cohorts" exist:
      | name     | idnumber | contextlevel | reference |
      | Cohort 1 | CH1      | System       |           |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following "roles" exist:
      | name            | shortname |
      | Curated manager | cmanager  |
    And the following "permission overrides" exist:
      | capability                     | permission | role     | contextlevel | reference |
      | moodle/cohort:view             | Allow      | cmanager | Category     | CAT1      |
      | tool/mucohort:manage           | Allow      | cmanager | Category     | CAT1      |
      | moodle/category:viewcourselist | Allow      | cmanager | Category     | CAT1      |
    And the following "role assigns" exist:
      | user     | role     | contextlevel | reference |
      | manager1 | cmanager | Category     | CAT1      |

  Scenario: Admin may create, update and delete curated cohorts
    Given I change window size to "large"
    And I log in as "admin"
    And I navigate to "Users > Accounts > Cohort management" in site administration
    When I press "Create cohort"
    And I click on "Continue" "button" in the "dialog[open]" "css_element"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Name                      | Curated 1 |
      | Cohort ID                 | CUR1      |
      | Category                  | Cat 1     |
      | Maximum number of members | 20        |
      | Candidate cohort          | Cohort 1  |
    And I click on "Create cohort" "button" in the "dialog[open]" "css_element"
    Then I should see "Curated 1" in the "page-header" "region"
    And I should see "Curated"
    And I should see "Cat 1"
    And I should see "20"
    And I should see "Cohort 1"

    When I click on "Edit" "button"
    And the following muform fields in the "dialog[open]" "css_element" match:
      | Maximum number of members | 20 |
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Name                      | Curated 11 |
      | Maximum number of members | 0          |
    And I click on "Update cohort" "button" in the "dialog[open]" "css_element"
    Then I should see "Curated 11" in the "page-header" "region"
    And I should see "Closed to new members"

    When I click on "Cohort actions" "button"
    And I click on "Delete cohort" "link"
    And I click on "Delete cohort" "button" in the "dialog[open]" "css_element"
    Then I should not see "Curated 11"

  Scenario: Category manager with advanced cohort permission may create only advanced cohorts
    Given I change window size to "large"
    And I log in as "manager1"
    And I am on course index
    And I follow "Cat 1"
    And I navigate to "Cohort management" in current page administration
    When I press "Create cohort"
    Then I should see "Rule-based" in the "dialog[open]" "css_element"
    And I should not see "Standard" in the "dialog[open]" "css_element"
    And I click on "Continue" "button" in the "dialog[open]" "css_element"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Name | Curated 2 |
    And I click on "Create cohort" "button" in the "dialog[open]" "css_element"
    And I should see "Curated 2" in the "page-header" "region"
    And I should see "Curated"

  Scenario: Admin may convert standard cohorts to curated and back without member changes
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | user1    | User      | 1        | user1@example.com |
    And the following "cohort members" exist:
      | user  | cohort |
      | user1 | CH1    |
    And I change window size to "large"
    And I log in as "admin"
    And I navigate to "Users > Accounts > Cohort management" in site administration
    And I click on "Cohort 1" "link" in the "reportbuilder-table" "table"
    When I click on "Cohort actions" "button"
    And I click on "Convert cohort type" "link"
    Then I should see "Cohort members are not changed" in the "dialog[open]" "css_element"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | New type | curated |
    And I click on "Convert" "button" in the "dialog[open]" "css_element"
    And I should see "Curated"
    And I should see "Unlimited"
    And I click on "Members" "link" in the ".secondary-navigation" "css_element"
    And I should see "User 1" in the "reportbuilder-table" "table"
    And I click on "Overview" "link" in the ".secondary-navigation" "css_element"

    When I click on "Cohort actions" "button"
    And I click on "Convert cohort type" "link"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | New type | standard |
    And I click on "Convert" "button" in the "dialog[open]" "css_element"
    Then I should see "Standard"
    And I should not see "Unlimited"
    And I click on "Members" "link" in the ".secondary-navigation" "css_element"
    And I should see "User 1" in the "reportbuilder-table" "table"
