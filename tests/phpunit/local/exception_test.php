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

use tool_mucohort\local\exception;
use tool_mucohort\local\type\curated;

/**
 * Exceptions tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\local\exception
 */
final class exception_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_set(): void {
        global $DB;

        $cohort = curated::create((object)['contextid' => \core\context\system::instance()->id, 'name' => 'Curated', 'maxmembers' => 0]);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->get_id(), $user2->id);
        $this->setAdminUser();

        // Forcing adds immediately, even to a closed cohort.
        $record = exception::set($cohort, $user1->id, exception::TYPE_FORCED, ' ');
        $this->assertSame((string)exception::TYPE_FORCED, (string)$record->type);
        $this->assertNull($record->reason);
        $this->assertSame((string)get_admin()->id, (string)$record->usermodified);
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user1->id]));

        // Blocking removes immediately, the same record is updated.
        $record2 = exception::set($cohort, $user1->id, exception::TYPE_BLOCKED, 'Cheated in exam');
        $this->assertSame($record->id, $record2->id);
        $this->assertSame('Cheated in exam', $record2->reason);
        $this->assertFalse($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user1->id]));

        exception::set($cohort, $user2->id, exception::TYPE_FORCED, null);
        $this->assertSame(1, exception::count($cohort, exception::TYPE_FORCED));
        $this->assertSame(1, exception::count($cohort, exception::TYPE_BLOCKED));

        // Removing of exceptions does not change membership.
        exception::remove((int)$record->id);
        $this->assertNull(exception::get_record($cohort, $user1->id));
        $this->assertFalse($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user1->id]));
        exception::remove((int)exception::get_record($cohort, $user2->id)->id);
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user2->id]));

        try {
            exception::set($cohort, $user1->id, 3, null);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\core\exception\coding_exception::class, $ex);
        }
    }
}
