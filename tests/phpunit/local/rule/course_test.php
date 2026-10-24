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

namespace tool_mucohort\phpunit\local\rule;

use tool_mucohort\local\broken_rules_exception;
use tool_mucohort\local\rule\base;
use tool_mucohort\local\ruleset;
use tool_mucohort\local\type\rules;

/**
 * Course enrolment and completion rule tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\local\rule\include_course_enrolment
 * @covers \tool_mucohort\local\rule\exclude_course_enrolment
 * @covers \tool_mucohort\local\rule\include_course_completion
 * @covers \tool_mucohort\local\rule\exclude_course_completion
 * @covers \tool_mucohort\muform\autocomplete\rule_courseid
 * @covers \tool_mucohort\muform\autocompletemany\rule_courseids
 */
final class course_test extends \advanced_testcase {
    /** @var rules */
    private rules $cohort;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->cohort = rules::create((object)[
            'contextid' => \core\context\system::instance()->id,
            'name' => 'Rules cohort',
        ]);
    }

    /**
     * Returns ids of users matched by one rule, admin is ignored.
     *
     * @param string $kind
     * @param string $type
     * @param array $config
     * @param int|null $userid
     * @return int[]
     */
    private function match(string $kind, string $type, array $config, ?int $userid = null): array {
        global $DB;
        $ruleset = new ruleset();
        $uuid = $ruleset->add_rule($kind, $type, $config);
        $sql = $this->cohort->get_rule_match_sql($ruleset, $uuid, $userid)->wrap("SELECT u.id FROM {user} u WHERE ", " AND u.id <> 2 ORDER BY u.id");
        return array_map('intval', $DB->get_fieldset_sql($sql->sql, $sql->params));
    }

    /**
     * Returns rule description.
     *
     * @param string $kind
     * @param string $type
     * @param array $config
     * @return string
     */
    private function describe(string $kind, string $type, array $config): string {
        $ruleset = new ruleset();
        $uuid = $ruleset->add_rule($kind, $type, $config);
        return $this->cohort->get_rule_description($ruleset->get_rule($uuid));
    }

    public function test_enrolment(): void {
        global $DB;

        $course1 = $this->getDataGenerator()->create_course(['fullname' => 'Course 1']);
        $course2 = $this->getDataGenerator()->create_course(['fullname' => 'Course 2']);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();
        $user5 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course2->id);
        $this->getDataGenerator()->enrol_user($user3->id, $course1->id, null, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->getDataGenerator()->enrol_user($user4->id, $course1->id, null, 'manual', 0, time() - 10);
        $this->getDataGenerator()->enrol_user($user5->id, $course1->id, null, 'self');
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['courseid' => $course1->id, 'enrol' => 'self']);

        $type = 'tool_mucohort:courseenrolment';
        $all = [(int)$user1->id, (int)$user3->id, (int)$user4->id, (int)$user5->id];
        $this->assertSame([(int)$user1->id], $this->match(base::KIND_INCLUDE, $type, ['courseids' => [(int)$course1->id], 'activeonly' => true]));
        $this->assertSame($all, $this->match(base::KIND_INCLUDE, $type, ['courseids' => [(int)$course1->id], 'activeonly' => false]));
        $this->assertSame(
            [(int)$user1->id, (int)$user2->id],
            $this->match(base::KIND_INCLUDE, $type, ['courseids' => [(int)$course1->id, (int)$course2->id], 'activeonly' => true])
        );
        $this->assertSame([(int)$user2->id], $this->match(base::KIND_EXCLUDE, $type, ['courseid' => (int)$course2->id, 'activeonly' => true]));
        $this->assertSame($all, $this->match(base::KIND_EXCLUDE, $type, ['courseid' => (int)$course1->id, 'activeonly' => false]));
        $this->assertSame([(int)$user1->id], $this->match(base::KIND_INCLUDE, $type, ['courseids' => [(int)$course1->id], 'activeonly' => true], (int)$user1->id));

        $this->assertSame(
            'Actively enrolled in any of these courses: Course 1, Course 2',
            $this->describe(base::KIND_INCLUDE, $type, ['courseids' => [(int)$course1->id, (int)$course2->id], 'activeonly' => true])
        );
        $this->assertSame('Enrolled in course: Course 2', $this->describe(base::KIND_EXCLUDE, $type, ['courseid' => (int)$course2->id, 'activeonly' => false]));

        delete_course($course2, false);
        $this->assertSame(
            'Enrolled in course: Missing course (' . $course2->id . ')',
            $this->describe(base::KIND_EXCLUDE, $type, ['courseid' => (int)$course2->id, 'activeonly' => false])
        );
        try {
            $this->match(base::KIND_EXCLUDE, $type, ['courseid' => (int)$course2->id, 'activeonly' => false]);
            $this->fail('Exception expected');
        } catch (broken_rules_exception $ex) {
            $this->assertStringContainsString('Course does not exist', $ex->getMessage());
        }
    }

    public function test_completion(): void {
        $course1 = $this->getDataGenerator()->create_course(['fullname' => 'Course 1', 'enablecompletion' => 1]);
        $course2 = $this->getDataGenerator()->create_course(['fullname' => 'Course 2', 'enablecompletion' => 1]);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        foreach ([$user1, $user2, $user3] as $user) {
            $this->getDataGenerator()->enrol_user($user->id, $course1->id);
            $this->getDataGenerator()->enrol_user($user->id, $course2->id);
        }
        (new \completion_completion(['userid' => $user1->id, 'course' => $course1->id]))->mark_complete();
        (new \completion_completion(['userid' => $user2->id, 'course' => $course2->id]))->mark_complete();
        (new \completion_completion(['userid' => $user3->id, 'course' => $course1->id]))->mark_inprogress();

        $type = 'tool_mucohort:coursecompletion';
        $this->assertSame([(int)$user1->id], $this->match(base::KIND_INCLUDE, $type, ['courseids' => [(int)$course1->id]]));
        $this->assertSame([(int)$user1->id, (int)$user2->id], $this->match(base::KIND_INCLUDE, $type, ['courseids' => [(int)$course1->id, (int)$course2->id]]));
        $this->assertSame([(int)$user2->id], $this->match(base::KIND_EXCLUDE, $type, ['courseid' => (int)$course2->id]));
        $this->assertSame([], $this->match(base::KIND_INCLUDE, $type, ['courseids' => [(int)$course1->id]], (int)$user2->id));

        $this->assertSame('Completed any of these courses: Course 1', $this->describe(base::KIND_INCLUDE, $type, ['courseids' => [(int)$course1->id]]));
        $this->assertSame('Completed course: Course 2', $this->describe(base::KIND_EXCLUDE, $type, ['courseid' => (int)$course2->id]));
    }

    public function test_course_pickers(): void {
        $this->setAdminUser();
        $context = \core\context\system::instance();
        $course1 = $this->getDataGenerator()->create_course(['fullname' => 'Alpha']);
        $course2 = $this->getDataGenerator()->create_course(['fullname' => 'Beta']);

        $source = new \tool_mucohort\muform\autocompletemany\rule_courseids($context->id, []);
        $this->assertSame([(string)$course1->id => 'Alpha', (string)$course2->id => 'Beta'], $source->search('', 10, []));
        $this->assertSame([(string)$course2->id => 'Beta'], $source->search('bet', 10, []));
        $this->assertSame([(string)$course1->id => 'Alpha'], $source->labels([(string)$course1->id, (string)SITEID, '999999']));

        $source = new \tool_mucohort\muform\autocomplete\rule_courseid($context->id, 0);
        $this->assertSame('Beta', $source->label((string)$course2->id));
        $this->assertNull($source->label((string)SITEID));

        // Other users see only courses where they may view participants, current values stay valid.
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course1->id, 'editingteacher');
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/mucohort:manage', CAP_ALLOW, $roleid, $context->id);
        $this->getDataGenerator()->role_assign($roleid, $teacher->id, $context->id);
        $this->setUser($teacher);
        $source = new \tool_mucohort\muform\autocompletemany\rule_courseids($context->id, [(int)$course2->id]);
        $this->assertSame([(string)$course1->id => 'Alpha'], $source->search('', 10, []));
        $this->assertSame([(string)$course1->id => 'Alpha', (string)$course2->id => 'Beta'], $source->labels([(string)$course1->id, (string)$course2->id]));
    }
}
