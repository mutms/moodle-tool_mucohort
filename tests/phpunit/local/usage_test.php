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

use tool_mucohort\local\type\base;
use tool_mucohort\local\type\curated;
use tool_mucohort\local\usage;

/**
 * Cohort usage tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\local\usage
 * @covers \tool_mucohort\hook\cohort_usage
 */
final class usage_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Returns usage as section => list of "name|url?|details|visible" strings.
     *
     * @param base $cohort
     * @param string|null $only section name
     * @return array
     */
    private function get_usage(base $cohort, ?string $only = null): array {
        $result = [];
        foreach (usage::get_usage($cohort)->get_sections() as $section => $items) {
            if ($only !== null && $section !== $only) {
                continue;
            }
            foreach ($items as $item) {
                $result[$section][] = $item->name . '|' . ($item->url ? 'url' : '') . '|' . $item->details
                    . '|' . ($item->visible ? 'visible' : 'hidden');
            }
        }
        return $result;
    }

    public function test_usage(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/badgeslib.php');

        $this->setAdminUser();
        $record = $this->getDataGenerator()->create_cohort(['name' => 'Used']);
        $cohort = base::fetch($record->id);
        $this->assertSame([], $this->get_usage($cohort));

        $curated = curated::create((object)[
            'contextid' => \core\context\system::instance()->id,
            'name' => 'Curated <b>1</b>',
            'candidatecohortid' => $record->id,
        ]);

        $course1 = $this->getDataGenerator()->create_course(['fullname' => 'Course 1']);
        $course2 = $this->getDataGenerator()->create_course(['fullname' => 'Course 2']);
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        enrol_get_plugin('cohort')->add_instance($course1, ['customint1' => $record->id, 'roleid' => $studentrole->id]);
        enrol_get_plugin('cohort')->add_instance(
            $course2,
            ['customint1' => $record->id, 'roleid' => $studentrole->id, 'status' => ENROL_INSTANCE_DISABLED]
        );
        set_config('enrol_plugins_enabled', $CFG->enrol_plugins_enabled . ',self');
        enrol_get_plugin('self')->add_instance($course2, ['customint5' => $record->id]);

        /** @var \core_badges_generator $badgegenerator */
        $badgegenerator = $this->getDataGenerator()->get_plugin_generator('core_badges');
        $badge = $badgegenerator->create_badge(['name' => 'Badge 1']);
        \award_criteria::build(['badgeid' => $badge->id, 'criteriatype' => BADGE_CRITERIA_TYPE_OVERALL])
            ->save(['agg' => BADGE_CRITERIA_AGGREGATION_ALL]);
        \award_criteria::build(['badgeid' => $badge->id, 'criteriatype' => BADGE_CRITERIA_TYPE_COHORT])
            ->save(['cohort_cohorts' => [$record->id]]);

        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('tool_cohortroles', (object)[
            'cohortid' => $record->id,
            'roleid' => $studentrole->id,
            'userid' => $user->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report(['name' => 'Report 1', 'source' => \core_user\reportbuilder\datasource\users::class]);
        $rbgenerator->create_audience([
            'reportid' => $report->get('id'),
            'classname' => \core_cohort\reportbuilder\audience\cohortmember::class,
            'configdata' => ['cohorts' => [$record->id]],
        ]);
        $other = $rbgenerator->create_report(['name' => 'Report 2', 'source' => \core_user\reportbuilder\datasource\users::class]);
        $rbgenerator->create_audience([
            'reportid' => $other->get('id'),
            'classname' => \core_cohort\reportbuilder\audience\cohortmember::class,
            'configdata' => ['cohorts' => [$curated->get_id()]],
        ]);

        $student = role_get_name($studentrole);
        $expected = [
            get_string('usage_badges', 'tool_mucohort') => ['Badge 1|url||visible'],
            get_string('usage_candidate', 'tool_mucohort') => ['Curated 1|url||visible'],
            get_string('usage_cohortroles', 'tool_mucohort') => [$student . '|url|Users: 1|visible'],
            get_string('usage_enrolcohort', 'tool_mucohort') => [
                'Course 1|url|' . $student . '|visible',
                'Course 2|url|' . $student . ', ' . get_string('disabled', 'admin') . '|visible',
            ],
            get_string('usage_enrolself', 'tool_mucohort') => ['Course 2|url||visible'],
            get_string('usage_audiences', 'tool_mucohort') => ['Report 1|url||visible'],
        ];
        $actual = $this->get_usage($cohort);
        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual);

        // Users without permissions see only the number of items.
        $viewer = $this->getDataGenerator()->create_user();
        $this->setUser($viewer);
        $section = get_string('usage_enrolcohort', 'tool_mucohort');
        $this->assertSame(
            [$section => ['Course 1||' . $student . '|hidden', 'Course 2||' . $student . ', Disabled|hidden']],
            $this->get_usage($cohort, $section)
        );
        $section = get_string('usage_candidate', 'tool_mucohort');
        $this->assertSame([$section => ['Curated 1|||hidden']], $this->get_usage($cohort, $section));
    }
}
