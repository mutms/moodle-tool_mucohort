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

use tool_mucohort\local\conversion;
use tool_mucohort\local\role_sync;
use tool_mucohort\local\type\curated;
use tool_mucohort\local\type\standard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Role sync tests.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mucohort\local\role_sync::class)]
#[CoversClass(\tool_mucohort\task\role_sync::class)]
#[CoversClass(\tool_mucohort\task\role_maintenance::class)]
#[CoversClass(\tool_mucohort\event\role_added::class)]
#[CoversClass(\tool_mucohort\event\role_removed::class)]
#[Group('MuTMS')]
final class role_sync_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Does user have role assigned by role sync?
     *
     * @param int $userid
     * @param int $roleid
     * @param int $contextid
     * @return bool
     */
    private function has_role(int $userid, int $roleid, int $contextid): bool {
        global $DB;
        $params = ['userid' => $userid, 'roleid' => $roleid, 'contextid' => $contextid, 'component' => 'tool_mucohort'];
        return $DB->record_exists('role_assignments', $params);
    }

    /**
     * Run adhoc role sync tasks.
     */
    private function run_role_tasks(): void {
        ob_start();
        $this->runAdhocTasks(\tool_mucohort\task\role_sync::class);
        ob_end_clean();
    }

    public function test_sync(): void {
        global $DB;

        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'reviewer']);
        set_role_contextlevels($roleid, [CONTEXT_COURSECAT]);

        $cohort = curated::create((object)['contextid' => \core\context\system::instance()->id, 'name' => 'Curated']);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->get_id(), $user1->id);

        $this->assertArrayHasKey($roleid, role_sync::get_assignable_roles($catcontext));
        $this->assertArrayNotHasKey($roleid, role_sync::get_assignable_roles(\core\context\system::instance()));

        $sink = $this->redirectEvents();
        $record = role_sync::add($cohort, $roleid, $catcontext->id);
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof \tool_mucohort\event\role_added));
        $sink->close();
        $this->assertCount(1, $events);
        // Adding the same role again does nothing.
        $this->assertEquals($record, role_sync::add($cohort, $roleid, $catcontext->id));

        // Existing members get the role from adhoc task.
        $this->assertFalse($this->has_role((int)$user1->id, $roleid, $catcontext->id));
        $this->run_role_tasks();
        $this->assertTrue($this->has_role((int)$user1->id, $roleid, $catcontext->id));

        // Membership changes are applied immediately.
        cohort_add_member($cohort->get_id(), $user2->id);
        $this->assertTrue($this->has_role((int)$user2->id, $roleid, $catcontext->id));
        cohort_remove_member($cohort->get_id(), $user1->id);
        $this->assertFalse($this->has_role((int)$user1->id, $roleid, $catcontext->id));

        // Missed changes are fixed by scheduled task.
        $DB->delete_records('role_assignments', ['userid' => $user2->id, 'component' => 'tool_mucohort']);
        $ra = (object)['roleid' => $roleid, 'contextid' => $catcontext->id, 'userid' => $user1->id, 'component' => 'tool_mucohort',
            'itemid' => $record->id, 'timemodified' => time(), 'modifierid' => 0, 'sortorder' => 0];
        $DB->insert_record('role_assignments', $ra);
        $task = new \tool_mucohort\task\role_maintenance();
        $this->assertNotEmpty($task->get_name());
        ob_start();
        $task->execute();
        ob_end_clean();
        $this->assertTrue($this->has_role((int)$user2->id, $roleid, $catcontext->id));
        $this->assertFalse($this->has_role((int)$user1->id, $roleid, $catcontext->id));

        // Removing unassigns all members.
        $sink = $this->redirectEvents();
        role_sync::remove((int)$record->id);
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof \tool_mucohort\event\role_removed));
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertFalse($this->has_role((int)$user2->id, $roleid, $catcontext->id));
        $this->assertFalse($DB->record_exists('tool_mucohort_role', ['id' => $record->id]));
    }

    public function test_conversion_and_deletion(): void {
        global $DB;

        $this->setAdminUser();
        $roleid = $this->getDataGenerator()->create_role();
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        $syscontext = \core\context\system::instance();
        $user = $this->getDataGenerator()->create_user();

        $cohort = curated::create((object)['contextid' => $syscontext->id, 'name' => 'Curated']);
        cohort_add_member($cohort->get_id(), $user->id);
        role_sync::add($cohort, $roleid, $syscontext->id);
        $this->run_role_tasks();
        $this->assertTrue($this->has_role((int)$user->id, $roleid, $syscontext->id));

        // Conversion to standard removes roles.
        $standard = conversion::convert($cohort, standard::TYPE);
        $this->assertFalse($this->has_role((int)$user->id, $roleid, $syscontext->id));
        $this->assertSame(0, $DB->count_records('tool_mucohort_role'));

        // Cohort deletion removes roles.
        $cohort = conversion::convert($standard, curated::TYPE);
        role_sync::add($cohort, $roleid, $syscontext->id);
        $this->run_role_tasks();
        $this->assertTrue($this->has_role((int)$user->id, $roleid, $syscontext->id));
        $cohort->delete();
        $this->assertFalse($this->has_role((int)$user->id, $roleid, $syscontext->id));
        $this->assertSame(0, $DB->count_records('tool_mucohort_role'));
    }

    public function test_cohort_context(): void {
        $this->setAdminUser();
        $category1 = $this->getDataGenerator()->create_category();
        $category2 = $this->getDataGenerator()->create_category();
        $context1 = \core\context\coursecat::instance($category1->id);
        $context2 = \core\context\coursecat::instance($category2->id);
        $roleid = $this->getDataGenerator()->create_role();
        set_role_contextlevels($roleid, [CONTEXT_COURSECAT]);
        $user = $this->getDataGenerator()->create_user();

        $cohort = curated::create((object)['contextid' => $context1->id, 'name' => 'Curated']);
        cohort_add_member($cohort->get_id(), $user->id);

        // No context means cohort context, it is different from the same explicit context.
        $record = role_sync::add($cohort, $roleid, null);
        $this->assertNull($record->contextid);
        $this->assertEquals($record, role_sync::get_record($cohort, $roleid, null));
        $this->assertNull(role_sync::get_record($cohort, $roleid, $context1->id));
        $this->run_role_tasks();
        $this->assertTrue($this->has_role((int)$user->id, $roleid, $context1->id));

        // Role follows the moved cohort.
        $cohort->move($context2->id);
        $this->run_role_tasks();
        $this->assertFalse($this->has_role((int)$user->id, $roleid, $context1->id));
        $this->assertTrue($this->has_role((int)$user->id, $roleid, $context2->id));

        // Member changes use the cohort context.
        cohort_remove_member($cohort->get_id(), $user->id);
        $this->assertFalse($this->has_role((int)$user->id, $roleid, $context2->id));
        cohort_add_member($cohort->get_id(), $user->id);
        $this->assertTrue($this->has_role((int)$user->id, $roleid, $context2->id));
    }
}
