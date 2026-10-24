@tool @tool_mucohort @javascript @MuTMS
Feature: External database rules of rule-based cohorts
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | user1    | User      | 1        | user1@example.com |
      | user2    | User      | 2        | user2@example.com |
    And the following "tool_mulib > extdb_servers" exist:
      | name          |
      | Test server 1 |
    And the following "tool_mulib > extdb_queries" exist:
      | name         | server        | component     | type | contextlevel | reference | sqlquery                                                   |
      | Test query 1 | Test server 1 | tool_mucohort | rule | System       |           | SELECT username FROM {user} WHERE username IN ('user1') |
    And I change window size to "large"
    And I log in as "admin"
    And I navigate to "Users > Accounts > Cohort management" in site administration
    And I press "Create cohort"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Type | rules |
    And I click on "Continue" "button" in the "dialog[open]" "css_element"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Name | Rules 1 |
    And I click on "Create cohort" "button" in the "dialog[open]" "css_element"

  Scenario: Admin uses external database query in cohort rules
    When I click on "Active rules" "link" in the ".secondary-navigation" "css_element"
    And I press "Modify rules"
    And I press "Add include rule"
    And I set the following muform fields:
      | Rule type | tool_mucohort:extdb |
    And I press "Continue"
    And I set the following muform fields:
      | External database query | Test query 1 |
      | Maximum age of data     | 3600         |
    And I press "Continue"
    Then I should see "Returned by external database query: Test query 1"
    And I should not see "Data from external database is not fetched yet"
    And I should see "Users matching this rule"
    And I press "Add rule"
    And I press "Approve rules"
    And I click on "Approve rules" "button" in the "dialog[open]" "css_element"
    And I press "Run queued sync now"
    And I should see "Sync result: Finished, added: 1, removed: 0"
    And I press "Continue"
    And I click on "Members" "link" in the ".secondary-navigation" "css_element"
    And I should see "User 1" in the "reportbuilder-table" "table"
    And I should not see "User 2" in the "reportbuilder-table" "table"
