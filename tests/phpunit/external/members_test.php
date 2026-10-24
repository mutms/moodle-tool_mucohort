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

namespace tool_mucohort\phpunit\external;

use core_external\external_api;
use tool_mucohort\external\add_members;
use tool_mucohort\external\get_members;
use tool_mucohort\external\remove_members;
use tool_mucohort\local\access;
use tool_mucohort\local\exception;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\curated;

/**
 * Member web services tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\external\bulk_change_api
 * @covers \tool_mucohort\external\add_members
 * @covers \tool_mucohort\external\remove_members
 * @covers \tool_mucohort\external\get_members
 */
final class members_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_standard(): void {
        global $DB;

        $syscontext = \core\context\system::instance();
        $cohort = $this->getDataGenerator()->create_cohort();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $manager = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/cohort:assign', CAP_ALLOW, $roleid, $syscontext);
        role_assign($roleid, $manager->id, $syscontext);
        $this->setUser($manager);

        $result = add_members::execute($cohort->id, [$user1->id, $user2->id, $user1->id, -1]);
        $result = external_api::clean_returnvalue(add_members::execute_returns(), $result);
        $this->assertSame([
            ['userid' => (int)$user1->id, 'result' => 'ok'],
            ['userid' => (int)$user2->id, 'result' => 'ok'],
            ['userid' => (int)$user1->id, 'result' => 'duplicate'],
            ['userid' => -1, 'result' => 'notfound'],
        ], $result);
        $this->assertSame(2, $DB->count_records('cohort_members', ['cohortid' => $cohort->id]));

        $result = get_members::execute($cohort->id);
        $result = external_api::clean_returnvalue(get_members::execute_returns(), $result);
        $this->assertSame(2, $result['total']);
        $this->assertSame([(int)$user1->id, (int)$user2->id], array_column($result['members'], 'userid'));
        $this->assertFalse($result['members'][0]['forced']);
        $result = get_members::execute($cohort->id, 1, 1);
        $this->assertSame([(int)$user2->id], array_column($result['members'], 'userid'));

        $result = remove_members::execute($cohort->id, [$user1->id, $manager->id]);
        $result = external_api::clean_returnvalue(remove_members::execute_returns(), $result);
        $this->assertSame([
            ['userid' => (int)$user1->id, 'result' => 'ok'],
            ['userid' => (int)$manager->id, 'result' => 'already'],
        ], $result);
        $this->assertSame(1, $DB->count_records('cohort_members', ['cohortid' => $cohort->id]));

        $this->setUser($user1);
        try {
            add_members::execute($cohort->id, [$user1->id]);
            $this->fail('Exception expected');
        } catch (\core\exception\required_capability_exception $e) {
            $this->assertSame(get_capability_string('moodle/cohort:assign'), $e->a);
        }
        try {
            get_members::execute($cohort->id);
            $this->fail('Exception expected');
        } catch (\core\exception\required_capability_exception $e) {
            $this->assertSame(get_capability_string('tool/mucohort:viewmembers'), $e->a);
        }
    }

    public function test_curated(): void {
        global $DB;

        $this->setAdminUser();
        $candidate = $this->getDataGenerator()->create_cohort();
        $cohort = curated::create((object)[
            'contextid' => \core\context\system::instance()->id,
            'name' => 'Curated',
            'candidatecohortid' => $candidate->id,
        ]);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $outsider = $this->getDataGenerator()->create_user();
        foreach ([$user1, $user2, $user3] as $user) {
            cohort_add_member($candidate->id, $user->id);
        }
        exception::set($cohort, $user2->id, exception::TYPE_BLOCKED, null);
        exception::set($cohort, $user3->id, exception::TYPE_FORCED, null);
        $delegate = $this->getDataGenerator()->create_user();
        access::set($cohort, $delegate->id, ['viewmembers', 'managemembers'], false);
        $this->setUser($delegate);

        $result = add_members::execute($cohort->get_id(), [$user1->id, $user2->id, $user3->id, $outsider->id]);
        $result = external_api::clean_returnvalue(add_members::execute_returns(), $result);
        $this->assertSame(['ok', 'notallowed', 'already', 'notallowed'], array_column($result, 'result'));

        $result = get_members::execute($cohort->get_id());
        $result = external_api::clean_returnvalue(get_members::execute_returns(), $result);
        $this->assertSame(2, $result['total']);
        $forced = array_column($result['members'], 'forced', 'userid');
        $this->assertSame([(int)$user3->id => true, (int)$user1->id => false], $forced);

        $result = remove_members::execute($cohort->get_id(), [$user1->id, $user3->id]);
        $result = external_api::clean_returnvalue(remove_members::execute_returns(), $result);
        $this->assertSame(['ok', 'notallowed'], array_column($result, 'result'));
        $this->assertSame(1, $DB->count_records('cohort_members', ['cohortid' => $cohort->get_id()]));

        // Member limit refuses the whole addition.
        $this->setAdminUser();
        $cohort->update((object)['maxmembers' => 1]);
        $this->setUser($delegate);
        try {
            add_members::execute($cohort->get_id(), [$user1->id]);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $e) {
            $this->assertSame('error_maxmembers', $e->errorcode);
        }

        // Access records without the flag do not allow changes.
        $this->setAdminUser();
        access::set(base::fetch($cohort->get_id()), $delegate->id, ['viewmembers'], false);
        $this->setUser($delegate);
        $this->assertSame(1, get_members::execute($cohort->get_id())['total']);
        try {
            add_members::execute($cohort->get_id(), [$user1->id]);
            $this->fail('Exception expected');
        } catch (\core\exception\required_capability_exception $e) {
            $this->assertSame(get_capability_string('tool/mucohort:managemembers'), $e->a);
        }
    }
}
