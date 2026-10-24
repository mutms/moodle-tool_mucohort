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

namespace tool_mucohort\phpunit\local;

use tool_mucohort\local\access;
use tool_mucohort\local\management;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\curated;

/**
 * Access records tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\local\access
 * @covers \tool_mucohort\local\type\advanced
 */
final class access_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_set(): void {
        global $DB;

        $cohort = curated::create((object)['contextid' => \core\context\system::instance()->id, 'name' => 'Curated']);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->setAdminUser();

        $record = access::set($cohort, $user1->id, [], false);
        $this->assertSame((string)$user1->id, (string)$record->userid);
        $this->assertNull($record->iscurator);
        $this->assertSame('0', (string)$record->viewmembers);
        $this->assertSame('0', (string)$record->managemembers);
        $this->assertSame('0', (string)$record->manageexceptions);
        $this->assertSame((string)get_admin()->id, (string)$record->usermodified);

        // Update keeps the record.
        $record2 = access::set($cohort, $user1->id, ['viewmembers', 'managemembers'], true);
        $this->assertSame($record->id, $record2->id);
        $this->assertSame('1', (string)$record2->viewmembers);
        $this->assertSame('1', (string)$record2->managemembers);
        $this->assertSame('1', (string)$record2->iscurator);
        $this->assertSame($user1->id, access::get_curator($cohort)->id);

        // Only one curator per cohort.
        access::set($cohort, $user2->id, ['viewmembers', 'manageexceptions'], true);
        $this->assertSame($user2->id, access::get_curator($cohort)->id);
        $this->assertNull($DB->get_field('tool_mucohort_access', 'iscurator', ['id' => $record->id]));
        $this->assertTrue(access::has_flag($cohort, 'viewmembers', $user2->id));
        $this->assertTrue(access::has_flag($cohort, 'manageexceptions', $user2->id));
        $this->assertFalse(access::has_flag($cohort, 'managemembers', $user2->id));

        access::remove((int)$record->id);
        $this->assertNull(access::get_record($cohort, $user1->id));
        $this->assertNotNull(access::get_record($cohort, $user2->id));

        $standard = base::fetch($this->getDataGenerator()->create_cohort()->id);
        $this->assertNull(access::get_record($standard, $user1->id));
        $this->assertNull(access::get_curator($standard));
        try {
            access::set($standard, $user1->id, [], false);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\core\exception\coding_exception::class, $ex);
        }
        try {
            access::set($cohort, $user1->id, ['managemembers'], false);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\core\exception\coding_exception::class, $ex);
        }
        try {
            access::set($cohort, $user1->id, ['xx'], false);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\core\exception\coding_exception::class, $ex);
        }
    }

    public function test_are_flags_valid(): void {
        $this->assertTrue(access::are_flags_valid([]));
        $this->assertTrue(access::are_flags_valid(['viewmembers']));
        $this->assertTrue(access::are_flags_valid(['viewmembers', 'managemembers', 'manageexceptions']));
        $this->assertFalse(access::are_flags_valid(['managemembers']));
        $this->assertFalse(access::are_flags_valid(['manageexceptions']));
        $this->assertFalse(access::are_flags_valid(['managemembers', 'manageexceptions']));
    }

    public function test_permissions(): void {
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);
        $cohort = curated::create((object)['contextid' => $catcontext->id, 'name' => 'Curated']);
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        $this->setUser($user);
        $this->assertFalse($cohort->can_view());
        $this->assertFalse(management::can_view_index($catcontext));

        access::set($cohort, $user->id, [], false);
        $this->assertTrue($cohort->can_view());
        $this->assertFalse($cohort->can_view_members());
        $this->assertFalse($cohort->can_manage_members());
        $this->assertFalse($cohort->can_manage_exceptions());
        $this->assertFalse($cohort->can_manage_access());
        $this->assertFalse($cohort->can_manage());
        $this->assertTrue(management::can_view_index($catcontext));
        $this->assertTrue(management::can_view_index(\core\context\system::instance()));

        access::set($cohort, $user->id, ['viewmembers'], false);
        $this->assertTrue($cohort->can_view_members());
        $this->assertFalse($cohort->can_manage_members());

        access::set($cohort, $user->id, ['viewmembers', 'managemembers'], false);
        $this->assertTrue($cohort->can_view_members());
        $this->assertTrue($cohort->can_manage_members());
        $this->assertFalse($cohort->can_manage_exceptions());

        access::set($cohort, $user->id, ['viewmembers', 'manageexceptions'], false);
        $this->assertTrue($cohort->can_manage_exceptions());
        $this->assertFalse($cohort->can_manage_members());

        // Curator alone grants nothing beyond finding the cohort.
        access::set($cohort, $user->id, [], true);
        $this->assertTrue($cohort->can_view());
        $this->assertFalse($cohort->can_view_members());

        $this->setUser($other);
        $this->assertFalse($cohort->can_view());

        // Oversight capabilities.
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/mucohort:viewmembers', CAP_ALLOW, $roleid, $catcontext);
        assign_capability('tool/mucohort:managemembers', CAP_ALLOW, $roleid, $catcontext);
        assign_capability('tool/mucohort:manageexceptions', CAP_ALLOW, $roleid, $catcontext);
        assign_capability('tool/mucohort:manageaccess', CAP_ALLOW, $roleid, $catcontext);
        role_assign($roleid, $other->id, $catcontext);
        $this->assertTrue($cohort->can_view_members());
        $this->assertTrue($cohort->can_manage_members());
        $this->assertTrue($cohort->can_manage_exceptions());
        $this->assertTrue($cohort->can_manage_access());
    }
}
