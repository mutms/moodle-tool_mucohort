@tool @tool_mucohort @javascript @MuTMS
Feature: Management of standard cohorts
  Background:
    Given the following "categories" exist:
      | name  | category | idnumber |
      | Cat 1 | 0        | CAT1     |
      | Cat 2 | CAT1     | CAT2     |
    And the following "cohorts" exist:
      | name     | idnumber | contextlevel | reference |
      | Cohort 1 | CH1      | System       |           |
      | Cohort 2 | CH2      | Category     | CAT1      |
      | Cohort 3 | CH3      | Category     | CAT2      |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | viewer1  | Viewer    | 1        | viewer1@example.com  |
      | mviewer1 | Member    | Viewer   | mviewer1@example.com |
      | user1    | User      | 1        | user1@example.com    |
      | user2    | User      | 2        | user2@example.com    |
      | user3    | User      | 3        | user3@example.com    |
    And the following "cohort members" exist:
      | user  | cohort |
      | user1 | CH1    |
      | user2 | CH1    |
    And the following "roles" exist:
      | name           | shortname |
      | Cohort manager | cmanager  |
      | Cohort viewer  | cviewer   |
      | Member viewer  | mviewer   |
    And the following "permission overrides" exist:
      | capability             | permission | role     | contextlevel | reference |
      | moodle/cohort:view     | Allow      | cmanager | Category     | CAT1      |
      | moodle/cohort:manage   | Allow      | cmanager | Category     | CAT1      |
      | moodle/cohort:assign   | Allow      | cmanager | Category     | CAT1      |
      | moodle/category:viewcourselist | Allow | cmanager | Category | CAT1      |
      | moodle/cohort:view     | Allow      | cviewer  | Category     | CAT1      |
      | moodle/category:viewcourselist | Allow | cviewer | Category | CAT1       |
      | moodle/cohort:view     | Allow      | mviewer  | Category     | CAT1      |
      | tool/mucohort:viewmembers | Allow   | mviewer  | Category     | CAT1      |
      | moodle/category:viewcourselist | Allow | mviewer | Category | CAT1       |
    And the following "role assigns" exist:
      | user     | role     | contextlevel | reference |
      | manager1 | cmanager | Category     | CAT1      |
      | viewer1  | cviewer  | Category     | CAT1      |
      | mviewer1 | mviewer  | Category     | CAT1      |
    And the following "cohort members" exist:
      | user  | cohort |
      | user1 | CH2    |

  Scenario: Admin may create, update, move and delete standard cohorts
    Given I change window size to "large"
    And I log in as "admin"
    When I navigate to "Users > Accounts > Cohort management" in site administration
    Then the following should exist in the "reportbuilder-table" table:
      | Name     | Cohort ID | Category | Type     | Cohort size |
      | Cohort 1 | CH1       | System   | Standard | 2           |
      | Cohort 2 | CH2       | Cat 1    | Standard | 1           |
      | Cohort 3 | CH3       | Cat 2    | Standard | 0           |

    When I press "Create cohort"
    And the following muform fields in the "dialog[open]" "css_element" match:
      | Type | curated |
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Type | standard |
    And I click on "Continue" "button" in the "dialog[open]" "css_element"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Name      | Cohort 4 |
      | Cohort ID | CH4      |
      | Category  | Cat 1    |
      | Visible   | 0        |
    And I click on "Create cohort" "button" in the "dialog[open]" "css_element"
    Then I should see "Cohort 4" in the "page-header" "region"
    And I should see "Cat 1"

    When I click on "Edit" "button"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Name      | Cohort 44 |
      | Cohort ID | CH44      |
    And I click on "Update cohort" "button" in the "dialog[open]" "css_element"
    Then I should see "Cohort 44" in the "page-header" "region"
    And I should see "CH44"

    When I click on "Move cohort" "link"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Category | Cat 2 |
    And I click on "Move cohort" "button" in the "dialog[open]" "css_element"
    Then I should see "Cat 2"

    When I click on "Cohort actions" "button"
    And I click on "Delete cohort" "link"
    And I click on "Delete cohort" "button" in the "dialog[open]" "css_element"
    Then I should not see "Cohort 44"
    And I should see "Cohort 3" in the "reportbuilder-table" "table"

  Scenario: Admin may add and remove cohort members
    Given I change window size to "large"
    And I log in as "admin"
    And I navigate to "Users > Accounts > Cohort management" in site administration
    When I click on "Cohort 1" "link" in the "reportbuilder-table" "table"
    And I click on "Members" "link" in the ".secondary-navigation" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | First name | Email address     |
      | User 1     | user1@example.com |
      | User 2     | user2@example.com |

    When I press "Add members"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Users | User 3 |
    And I click on "Add members" "button" in the "dialog[open]" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | First name | Email address     |
      | User 3     | user3@example.com |

    When I press "Remove member" action in the "User 1" report row
    And I click on "Remove member" "button" in the "dialog[open]" "css_element"
    Then I should not see "User 1" in the "reportbuilder-table" "table"
    And I should see "User 2" in the "reportbuilder-table" "table"

  Scenario: Category cohort manager sees only cohorts from own category
    Given I change window size to "large"
    And I log in as "manager1"
    And I am on course index
    And I follow "Cat 1"
    And I navigate to "Cohort management" in current page administration
    Then the following should exist in the "reportbuilder-table" table:
      | Name     | Category |
      | Cohort 2 | Cat 1    |
      | Cohort 3 | Cat 2    |
    And I should not see "Cohort 1"

  Scenario: Category cohort manager may manage cohorts and members in own category
    Given I change window size to "large"
    And I log in as "manager1"
    And I am on course index
    And I follow "Cat 1"
    And I navigate to "Cohort management" in current page administration

    When I press "Create cohort"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Name      | Cohort 5 |
      | Cohort ID | CH5      |
    And I click on "Create cohort" "button" in the "dialog[open]" "css_element"
    Then I should see "Cohort 5" in the "page-header" "region"
    And I should see "Cat 1"

    When I click on "Edit" "button"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Name | Cohort 55 |
    And I click on "Update cohort" "button" in the "dialog[open]" "css_element"
    Then I should see "Cohort 55" in the "page-header" "region"

    When I click on "Members" "link" in the ".secondary-navigation" "css_element"
    And I press "Add members"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | Users | User 3 |
    And I click on "Add members" "button" in the "dialog[open]" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | First name |
      | User 3     |

    When I press "Remove member" action in the "User 3" report row
    And I click on "Remove member" "button" in the "dialog[open]" "css_element"
    Then I should see "No cohort members found"

    When I click on "Overview" "link" in the ".secondary-navigation" "css_element"
    And I click on "Cohort actions" "button"
    And I click on "Delete cohort" "link"
    And I click on "Delete cohort" "button" in the "dialog[open]" "css_element"
    Then I should not see "Cohort 55"
    And I should see "Cohort 2" in the "reportbuilder-table" "table"

  Scenario: Cohort viewer may see cohorts but not members or any actions
    Given I change window size to "large"
    And I log in as "viewer1"
    And I am on course index
    And I follow "Cat 1"
    And I navigate to "Cohort management" in current page administration
    Then the following should exist in the "reportbuilder-table" table:
      | Name     | Category | Cohort size |
      | Cohort 2 | Cat 1    | 1           |
      | Cohort 3 | Cat 2    | 0           |
    And "Create cohort" "button" should not exist
    And "Cohort 2" row "Actions" column of "reportbuilder-table" table should not contain "Delete cohort"

    When I click on "Cohort 2" "link" in the "reportbuilder-table" "table"
    Then I should see "CH2"
    And "Edit" "button" should not exist
    And "Members" "link" should not exist in the ".secondary-navigation" "css_element"

  Scenario: Member viewer may see members but not change them
    Given I change window size to "large"
    And I log in as "mviewer1"
    And I am on course index
    And I follow "Cat 1"
    And I navigate to "Cohort management" in current page administration
    When I click on "Cohort 2" "link" in the "reportbuilder-table" "table"
    And I click on "Members" "link" in the ".secondary-navigation" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | First name |
      | User 1     |
    And "Add members" "button" should not exist
    And "Edit" "button" should not exist

  Scenario: Core cohort pages redirect to new management when takeover enabled
    Given the following config values are set as admin:
      | takeover | 1 | tool_mucohort |
    And I log in as "admin"
    When I visit "/cohort/index.php"
    Then I should see "Cohort 1" in the "reportbuilder-table" "table"
    And I should see "Type" in the "reportbuilder-table" "table"
    When I navigate to "Users > Accounts > Cohorts" in site administration
    Then I should see "Cohort 1" in the "reportbuilder-table" "table"
    And I should see "Type" in the "reportbuilder-table" "table"
    And I should not see "Cohort management" in the "page-header" "region"
    When I am on course index
    And I follow "Cat 1"
    And I navigate to "Cohorts" in current page administration
    Then I should see "Cohort 2" in the "reportbuilder-table" "table"
    And I should see "Type" in the "reportbuilder-table" "table"
