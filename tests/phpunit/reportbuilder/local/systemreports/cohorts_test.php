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

namespace tool_mucohort\phpunit\reportbuilder\local\systemreports;

use tool_mucohort\reportbuilder\local\systemreports\cohorts;
use core_reportbuilder\system_report_factory;

/**
 * Cohort list report tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\reportbuilder\local\systemreports\cohorts
 */
final class cohorts_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Returns cohort names shown in report.
     *
     * @param \core\context $context
     * @return string html
     */
    private function get_report_html(\core\context $context): string {
        global $PAGE;
        $PAGE->set_url('/admin/tool/mucohort/index.php');
        // Report instances are cached per request and user.
        \core_reportbuilder\manager::reset_caches();
        $report = system_report_factory::create(cohorts::class, $context);
        return $report->output();
    }

    public function test_visibility(): void {
        $category1 = $this->getDataGenerator()->create_category();
        $category2 = $this->getDataGenerator()->create_category(['parent' => $category1->id]);
        $category3 = $this->getDataGenerator()->create_category();
        $catcontext1 = \core\context\coursecat::instance($category1->id);
        $catcontext2 = \core\context\coursecat::instance($category2->id);
        $catcontext3 = \core\context\coursecat::instance($category3->id);
        $syscontext = \core\context\system::instance();

        $this->getDataGenerator()->create_cohort(['name' => 'Cohort sys']);
        $this->getDataGenerator()->create_cohort(['name' => 'Cohort cat1', 'contextid' => $catcontext1->id]);
        $this->getDataGenerator()->create_cohort(['name' => 'Cohort cat2', 'contextid' => $catcontext2->id]);
        $this->getDataGenerator()->create_cohort(['name' => 'Cohort cat3', 'contextid' => $catcontext3->id]);

        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid, $syscontext);
        role_assign($roleid, $user->id, $catcontext1);
        \tool_mulib\local\context_map_builder::build();

        $this->setAdminUser();
        $html = $this->get_report_html($syscontext);
        $this->assertStringContainsString('Cohort sys', $html);
        $this->assertStringContainsString('Cohort cat1', $html);
        $this->assertStringContainsString('Cohort cat2', $html);
        $this->assertStringContainsString('Cohort cat3', $html);

        $html = $this->get_report_html($catcontext1);
        $this->assertStringNotContainsString('Cohort sys', $html);
        $this->assertStringContainsString('Cohort cat1', $html);
        $this->assertStringContainsString('Cohort cat2', $html);
        $this->assertStringNotContainsString('Cohort cat3', $html);

        $this->setUser($user);
        $html = $this->get_report_html($catcontext1);
        $this->assertStringNotContainsString('Cohort sys', $html);
        $this->assertStringContainsString('Cohort cat1', $html);
        $this->assertStringContainsString('Cohort cat2', $html);
        $this->assertStringNotContainsString('Cohort cat3', $html);

        // Prohibit in subcategory hides its cohorts.
        $roleid2 = $this->getDataGenerator()->create_role();
        assign_capability('moodle/cohort:view', CAP_PROHIBIT, $roleid2, $syscontext);
        role_assign($roleid2, $user->id, $catcontext2);
        \tool_mulib\local\context_map_builder::build();
        $html = $this->get_report_html($catcontext1);
        $this->assertStringContainsString('Cohort cat1', $html);
        $this->assertStringNotContainsString('Cohort cat2', $html);
    }

    public function test_access_records(): void {
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);
        $syscontext = \core\context\system::instance();
        $curated1 = \tool_mucohort\local\type\curated::create((object)['contextid' => $catcontext->id, 'name' => 'Curated 1']);
        \tool_mucohort\local\type\curated::create((object)['contextid' => $catcontext->id, 'name' => 'Curated 2']);
        $this->getDataGenerator()->create_cohort(['name' => 'Cohort sys']);

        $user = $this->getDataGenerator()->create_user();
        \tool_mucohort\local\access::set($curated1, $user->id, [], false);
        \tool_mulib\local\context_map_builder::build();

        $this->setUser($user);
        $html = $this->get_report_html($syscontext);
        $this->assertStringContainsString('Curated 1', $html);
        $this->assertStringNotContainsString('Curated 2', $html);
        $this->assertStringNotContainsString('Cohort sys', $html);
        $html = $this->get_report_html($catcontext);
        $this->assertStringContainsString('Curated 1', $html);
        $this->assertStringNotContainsString('Curated 2', $html);
    }
}
