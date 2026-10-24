@tool @tool_mucohort @javascript @MuTMS
Feature: Forced and blocked users of advanced cohorts
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | delegate | Del       | Egate    | delegate@example.com |
      | user1    | User      | 1        | user1@example.com    |
      | user2    | User      | 2        | user2@example.com    |
    And I change window size to "large"
    And I log in as "admin"
    And I navigate to "Users > Accounts > Cohort management" in site administration
    And I press "Create cohort"
    And I click on "Continue" "button" in the "dialog[open]" "css_element"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Name | Curated 1 |
    And I click on "Create cohort" "button" in the "dialog[open]" "css_element"

  Scenario: Exception manager forces and blocks users
    When I click on "Access" "link" in the ".secondary-navigation" "css_element"
    And I press "Grant access"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | User        | Del Egate                     |
      | Permissions | viewmembers, manageexceptions |
    And I click on "Grant access" "button" in the "dialog[open]" "css_element"
    And I log out

    When I log in as "delegate"
    And I visit "/admin/tool/mucohort/index.php"
    And I click on "Curated 1" "link" in the "reportbuilder-table" "table"
    And I click on "Exceptions" "link" in the ".secondary-navigation" "css_element"
    And I press "Add exceptions"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Users     | User 1, User 2 |
      | Exception | 1              |
      | Reason    | Pilot users    |
    And I click on "Add exceptions" "button" in the "dialog[open]" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | First name | Exception | Member | Reason      |
      | User 1     | Forced    | Yes    | Pilot users |
      | User 2     | Forced    | Yes    | Pilot users |

    When I press "Update exception" action in the "User 2" report row
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Exception | 2               |
      | Reason    | Cheated in exam |
    And I click on "Update exception" "button" in the "dialog[open]" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | First name | Exception | Member | Reason          |
      | User 2     | Blocked   | No     | Cheated in exam |

    When I press "Remove exception" action in the "User 1" report row
    Then I should see "User stays a member of the cohort." in the "dialog[open]" "css_element"
    And I click on "Remove exception" "button" in the "dialog[open]" "css_element"
    Then I should not see "User 1" in the "reportbuilder-table" "table"
    And I click on "Members" "link" in the ".secondary-navigation" "css_element"
    And I should see "User 1" in the "reportbuilder-table" "table"
    And I should not see "User 2" in the "reportbuilder-table" "table"
    And I click on "Overview" "link" in the ".secondary-navigation" "css_element"
    And I should see "Forced: 0, blocked: 1"

  Scenario: Member viewer sees exceptions without reasons
    When I click on "Access" "link" in the ".secondary-navigation" "css_element"
    And I press "Grant access"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | User        | Del Egate   |
      | Permissions | viewmembers |
    And I click on "Grant access" "button" in the "dialog[open]" "css_element"
    And I click on "Exceptions" "link" in the ".secondary-navigation" "css_element"
    And I press "Add exceptions"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Users     | User 1          |
      | Exception | 2               |
      | Reason    | Cheated in exam |
    And I click on "Add exceptions" "button" in the "dialog[open]" "css_element"
    And I log out

    When I log in as "delegate"
    And I visit "/admin/tool/mucohort/index.php"
    And I click on "Curated 1" "link" in the "reportbuilder-table" "table"
    And I click on "Exceptions" "link" in the ".secondary-navigation" "css_element"
    Then I should see "User 1" in the "reportbuilder-table" "table"
    And I should see "Blocked" in the "reportbuilder-table" "table"
    And I should not see "Cheated in exam"
    And "Add exceptions" "button" should not exist

  Scenario: Removing of forced exception in curated cohort may also remove the membership
    When I click on "Exceptions" "link" in the ".secondary-navigation" "css_element"
    And I press "Add exceptions"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Users     | User 1, User 2 |
      | Exception | 1              |
    And I click on "Add exceptions" "button" in the "dialog[open]" "css_element"

    And I press "Remove exception" action in the "User 1" report row
    Then I should see "User stays a member of the cohort." in the "dialog[open]" "css_element"
    And I should not see "User is removed from the cohort." in the "dialog[open]" "css_element"
    And I click on "Remove exception" "button" in the "dialog[open]" "css_element"

    And I press "Remove exception" action in the "User 2" report row
    And I set the following muform fields in the "dialog[open]" "css_element":
      | removemember | 1 |
    Then I should see "User is removed from the cohort." in the "dialog[open]" "css_element"
    And I should not see "User stays a member of the cohort." in the "dialog[open]" "css_element"
    And I click on "Remove exception" "button" in the "dialog[open]" "css_element"

    When I click on "Members" "link" in the ".secondary-navigation" "css_element"
    Then I should see "User 1" in the "reportbuilder-table" "table"
    And I should not see "User 2" in the "reportbuilder-table" "table"
