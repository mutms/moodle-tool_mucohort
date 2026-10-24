@tool @tool_mucohort @javascript @MuTMS
Feature: Delegated access to advanced cohorts via access records
  Background:
    Given the following "categories" exist:
      | name  | category | idnumber |
      | Cat 1 | 0        | CAT1     |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | delegate | Del       | Egate    | delegate@example.com |
      | user1    | User      | 1        | user1@example.com    |
    And I change window size to "large"
    And I log in as "admin"
    And I am on course index
    And I follow "Cat 1"
    And I navigate to "Cohort management" in current page administration
    And I press "Create cohort"
    And I click on "Continue" "button" in the "dialog[open]" "css_element"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Name | Curated 1 |
    And I click on "Create cohort" "button" in the "dialog[open]" "css_element"
    And I should see "Curated 1" in the "page-header" "region"

  Scenario: Admin grants access and the delegate sees only what the access record allows
    When I click on "Access" "link" in the ".secondary-navigation" "css_element"
    And I should see "No access records found"
    And I press "Grant access"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | User        | Del Egate |
      | iscurator   | 1         |
    And I click on "Grant access" "button" in the "dialog[open]" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | First name | Curator | View members |
      | Del Egate  | Yes     | No           |
    And I click on "Overview" "link" in the ".secondary-navigation" "css_element"
    And I should see "Del Egate"
    And I log out

    When I log in as "delegate"
    And I am on course index
    And I follow "Cat 1"
    And I navigate to "Cohort management" in current page administration
    Then I should see "Curated 1" in the "reportbuilder-table" "table"
    And "Create cohort" "button" should not exist
    And I click on "Curated 1" "link" in the "reportbuilder-table" "table"
    And "Members" "link" should not exist in the ".secondary-navigation" "css_element"
    And "Edit" "button" should not exist
    And I click on "Access" "link" in the ".secondary-navigation" "css_element"
    And "Grant access" "button" should not exist
    And I log out

    When I log in as "admin"
    And I am on course index
    And I follow "Cat 1"
    And I navigate to "Cohort management" in current page administration
    And I click on "Curated 1" "link" in the "reportbuilder-table" "table"
    And I click on "Access" "link" in the ".secondary-navigation" "css_element"
    And I press "Update access" action in the "Del Egate" report row
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Permissions | managemembers |
    And I click on "Update access" "button" in the "dialog[open]" "css_element"
    And I should see "View members is required for managing members or exceptions" in the "dialog[open]" "css_element"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Permissions | viewmembers |
    And I click on "Update access" "button" in the "dialog[open]" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | First name | Curator | View members |
      | Del Egate  | Yes     | Yes          |
    And I click on "Filters" "button"
    And I set the following fields in the "Manage members" "core_reportbuilder > Filter" to these values:
      | Manage members operator | Yes |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I should see "No access records found"
    And I set the following fields in the "Manage members" "core_reportbuilder > Filter" to these values:
      | Manage members operator | No |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I should see "Del Egate" in the "reportbuilder-table" "table"
    And I click on "Reset all" "button" in the "[data-region='report-filters']" "css_element"
    And I log out

    When I log in as "delegate"
    And I am on course index
    And I follow "Cat 1"
    And I navigate to "Cohort management" in current page administration
    And I click on "Curated 1" "link" in the "reportbuilder-table" "table"
    Then "Members" "link" should exist in the ".secondary-navigation" "css_element"
    And I log out

    When I log in as "admin"
    And I am on course index
    And I follow "Cat 1"
    And I navigate to "Cohort management" in current page administration
    And I click on "Curated 1" "link" in the "reportbuilder-table" "table"
    And I click on "Access" "link" in the ".secondary-navigation" "css_element"
    And I press "Revoke access" action in the "Del Egate" report row
    And I click on "Revoke access" "button" in the "dialog[open]" "css_element"
    Then I should see "No access records found"
