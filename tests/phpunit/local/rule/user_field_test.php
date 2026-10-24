<?php
// This file is part of MuTMS suite of plugins for Moodle™ LMS.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <https://www.gnu.org/licenses/>.

// phpcs:disable moodle.Files.BoilerplateComment.CommentEndedTooSoon
// phpcs:disable moodle.Files.LineLength.TooLong
// phpcs:disable moodle.Commenting.DocblockDescription.Missing

namespace tool_mucohort\phpunit\local\rule;

use tool_mucohort\local\broken_rules_exception;
use tool_mucohort\local\rule\base;
use tool_mucohort\local\rule\user_field;
use tool_mucohort\local\ruleset;
use tool_mucohort\local\type\rules;

/**
 * User field rule tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\local\rule\user_field
 * @covers \tool_mucohort\local\rule\include_user_field
 * @covers \tool_mucohort\local\rule\exclude_user_field
 */
final class user_field_test extends \advanced_testcase {
    /** @var rules */
    private rules $cohort;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->cohort = rules::create((object)[
            'contextid' => \core\context\system::instance()->id,
            'name' => 'Rules cohort',
        ]);
    }

    /**
     * Returns ids of users matched by one rule, admin is ignored.
     *
     * @param string $kind
     * @param array $config
     * @return int[]
     */
    private function match(string $kind, array $config): array {
        global $DB;
        $ruleset = new ruleset();
        $uuid = $ruleset->add_rule($kind, 'tool_mucohort:userfield', $config);
        $sql = $this->cohort->get_rule_match_sql($ruleset, $uuid)->wrap("SELECT u.id FROM {user} u WHERE ", " AND u.id <> 2 ORDER BY u.id");
        return array_map('intval', $DB->get_fieldset_sql($sql->sql, $sql->params));
    }

    /**
     * Returns rule description.
     *
     * @param string $kind
     * @param array $config
     * @return string
     */
    private function describe(string $kind, array $config): string {
        $ruleset = new ruleset();
        $uuid = $ruleset->add_rule($kind, 'tool_mucohort:userfield', $config);
        return $this->cohort->get_rule_description($ruleset->get_rule($uuid));
    }

    public function test_get_fields(): void {
        foreach (user_field::get_fields() as $field => $fieldtype) {
            $this->assertNotEmpty(user_field::get_field_name($field));
            $this->assertNotEmpty(user_field::get_operators($fieldtype));
            if ($fieldtype === user_field::FIELDTYPE_MENU) {
                $this->assertNotEmpty(user_field::get_menu_options($field));
            }
        }
        $this->assertSame('Language', user_field::get_field_name('lang'));
        $this->assertSame('Authentication', user_field::get_field_name('auth'));
        $this->assertSame('Department', user_field::get_field_name('department'));
        $this->assertSame('Czechia', user_field::get_menu_options('country')['CZ']);
        $this->assertSame('Manual accounts', user_field::get_menu_options('auth')['manual']);
    }

    public function test_text(): void {
        $user1 = $this->getDataGenerator()->create_user(['email' => 'john@example.com', 'department' => 'Sales']);
        $user2 = $this->getDataGenerator()->create_user(['email' => 'jane@EXAMPLE.org', 'department' => 'sales team']);
        $user3 = $this->getDataGenerator()->create_user(['email' => 'pepa@example.cz', 'department' => '']);
        $user4 = $this->getDataGenerator()->create_user(['email' => 'x_y@example.cz', 'department' => '100%']);

        $this->assertSame([(int)$user1->id], $this->match(base::KIND_INCLUDE, ['field' => 'department', 'operator' => 'equals', 'values' => ['SALES']]));
        $this->assertSame([(int)$user1->id, (int)$user3->id], $this->match(base::KIND_INCLUDE, ['field' => 'department', 'operator' => 'equals', 'values' => ['sales', '']]));
        $this->assertSame([(int)$user1->id, (int)$user2->id], $this->match(base::KIND_INCLUDE, ['field' => 'department', 'operator' => 'startswith', 'values' => ['sal']]));
        $this->assertSame([(int)$user2->id], $this->match(base::KIND_INCLUDE, ['field' => 'department', 'operator' => 'contains', 'values' => ['team']]));
        $this->assertSame([(int)$user4->id], $this->match(base::KIND_INCLUDE, ['field' => 'department', 'operator' => 'endswith', 'values' => ['0%']]));
        $config = ['field' => 'email', 'operator' => 'endswith', 'values' => ['@example.com', '@example.org']];
        $this->assertSame([(int)$user1->id, (int)$user2->id], $this->match(base::KIND_INCLUDE, $config));
        $this->assertSame([(int)$user4->id], $this->match(base::KIND_EXCLUDE, ['field' => 'email', 'operator' => 'startswith', 'values' => ['x_']]));

        $this->assertSame(
            'Email address ends with "@example.com" or "@example.org" or "@x.cz"',
            $this->describe(base::KIND_INCLUDE, ['field' => 'email', 'operator' => 'endswith', 'values' => ['@example.com', '@example.org', '@x.cz']])
        );
        $this->assertSame(
            'Department is "Sales" or empty',
            $this->describe(base::KIND_INCLUDE, ['field' => 'department', 'operator' => 'equals', 'values' => ['Sales', '']])
        );
        $this->assertSame(
            'City/town starts with "&#60;b&#62;"',
            $this->describe(base::KIND_EXCLUDE, ['field' => 'city', 'operator' => 'startswith', 'values' => ['<b>']])
        );
        $this->assertSame(
            'Institution contains "abc"',
            $this->describe(base::KIND_EXCLUDE, ['field' => 'institution', 'operator' => 'contains', 'values' => ['abc']])
        );
    }

    public function test_menu(): void {
        $user1 = $this->getDataGenerator()->create_user(['country' => 'CZ', 'auth' => 'manual']);
        $user2 = $this->getDataGenerator()->create_user(['country' => 'DE', 'auth' => 'nologin']);
        $user3 = $this->getDataGenerator()->create_user(['country' => '', 'auth' => 'manual']);

        $this->assertSame([(int)$user1->id, (int)$user2->id], $this->match(base::KIND_INCLUDE, ['field' => 'country', 'operator' => 'equals', 'values' => ['CZ', 'DE']]));
        $this->assertSame([(int)$user3->id], $this->match(base::KIND_INCLUDE, ['field' => 'country', 'operator' => 'equals', 'values' => ['']]));
        $this->assertSame([(int)$user2->id], $this->match(base::KIND_EXCLUDE, ['field' => 'auth', 'operator' => 'equals', 'values' => ['nologin']]));

        $this->assertSame(
            'Country: Czechia or Germany',
            $this->describe(base::KIND_INCLUDE, ['field' => 'country', 'operator' => 'equals', 'values' => ['CZ', 'DE']])
        );
        $this->assertSame(
            'Language: xx',
            $this->describe(base::KIND_INCLUDE, ['field' => 'lang', 'operator' => 'equals', 'values' => ['xx']])
        );
    }

    public function test_date(): void {
        global $DB;

        $now = time();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'lastaccess', 0, ['id' => $user1->id]);
        $DB->set_field('user', 'lastaccess', $now - DAYSECS * 100, ['id' => $user2->id]);
        $DB->set_field('user', 'lastaccess', $now - DAYSECS * 2, ['id' => $user3->id]);

        $this->assertSame([(int)$user1->id], $this->match(base::KIND_INCLUDE, ['field' => 'lastaccess', 'operator' => 'never', 'values' => []]));
        $this->assertSame([(int)$user3->id], $this->match(base::KIND_INCLUDE, ['field' => 'lastaccess', 'operator' => 'lastdays', 'values' => [30]]));
        $config = ['field' => 'lastaccess', 'operator' => 'before', 'values' => [$now - DAYSECS * 30]];
        $this->assertSame([(int)$user1->id, (int)$user2->id], $this->match(base::KIND_INCLUDE, $config));
        $this->assertSame([(int)$user3->id], $this->match(base::KIND_EXCLUDE, ['field' => 'lastaccess', 'operator' => 'after', 'values' => [$now - DAYSECS * 30]]));

        $this->assertSame(
            'Last access: never',
            $this->describe(base::KIND_INCLUDE, ['field' => 'lastaccess', 'operator' => 'never', 'values' => []])
        );
        $this->assertSame(
            'First access within the last 30 days',
            $this->describe(base::KIND_INCLUDE, ['field' => 'firstaccess', 'operator' => 'lastdays', 'values' => [30]])
        );
        $date = userdate(1767225600, get_string('strftimedatetimeshort', 'core_langconfig'));
        $this->assertSame(
            'Time created before ' . $date,
            $this->describe(base::KIND_INCLUDE, ['field' => 'timecreated', 'operator' => 'before', 'values' => [1767225600]])
        );
    }

    public function test_invalid(): void {
        $invalids = [
            [],
            ['field' => 'description', 'operator' => 'equals', 'values' => ['x']],
            ['field' => 'email', 'operator' => 'before', 'values' => ['x']],
            ['field' => 'email', 'operator' => 'equals', 'values' => []],
            ['field' => 'email', 'operator' => 'equals', 'values' => 'x'],
            ['field' => 'email', 'operator' => 'equals', 'values' => [1]],
            ['field' => 'email', 'operator' => 'contains', 'values' => ['']],
            ['field' => 'country', 'operator' => 'contains', 'values' => ['CZ']],
            ['field' => 'lastaccess', 'operator' => 'never', 'values' => [1]],
            ['field' => 'lastaccess', 'operator' => 'before', 'values' => []],
            ['field' => 'lastaccess', 'operator' => 'before', 'values' => ['1']],
            ['field' => 'lastaccess', 'operator' => 'lastdays', 'values' => [0]],
            ['field' => 'lastaccess', 'operator' => 'lastdays', 'values' => [1, 2]],
        ];
        foreach ($invalids as $config) {
            try {
                $this->match(base::KIND_INCLUDE, $config);
                $this->fail('Exception expected: ' . json_encode($config));
            } catch (broken_rules_exception $ex) {
                $this->assertSame('error_rule_invalidconfig', $ex->errorcode);
            }
        }
        $this->assertSame('Invalid rule settings', $this->describe(base::KIND_INCLUDE, ['field' => 'description']));
    }

    public function test_form_data(): void {
        $ruleset = new ruleset();
        $uuid = $ruleset->add_rule(base::KIND_INCLUDE, 'tool_mucohort:userfield', []);
        $rule = $this->cohort->get_rule_instance($ruleset->get_rule($uuid));
        $this->assertTrue($rule->has_settings());

        $configs = [
            ['field' => 'email', 'operator' => 'endswith', 'values' => ['@example.com', '@example.org']],
            ['field' => 'country', 'operator' => 'equals', 'values' => ['CZ', 'DE']],
            ['field' => 'lastaccess', 'operator' => 'before', 'values' => [1767225600]],
            ['field' => 'lastaccess', 'operator' => 'lastdays', 'values' => [30]],
            ['field' => 'firstaccess', 'operator' => 'never', 'values' => []],
        ];
        foreach ($configs as $config) {
            $data = $rule->get_form_data($config);
            $this->assertSame($config, $rule->get_config((object)$data));
        }

        $data = (object)['field' => 'department', 'textoperator' => 'equals', 'textvalues' => " Sales \n\nHR\nSales\n"];
        $this->assertSame(['field' => 'department', 'operator' => 'equals', 'values' => ['Sales', 'HR']], $rule->get_config($data));

        $errors = [];
        $rule->validate_form_data(['field' => 'department', 'textoperator' => 'equals', 'textvalues' => " \n "], $errors);
        $this->assertArrayHasKey('textvalues', $errors);
        $errors = [];
        $rule->validate_form_data(['field' => 'country', 'values_country' => []], $errors);
        $this->assertArrayHasKey('values_country', $errors);
        $errors = [];
        $rule->validate_form_data(['field' => 'lastaccess', 'dateoperator' => 'lastdays', 'days' => null, 'date' => null], $errors);
        $this->assertArrayHasKey('days', $errors);
        $errors = [];
        $rule->validate_form_data(['field' => 'lastaccess', 'dateoperator' => 'never', 'days' => null, 'date' => null], $errors);
        $this->assertSame([], $errors);
    }
}
