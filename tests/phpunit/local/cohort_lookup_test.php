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
use tool_mucohort\local\type\curated;
use tool_mulib\local\cohort_util;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests of access records in cohort lookups of MuTMS plugins.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mulib\local\cohort_util::class)]
#[CoversTrait(\tool_mulib\muform\util\autocomplete\cohort_trait::class)]
#[Group('MuTMS')]
final class cohort_lookup_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_access_records(): void {
        global $DB;

        $syscontext = \core\context\system::instance();
        $this->setAdminUser();
        $visible = $this->getDataGenerator()->create_cohort(['name' => 'Visible']);
        $hidden = $this->getDataGenerator()->create_cohort(['name' => 'Hidden', 'visible' => 0]);
        $cohort = curated::create((object)['contextid' => $syscontext->id, 'name' => 'Curated', 'visible' => 0]);
        $curated = $DB->get_record('cohort', ['id' => $cohort->get_id()], '*', MUST_EXIST);
        $user = $this->getDataGenerator()->create_user();

        $picker = new class {
            use \tool_mulib\muform\util\autocomplete\cohort_trait;

            /**
             * Search.
             *
             * @param string $query
             * @return array|null
             */
            public function search(string $query): ?array {
                return $this->search_cohorts(\core\context\system::instance(), $query, 10);
            }

            /**
             * Validate.
             *
             * @param array $ids
             * @return array
             */
            public function validate(array $ids): array {
                return $this->validate_cohorts(\core\context\system::instance(), $ids);
            }
        };

        $this->setUser($user);
        $this->assertSame([(int)$visible->id], array_keys($picker->search('')));
        $this->assertFalse(cohort_util::can_use($curated));
        $this->assertSame([(int)$curated->id], array_keys($picker->validate([(string)$curated->id])));

        // Access records without the permission do not allow use of the hidden advanced cohort.
        $this->setAdminUser();
        access::set($cohort, $user->id, ['viewmembers', 'managemembers', 'manageexceptions'], true);
        $this->setUser($user);
        $this->assertSame([(int)$visible->id], array_keys($picker->search('')));
        $this->assertFalse(cohort_util::can_use($curated));
        $this->assertSame([(int)$curated->id], array_keys($picker->validate([(string)$curated->id])));

        $this->setAdminUser();
        access::set($cohort, $user->id, ['usecohort'], false);
        $this->setUser($user);
        $this->assertSame([(int)$curated->id, (int)$visible->id], array_keys($picker->search('')));
        $this->assertTrue(cohort_util::can_use($curated));
        $this->assertSame([], $picker->validate([(string)$curated->id]));
        $this->assertSame([(int)$hidden->id], array_keys($picker->validate([(string)$hidden->id])));

