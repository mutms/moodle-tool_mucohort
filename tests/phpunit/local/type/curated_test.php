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

use tool_mucohort\local\type\base;
use tool_mucohort\local\type\curated;

/**
 * Curated cohort type tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\local\type\curated
 */
final class curated_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_create(): void {
        global $DB;

        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);
        $candidate = $this->getDataGenerator()->create_cohort();

        $sink = $this->redirectEvents();
        $cohort = curated::create((object)[
            'contextid' => $catcontext->id,
            'name' => 'Curated 1',
            'idnumber' => 'cur1',
            'description' => 'Some text',
            'descriptionformat' => FORMAT_HTML,
            'visible' => 0,
            'maxmembers' => 10,
            'candidatecohortid' => $candidate->id,
        ]);
        $events = $sink->get_events();
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\core\event\cohort_created::class, $events[0]);

        $this->assertInstanceOf(curated::class, $cohort);
        $record = $cohort->get_cohort();
        $this->assertSame('tool_mucohort', $record->component);
        $this->assertSame('Curated 1', $record->name);
        $this->assertSame('cur1', $record->idnumber);
        $this->assertSame('0', (string)$record->visible);
        $this->assertSame($catcontext->id, $cohort->get_context()->id);

        $mucohort = $cohort->get_mucohort();
        $this->assertSame('curated', $mucohort->type);
        $this->assertSame((string)$record->id, (string)$mucohort->cohortid);
        $this->assertSame((string)$candidate->id, (string)$mucohort->candidatecohortid);
        $this->assertSame('10', (string)$cohort->get_curated()->maxmembers);

        $cohort = curated::create((object)[
            'contextid' => $catcontext->id,
            'name' => 'Curated 2',
        ]);
        $this->assertNull($cohort->get_cohort()->idnumber);
        $this->assertNull($cohort->get_mucohort()->candidatecohortid);
        $this->assertNull($cohort->get_curated()->maxmembers);

        $cohort = curated::create((object)[
            'contextid' => $catcontext->id,
            'name' => 'Curated 3',
            'maxmembers' => 0,
        ]);
        $this->assertSame('0', (string)$cohort->get_curated()->maxmembers);
    }

    public function test_update(): void {
        $candidate = $this->getDataGenerator()->create_cohort();
        $cohort = curated::create((object)[
            'contextid' => \core\context\system::instance()->id,
            'name' => 'Curated 1',
            'maxmembers' => 10,
        ]);

        $cohort->update((object)[
            'name' => 'Curated 11',
            'maxmembers' => null,
            'candidatecohortid' => $candidate->id,
        ]);
        $this->assertSame('Curated 11', $cohort->get_cohort()->name);
        $this->assertNull($cohort->get_curated()->maxmembers);
        $this->assertSame((string)$candidate->id, (string)$cohort->get_mucohort()->candidatecohortid);

        $data = $cohort->get_update_form_data();
        $this->assertSame('Curated 11', $data['name']);
        $this->assertSame('', $data['idnumber']);
        $this->assertNull($data['maxmembers']);
        $this->assertSame((string)$candidate->id, (string)$data['candidatecohortid']);

        // Missing values stay unchanged.
        $cohort->update((object)['maxmembers' => 5]);
        $cohort->update((object)['name' => 'Curated 12']);
        $this->assertSame('5', (string)$cohort->get_curated()->maxmembers);
        $this->assertSame((string)$candidate->id, (string)$cohort->get_mucohort()->candidatecohortid);
    }

    public function test_delete(): void {
        global $DB;

        $cohort = curated::create((object)[
            'contextid' => \core\context\system::instance()->id,
            'name' => 'Curated 1',
        ]);
        $user = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->get_id(), $user->id);
        $mucohortid = $cohort->get_mucohort()->id;

        $cohort->delete();
        $this->assertFalse($DB->record_exists('cohort', ['id' => $cohort->get_id()]));
        $this->assertFalse($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id()]));
        $this->assertFalse($DB->record_exists('tool_mucohort', ['id' => $mucohortid]));
        $this->assertFalse($DB->record_exists('tool_mucohort_curated', ['mucohortid' => $mucohortid]));
    }

    public function test_permissions(): void {
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);
        $syscontext = \core\context\system::instance();
        $cohort = curated::create((object)['contextid' => $catcontext->id, 'name' => 'Curated 1']);

        $manager = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/mucohort:manage', CAP_ALLOW, $roleid, $syscontext);
        role_assign($roleid, $manager->id, $catcontext);
        $standardmanager = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/cohort:manage', CAP_ALLOW, $roleid, $syscontext);
        role_assign($roleid, $standardmanager->id, $catcontext);

        $this->setUser($manager);
        $this->assertTrue(curated::can_create($catcontext));
        $this->assertFalse(curated::can_create($syscontext));
        $this->assertTrue($cohort->can_manage());
        $this->assertTrue($cohort->can_move());
        $this->assertTrue($cohort->can_delete());
        $this->assertFalse($cohort->can_view_members());
        $this->assertFalse($cohort->can_manage_members());

        $this->setUser($standardmanager);
        $this->assertFalse(curated::can_create($catcontext));
        $this->assertFalse($cohort->can_manage());
        $this->assertFalse($cohort->can_delete());
    }

    public function test_members(): void {
        global $DB;

        $candidate = $this->getDataGenerator()->create_cohort();
        $cohort = curated::create((object)['contextid' => \core\context\system::instance()->id, 'name' => 'Curated']);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();
        $this->setAdminUser();

        $this->assertSame(\tool_mucohort\local\form\curated_member_add::class, $cohort->get_member_add_form_class());
        $this->assertSame(\tool_mucohort\local\form\member_remove::class, $cohort->get_member_remove_form_class());
        $this->assertNull($cohort->get_remaining_capacity());

        $cohort->add_members([$user1->id]);
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user1->id]));

        // Existing members and blocked users cannot be added.
        \tool_mucohort\local\exception::set($cohort, $user2->id, \tool_mucohort\local\exception::TYPE_BLOCKED, null);
        foreach ([$user1->id, $user2->id] as $userid) {
            try {
                $cohort->add_members([$userid]);
                $this->fail('Exception expected');
            } catch (\core\exception\moodle_exception $ex) {
                $this->assertSame('error_membernotallowed', $ex->errorcode);
            }
        }

        // Candidate cohort limits additions, but never removes.
        $cohort->update((object)['candidatecohortid' => $candidate->id]);
        try {
            $cohort->add_members([$user3->id]);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertSame('error_membernotallowed', $ex->errorcode);
        }
        cohort_add_member($candidate->id, $user3->id);
        $cohort->add_members([$user3->id]);
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user1->id]));
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user3->id]));

        // Deleted candidate cohort blocks all additions.
        cohort_add_member($candidate->id, $user4->id);
        cohort_delete_cohort($candidate);
        try {
            $cohort->add_members([$user4->id]);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertSame('error_membernotallowed', $ex->errorcode);
        }
        $cohort->update((object)['candidatecohortid' => null]);

        // Member limit refuses the whole batch.
        $cohort->update((object)['maxmembers' => 3]);
        $this->assertSame(1, $cohort->get_remaining_capacity());
        $user5 = $this->getDataGenerator()->create_user();
        try {
            $cohort->add_members([$user4->id, $user5->id]);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertSame('error_maxmembers', $ex->errorcode);
        }
        $this->assertFalse($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user4->id]));
        $cohort->add_members([$user4->id]);
        $this->assertSame(0, $cohort->get_remaining_capacity());

        // Lowering the limit never removes anybody.
        $cohort->update((object)['maxmembers' => 0]);
        $this->assertSame(3, $DB->count_records('cohort_members', ['cohortid' => $cohort->get_id()]));

        // Forced users cannot be removed directly.
        \tool_mucohort\local\exception::set($cohort, $user5->id, \tool_mucohort\local\exception::TYPE_FORCED, null);
        try {
            $cohort->remove_member($user5->id);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertSame('error_memberforced', $ex->errorcode);
        }
        $cohort->remove_member($user1->id);
        $this->assertFalse($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user1->id]));
    }

    public function test_suspended_members(): void {
        global $DB;

        $this->setAdminUser();
        $cohort = curated::create((object)['contextid' => \core\context\system::instance()->id, 'name' => 'Curated']);
        $user = $this->getDataGenerator()->create_user(['suspended' => 1]);

        $source = new \tool_mucohort\muform\autocompletemany\curated_member_add_userids($cohort->get_id());
        $this->assertSame([], $source->validate([(string)$user->id]));
        $this->assertArrayHasKey((string)$user->id, $source->labels([(string)$user->id]));
        $source = new \tool_mucohort\muform\autocompletemany\exception_add_userids($cohort->get_id());
        $this->assertSame([], $source->validate([(string)$user->id]));
        $standard = $this->getDataGenerator()->create_cohort();
        $source = new \tool_mucohort\muform\autocompletemany\standard_member_add_userids((int)$standard->id);
        $this->assertSame([], $source->validate([(string)$user->id]));
        $this->assertArrayHasKey((string)$user->id, $source->search('', 50, []));

        $cohort->add_members([$user->id]);
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user->id]));
    }

    public function test_get_curated_repair(): void {
        global $DB;

        $cohort = curated::create((object)['contextid' => \core\context\system::instance()->id, 'name' => 'Curated 1']);
        $DB->delete_records('tool_mucohort_curated', []);
        $cohort = base::fetch($cohort->get_id());
        $this->assertNull($cohort->get_curated()->maxmembers);
        $this->assertTrue($DB->record_exists('tool_mucohort_curated', ['mucohortid' => $cohort->get_mucohort()->id]));
    }

    public function test_other_tenant_members(): void {
        global $DB;

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

        $cohort = curated::create((object)['contextid' => \core\context\system::instance()->id, 'name' => 'Curated 1']);
        $cohort->add_members([$user0->id, $user1->id, $user2->id, $user3->id]);
        \tool_mucohort\local\exception::set($cohort, $user3->id, \tool_mucohort\local\exception::TYPE_FORCED, null);
        $this->assertSame([], $cohort->get_other_tenant_memberids());

        $cohort->move($catcontext->id);
        $cohort = base::fetch($cohort->get_id());
        $this->assertSame([(int)$user2->id, (int)$user3->id], $cohort->get_other_tenant_memberids());

        $this->assertSame(2, $cohort->remove_other_tenant_members());
        $this->assertSame([], $cohort->get_other_tenant_memberids());
        $this->assertSame(2, $DB->count_records('cohort_members', ['cohortid' => $cohort->get_id()]));
    }
}
