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

namespace tool_mucohort\phpunit;

use tool_mucohort\local\type\curated;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;

/**
 * Uninstallation tests.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[Group('MuTMS')]
#[CoversNothing]
final class uninstall_test extends \advanced_testcase {
    public function test_uninstall(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/admin/tool/mucohort/db/uninstall.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $standard = $this->getDataGenerator()->create_cohort();
        $plugin = $this->getDataGenerator()->create_cohort(['component' => 'tool_mutenancy']);
        $cohort = curated::create((object)['contextid' => \core\context\system::instance()->id, 'name' => 'Curated']);
        $user = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->get_id(), $user->id);
        $syscontext = \core\context\system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        role_assign($roleid, $user->id, $syscontext->id, 'tool_mucohort', 1);
        role_assign($roleid, $user->id, $syscontext->id);

        $this->assertTrue(xmldb_tool_mucohort_uninstall());

        $this->assertSame('', $DB->get_field('cohort', 'component', ['id' => $cohort->get_id()]));
        $this->assertSame('', $DB->get_field('cohort', 'component', ['id' => $standard->id]));
        $this->assertSame('tool_mutenancy', $DB->get_field('cohort', 'component', ['id' => $plugin->id]));
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user->id]));
        $this->assertFalse($DB->record_exists('role_assignments', ['component' => 'tool_mucohort']));
        $this->assertTrue($DB->record_exists('role_assignments', ['roleid' => $roleid, 'userid' => $user->id, 'component' => '']));
    }
}