        // Access records of converted cohorts are ignored.
        $DB->set_field('cohort', 'component', '', ['id' => $curated->id]);
        $curated = $DB->get_record('cohort', ['id' => $curated->id], '*', MUST_EXIST);
        $this->assertFalse(cohort_util::can_use($curated));
        $this->assertSame([(int)$visible->id], array_keys($picker->search('')));
    }

    public function test_use(): void {
        global $DB;

        $syscontext = \core\context\system::instance();
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);
        $this->setAdminUser();
        $cohorts = [];
        $cohorts[] = $this->getDataGenerator()->create_cohort(['name' => 'Visible']);
        $cohorts[] = $this->getDataGenerator()->create_cohort(['name' => 'Standard', 'visible' => 0]);
        $cohorts[] = $this->getDataGenerator()->create_cohort(
            ['name' => 'Standard in category', 'visible' => 0, 'contextid' => $catcontext->id]
        );
        $cohorts[] = $this->getDataGenerator()->create_cohort(['name' => 'Plugin', 'visible' => 0, 'component' => 'enrol_database']);
        $curated1 = curated::create((object)['contextid' => $syscontext->id, 'name' => 'Curated 1', 'visible' => 0]);
        $curated2 = curated::create((object)['contextid' => $catcontext->id, 'name' => 'Curated 2', 'visible' => 0]);
        $curated3 = curated::create((object)['contextid' => $syscontext->id, 'name' => 'Curated 3', 'visible' => 1]);
        $rules = \tool_mucohort\local\type\rules::create((object)['contextid' => $syscontext->id, 'name' => 'Rules', 'visible' => 0]);
        $broken = curated::create((object)['contextid' => $syscontext->id, 'name' => 'Broken', 'visible' => 0]);
        $DB->delete_records('tool_mucohort', ['cohortid' => $broken->get_id()]);
        foreach ([$curated1, $curated2, $curated3, $rules, $broken] as $cohort) {
            $cohorts[] = $DB->get_record('cohort', ['id' => $cohort->get_id()], '*', MUST_EXIST);
        }

        $generator = $this->getDataGenerator();
        $users = ['nobody' => $generator->create_user(), 'admin' => get_admin(), 'guest' => guest_user()];
        $roles = [
            'view' => ['moodle/cohort:view', $syscontext],
            'viewcat' => ['moodle/cohort:view', $catcontext],
            'assign' => ['moodle/cohort:assign', $syscontext],
            'viewmembers' => ['tool/mucohort:viewmembers', $syscontext],
        ];
        foreach ($roles as $name => [$capability, $context]) {
            $users[$name] = $generator->create_user();
            $roleid = $generator->create_role();
            assign_capability($capability, CAP_ALLOW, $roleid, $context);
            role_assign($roleid, $users[$name]->id, $context);
        }
        $users['other'] = $generator->create_user();
        access::set($curated1, $users['other']->id, ['viewmembers', 'managemembers', 'manageexceptions'], true);
        access::set($rules, $users['other']->id, ['viewmembers'], false);
        $users['use'] = $generator->create_user();
        access::set($curated1, $users['use']->id, ['usecohort'], false);
        access::set($curated2, $users['use']->id, ['usecohort', 'viewmembers'], false);
        access::set($rules, $users['use']->id, ['usecohort'], false);
        access::set($broken, $users['use']->id, ['usecohort'], false);

        $names = function (\stdClass $user) use ($DB): array {
            $sql = (new \tool_mulib\local\sql("SELECT ch.name FROM {cohort} ch WHERE /* use */ ORDER BY ch.id"))
                ->replace_comment('use', cohort_util::get_use_sql('ch', (int)$user->id));
            return $DB->get_fieldset_sql($sql->sql, $sql->params);
        };

        $all = array_column($cohorts, 'name');
        $visible = ['Visible', 'Curated 3'];
        $this->assertSame($visible, $names($users['nobody']));
        $this->assertSame($visible, $names($users['guest']));
        $this->assertSame($visible, $names($users['assign']));
        $this->assertSame($visible, $names($users['viewmembers']));
        $this->assertSame($visible, $names($users['other']));
        $this->assertSame($all, $names($users['admin']));
        $this->assertSame($all, $names($users['view']));
        $this->assertSame(['Visible', 'Standard in category', 'Curated 2', 'Curated 3'], $names($users['viewcat']));
        $this->assertSame(['Visible', 'Curated 1', 'Curated 2', 'Curated 3', 'Rules'], $names($users['use']));

        // The SQL and can_use() of the cohort types are the same rule.
        foreach ($users as $user) {
            $this->setUser($user);
            $expected = $names($user);
            foreach ($cohorts as $cohort) {
                $allowed = in_array($cohort->name, $expected, true);
                $this->assertSame($allowed, \tool_mucohort\local\type\base::from_record($cohort)->can_use(), $cohort->name);
                $this->assertSame($allowed, cohort_util::can_use($cohort), $cohort->name);
            }
        }
    }

    public function test_view_members(): void {
        global $DB;

        $syscontext = \core\context\system::instance();
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);
        $this->setAdminUser();
        $cohorts = [];
        $cohorts[] = $this->getDataGenerator()->create_cohort(['name' => 'Standard']);
        $cohorts[] = $this->getDataGenerator()->create_cohort(['name' => 'Standard in category', 'contextid' => $catcontext->id]);
        $cohorts[] = $this->getDataGenerator()->create_cohort(['name' => 'Plugin', 'component' => 'enrol_database']);
        $curated1 = curated::create((object)['contextid' => $syscontext->id, 'name' => 'Curated 1']);
        $curated2 = curated::create((object)['contextid' => $catcontext->id, 'name' => 'Curated 2']);
        $curated3 = curated::create((object)['contextid' => $syscontext->id, 'name' => 'Curated 3']);
        $rules = \tool_mucohort\local\type\rules::create((object)['contextid' => $syscontext->id, 'name' => 'Rules']);
        $broken = curated::create((object)['contextid' => $syscontext->id, 'name' => 'Broken']);
        $DB->delete_records('tool_mucohort', ['cohortid' => $broken->get_id()]);
        foreach ([$curated1, $curated2, $curated3, $rules, $broken] as $cohort) {
            $cohorts[] = $DB->get_record('cohort', ['id' => $cohort->get_id()], '*', MUST_EXIST);
        }

        $generator = $this->getDataGenerator();
        $users = ['nobody' => $generator->create_user(), 'admin' => get_admin()];
        $roles = [
            'view' => ['moodle/cohort:view', $syscontext],
            'assign' => ['moodle/cohort:assign', $syscontext],
            'assigncat' => ['moodle/cohort:assign', $catcontext],
            'viewmembers' => ['tool/mucohort:viewmembers', $syscontext],
            'viewmemberscat' => ['tool/mucohort:viewmembers', $catcontext],
        ];
        foreach ($roles as $name => [$capability, $context]) {
            $users[$name] = $generator->create_user();
            $roleid = $generator->create_role();
            assign_capability($capability, CAP_ALLOW, $roleid, $context);
            role_assign($roleid, $users[$name]->id, $context);
        }
        $users['use'] = $generator->create_user();
        access::set($curated1, $users['use']->id, [], false);
        access::set($rules, $users['use']->id, [], false);
        $users['flag'] = $generator->create_user();
        access::set($curated1, $users['flag']->id, ['viewmembers'], false);
        access::set($curated2, $users['flag']->id, ['viewmembers', 'managemembers'], false);
        access::set($curated3, $users['flag']->id, [], false);
        access::set($rules, $users['flag']->id, ['viewmembers'], false);
        access::set($broken, $users['flag']->id, ['viewmembers'], false);

        $names = function (\stdClass $user) use ($DB): array {
            $sql = (new \tool_mulib\local\sql("SELECT ch.name FROM {cohort} ch WHERE /* viewmembers */ ORDER BY ch.id"))
                ->replace_comment('viewmembers', cohort_util::get_view_members_sql('ch', (int)$user->id));
            return $DB->get_fieldset_sql($sql->sql, $sql->params);
        };

        $all = array_column($cohorts, 'name');
        $this->assertSame([], $names($users['nobody']));
        $this->assertSame([], $names($users['view']));
        $this->assertSame([], $names($users['use']));
        $this->assertSame($all, $names($users['admin']));
        $this->assertSame($all, $names($users['viewmembers']));
        $this->assertSame(['Standard in category', 'Curated 2'], $names($users['viewmemberscat']));
        $this->assertSame(['Standard', 'Standard in category', 'Plugin', 'Broken'], $names($users['assign']));
        $this->assertSame(['Standard in category'], $names($users['assigncat']));
        $this->assertSame(['Curated 1', 'Curated 2', 'Rules'], $names($users['flag']));

        // The SQL and can_view_members() of the cohort types are the same rule.
        foreach ($users as $user) {
            $this->setUser($user);
            $expected = $names($user);
            foreach ($cohorts as $cohort) {
                $allowed = in_array($cohort->name, $expected, true);
                $this->assertSame($allowed, \tool_mucohort\local\type\base::from_record($cohort)->can_view_members(), $cohort->name);
                $this->assertSame($allowed, cohort_util::can_view_members($cohort), $cohort->name);
            }
        }
    }
}
