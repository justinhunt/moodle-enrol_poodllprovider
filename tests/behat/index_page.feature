@enrol @enrol_poodllprovider
Feature: Check that the page listing the shared external tools is functioning as expected
  In order to edit an external tool
  As a teacher
  I need to ensure the tool listing page is working as expected

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1 | topics |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And I log in as "admin"
    And I navigate to "Plugins > Enrolments > Manage enrol plugins" in site administration
    And I click on "Enable" "link" in the "Publish as poodllprovider tool" "table_row"
    And I log out

  Scenario: I want to edit an external tool
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Assignment" to section "1" and I fill the form with:
      | Assignment name | Test assignment name |
      | Description | Submit your online text |
    And I navigate to "Users > Enrolment methods" in current page administration
    And I select "Publish as poodllprovider tool" from the "Add method" singleselect
    And I set the following fields to these values:
      | Custom instance name | Assignment - poodllprovider |
      | Tool to be published | Test assignment name |
    And I press "Add method"
    And I am on "Course 1" course homepage
    And I navigate to "Published as poodllprovider tools" in current page administration
    And I should see "Assignment - poodllprovider" in the ".generaltable" "css_element"
    When I click on "Disable" "link" in the "Assignment - poodllprovider" "table_row"
    Then ".dimmed_text" "css_element" should exist in the "Assignment - poodllprovider" "table_row"
    And I click on "Enable" "link" in the "Assignment - poodllprovider" "table_row"
    And ".dimmed_text" "css_element" should not exist in the "Assignment - poodllprovider" "table_row"
    And I click on "Edit" "link" in the "Assignment - poodllprovider" "table_row"
    And I set the following fields to these values:
      | Custom instance name | Course - poodllprovider |
      | Tool to be published | Course |
    And I press "Save changes"
    And I should see "Course - poodllprovider" in the ".generaltable" "css_element"
    And I click on "Delete" "link" in the "Course - poodllprovider" "table_row"
    And I press "Cancel"
    And I should see "Course - poodllprovider" in the ".generaltable" "css_element"
    And I click on "Delete" "link" in the "Course - poodllprovider" "table_row"
    And I press "Continue"
    And I should see "No tools provided"
    And I should not see "Course - poodllprovider"
