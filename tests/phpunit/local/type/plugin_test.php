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
use tool_mucohort\local\type\plugin;

/**
 * Plugin cohort type tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\local\type\plugin
 */
final class plugin_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_read_only(): void {
        global $DB;

        $this->setAdminUser();
        $record = $this->getDataGenerator()->create_cohort(['component' => 'tool_mutenancy']);
        $cohort = base::fetch($record->id);
        $this->assertInstanceOf(plugin::class, $cohort);

        $this->assertTrue($cohort->can_view());
        $this->assertFalse($cohort->can_manage());
        $this->assertFalse($cohort->can_move());
        $this->assertTrue($cohort->can_view_members());
        $this->assertFalse($cohort->can_manage_members());

        $this->assertNull($cohort->get_update_form_class());
        $this->assertNull($cohort->get_member_add_form_class());
        $this->assertNull($cohort->get_member_remove_form_class());

        $methods = [
            'update' => [(object)['name' => 'x']],
            'move' => [$record->contextid],
            'delete' => [],
            'add_members' => [[2]],
            'remove_member' => [2],
        ];
        foreach ($methods as $method => $args) {
            try {
                $cohort->$method(...$args);
                $this->fail('Exception expected');
            } catch (\core\exception\moodle_exception $ex) {
                $this->assertInstanceOf(\core\exception\coding_exception::class, $ex);
            }
        }
        $this->assertEquals($record, $DB->get_record('cohort', ['id' => $record->id]));
    }

    public function test_owner_hook(): void {
        global $OUTPUT;

        $this->setAdminUser();
        $record = $this->getDataGenerator()->create_cohort(['component' => 'tool_mutenancy']);
        $cohort = base::fetch($record->id);

        $this->redirectHook(\tool_mucohort\hook\cohort_owner::class, function (\tool_mucohort\hook\cohort_owner $hook) use ($record) {
            if ($hook->get_cohort()->id == $record->id) {
                $hook->set_description('Tenant <cohort>', new \core\url('/admin/tool/mutenancy/management/tenant.php', ['id' => 7]));
            }
        });
        $hook = $cohort->get_owner_hook();
        $this->assertSame('Tenant <cohort>', $hook->get_description());
        $this->assertStringContainsString('tenant.php?id=7', $hook->get_url()->out(false));

        $details = new \tool_mulib\output\entity_details();
        $cohort->add_overview_details($details);
        $html = $OUTPUT->render($details);
        $this->assertStringContainsString(get_string('owner', 'tool_mucohort'), $html);
        $this->assertStringContainsString('>Tenant &lt;cohort&gt;</a>', $html);

        // Nothing is shown without description.
        $this->redirectHook(\tool_mucohort\hook\cohort_owner::class, function () {
        });
        $details = new \tool_mulib\output\entity_details();
        $cohort->add_overview_details($details);
        $this->assertStringNotContainsString(get_string('owner', 'tool_mucohort'), $OUTPUT->render($details));
    }
}
