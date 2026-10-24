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

use tool_mucohort\local\extdb_query;
use tool_mucohort\local\extdb_source;
use tool_mucohort\local\rule\base;
use tool_mucohort\local\ruleset;
use tool_mucohort\local\sync;
use tool_mucohort\local\type\rules;

/**
 * External database rule tests.
 *
 * The test external database is the Moodle database itself.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\local\rule\extdb
 * @covers \tool_mucohort\local\rule\include_extdb
 * @covers \tool_mucohort\local\rule\exclude_extdb
 * @covers \tool_mucohort\local\extdb_source
 * @covers \tool_mucohort\local\extdb_query
 */
final class extdb_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // The external database connection sees only committed data.
        $this->preventResetByRollback();
    }

    /**
     * Create cohort rule query.
     *
     * @param string $sql
     * @return \stdClass
     */
    private function create_query(string $sql): \stdClass {
        /** @var \tool_mulib_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_mulib');
        $server = $generator->create_extdb_server([]);
        return $generator->create_extdb_query([
            'serverid' => $server->id,
            'component' => 'tool_mucohort',
            'type' => 'rule',
            'sqlquery' => $sql,
        ]);
    }

    /**
     * Create cohort with active rules.
     *
     * @param ruleset $ruleset
     * @param array $data
     * @return rules
     */
    private function create_cohort(ruleset $ruleset, array $data = []): rules {
        $cohort = rules::create((object)($data + ['contextid' => \core\context\system::instance()->id, 'name' => 'Ext']));
        $cohort->set_pending_ruleset($ruleset);
        $cohort->approve_draft(sha1($cohort->get_rules_record()->pendingrulesjson));
        return \tool_mucohort\local\type\base::fetch($cohort->get_id());
    }

    /**
     * Returns member ids.
     *
     * @param rules $cohort
     * @return int[]
     */
    private function get_memberids(rules $cohort): array {
        global $DB;
        $ids = array_map('intval', $DB->get_fieldset('cohort_members', 'userid', ['cohortid' => $cohort->get_id()]));
        sort($ids);
        return $ids;
    }

    public function test_sync(): void {
        global $CFG, $DB;

        $user1 = $this->getDataGenerator()->create_user(['idnumber' => 'U1']);
        $user2 = $this->getDataGenerator()->create_user(['email' => 'Two@example.com']);
        $user3 = $this->getDataGenerator()->create_user(['department' => 'ext']);
        $user4 = $this->getDataGenerator()->create_user(['email' => 'same@example.com']);
        $user5 = $this->getDataGenerator()->create_user(['email' => 'same@example.com']);

        // Different identifiers, ambiguous email is not matched.
        $sql = "SELECT NULL AS useridnumber, NULL AS useremail, id AS userid FROM {$CFG->prefix}user WHERE department = 'ext'
                UNION ALL SELECT idnumber, NULL, NULL FROM {$CFG->prefix}user WHERE idnumber = 'U1'
                UNION ALL SELECT NULL, 'two@example.com', NULL
                UNION ALL SELECT NULL, 'same@example.com', NULL";
        $query = $this->create_query($sql);
        $types = rules::create_rule_types((object)['id' => null, 'contextid' => 1], (object)['id' => null]);
        $this->assertNotNull($types->get_class(base::KIND_INCLUDE, 'tool_mucohort:extdb'));

        $ruleset = new ruleset();
        $uuid = $ruleset->add_rule(base::KIND_INCLUDE, 'tool_mucohort:extdb', ['queryid' => (int)$query->id, 'maxage' => DAYSECS]);
        $cohort = $this->create_cohort($ruleset);
        $this->assertTrue(extdb_query::is_query_used((int)$query->id));
        $description = $cohort->get_rule_description($ruleset->get_rule($uuid));
        $this->assertSame('Returned by external database query: ' . $query->name, $description);

        // Nothing is fetched until the first sync, not ready rules are not broken.
        $problems = $cohort->get_problems($ruleset);
        $this->assertStringContainsString('Data from external database is not fetched yet', $problems[$uuid]);
        $this->assertSame(rules::STATE_ACTIVE, $cohort->get_state());

        // Preview fetches the data.
        $cohort->before_preview($ruleset);
        $this->assertSame([], $cohort->get_problems($ruleset));
        $DB->delete_records('tool_mucohort_extdb_user');
        $DB->delete_records('tool_mucohort_extdb');

        $result = sync::run($cohort, new \null_progress_trace());
        $this->assertSame(sync::STATUS_OK, (int)$result->status);
        $this->assertSame([(int)$user1->id, (int)$user2->id, (int)$user3->id], $this->get_memberids($cohort));
        $source = extdb_source::get_source((int)$cohort->get_mucohort()->id, (int)$query->id);
        $this->assertSame(sync::STATUS_OK, (int)$source->status);
        $this->assertNotNull($source->timefirstcompleted);
        $this->assertSame(4, $DB->count_records('tool_mucohort_extdb_user', ['extdbid' => $source->id]));
        $this->assertSame(1, $DB->count_records('tool_mucohort_extdb_user', ['extdbid' => $source->id, 'userid' => null]));

        // Data is not fetched again until it is too old.
        $DB->set_field('user', 'department', '', ['id' => $user3->id]);
        sync::run($cohort, new \null_progress_trace());
        $this->assertSame([(int)$user1->id, (int)$user2->id, (int)$user3->id], $this->get_memberids($cohort));
        $DB->set_field('tool_mucohort_extdb', 'timefetchstarted', time() - DAYSECS - 10, ['id' => $source->id]);
        sync::run($cohort, new \null_progress_trace());
        $this->assertSame([(int)$user1->id, (int)$user2->id], $this->get_memberids($cohort));

        // Failed fetch keeps the data and stops the sync.
        $DB->set_field('tool_mulib_extdb_query', 'sqlquery', 'SELECT xx FROM nothing', ['id' => $query->id]);
        $DB->set_field('tool_mucohort_extdb', 'timefetchstarted', time() - DAYSECS - 10, ['id' => $source->id]);
        cohort_remove_member($cohort->get_id(), $user1->id);
        $result = sync::run($cohort, new \null_progress_trace());
        $this->assertSame(sync::STATUS_BROKEN, (int)$result->status);
        $this->assertStringContainsString('Data cannot be fetched from external database', $result->message);
        $this->assertSame('0', (string)$cohort->get_rules_record()->broken);
        $this->assertSame([(int)$user2->id], $this->get_memberids($cohort));
        $this->assertSame(sync::STATUS_FAILED, (int)extdb_source::get_source((int)$cohort->get_mucohort()->id, (int)$query->id)->status);
        $this->assertSame(3, $DB->count_records('tool_mucohort_extdb_user', ['extdbid' => $source->id]));

        // Empty result is rejected.
        $sql = "SELECT id AS userid FROM {$CFG->prefix}user WHERE id = -1";
        $DB->set_field('tool_mulib_extdb_query', 'sqlquery', $sql, ['id' => $query->id]);
        $result = sync::run($cohort, new \null_progress_trace());
        $this->assertSame(sync::STATUS_BROKEN, (int)$result->status);
        $this->assertSame(sync::STATUS_REJECTED, (int)extdb_source::get_source((int)$cohort->get_mucohort()->id, (int)$query->id)->status);
        $this->assertSame(3, $DB->count_records('tool_mucohort_extdb_user', ['extdbid' => $source->id]));

        // Single user updates use the snapshot only.
        $this->assertTrue(sync::sync_user($cohort, (int)$user1->id));
        $this->assertSame([(int)$user1->id, (int)$user2->id], $this->get_memberids($cohort));

        // Sources not used in rules are removed after sync.
        $ruleset = new ruleset();
        $ruleset->add_rule(base::KIND_INCLUDE, 'tool_mucohort:allusers', []);
        $cohort->set_pending_ruleset($ruleset);
        $cohort->approve_draft(sha1($cohort->get_rules_record()->pendingrulesjson));
        sync::run(\tool_mucohort\local\type\base::fetch($cohort->get_id()), new \null_progress_trace());
        $this->assertFalse($DB->record_exists('tool_mucohort_extdb', ['id' => $source->id]));
        $this->assertSame(0, $DB->count_records('tool_mucohort_extdb_user', ['extdbid' => $source->id]));
        $this->assertFalse(extdb_query::is_query_used((int)$query->id));
    }

    public function test_form_data(): void {
        $query = $this->create_query('SELECT 1 AS userid');
        $cohort = rules::create((object)['contextid' => \core\context\system::instance()->id, 'name' => 'Ext']);
        $ruleset = new ruleset();
        $uuid = $ruleset->add_rule(base::KIND_EXCLUDE, 'tool_mucohort:extdb', []);
        $rule = $cohort->get_rule_instance($ruleset->get_rule($uuid));
        $config = ['maxage' => HOURSECS, 'queryid' => (int)$query->id];
        $this->assertSame($config, $rule->get_config((object)$rule->get_form_data($config)));
        $this->assertTrue($rule->has_settings());

        $ruleset->update_rule($uuid, ['queryid' => -1, 'maxage' => HOURSECS]);
        $problems = $cohort->get_problems($ruleset);
        $this->assertSame('External database query does not exist: -1', $problems[$uuid]);
        $this->assertSame('Missing external database query (-1)', $cohort->get_rule_description($ruleset->get_rule($uuid)));
    }
}
