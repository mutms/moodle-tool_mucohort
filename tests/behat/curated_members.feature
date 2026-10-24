@tool @tool_mucohort @javascript @MuTMS
Feature: Member managers of curated cohorts
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | delegate | Del       | Egate    | delegate@example.com |
      | user1    | User      | 1        | user1@example.com    |
      | user2    | User      | 2        | user2@example.com    |
      | user3    | User      | 3        | user3@example.com    |
    And the following "cohorts" exist:
      | name      | idnumber | contextlevel | reference |
      | Candidate | CAND     | System       |           |
    And the following "cohort members" exist:
      | user  | cohort |
      | user1 | CAND   |
      | user2 | CAND   |
    And I change window size to "large"
    And I log in as "admin"
    And I navigate to "Users > Accounts > Cohort management" in site administration
    And I press "Create cohort"
    And I click on "Continue" "button" in the "dialog[open]" "css_element"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Name                      | Curated 1 |
      | Maximum number of members | 1         |
      | Candidate cohort          | Candidate |
    And I click on "Create cohort" "button" in the "dialog[open]" "css_element"
    And I click on "Access" "link" in the ".secondary-navigation" "css_element"
    And I press "Grant access"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | User        | Del Egate                  |
      | Permissions | viewmembers, managemembers |
    And I click on "Grant access" "button" in the "dialog[open]" "css_element"
    And I log out

  Scenario: Member manager adds and removes members within the limits
    When I log in as "delegate"
    And I visit "/admin/tool/mucohort/index.php"
    And I click on "Curated 1" "link" in the "reportbuilder-table" "table"
    And I click on "Members" "link" in the ".secondary-navigation" "css_element"
    And I should see "Candidate cohort"
    And I should see "Maximum number of members"
    And I should see "Blocked users"
    And I press "Add members"
    Then I should see "Members that can still be added: 1" in the "dialog[open]" "css_element"
    And I type "User 3" into the "userids" muform search field
    And I should see "No results" in the "dialog[open]" "css_element"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Users | User 1, User 2 |
    And I click on "Add members" "button" in the "dialog[open]" "css_element"
    Then I should see "Member limit exceeded, members that can still be added: 1" in the "dialog[open]" "css_element"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Users | User 1 |
    And I click on "Add members" "button" in the "dialog[open]" "css_element"
    And the following should exist in the "reportbuilder-table" table:
      | First name |
      | User 1     |

    When I press "Remove member" action in the "User 1" report row
    And I click on "Remove member" "button" in the "dialog[open]" "css_element"
    Then I should see "No cohort members found"
