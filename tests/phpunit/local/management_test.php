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
use tool_mucohort\local\type\curated;

/**
 * Management helper tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\local\management
 */
final class management_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_get_cohort_url(): void {
        global $DB;

        $this->setAdminUser();
        $standard = $this->getDataGenerator()->create_cohort();
        $cohort = curated::create((object)['contextid' => \core\context\system::instance()->id, 'name' => 'Curated']);
        $record = $DB->get_record('cohort', ['id' => $cohort->get_id()], '*', MUST_EXIST);
        $user = $this->getDataGenerator()->create_user();

        $url = management::get_cohort_url($standard);
        $this->assertStringEndsWith('/admin/tool/mucohort/cohort.php', $url->get_path(false));
        $this->assertSame($standard->id, $url->get_param('id'));

        // Any access record allows viewing of advanced cohorts.
        $this->setUser($user);
        $this->assertNull(management::get_cohort_url($standard));
        $this->assertNull(management::get_cohort_url($record));
        $this->setAdminUser();
        access::set($cohort, $user->id, [], false);
        $this->setUser($user);
        $this->assertSame($record->id, management::get_cohort_url($record)->get_param('id'));
    }
}
