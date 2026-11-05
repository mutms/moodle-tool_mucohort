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

use tool_mucohort\local\webservice_guard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Web service guard tests.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mucohort\local\webservice_guard::class)]
#[Group('MuTMS')]
final class webservice_guard_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Execute web service function the same way as core.
     *
     * @param string $function
     * @param array $args
     * @return array
     */
    private function call(string $function, array $args): array {
        $_POST['sesskey'] = sesskey();
        return \core_external\external_api::call_external_function($function, $args);
    }

    public function test_check(): void {
        global $DB;

        $this->setAdminUser();

        $cohort1 = $this->getDataGenerator()->create_cohort(['idnumber' => 'c1']);
        $cohort2 = $this->getDataGenerator()->create_cohort(['idnumber' => 'c2', 'component' => 'tool_mutenancy']);
        $user = $this->getDataGenerator()->create_user();

        $result = $this->call('core_cohort_add_cohort_members', ['members' => [
            ['cohorttype' => ['type' => 'id', 'value' => $cohort1->id], 'usertype' => ['type' => 'id', 'value' => $user->id]],
        ]]);
        $this->assertFalse($result['error']);
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort1->id, 'userid' => $user->id]));

        $result = $this->call('core_cohort_add_cohort_members', ['members' => [
            ['cohorttype' => ['type' => 'id', 'value' => $cohort2->id], 'usertype' => ['type' => 'id', 'value' => $user->id]],
        ]]);
        $this->assertTrue($result['error']);
        $this->assertSame('error_cohortcomponent', $result['exception']->errorcode);

        $result = $this->call('core_cohort_add_cohort_members', ['members' => [
            ['cohorttype' => ['type' => 'idnumber', 'value' => 'c2'], 'usertype' => ['type' => 'id', 'value' => $user->id]],
        ]]);
        $this->assertTrue($result['error']);
        $this->assertFalse($DB->record_exists('cohort_members', ['cohortid' => $cohort2->id]));

        cohort_add_member($cohort2->id, $user->id);
        $result = $this->call('core_cohort_delete_cohort_members', ['members' => [
            ['cohortid' => $cohort2->id, 'userid' => $user->id],
        ]]);
        $this->assertTrue($result['error']);
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort2->id, 'userid' => $user->id]));

        $result = $this->call('core_cohort_update_cohorts', ['cohorts' => [
            ['id' => $cohort2->id, 'categorytype' => ['type' => 'system', 'value' => ''], 'name' => 'xx'],
        ]]);
        $this->assertTrue($result['error']);
        $this->assertNotSame('xx', $DB->get_field('cohort', 'name', ['id' => $cohort2->id]));

        $result = $this->call('core_cohort_delete_cohorts', ['cohortids' => [$cohort1->id, $cohort2->id]]);
        $this->assertTrue($result['error']);
        $this->assertTrue($DB->record_exists('cohort', ['id' => $cohort1->id]));
        $this->assertTrue($DB->record_exists('cohort', ['id' => $cohort2->id]));

        $result = $this->call('core_update_inplace_editable', [
            'component' => 'core_cohort',
            'itemtype' => 'cohortname',
            'itemid' => $cohort2->id,
            'value' => 'yy',
        ]);
        $this->assertTrue($result['error']);
        $this->assertNotSame('yy', $DB->get_field('cohort', 'name', ['id' => $cohort2->id]));

        $result = $this->call('core_cohort_delete_cohorts', ['cohortids' => [$cohort1->id]]);
        $this->assertFalse($result['error']);
        $this->assertFalse($DB->record_exists('cohort', ['id' => $cohort1->id]));

        // Cohorts of other plugins keep the standard core behaviour.
        $cohort3 = $this->getDataGenerator()->create_cohort(['component' => 'local_other']);
        $result = $this->call('core_cohort_add_cohort_members', ['members' => [
            ['cohorttype' => ['type' => 'id', 'value' => $cohort3->id], 'usertype' => ['type' => 'id', 'value' => $user->id]],
        ]]);
        $this->assertFalse($result['error']);
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort3->id, 'userid' => $user->id]));
        $result = $this->call('core_cohort_delete_cohorts', ['cohortids' => [$cohort3->id]]);
        $this->assertFalse($result['error']);
        $this->assertFalse($DB->record_exists('cohort', ['id' => $cohort3->id]));

        foreach (['tool_mucohort', 'tool_murelation'] as $component) {
            $cohort = $this->getDataGenerator()->create_cohort(['component' => $component]);
            $result = $this->call('core_cohort_delete_cohorts', ['cohortids' => [$cohort->id]]);
            $this->assertTrue($result['error']);
            $this->assertTrue($DB->record_exists('cohort', ['id' => $cohort->id]));
        }
    }
}
