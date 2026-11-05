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

use tool_mucohort\local\compare;
use tool_mucohort\local\exception;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\curated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cohort comparison tests.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mucohort\local\compare::class)]
#[Group('MuTMS')]
final class compare_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_parse_withids(): void {
        $this->assertSame([1, 22, 3], compare::parse_withids('1,22, 3,x,0,-1,1'));
        $this->assertSame([], compare::parse_withids(''));
    }

    public function test_standard(): void {
        global $DB;

        $target = $this->getDataGenerator()->create_cohort();
        $other1 = $this->getDataGenerator()->create_cohort();
        $other2 = $this->getDataGenerator()->create_cohort();
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user();
        }
        cohort_add_member($target->id, $users[0]->id);
        cohort_add_member($target->id, $users[1]->id);
        cohort_add_member($other1->id, $users[1]->id);
        cohort_add_member($other1->id, $users[2]->id);
        cohort_add_member($other2->id, $users[3]->id);

        $compare = new compare(base::fetch($target->id), [$other1->id, $other2->id, $target->id]);
        $this->assertSame([(int)$other1->id, (int)$other2->id], $compare->get_withids());
        $this->assertSame(2, $compare->count(compare::MODE_MISSING));
        $this->assertSame(1, $compare->count(compare::MODE_SHARED));
        $this->assertSame(1, $compare->count(compare::MODE_EXTRA));

        $this->assertSame(['missing' => 2, 'addable' => 2, 'capacity' => null], $compare->get_add_plan());
        $this->assertSame(2, $compare->add_missing());
        $this->assertSame(0, $compare->count(compare::MODE_MISSING));

        $this->assertSame(['extra' => 1, 'removable' => 1], $compare->get_remove_plan());
        $this->assertSame(1, $compare->remove_extra());
        $this->assertFalse($DB->record_exists('cohort_members', ['cohortid' => $target->id, 'userid' => $users[0]->id]));
        $this->assertSame(3, $DB->count_records('cohort_members', ['cohortid' => $target->id]));

        $compare = new compare(base::fetch($target->id), []);
        $this->assertSame(0, $compare->count(compare::MODE_MISSING));
        $this->assertSame(0, $compare->count(compare::MODE_EXTRA));
    }

    public function test_curated(): void {
        global $DB;

        $this->setAdminUser();
        $candidate = $this->getDataGenerator()->create_cohort();
        $other = $this->getDataGenerator()->create_cohort();
        $target = curated::create((object)[
            'contextid' => \core\context\system::instance()->id,
            'name' => 'Curated',
            'candidatecohortid' => $candidate->id,
        ]);
        $users = [];
        for ($i = 0; $i < 6; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user();
            cohort_add_member($candidate->id, $users[$i]->id);
        }
        $outsider = $this->getDataGenerator()->create_user();
        cohort_add_member($other->id, $users[0]->id);
        cohort_add_member($other->id, $users[1]->id);
        cohort_add_member($other->id, $users[2]->id);
        cohort_add_member($other->id, $outsider->id);
        exception::set($target, $users[1]->id, exception::TYPE_BLOCKED, null);
        exception::set($target, $users[4]->id, exception::TYPE_FORCED, null);
        cohort_add_member($target->get_id(), $users[5]->id);

        $compare = new compare($target, [$other->id]);
        // Outsider is not in candidate cohort and user 1 is blocked.
        $this->assertSame(['missing' => 4, 'addable' => 2, 'capacity' => null], $compare->get_add_plan());
        // Forced user 4 cannot be removed.
        $this->assertSame(['extra' => 2, 'removable' => 1], $compare->get_remove_plan());

        // Member limit refuses the whole addition.
        $target->update((object)['maxmembers' => 3]);
        $compare = new compare(base::fetch($target->get_id()), [$other->id]);
        $this->assertSame(1, $compare->get_add_plan()['capacity']);
        try {
            $compare->add_missing();
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertSame('error_maxmembers', $ex->errorcode);
        }
        $this->assertSame(2, $DB->count_records('cohort_members', ['cohortid' => $target->get_id()]));

        $target->update((object)['maxmembers' => null]);
        $compare = new compare(base::fetch($target->get_id()), [$other->id]);
        $this->assertSame(2, $compare->add_missing());
        $this->assertSame(1, $compare->remove_extra());
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $target->get_id(), 'userid' => $users[4]->id]));
        $this->assertFalse($DB->record_exists('cohort_members', ['cohortid' => $target->get_id(), 'userid' => $users[5]->id]));
        $this->assertFalse($DB->record_exists('cohort_members', ['cohortid' => $target->get_id(), 'userid' => $outsider->id]));
    }
}
