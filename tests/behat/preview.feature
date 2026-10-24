@tool @tool_mucohort @javascript @MuTMS
Feature: Preview of draft rules and running of queued syncs
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             | department |
      | user1    | User      | 1        | user1@example.com | Sales      |
      | user2    | User      | 2        | user2@example.com | HR         |
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
    And I click on "Active rules" "link" in the ".secondary-navigation" "css_element"
    And I press "Modify rules"
    And I press "Add include rule"
    And I set the following muform fields:
      | Rule type | tool_mucohort:userfield |
    And I press "Continue"
    And I set the following muform fields:
      | Field        | department |
      | textoperator | equals     |
      | textvalues   | Sales      |
    And I press "Continue"
    And I press "Add rule"

  Scenario: Admin previews draft rules and runs queued sync without waiting for cron
    When I press "Preview"
    Then the following should exist in the "tool_mucohort-preview-rules" table:
      | Rule                          | Users matching this rule |
      | Include: Department is "Sales" | 1                        |
    And the following should exist in the "tool_mucohort-preview-changes" table:
      | Membership change      | Users |
      | Users to be added      | 1     |
      | Members to be removed  | 0     |
    And I should see "SQL"
    And I press "Back"

    When I press "Approve rules"
    And I click on "Approve rules" "button" in the "dialog[open]" "css_element"
    And "Run queued sync now" "button" should exist
    And I press "Run queued sync now"
    Then I should see "Taking over queued sync"
    And I should see "Sync result: Finished, added: 1, removed: 0"
    And I press "Continue"
    And the following should exist in the "reportbuilder-table" table:
      | Status   | Added | Removed |
      | Finished | 1     | 0       |
    And I run all adhoc tasks
    And I reload the page
    And "Run queued sync now" "button" should not exist
    And I should see "Finished" in the "reportbuilder-table" "table"
    And I should not see "Failed" in the "reportbuilder-table" "table"

  Scenario: Admin uses custom profile field in rules after the values are prepared
    Given the following "custom profile fields" exist:
      | datatype | shortname | name | param1        |
      | menu     | level     | Level | Junior\nSenior |
    And the following "users" exist:
      | username | firstname | lastname | email             | profile_field_level |
      | user3    | User      | 3        | user3@example.com | Senior              |
    And I press "Add exclude rule"
    And I set the following muform fields:
      | Rule type | tool_mucohort:profilefield |
    And I press "Continue"
    And I set the following muform fields:
      | Profile field | Level  |
    And I set the following muform fields:
      | Values        | Senior |
    And I press "Continue"
    Then I should see "Level: Senior"
    And I should not see "Values of profile field are being prepared"
    And I press "Add rule"
    And I press "Preview"
    And the following should exist in the "tool_mucohort-preview-rules" table:
      | Rule                   | Users matching this rule |
      | Exclude: Level: Senior | 1                        |
    And I press "Back"
    And I press "Approve rules"
    And I click on "Approve rules" "button" in the "dialog[open]" "css_element"
    And I press "Run queued sync now"
    And I should see "Sync result: Finished, added: 1, removed: 0"
    And I press "Continue"
    And I click on "Active rules" "link" in the ".secondary-navigation" "css_element"
    And I should see "Level: Senior"
    And I should not see "Values of profile field are being prepared"
