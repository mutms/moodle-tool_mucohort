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

use tool_mucohort\local\membership_writer;

/**
 * Membership writer tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\local\membership_writer
 */
final class membership_writer_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_add_remove(): void {
        global $DB;

        $cohort = $this->getDataGenerator()->create_cohort();
        $user = $this->getDataGenerator()->create_user();

        $sink = $this->redirectEvents();
        $this->assertTrue(membership_writer::add_member($cohort->id, $user->id));
        $this->assertFalse(membership_writer::add_member($cohort->id, $user->id));
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\core\event\cohort_member_added::class, $events[0]);
        $this->assertSame(1, $DB->count_records('cohort_members', ['cohortid' => $cohort->id]));
        $sink->clear();

        $this->assertTrue(membership_writer::remove_member($cohort->id, $user->id));
        $this->assertFalse(membership_writer::remove_member($cohort->id, $user->id));
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\core\event\cohort_member_removed::class, $events[0]);
        $this->assertSame(0, $DB->count_records('cohort_members', ['cohortid' => $cohort->id]));
    }
}
