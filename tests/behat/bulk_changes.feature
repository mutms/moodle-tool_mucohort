@tool @tool_mucohort @javascript @MuTMS
Feature: Bulk changes of cohort members and exceptions
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | user1    | User      | 1        | user1@example.com |
      | user2    | User      | 2        | user2@example.com |
      | user3    | User      | 3        | user3@example.com |
    And the following "cohorts" exist:
      | name      | idnumber | contextlevel | reference |
      | Cohort 1  | CH1      | System       |           |
      | Candidate | CAND     | System       |           |
    And the following "cohort members" exist:
      | user  | cohort |
      | user1 | CH1    |
      | user1 | CAND   |
      | user2 | CAND   |
    And I change window size to "large"

  Scenario: Admin adds pasted users and removes uploaded users of a standard cohort
    Given I log in as "admin"
    And I visit "/admin/tool/mucohort/index.php"
    And I click on "Cohort 1" "link" in the "reportbuilder-table" "table"
    And I click on "Members" "link" in the ".secondary-navigation" "css_element"
    And I click on "Cohort actions" "button"
    And I click on "Bulk changes" "link"
    # One pasted column needs no column stage.
    When I set the following muform fields:
      | csvtext | user1\nuser2\nUSER3\nnobody |
    And I press "Continue"
    Then I should see "Changes"
    And the following muform fields match:
      | Users are identified by | username |
    And I should see "Ready: 2"
    And I should see "Nothing to do: 1"
    And I should see "User not found: 1"
    And I should see "nobody"
    When I press "Apply changes (2)"
    Then I should see "Users processed: 2"
    And the following should exist in the "reportbuilder-table" table:
      | First name |
      | User 1     |
      | User 2     |
      | User 3     |

    When I click on "Cohort actions" "button"
    And I click on "Bulk changes" "link"
    And I set the following muform fields:
      | csvtext | Full name,Email address\nUser 2,user2@example.com\nUser 3,user3@example.com |
    And I press "Continue"
    Then I should see "Columns"
    And the following muform fields match:
      | First row contains column names | 1 |
      | Column with users               | 1 |
    When I press "Continue"
    And I set the following muform fields:
      | Action | remove |
    And I press "Update preview"
    Then the following muform fields match:
      | Users are identified by | email |
    And I should see "Ready: 2"
    When I press "Apply changes (2)"
    Then I should see "Users processed: 2"
    And the following should exist in the "reportbuilder-table" table:
      | First name |
      | User 1     |
    And I should not see "User 2" in the "reportbuilder-table" "table"

  Scenario: Admin changes members and exceptions of a curated cohort in bulk
    Given I log in as "admin"
    And I visit "/admin/tool/mucohort/index.php"
    And I press "Create cohort"
    And I click on "Continue" "button" in the "dialog[open]" "css_element"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Name             | Curated 1 |
      | Candidate cohort | Candidate |
    And I click on "Create cohort" "button" in the "dialog[open]" "css_element"
    And I click on "Members" "link" in the ".secondary-navigation" "css_element"
    And I click on "Cohort actions" "button"
    And I click on "Bulk changes" "link"
    And I set the following muform fields:
      | csvtext | user1\nuser3 |
    And I press "Continue"
    # User 3 is not in the candidate cohort.
    Then I should see "Ready: 1"
    And I should see "Not allowed: 1"
    And I press "Apply changes (1)"
    And the following should exist in the "reportbuilder-table" table:
      | First name |
      | User 1     |

    When I click on "Exceptions" "link" in the ".secondary-navigation" "css_element"
    And I click on "Cohort actions" "button"
    And I click on "Bulk changes" "link"
    And I set the following muform fields:
      | csvtext | user1@example.com\nuser3@example.com |
    And I press "Continue"
    Then the following muform fields match:
      | Action                  | force |
      | Users are identified by | email |
    And I should see "Ready: 2"
    When I set the following muform fields:
      | Action | block   |
      | Reason | Leavers |
    And I press "Update preview"
    And I press "Apply changes (2)"
    Then I should see "Users processed: 2"
    And the following should exist in the "reportbuilder-table" table:
      | First name | Exception | Reason  |
      | User 1     | Blocked   | Leavers |
      | User 3     | Blocked   | Leavers |
    When I click on "Members" "link" in the ".secondary-navigation" "css_element"
    Then I should see "No cohort members found"

    When I click on "Exceptions" "link" in the ".secondary-navigation" "css_element"
    And I click on "Cohort actions" "button"
    And I click on "Bulk changes" "link"
    And I set the following muform fields:
      | csvtext | user1 |
    And I press "Continue"
    And I set the following muform fields:
      | Action | unexcept |
    And I press "Update preview"
    And I press "Apply changes (1)"
    Then I should see "Users processed: 1"
    And the following should exist in the "reportbuilder-table" table:
      | First name | Exception |
      | User 3     | Blocked   |
    And I should not see "User 1" in the "reportbuilder-table" "table"
