@tool @tool_mucohort @javascript @MuTMS
Feature: Draft rules of rule-based cohorts
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                | department |
      | user1    | User      | 1        | user1@example.com    | Sales      |
      | user2    | User      | 2        | user2@example.com    | HR         |
      | user3    | User      | 3        | user3@example.org    | Sales team |
    And the following "cohorts" exist:
      | name  | idnumber | contextlevel | reference |
      | Staff | STAFF    | System       |           |
    And the following "cohort members" exist:
      | user  | cohort |
      | user1 | STAFF  |
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
    And I should see "Rules 1" in the "page-header" "region"
    And I should see "Inactive"

  Scenario: Admin edits, approves and discards draft rules
    When I click on "Active rules" "link" in the ".secondary-navigation" "css_element"
    Then I should see "No rules were approved yet, membership is not synchronised."
    And "Add include rule" "button" should not exist
    And "Draft rules" "link" should not exist in the ".secondary-navigation" "css_element"
    And I press "Modify rules"
    And I should see "Draft rules are waiting for approval"
    And I should see "No include rules, nobody matches."
    And "Approve rules" "button" should not exist

    When I press "Add include rule"
    And I set the following muform fields:
      | Rule type | tool_mucohort:userfield |
    And I press "Continue"
    And I set the following muform fields:
      | Field        | department     |
      | textoperator | startswith     |
      | textvalues   | Sales\nHR      |
    And I press "Continue"
    Then I should see "Department starts with \"Sales\" or \"HR\""
    And I should see "Users matching this rule"
    And I press "Add rule"
    And I should see "Draft rules are waiting for approval"
    And I should see "Department starts with \"Sales\" or \"HR\""

    When I press "Add exclude rule"
    And I set the following muform fields:
      | Rule type | tool_mucohort:cohort |
    And I press "Continue"
    And I set the following muform fields:
      | Cohort | Staff |
    And I press "Continue"
    And I should see "Member of cohort: Staff"
    And I press "Back"
    And I press "Back"
    And I set the following muform fields:
      | Rule type | tool_mucohort:suspended |
    And I press "Continue"
    And I should see "User account is suspended"
    And I press "Add rule"
    Then I should see "User account is suspended"
    And I should not see "Member of cohort: Staff"

    When I click on "Edit rule" "link" in the "Department starts with \"Sales\" or \"HR\"" "list_item"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | textoperator | equals |
      | textvalues   | Sales  |
    And I click on "Edit rule" "button" in the "dialog[open]" "css_element"
    Then I should see "Department is \"Sales\""
    And I should not see "Department starts with \"Sales\" or \"HR\""

    When I click on "Delete rule" "link" in the "User account is suspended" "list_item"
    And I click on "Delete rule" "button" in the "dialog[open]" "css_element"
    Then I should not see "User account is suspended"
    And I should see "No exclude rules."

    When I click on "Overview" "link" in the ".secondary-navigation" "css_element"
    Then I should see "Waiting for approval"
    And I should see "Add and remove members"
    And I click on "Edit" "button"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Synchronisation | 2       |
      | Automatic sync  | 0       |
    And "Sync priority" "field" should not be visible
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Automatic sync  | 604800  |
      | Sync priority   | 0       |
    And I click on "Update cohort" "button" in the "dialog[open]" "css_element"
    And I should see "Add members only"
    And I should see "Every 7 days"
    And I click on "Sync history" "link" in the ".secondary-navigation" "css_element"
    And I should see "There is no sync history yet."
    And I click on "Draft rules" "link" in the ".secondary-navigation" "css_element"
    And I should see "members are never removed because of rules"
    And I click on "Overview" "link" in the ".secondary-navigation" "css_element"
    And I click on "Active rules" "link" in the ".secondary-navigation" "css_element"
    And I should see "There are draft rule changes waiting for approval"
    And I should not see "Department is \"Sales\""
    And I click on "Draft rules" "link" in the "region-main" "region"
    And I press "Approve rules"
    Then I should see "Draft rules replace the active rules." in the "dialog[open]" "css_element"
    And the following muform fields in the "dialog[open]" "css_element" match:
      | syncnow | 1 |
    And I click on "Approve rules" "button" in the "dialog[open]" "css_element"
    And "Run queued sync now" "button" should exist
    And the following should exist in the "reportbuilder-table" table:
      | Status | Added | Removed |
      | Queued | 0     | 0       |
    And I run all adhoc tasks
    And I reload the page
    And the following should exist in the "reportbuilder-table" table:
      | Status   | Added | Removed |
      | Finished | 1     | 0       |
    And I click on "Members" "link" in the ".secondary-navigation" "css_element"
    And I should see "Rule-based"
    And I should see "Add members only"
    And I should see "Blocked users"
    And I should see "(Finished, added: 1, removed: 0)"
    And I should see "User 1" in the "reportbuilder-table" "table"
    And I should not see "User 2" in the "reportbuilder-table" "table"
    And I click on "Active rules" "link" in the ".secondary-navigation" "css_element"
    And I should see "Membership is synchronised with active rules."
    And I should see "Department is \"Sales\""
    And I should not see "There are draft rule changes waiting for approval"
    And I press "Sync now"
    And I should see "Membership is updated with active rules by a background task" in the "dialog[open]" "css_element"
    And I click on "Sync now" "button" in the "dialog[open]" "css_element"
    And the following should exist in the "reportbuilder-table" table:
      | Status | Added | Removed |
      | Queued | 0     | 0       |
    And I click on "Active rules" "link" in the ".secondary-navigation" "css_element"
    And "Draft rules" "link" should not exist in the ".secondary-navigation" "css_element"
    When I press "Modify rules"
    Then I should see "Department is \"Sales\""
    And I press "Add exclude rule"
    And I set the following muform fields:
      | Rule type | tool_mucohort:suspended |
    And I press "Continue"
    And I press "Add rule"
    And I should see "User account is suspended"
    And I press "Discard draft rules"
    And I click on "Discard draft rules" "button" in the "dialog[open]" "css_element"
    Then I should see "Membership is synchronised with active rules."
    And "Draft rules" "link" should not exist in the ".secondary-navigation" "css_element"
    And I should not see "User account is suspended"
    And I should see "Department is \"Sales\""

  Scenario: Admin converts standard cohort to rule-based cohort and back without member changes
    Given the following "cohort members" exist:
      | user  | cohort |
      | user2 | STAFF  |
    And I click on "Cohorts" "link" in the ".breadcrumb" "css_element"
    And I click on "Staff" "link" in the "reportbuilder-table" "table"
    When I click on "Cohort actions" "button"
    And I click on "Convert cohort type" "link"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | New type | rules |
    Then I should see "There are no rules after conversion" in the "dialog[open]" "css_element"
    And the following muform fields in the "dialog[open]" "css_element" match:
      | Synchronisation | 2 |
    And I set the following muform fields in the "dialog[open]" "css_element":
      | seedforced | seedforced |
    And I click on "Convert" "button" in the "dialog[open]" "css_element"
    And I should see "Rule-based"
    And I should see "Add members only"
    And I should see "Forced: 2, blocked: 0"
    And I click on "Members" "link" in the ".secondary-navigation" "css_element"
    And I should see "User 1" in the "reportbuilder-table" "table"
    And I should see "User 2" in the "reportbuilder-table" "table"

    When I click on "Overview" "link" in the ".secondary-navigation" "css_element"
    And I click on "Cohort actions" "button"
    And I click on "Convert cohort type" "link"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | New type | standard |
    Then I should see "Forced or blocked users to be deleted: 2" in the "dialog[open]" "css_element"
    And I click on "Convert" "button" in the "dialog[open]" "css_element"
    And I should see "Standard"
    And I click on "Members" "link" in the ".secondary-navigation" "css_element"
    And I should see "User 1" in the "reportbuilder-table" "table"
    And I should see "User 2" in the "reportbuilder-table" "table"
