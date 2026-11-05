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
use tool_mucohort\external\get_exceptions;
use tool_mucohort\external\remove_exceptions;
use tool_mucohort\external\set_exceptions;
use tool_mucohort\local\access;
use tool_mucohort\local\exception;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\curated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Exception web services tests.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mucohort\external\set_exceptions::class)]
#[CoversClass(\tool_mucohort\external\remove_exceptions::class)]
#[CoversClass(\tool_mucohort\external\get_exceptions::class)]
#[Group('MuTMS')]
final class exceptions_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_exceptions(): void {
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
        cohort_add_member($cohort->get_id(), $user2->id);
        $delegate = $this->getDataGenerator()->create_user();
        access::set($cohort, $delegate->id, ['viewmembers', 'manageexceptions'], false);
        $viewer = $this->getDataGenerator()->create_user();
        access::set($cohort, $viewer->id, ['viewmembers'], false);
        $this->setUser($delegate);

        // Forced users ignore the candidate cohort.
        $result = set_exceptions::execute($cohort->get_id(), [$user1->id], 'forced', ' VIP ');
        $result = external_api::clean_returnvalue(set_exceptions::execute_returns(), $result);
        $this->assertSame([['userid' => (int)$user1->id, 'result' => 'ok']], $result);
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user1->id]));

        $result = set_exceptions::execute($cohort->get_id(), [$user1->id, $user2->id], 'blocked', null);
        $this->assertSame(['ok', 'ok'], array_column($result, 'result'));
        $this->assertFalse($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user2->id]));

        $result = get_exceptions::execute($cohort->get_id());
        $result = external_api::clean_returnvalue(get_exceptions::execute_returns(), $result);
        $this->assertSame([(int)$user1->id, (int)$user2->id], array_column($result, 'userid'));
        $this->assertSame(['blocked', 'blocked'], array_column($result, 'type'));
        $this->assertSame([null, null], array_column($result, 'reason'));

        set_exceptions::execute($cohort->get_id(), [$user1->id], 'forced', 'VIP');
        $this->assertSame('VIP', get_exceptions::execute($cohort->get_id())[0]['reason']);

        // Reasons are visible to exception managers only.
        $this->setUser($viewer);
        $result = get_exceptions::execute($cohort->get_id());
        $this->assertSame('forced', $result[0]['type']);
        $this->assertNull($result[0]['reason']);
        try {
            set_exceptions::execute($cohort->get_id(), [$user2->id], 'forced', null);
            $this->fail('Exception expected');
        } catch (\core\exception\required_capability_exception $e) {
            $this->assertSame(get_capability_string('tool/mucohort:manageexceptions'), $e->a);
        }

        $this->setUser($delegate);
        try {
            set_exceptions::execute($cohort->get_id(), [$user2->id], 'other', null);
            $this->fail('Exception expected');
        } catch (\core\exception\invalid_parameter_exception $e) {
            $this->assertStringContainsString('Invalid exception type', $e->getMessage());
        }

        $result = remove_exceptions::execute($cohort->get_id(), [$user1->id, $viewer->id]);
        $result = external_api::clean_returnvalue(remove_exceptions::execute_returns(), $result);
        $this->assertSame(['ok', 'already'], array_column($result, 'result'));
        $this->assertNull(exception::get_record(base::fetch($cohort->get_id()), (int)$user1->id));
        // Membership is not changed.
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user1->id]));
    }

    public function test_standard(): void {
        $this->setAdminUser();
        $cohort = $this->getDataGenerator()->create_cohort();
        $user = $this->getDataGenerator()->create_user();

        try {
            get_exceptions::execute($cohort->id);
            $this->fail('Exception expected');
        } catch (\core\exception\invalid_parameter_exception $e) {
            $this->assertStringContainsString('advanced cohorts only', $e->getMessage());
        }
        try {
            set_exceptions::execute($cohort->id, [$user->id], 'forced', null);
            $this->fail('Exception expected');
        } catch (\core\exception\required_capability_exception $e) {
            $this->assertSame(get_capability_string('tool/mucohort:manageexceptions'), $e->a);
        }
    }
}
