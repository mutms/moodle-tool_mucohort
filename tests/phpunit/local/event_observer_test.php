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
use tool_mucohort\local\conversion;
use tool_mucohort\local\exception;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\curated;
use tool_mucohort\local\type\standard;

/**
 * Events and event observer tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\local\event_observer
 * @covers \tool_mucohort\event\access_granted
 * @covers \tool_mucohort\event\access_updated
 * @covers \tool_mucohort\event\access_revoked
 * @covers \tool_mucohort\event\exception_created
 * @covers \tool_mucohort\event\exception_updated
 * @covers \tool_mucohort\event\exception_deleted
 * @covers \tool_mucohort\event\cohort_converted
 */
final class event_observer_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_events(): void {
        $this->setAdminUser();
        $cohort = curated::create((object)['contextid' => \core\context\system::instance()->id, 'name' => 'Curated']);
        $user = $this->getDataGenerator()->create_user();

        $sink = $this->redirectEvents();
        $record = access::set($cohort, $user->id, [], false);
        access::set($cohort, $user->id, ['viewmembers'], true);
        access::remove((int)$record->id);
        $exc = exception::set($cohort, $user->id, exception::TYPE_FORCED, null);
        exception::set($cohort, $user->id, exception::TYPE_BLOCKED, 'x');
        exception::remove((int)$exc->id);
        $events = array_values(array_filter($sink->get_events(), fn($e) => str_starts_with(get_class($e), 'tool_mucohort')));
        $sink->close();

        $this->assertCount(6, $events);
        $this->assertInstanceOf(\tool_mucohort\event\access_granted::class, $events[0]);
        $this->assertInstanceOf(\tool_mucohort\event\access_updated::class, $events[1]);
        $this->assertInstanceOf(\tool_mucohort\event\access_revoked::class, $events[2]);
        $this->assertInstanceOf(\tool_mucohort\event\exception_created::class, $events[3]);
        $this->assertInstanceOf(\tool_mucohort\event\exception_updated::class, $events[4]);
        $this->assertInstanceOf(\tool_mucohort\event\exception_deleted::class, $events[5]);
        foreach ($events as $event) {
            $this->assertSame($cohort->get_context()->id, $event->get_context()->id);
            $this->assertSame((int)$user->id, (int)$event->relateduserid);
            $this->assertSame($cohort->get_id(), $event->other['cohortid']);
            $this->assertNotEmpty($event->get_name());
            $this->assertNotEmpty($event->get_description());
            $this->assertInstanceOf(\core\url::class, $event->get_url());
        }

        $sink = $this->redirectEvents();
        conversion::convert($cohort, standard::TYPE);
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof \tool_mucohort\event\cohort_converted));
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertSame('curated', $events[0]->other['fromtype']);
        $this->assertSame('standard', $events[0]->other['totype']);
        $this->assertSame($cohort->get_id(), (int)$events[0]->objectid);
    }

    public function test_user_deleted(): void {
        global $DB;

        $this->setAdminUser();
        $cohort = curated::create((object)['contextid' => \core\context\system::instance()->id, 'name' => 'Curated']);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        access::set($cohort, $user1->id, ['viewmembers'], true);
        exception::set($cohort, $user1->id, exception::TYPE_BLOCKED, 'x');
        $this->setUser($user1);
        access::set($cohort, $user2->id, [], false);
        exception::set($cohort, $user2->id, exception::TYPE_FORCED, null);

        delete_user($user1);
        $mucohortid = $cohort->get_mucohort()->id;
        $this->assertFalse($DB->record_exists('tool_mucohort_access', ['userid' => $user1->id]));
        $this->assertFalse($DB->record_exists('tool_mucohort_exception', ['userid' => $user1->id]));
        $this->assertFalse($DB->record_exists('tool_mucohort_access', ['usermodified' => $user1->id]));
        $this->assertFalse($DB->record_exists('tool_mucohort_exception', ['usermodified' => $user1->id]));
        $this->assertTrue($DB->record_exists('tool_mucohort_access', ['mucohortid' => $mucohortid, 'userid' => $user2->id]));
        $this->assertTrue($DB->record_exists('tool_mucohort_exception', ['mucohortid' => $mucohortid, 'userid' => $user2->id]));
    }

    public function test_cohort_deleted(): void {
        global $DB;

        $this->setAdminUser();
        $cohort = curated::create((object)['contextid' => \core\context\system::instance()->id, 'name' => 'Curated']);
        $user = $this->getDataGenerator()->create_user();
        access::set($cohort, $user->id, [], false);
        $mucohortid = $cohort->get_mucohort()->id;

        // Deleted via core API, not via cohort type.
        cohort_delete_cohort($cohort->get_cohort());
        $this->assertFalse($DB->record_exists('tool_mucohort', ['id' => $mucohortid]));
        $this->assertFalse($DB->record_exists('tool_mucohort_curated', ['mucohortid' => $mucohortid]));
        $this->assertFalse($DB->record_exists('tool_mucohort_access', ['mucohortid' => $mucohortid]));
    }
}
