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

namespace tool_mucohort\phpunit\muform\userpicker;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use tool_mucohort\local\access;
use tool_mucohort\local\type\curated;
use tool_mucohort\muform\userpicker\access_add_userid;

/**
 * User granted access to an advanced cohort users picker source tests.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mucohort\muform\userpicker\access_add_userid::class)]
#[Group('MuTMS')]
final class access_add_userid_test extends \advanced_testcase {
    public function test_source(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cohort = curated::create((object)['contextid' => \core\context\system::instance()->id, 'name' => 'Curated']);
        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'First', 'lastname' => 'Prijmeni']);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Second', 'lastname' => 'Prijmeni']);
        $standard = $this->getDataGenerator()->create_cohort();

        $source = new access_add_userid($cohort->get_id());
        $this->assertSame([$cohort->get_id()], $source->get_args());
        $ids = array_column($source->search('Prijmeni', [], 0)['rows'], 'id');
        $this->assertSame([(int)$user1->id, (int)$user2->id], $ids);
        $this->assertSame([(int)$user2->id], array_column($source->search('Prijmeni', [(string)$user1->id], 0)['rows'], 'id'));
        $this->assertSame([(string)$user1->id], array_map('strval', array_keys($source->labels([(string)$user1->id, '0']))));
        $this->assertSame([], $source->validate([(string)$user1->id]));

        // Users with access are not candidates.
        access::set($cohort, (int)$user1->id, [], false);
        $this->assertSame([(int)$user2->id], array_column($source->search('Prijmeni', [], 0)['rows'], 'id'));
        $this->assertSame([], $source->labels([(string)$user1->id]));

        try {
            new access_add_userid((int)$standard->id);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\core\exception\required_capability_exception::class, $ex);
        }

        $this->setUser($user2);
        try {
            new access_add_userid($cohort->get_id());
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\core\exception\required_capability_exception::class, $ex);
        }
    }
}
