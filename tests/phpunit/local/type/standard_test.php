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
use tool_mucohort\local\type\standard;

/**
 * Standard cohort type tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\local\type\standard
 */
final class standard_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_create(): void {
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);

        $cohort = standard::create((object)[
            'contextid' => $catcontext->id,
            'name' => 'Cohort 1',
            'idnumber' => '',
            'description' => 'Some text',
            'descriptionformat' => FORMAT_MARKDOWN,
            'visible' => 0,
        ]);
        $record = $cohort->get_cohort();
        $this->assertSame((string)$catcontext->id, (string)$record->contextid);
        $this->assertSame('Cohort 1', $record->name);
        $this->assertNull($record->idnumber);
        $this->assertSame('Some text', $record->description);
        $this->assertSame((string)FORMAT_MARKDOWN, (string)$record->descriptionformat);
        $this->assertSame('0', (string)$record->visible);
        $this->assertSame('', $record->component);

        $cohort = standard::create((object)[
            'contextid' => \core\context\system::instance()->id,
            'name' => 'Cohort 2',
            'idnumber' => 'c2',
        ]);
        $record = $cohort->get_cohort();
        $this->assertSame('c2', $record->idnumber);
        $this->assertSame('1', (string)$record->visible);

        $course = $this->getDataGenerator()->create_course();
        try {
            standard::create((object)[
                'contextid' => \core\context\course::instance($course->id)->id,
                'name' => 'Cohort 3',
            ]);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\core\exception\invalid_parameter_exception::class, $ex);
        }
    }

    public function test_members(): void {
        global $DB;

        $cohort = base::fetch($this->getDataGenerator()->create_cohort()->id);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->assertSame(\tool_mucohort\local\form\standard_member_add::class, $cohort->get_member_add_form_class());
        $this->assertSame(\tool_mucohort\local\form\member_remove::class, $cohort->get_member_remove_form_class());

        $cohort->add_members([$user1->id, $user2->id]);
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user1->id]));
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user2->id]));

        $cohort->remove_member($user1->id);
        $this->assertFalse($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user1->id]));
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user2->id]));
    }

    public function test_permissions(): void {
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);
        $cohort1 = base::fetch($this->getDataGenerator()->create_cohort(['contextid' => $catcontext->id])->id);
        $cohort2 = base::fetch($this->getDataGenerator()->create_cohort()->id);

        $viewer = $this->getDataGenerator()->create_user();
        $manager = $this->getDataGenerator()->create_user();
        $assigner = $this->getDataGenerator()->create_user();
        $memberviewer = $this->getDataGenerator()->create_user();
        $roleid1 = $this->getDataGenerator()->create_role();
        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid1, $catcontext);
        role_assign($roleid1, $viewer->id, $catcontext);
        $roleid2 = $this->getDataGenerator()->create_role();
        assign_capability('moodle/cohort:manage', CAP_ALLOW, $roleid2, $catcontext);
        role_assign($roleid2, $manager->id, $catcontext);
        $roleid3 = $this->getDataGenerator()->create_role();
        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid3, $catcontext);
        assign_capability('moodle/cohort:assign', CAP_ALLOW, $roleid3, $catcontext);
        role_assign($roleid3, $assigner->id, $catcontext);
        $roleid4 = $this->getDataGenerator()->create_role();
        assign_capability('tool/mucohort:viewmembers', CAP_ALLOW, $roleid4, $catcontext);
        role_assign($roleid4, $memberviewer->id, $catcontext);

        $this->setUser($viewer);
        $this->assertTrue($cohort1->can_view());
        $this->assertFalse($cohort2->can_view());
        $this->assertFalse($cohort1->can_manage());
        $this->assertFalse($cohort1->can_move());
        $this->assertFalse($cohort1->can_view_members());
        $this->assertFalse($cohort1->can_manage_members());

        $this->setUser($manager);
        $this->assertTrue($cohort1->can_view());
        $this->assertTrue($cohort1->can_manage());
        $this->assertTrue($cohort1->can_move());
        $this->assertFalse($cohort2->can_manage());
        $this->assertFalse($cohort1->can_view_members());
        $this->assertFalse($cohort1->can_manage_members());

        $this->setUser($assigner);
        $this->assertFalse($cohort1->can_manage());
        $this->assertTrue($cohort1->can_view_members());
        $this->assertTrue($cohort1->can_manage_members());
        $this->assertFalse($cohort2->can_manage_members());

        $this->setUser($memberviewer);
        $this->assertTrue($cohort1->can_view_members());
        $this->assertFalse($cohort1->can_manage_members());
    }
}
