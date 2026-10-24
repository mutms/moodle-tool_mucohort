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

namespace tool_mucohort\phpunit\local\type;

use tool_mucohort\local\exception;
use tool_mucohort\local\rule\base as rule;
use tool_mucohort\local\broken_rules_exception;
use tool_mucohort\local\ruleset;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\rules;
use tool_mulib\local\sql;

/**
 * Rule-based cohort type tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\local\type\rules
 * @covers \tool_mucohort\local\rule\include_all_users
 * @covers \tool_mucohort\local\rule\include_suspended
 * @covers \tool_mucohort\local\rule\exclude_suspended
 * @covers \tool_mucohort\local\rule\include_cohort
 * @covers \tool_mucohort\local\rule\exclude_cohort
 * @covers \tool_mucohort\local\rule\base
 * @covers \tool_mucohort\hook\rule_types
 */
final class rules_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create rule-based cohort.
     *
     * @param array $data
     * @return rules
     */
    private function create_cohort(array $data = []): rules {
        $data = (object)array_merge([
            'contextid' => \core\context\system::instance()->id,
            'name' => 'Rules cohort',
        ], $data);
        return rules::create($data);
    }

    /**
     * Returns ids of users matching condition.
     *
     * @param sql $sql condition on user alias "u"
     * @return int[]
     */
    private function get_userids(sql $sql): array {
        global $DB;
        $sql = $sql->wrap("SELECT u.id FROM {user} u WHERE ", " ORDER BY u.id");
        return array_map('intval', $DB->get_fieldset_sql($sql->sql, $sql->params));
    }

    public function test_create(): void {
        global $DB;

        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);
        $candidate = $this->getDataGenerator()->create_cohort();

        $cohort = $this->create_cohort([
            'contextid' => $catcontext->id,
            'name' => 'Rules 1',
            'idnumber' => 'r1',
            'candidatecohortid' => $candidate->id,
        ]);
        $this->assertInstanceOf(rules::class, $cohort);
        $this->assertInstanceOf(rules::class, base::fetch($cohort->get_id()));
        $this->assertSame('tool_mucohort', $cohort->get_cohort()->component);
        $this->assertSame('r1', $cohort->get_cohort()->idnumber);
        $this->assertSame($catcontext->id, $cohort->get_context()->id);
        $this->assertSame('rules', $cohort->get_mucohort()->type);
        $this->assertSame((string)$candidate->id, (string)$cohort->get_mucohort()->candidatecohortid);

        $record = $cohort->get_rules_record();
        $this->assertSame('0', (string)$record->broken);
        $this->assertNull($record->rulesjson);
        $this->assertNull($record->pendingrulesjson);
        $this->assertNull($record->syncinterval);
        $this->assertSame('1', (string)$record->syncpriority);
        $this->assertNull($record->timenextsync);
        $this->assertNull($cohort->get_active_ruleset());
        $this->assertNull($cohort->get_pending_ruleset());

        // Missing record is repaired.
        $DB->delete_records('tool_mucohort_rules', ['id' => $record->id]);
        $cohort = base::fetch($cohort->get_id());
        $this->assertNull($cohort->get_rules_record()->rulesjson);
        $this->assertSame(1, $DB->count_records('tool_mucohort_rules', ['mucohortid' => $cohort->get_mucohort()->id]));
    }

    public function test_delete(): void {
        global $DB;

        $cohort = $this->create_cohort();
        $mucohortid = $cohort->get_mucohort()->id;
        $cohort->delete();
        $this->assertFalse($DB->record_exists('cohort', ['id' => $cohort->get_id()]));
        $this->assertFalse($DB->record_exists('tool_mucohort', ['id' => $mucohortid]));
        $this->assertFalse($DB->record_exists('tool_mucohort_rules', ['mucohortid' => $mucohortid]));
    }

    public function test_pending_ruleset(): void {
        global $DB;

        $cohort = $this->create_cohort();
        $ruleset = new ruleset();
        $uuid = $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:allusers', []);
        $cohort->set_pending_ruleset($ruleset);
        $this->assertSame($ruleset->to_json(), $cohort->get_rules_record()->pendingrulesjson);
        $this->assertSame([$uuid], array_keys($cohort->get_pending_ruleset()->get_rules()));
        $this->assertNull($cohort->get_active_ruleset());

        $cohort->set_pending_ruleset(null);
        $this->assertNull($cohort->get_pending_ruleset());

        $DB->set_field('tool_mucohort_rules', 'rulesjson', 'xx', ['mucohortid' => $cohort->get_mucohort()->id]);
        $cohort = base::fetch($cohort->get_id());
        $this->expectException(broken_rules_exception::class);
        $cohort->get_active_ruleset();
    }

    public function test_rule_types(): void {
        $cohort = $this->create_cohort();
        $types = $cohort->get_rule_types();
        $this->assertSame([
            'tool_mucohort:allusers' => \tool_mucohort\local\rule\include_all_users::class,
            'tool_mucohort:suspended' => \tool_mucohort\local\rule\include_suspended::class,
            'tool_mucohort:userfield' => \tool_mucohort\local\rule\include_user_field::class,
            'tool_mucohort:cohort' => \tool_mucohort\local\rule\include_cohort::class,
            'tool_mucohort:courseenrolment' => \tool_mucohort\local\rule\include_course_enrolment::class,
            'tool_mucohort:coursecompletion' => \tool_mucohort\local\rule\include_course_completion::class,
            'tool_mucohort:programcompletion' => \tool_mucohort\local\rule\include_program_completion::class,
            'tool_mucohort:certification' => \tool_mucohort\local\rule\include_certification::class,
        ], $types->get_classes(rule::KIND_INCLUDE));
        $this->assertSame([
            'tool_mucohort:suspended' => \tool_mucohort\local\rule\exclude_suspended::class,
            'tool_mucohort:userfield' => \tool_mucohort\local\rule\exclude_user_field::class,
            'tool_mucohort:cohort' => \tool_mucohort\local\rule\exclude_cohort::class,
            'tool_mucohort:courseenrolment' => \tool_mucohort\local\rule\exclude_course_enrolment::class,
            'tool_mucohort:coursecompletion' => \tool_mucohort\local\rule\exclude_course_completion::class,
            'tool_mucohort:programcompletion' => \tool_mucohort\local\rule\exclude_program_completion::class,
            'tool_mucohort:certification' => \tool_mucohort\local\rule\exclude_certification::class,
        ], $types->get_classes(rule::KIND_EXCLUDE));
        $this->assertSame(\tool_mucohort\local\rule\exclude_cohort::class, $types->get_class(rule::KIND_EXCLUDE, 'tool_mucohort:cohort'));
        $this->assertNull($types->get_class(rule::KIND_INCLUDE, 'tool_mucohort:xyz'));
        $this->assertEquals($cohort->get_cohort(), $types->get_cohort());
        $this->assertEquals($cohort->get_mucohort(), $types->get_mucohort());

        // Callbacks may remove rule types.
        $this->redirectHook(\tool_mucohort\hook\rule_types::class, function (\tool_mucohort\hook\rule_types $hook) {
            $hook->unregister('tool_mucohort', 'cohort', rule::KIND_EXCLUDE);
            $hook->unregister('tool_mucohort', 'suspended');
        });
        $cohort = base::fetch($cohort->get_id());
        $types = $cohort->get_rule_types();
        $this->assertSame(
            ['tool_mucohort:allusers', 'tool_mucohort:userfield', 'tool_mucohort:cohort', 'tool_mucohort:courseenrolment',
                'tool_mucohort:coursecompletion', 'tool_mucohort:programcompletion', 'tool_mucohort:certification'],
            array_keys($types->get_classes(rule::KIND_INCLUDE))
        );
        $this->assertSame(
            ['tool_mucohort:userfield', 'tool_mucohort:courseenrolment', 'tool_mucohort:coursecompletion', 'tool_mucohort:programcompletion',
                'tool_mucohort:certification'],
            array_keys($types->get_classes(rule::KIND_EXCLUDE))
        );

        // Unsaved cohorts are supported.
        $types = rules::create_rule_types(
            (object)['id' => null, 'contextid' => \core\context\system::instance()->id, 'name' => 'New'],
            (object)['id' => null, 'type' => rules::TYPE]
        );
        $this->assertNull($types->get_cohort()->id);
    }

    public function test_target(): void {
        global $CFG;

        $cohort = $this->create_cohort();
        $other = $this->getDataGenerator()->create_cohort();
        $other2 = $this->getDataGenerator()->create_cohort();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user(['suspended' => 1]);
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();
        $user5 = $this->getDataGenerator()->create_user(['confirmed' => 0]);
        $user6 = $this->getDataGenerator()->create_user();
        delete_user($user6);
        $admin = get_admin();

        cohort_add_member($other->id, $user1->id);
        cohort_add_member($other->id, $user2->id);
        cohort_add_member($other->id, $user5->id);
        cohort_add_member($other2->id, $user1->id);

        // No include rules means nobody.
        $ruleset = new ruleset();
        $this->assertSame([], $this->get_userids($cohort->get_target_sql($ruleset)));
        $ruleset->add_rule(rule::KIND_EXCLUDE, 'tool_mucohort:suspended', []);
        $this->assertSame([], $this->get_userids($cohort->get_target_sql($ruleset)));

        $allusers = $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:allusers', []);
        $expected = [(int)$admin->id, (int)$user1->id, (int)$user3->id, (int)$user4->id];
        $this->assertSame($expected, $this->get_userids($cohort->get_target_sql($ruleset)));

        // Suspended users only.
        $suspended = new ruleset();
        $suspended->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:allusers', []);
        $suspended->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:suspended', []);
        $this->assertSame([(int)$user2->id], $this->get_userids($cohort->get_target_sql($suspended)));
        $suspended->add_rule(rule::KIND_EXCLUDE, 'tool_mucohort:suspended', []);
        $this->assertSame([], $this->get_userids($cohort->get_target_sql($suspended)));

        $ruleset->delete_rule($allusers);
        $incohort = $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:cohort', ['cohortids' => [(int)$other->id, (int)$other2->id]]);
        $this->assertSame([(int)$user1->id], $this->get_userids($cohort->get_target_sql($ruleset)));

        $excohort = $ruleset->add_rule(rule::KIND_EXCLUDE, 'tool_mucohort:cohort', ['cohortid' => (int)$other2->id]);
        $this->assertSame([], $this->get_userids($cohort->get_target_sql($ruleset)));

        // Exceptions.
        exception::set($cohort, $user4->id, exception::TYPE_FORCED, null);
        exception::set($cohort, $user1->id, exception::TYPE_FORCED, null);
        $this->assertSame([(int)$user1->id, (int)$user4->id], $this->get_userids($cohort->get_target_sql($ruleset)));
        exception::set($cohort, $user1->id, exception::TYPE_BLOCKED, null);
        $this->assertSame([(int)$user4->id], $this->get_userids($cohort->get_target_sql($ruleset)));

        // Single user mode.
        $this->assertSame([(int)$user4->id], $this->get_userids($cohort->get_target_sql($ruleset, (int)$user4->id)));
        $this->assertSame([], $this->get_userids($cohort->get_target_sql($ruleset, (int)$user1->id)));
        $this->assertSame([], $this->get_userids($cohort->get_target_sql($ruleset, (int)$user3->id)));

        // Guest is never a member.
        exception::set($cohort, (int)$CFG->siteguest, exception::TYPE_FORCED, null);
        $this->assertSame([(int)$user4->id], $this->get_userids($cohort->get_target_sql($ruleset)));

        // Rule ids are referenced in SQL comments.
        $sql = $cohort->get_target_sql($ruleset);
        $this->assertStringContainsString("-- rule $incohort\n", $sql->sql);
        $this->assertStringContainsString("-- rule $excohort\n", $sql->sql);

        // Per rule matching.
        $this->assertSame([(int)$user1->id, (int)$user2->id], $this->get_userids($cohort->get_rule_match_sql($ruleset, $incohort)));
        $this->assertSame([(int)$user1->id], $this->get_userids($cohort->get_rule_match_sql($ruleset, $excohort)));
        $this->assertSame([], $this->get_userids($cohort->get_rule_match_sql($ruleset, $excohort, (int)$user3->id)));
    }

    public function test_changes(): void {
        $candidate = $this->getDataGenerator()->create_cohort();
        $cohort = $this->create_cohort();
        $other = $this->getDataGenerator()->create_cohort();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();
        $user5 = $this->getDataGenerator()->create_user();

        foreach ([$user1, $user2, $user3] as $user) {
            cohort_add_member($other->id, $user->id);
        }
        // User 4 is a member not matching rules, user 2 is already a member.
        cohort_add_member($cohort->get_id(), $user2->id);
        cohort_add_member($cohort->get_id(), $user4->id);

        $ruleset = new ruleset();
        $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:cohort', ['cohortids' => [(int)$other->id]]);

        $this->assertSame([(int)$user1->id, (int)$user3->id], $this->get_userids($cohort->get_additions_sql($ruleset)));
        $this->assertSame([(int)$user4->id], $this->get_userids($cohort->get_removals_sql($ruleset)));
        $this->assertSame([(int)$user1->id], $this->get_userids($cohort->get_additions_sql($ruleset, (int)$user1->id)));
        $this->assertSame([], $this->get_userids($cohort->get_additions_sql($ruleset, (int)$user2->id)));
        $this->assertSame([(int)$user4->id], $this->get_userids($cohort->get_removals_sql($ruleset, (int)$user4->id)));
        $this->assertSame([], $this->get_userids($cohort->get_removals_sql($ruleset, (int)$user2->id)));

        // Add-only keeps members not matched by rules, full mode removals may be still previewed.
        $cohort->update((object)['synctype' => rules::SYNCTYPE_ADDONLY]);
        $this->assertSame(rules::SYNCTYPE_ADDONLY, $cohort->get_synctype());
        $removals = $cohort->get_removals_sql($ruleset, null, rules::SYNCTYPE_FULL);
        $this->assertSame([(int)$user4->id], $this->get_userids($removals));
        $this->assertSame([(int)$user1->id, (int)$user3->id], $this->get_userids($cohort->get_additions_sql($ruleset)));
        $this->assertSame([], $this->get_userids($cohort->get_removals_sql($ruleset)));

        // Blocked users are removed even in add-only mode.
        exception::set($cohort, $user5->id, exception::TYPE_FORCED, null);
        exception::set($cohort, $user4->id, exception::TYPE_BLOCKED, null);
        cohort_add_member($cohort->get_id(), $user4->id);
        $this->assertSame([(int)$user4->id], $this->get_userids($cohort->get_removals_sql($ruleset)));
        $cohort->update((object)['synctype' => rules::SYNCTYPE_FULL]);
        $this->assertSame([(int)$user4->id], $this->get_userids($cohort->get_removals_sql($ruleset)));

        // Candidate cohort limits additions only, forced users are exempt.
        $cohort->update((object)['candidatecohortid' => $candidate->id]);
        cohort_remove_member($cohort->get_id(), $user5->id);
        cohort_add_member($candidate->id, $user3->id);
        $this->assertSame([(int)$user3->id, (int)$user5->id], $this->get_userids($cohort->get_additions_sql($ruleset)));
        $this->assertSame([(int)$user4->id], $this->get_userids($cohort->get_removals_sql($ruleset)));

        $this->assertSame([(int)$user3->id], $this->get_userids($cohort->get_candidate_match_sql()));

        // Deleted candidate cohort blocks additions except forced.
        cohort_delete_cohort($candidate);
        $cohort = base::fetch($cohort->get_id());
        $this->assertSame([(int)$user5->id], $this->get_userids($cohort->get_additions_sql($ruleset)));
    }

    public function test_tenant(): void {
        if (!\tool_mulib\local\mulib::is_mutenancy_available()) {
            $this->markTestSkipped('tenant support not available');
        }
        \tool_mutenancy\local\tenancy::activate();

        /** @var \tool_mutenancy_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_mutenancy');
        $tenant1 = $tenantgenerator->create_tenant();
        $tenant2 = $tenantgenerator->create_tenant();
        $catcontext = \core\context\coursecat::instance($tenant1->categoryid);

        $user0 = $this->getDataGenerator()->create_user();
        $user1 = $this->getDataGenerator()->create_user(['tenantid' => $tenant1->id]);
        $user2 = $this->getDataGenerator()->create_user(['tenantid' => $tenant2->id]);
        $user3 = $this->getDataGenerator()->create_user(['tenantid' => $tenant2->id]);
        $other = $this->getDataGenerator()->create_cohort();
        foreach ([$user0, $user1, $user2] as $user) {
            cohort_add_member($other->id, $user->id);
        }

        $cohort = $this->create_cohort(['contextid' => $catcontext->id]);
        $syscohort = $this->create_cohort();
        exception::set($cohort, $user3->id, exception::TYPE_FORCED, null);

        $ruleset = new ruleset();
        $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:cohort', ['cohortids' => [(int)$other->id]]);
        $this->assertSame([(int)$user0->id, (int)$user1->id], $this->get_userids($cohort->get_target_sql($ruleset)));
        $this->assertSame([(int)$user0->id, (int)$user1->id, (int)$user2->id], $this->get_userids($syscohort->get_target_sql($ruleset)));

        // Users of other tenants are removed even in add-only mode, forced users included.
        $cohort->update((object)['synctype' => rules::SYNCTYPE_ADDONLY]);
        $syscohort->update((object)['synctype' => rules::SYNCTYPE_ADDONLY]);
        $this->assertSame([(int)$user3->id], $this->get_userids($cohort->get_removals_sql($ruleset)));
        $this->assertSame([], $this->get_userids($syscohort->get_removals_sql($ruleset)));
    }

    public function test_broken(): void {
        $cohort = $this->create_cohort();
        $other = $this->getDataGenerator()->create_cohort(['name' => 'Other cohort']);

        $ruleset = new ruleset();
        $ok = $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:cohort', ['cohortids' => [(int)$other->id]]);
        $missing = $ruleset->add_rule(rule::KIND_EXCLUDE, 'tool_mucohort:cohort', ['cohortid' => (int)$other->id + 100]);
        $unknown = $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:xyz', []);
        $invalid = $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:cohort', ['cohortids' => []]);

        $this->assertSame('Member of any of these cohorts: Other cohort', $cohort->get_rule_description($ruleset->get_rule($ok)));
        $this->assertSame('Member of cohort: Missing cohort (' . ($other->id + 100) . ')', $cohort->get_rule_description($ruleset->get_rule($missing)));
        $this->assertSame('tool_mucohort:xyz', $cohort->get_rule_description($ruleset->get_rule($unknown)));

        $problems = $cohort->get_problems($ruleset);
        $this->assertSame([$unknown, $invalid, $missing], array_keys($problems));
        $this->assertSame('Rule type is not available: tool_mucohort:xyz', $problems[$unknown]);
        $this->assertSame('Invalid rule settings', $problems[$invalid]);
        $this->assertSame('Cohort does not exist: ' . ($other->id + 100), $problems[$missing]);

        try {
            $cohort->get_target_sql($ruleset);
            $this->fail('Exception expected');
        } catch (broken_rules_exception $ex) {
            $this->assertInstanceOf(broken_rules_exception::class, $ex);
        }

        $ruleset->delete_rule($missing);
        $ruleset->delete_rule($unknown);
        $ruleset->delete_rule($invalid);
        $this->assertSame([], $cohort->get_problems($ruleset));

        // Descriptions of built-in rules.
        $suspended = $ruleset->add_rule(rule::KIND_EXCLUDE, 'tool_mucohort:suspended', []);
        $insuspended = $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:suspended', []);
        $allusers = $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:allusers', []);
        $this->assertSame('User account is suspended', $cohort->get_rule_description($ruleset->get_rule($suspended)));
        $this->assertSame('User account is suspended', $cohort->get_rule_description($ruleset->get_rule($insuspended)));
        $this->assertSame('All users', $cohort->get_rule_description($ruleset->get_rule($allusers)));
    }

    public function test_draft_and_state(): void {
        global $DB;

        $cohort = $this->create_cohort();
        $this->assertSame(rules::STATE_INACTIVE, $cohort->get_state());
        $this->assertSame([], $cohort->start_draft()->get_rules());
        $this->assertNotNull($cohort->get_pending_ruleset());
        $cohort->set_pending_ruleset(null);

        $active = new ruleset();
        $uuid = $active->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:allusers', []);
        $DB->set_field('tool_mucohort_rules', 'rulesjson', $active->to_json(), ['mucohortid' => $cohort->get_mucohort()->id]);
        $cohort = base::fetch($cohort->get_id());
        $this->assertSame(rules::STATE_ACTIVE, $cohort->get_state());
        $cohort->update((object)['synctype' => rules::SYNCTYPE_NONE]);
        $this->assertSame(rules::STATE_DISABLED, $cohort->get_state());
        $cohort->update((object)['synctype' => rules::SYNCTYPE_FULL]);

        // Draft starts as a copy of active rules.
        $draft = $cohort->start_draft();
        $this->assertSame($active->to_json(), $draft->to_json());
        $this->assertSame($active->to_json(), $cohort->get_rules_record()->pendingrulesjson);
        $draft->add_rule(rule::KIND_EXCLUDE, 'tool_mucohort:suspended', []);
        $cohort->set_pending_ruleset($draft);
        // Existing draft is not replaced.
        $this->assertCount(2, $cohort->start_draft()->get_rules());
        $this->assertSame([$uuid], array_keys($cohort->get_active_ruleset()->get_rules()));

        $active->add_rule(rule::KIND_EXCLUDE, 'tool_mucohort:cohort', ['cohortid' => 999999]);
        $DB->set_field('tool_mucohort_rules', 'rulesjson', $active->to_json(), ['mucohortid' => $cohort->get_mucohort()->id]);
        $cohort = base::fetch($cohort->get_id());
        $this->assertSame(rules::STATE_BROKEN, $cohort->get_state());

        $DB->set_field('tool_mucohort_rules', 'rulesjson', 'xx', ['mucohortid' => $cohort->get_mucohort()->id]);
        $cohort = base::fetch($cohort->get_id());
        $this->assertSame(rules::STATE_BROKEN, $cohort->get_state());
    }

    public function test_update_and_permissions(): void {
        $candidate = $this->getDataGenerator()->create_cohort();
        $cohort = $this->create_cohort();
        $cohort->update((object)['name' => 'Rules 2', 'candidatecohortid' => $candidate->id]);
        $this->assertSame('Rules 2', $cohort->get_cohort()->name);
        $this->assertSame((string)$candidate->id, (string)$cohort->get_mucohort()->candidatecohortid);
        $this->assertSame((string)$candidate->id, (string)$cohort->get_update_form_data()['candidatecohortid']);
        $this->assertSame(rules::SYNCTYPE_FULL, $cohort->get_update_form_data()['synctype']);
        $cohort->update((object)['synctype' => rules::SYNCTYPE_NONE]);
        $this->assertSame(rules::SYNCTYPE_NONE, $cohort->get_update_form_data()['synctype']);
        $this->assertSame(rules::SYNCTYPE_NONE, $cohort->get_synctype());
        $cohort->update((object)['synctype' => 99]);
        $this->assertSame(rules::SYNCTYPE_NONE, $cohort->get_synctype());
        $this->assertSame(rules::SYNCTYPE_ADDONLY, $this->create_cohort(['synctype' => rules::SYNCTYPE_ADDONLY])->get_synctype());
        $this->assertSame([rules::SYNCTYPE_FULL, rules::SYNCTYPE_ADDONLY, rules::SYNCTYPE_NONE], array_keys(rules::get_synctypes()));
        $this->assertSame(array_keys(rules::get_synctypes()), array_keys(rules::get_synctype_descriptions()));

        $this->setAdminUser();
        $this->assertTrue($cohort->can_manage_rules());
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse($cohort->can_manage_rules());
    }

    public function test_approve_draft(): void {
        $cohort = $this->create_cohort();
        $other = $this->getDataGenerator()->create_cohort();

        $draft = new ruleset();
        $uuid = $draft->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:cohort', ['cohortids' => [(int)$other->id]]);
        $cohort->set_pending_ruleset($draft);
        $hash = sha1($cohort->get_rules_record()->pendingrulesjson);

        try {
            $cohort->approve_draft(sha1('xx'));
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertSame('error_rule_changed', $ex->errorcode);
        }

        $cohort->approve_draft($hash);
        $this->assertNull($cohort->get_pending_ruleset());
        $this->assertSame([$uuid], array_keys($cohort->get_active_ruleset()->get_rules()));
        $this->assertSame(rules::STATE_ACTIVE, $cohort->get_state());

        // Empty drafts cannot be approved, exclude rules alone are fine.
        $cohort->set_pending_ruleset(new ruleset());
        try {
            $cohort->approve_draft(sha1($cohort->get_rules_record()->pendingrulesjson));
            $this->fail('Exception expected');
        } catch (broken_rules_exception $ex) {
            $this->assertSame('rules_approve_norules', $ex->errorcode);
        }
        $excludeonly = new ruleset();
        $excludeonly->add_rule(rule::KIND_EXCLUDE, 'tool_mucohort:suspended', []);
        $cohort->set_pending_ruleset($excludeonly);
        $cohort->approve_draft(sha1($cohort->get_rules_record()->pendingrulesjson));
        $this->assertSame([], $cohort->get_active_ruleset()->get_rules(rule::KIND_INCLUDE));
        $cohort->set_pending_ruleset($draft);
        $cohort->approve_draft(sha1($cohort->get_rules_record()->pendingrulesjson));
        $cohort->set_pending_ruleset(null);

        // Broken drafts cannot be approved.
        $draft = $cohort->start_draft();
        $draft->add_rule(rule::KIND_EXCLUDE, 'tool_mucohort:cohort', ['cohortid' => (int)$other->id + 100]);
        $cohort->set_pending_ruleset($draft);
        try {
            $cohort->approve_draft(sha1($cohort->get_rules_record()->pendingrulesjson));
            $this->fail('Exception expected');
        } catch (broken_rules_exception $ex) {
            $this->assertSame('rules_approve_broken', $ex->errorcode);
        }
        $this->assertCount(1, $cohort->get_active_ruleset()->get_rules());

        $this->assertSame(0, rules::count_users(new sql('1=2')));
        $this->assertGreaterThan(0, rules::count_users(new sql('1=1')));
    }

    public function test_exceptions_without_sync(): void {
        global $DB;

        $cohort = $this->create_cohort(['synctype' => rules::SYNCTYPE_NONE]);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->get_id(), $user2->id);

        // Exceptions change membership immediately, sync type does not matter.
        exception::set($cohort, $user1->id, exception::TYPE_FORCED, null);
        exception::set($cohort, $user2->id, exception::TYPE_BLOCKED, null);
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user1->id]));
        $this->assertFalse($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user2->id]));
    }
}
