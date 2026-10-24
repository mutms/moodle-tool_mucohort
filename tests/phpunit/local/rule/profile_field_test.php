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

use tool_mucohort\local\profilefield_cache;
use tool_mucohort\local\rule\base;
use tool_mucohort\local\ruleset;
use tool_mucohort\local\sync;
use tool_mucohort\local\type\rules;

/**
 * Custom profile field rule tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\local\rule\profile_field
 * @covers \tool_mucohort\local\rule\include_profile_field
 * @covers \tool_mucohort\local\rule\exclude_profile_field
 * @covers \tool_mucohort\local\rule_not_ready_exception
 */
final class profile_field_test extends \advanced_testcase {
    /** @var rules */
    private rules $cohort;
    /** @var array fields indexed by shortname */
    private array $fields = [];

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $this->fields['dept'] = $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'dept', 'name' => 'Dept']);
        $this->fields['level'] = $generator->create_custom_profile_field(
            ['datatype' => 'menu', 'shortname' => 'level', 'name' => 'Level', 'param1' => "Junior\nSenior\nLead", 'defaultdata' => 'Junior']
        );
        $this->fields['vip'] = $generator->create_custom_profile_field(['datatype' => 'checkbox', 'shortname' => 'vip', 'name' => 'VIP']);
        $this->fields['hired'] = $generator->create_custom_profile_field(['datatype' => 'datetime', 'shortname' => 'hired', 'name' => 'Hired', 'param1' => 2000, 'param2' => 2050]);

        $this->cohort = rules::create((object)['contextid' => \core\context\system::instance()->id, 'name' => 'Rules']);
    }

    /**
     * Cache all test fields.
     */
    private function cache_fields(): void {
        foreach ($this->fields as $field) {
            profilefield_cache::populate_field((int)$field->id, new \null_progress_trace());
        }
    }

    /**
     * Returns ids of matched users, admin is ignored.
     *
     * @param string $kind
     * @param string $field
     * @param string $operator
     * @param array $values
     * @return int[]
     */
    private function match(string $kind, string $field, string $operator, array $values): array {
        global $DB;
        $ruleset = new ruleset();
        $config = ['fieldid' => (int)$this->fields[$field]->id, 'operator' => $operator, 'values' => $values];
        $uuid = $ruleset->add_rule($kind, 'tool_mucohort:profilefield', $config);
        $sql = $this->cohort->get_rule_match_sql($ruleset, $uuid)->wrap('SELECT u.id FROM {user} u WHERE ', ' AND u.id <> 2 ORDER BY u.id');
        return array_map('intval', $DB->get_fieldset_sql($sql->sql, $sql->params));
    }

    /**
     * Returns rule description.
     *
     * @param string $field
     * @param string $operator
     * @param array $values
     * @return string
     */
    private function describe(string $field, string $operator, array $values): string {
        $ruleset = new ruleset();
        $config = ['fieldid' => (int)$this->fields[$field]->id, 'operator' => $operator, 'values' => $values];
        $uuid = $ruleset->add_rule(base::KIND_INCLUDE, 'tool_mucohort:profilefield', $config);
        return $this->cohort->get_rule_description($ruleset->get_rule($uuid));
    }

    public function test_matching(): void {
        $generator = $this->getDataGenerator();
        $user1 = $generator->create_user(
            ['profile_field_dept' => 'Sales', 'profile_field_level' => 'Senior', 'profile_field_vip' => 1, 'profile_field_hired' => time() - DAYSECS * 10]
        );
        $user2 = $generator->create_user(['profile_field_dept' => 'Sales team', 'profile_field_level' => 'Lead', 'profile_field_hired' => 946771200]);
        $user3 = $generator->create_user();
        $this->cache_fields();

        $this->assertSame([(int)$user1->id], $this->match(base::KIND_INCLUDE, 'dept', 'equals', ['sales']));
        $this->assertSame([(int)$user1->id, (int)$user2->id], $this->match(base::KIND_INCLUDE, 'dept', 'startswith', ['Sal']));
        $this->assertSame([(int)$user3->id], $this->match(base::KIND_INCLUDE, 'dept', 'empty', []));
        $this->assertSame([(int)$user2->id], $this->match(base::KIND_EXCLUDE, 'dept', 'contains', ['team']));

        $this->assertSame([(int)$user1->id, (int)$user2->id], $this->match(base::KIND_INCLUDE, 'level', 'equals', ['Senior', 'Lead']));
        // Default value of users without data.
        $this->assertSame([(int)$user3->id], $this->match(base::KIND_INCLUDE, 'level', 'equals', ['Junior']));

        $this->assertSame([(int)$user1->id], $this->match(base::KIND_INCLUDE, 'vip', 'checked', []));
        $this->assertSame([(int)$user2->id, (int)$user3->id], $this->match(base::KIND_INCLUDE, 'vip', 'notchecked', []));

        $this->assertSame([(int)$user3->id], $this->match(base::KIND_INCLUDE, 'hired', 'notset', []));
        $this->assertSame([(int)$user1->id], $this->match(base::KIND_INCLUDE, 'hired', 'lastdays', [30]));
        $this->assertSame([(int)$user2->id], $this->match(base::KIND_INCLUDE, 'hired', 'before', [1000000000]));
        $this->assertSame([(int)$user1->id], $this->match(base::KIND_INCLUDE, 'hired', 'after', [1000000000]));
    }

    public function test_descriptions(): void {
        $this->assertSame('Dept is "Sales" or "HR"', $this->describe('dept', 'equals', ['Sales', 'HR']));
        $this->assertSame('Dept is empty', $this->describe('dept', 'empty', []));
        $this->assertSame('Level: Senior or Lead', $this->describe('level', 'equals', ['Senior', 'Lead']));
        $this->assertSame('VIP is checked', $this->describe('vip', 'checked', []));
        $this->assertSame('VIP is not checked', $this->describe('vip', 'notchecked', []));
        $this->assertSame('Hired is not set', $this->describe('hired', 'notset', []));
        $this->assertSame('Hired within the last 30 days', $this->describe('hired', 'lastdays', [30]));

        $ruleset = new ruleset();
        $uuid = $ruleset->add_rule(base::KIND_INCLUDE, 'tool_mucohort:profilefield', ['fieldid' => 999999, 'operator' => 'equals', 'values' => ['x']]);
        $this->assertSame('Missing profile field (999999)', $this->cohort->get_rule_description($ruleset->get_rule($uuid)));
        $problems = $this->cohort->get_problems($ruleset);
        $this->assertSame('Profile field does not exist: 999999', $problems[$uuid]);
    }

    public function test_not_ready(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user(['profile_field_dept' => 'Sales']);
        $ruleset = new ruleset();
        $ruleset->add_rule(base::KIND_INCLUDE, 'tool_mucohort:profilefield', ['fieldid' => (int)$this->fields['dept']->id, 'operator' => 'equals', 'values' => ['Sales']]);
        $rule = $this->cohort->get_rule_instance($ruleset->get_rules()[array_key_first($ruleset->get_rules())]);

        // Not ready rules are not broken, they can be approved.
        $this->assertCount(1, $this->cohort->get_problems($ruleset));
        $this->assertSame([], $this->cohort->get_problems($ruleset, false));
        $this->cohort->set_pending_ruleset($ruleset);
        $this->cohort->approve_draft(sha1($this->cohort->get_rules_record()->pendingrulesjson));
        $this->assertSame(rules::STATE_ACTIVE, $this->cohort->get_state());

        // Preview caches values.
        $this->cohort->before_preview($ruleset);
        $this->assertTrue(profilefield_cache::is_ready((int)$this->fields['dept']->id));
        profilefield_cache::remove_field((int)$this->fields['dept']->id);

        // Values are cached before full sync.
        $result = sync::run($this->cohort, new \null_progress_trace());
        $this->assertSame(sync::STATUS_OK, (int)$result->status);
        $this->assertTrue(profilefield_cache::is_ready((int)$this->fields['dept']->id));
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $this->cohort->get_id(), 'userid' => $user->id]));

        // Single user sync uses current value.
        $DB->set_field('user_info_data', 'data', 'HR', ['userid' => $user->id, 'fieldid' => $this->fields['dept']->id]);
        $this->assertTrue(sync::sync_user($this->cohort, (int)$user->id));
        $this->assertFalse($DB->record_exists('cohort_members', ['cohortid' => $this->cohort->get_id(), 'userid' => $user->id]));
        $this->assertNotNull($rule);
    }

    public function test_form_data(): void {
        $ruleset = new ruleset();
        $uuid = $ruleset->add_rule(base::KIND_INCLUDE, 'tool_mucohort:profilefield', []);
        $rule = $this->cohort->get_rule_instance($ruleset->get_rule($uuid));
        $this->assertTrue($rule->has_settings());
        $this->assertTrue($rule::is_available());

        $configs = [
            ['fieldid' => (int)$this->fields['dept']->id, 'operator' => 'endswith', 'values' => ['a', 'b']],
            ['fieldid' => (int)$this->fields['dept']->id, 'operator' => 'empty', 'values' => []],
            ['fieldid' => (int)$this->fields['level']->id, 'operator' => 'equals', 'values' => ['Lead']],
            ['fieldid' => (int)$this->fields['vip']->id, 'operator' => 'notchecked', 'values' => []],
            ['fieldid' => (int)$this->fields['hired']->id, 'operator' => 'before', 'values' => [1767225600]],
            ['fieldid' => (int)$this->fields['hired']->id, 'operator' => 'lastdays', 'values' => [7]],
        ];
        foreach ($configs as $config) {
            $data = $rule->get_form_data($config);
            $this->assertSame($config, $rule->get_config((object)$data));
            $errors = [];
            $rule->validate_form_data($data, $errors);
            $this->assertSame([], $errors);
        }
        $errors = [];
        $rule->validate_form_data(['fieldid' => ''], $errors);
        $this->assertArrayHasKey('fieldid', $errors);
    }
}
