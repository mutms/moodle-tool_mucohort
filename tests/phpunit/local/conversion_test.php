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
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\curated;
use tool_mucohort\local\type\standard;
use tool_mucohort\local\exception;
use tool_mucohort\local\type\rules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cohort conversion tests.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mucohort\local\conversion::class)]
#[Group('MuTMS')]
final class conversion_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_convert(): void {
        global $DB;

        $record = $this->getDataGenerator()->create_cohort(['name' => 'Cohort 1', 'idnumber' => 'c1']);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        cohort_add_member($record->id, $user1->id);
        cohort_add_member($record->id, $user2->id);
        $members = $DB->get_records('cohort_members', ['cohortid' => $record->id], 'id ASC');

        $cohort = conversion::convert(base::fetch($record->id), curated::TYPE);
        $this->assertInstanceOf(curated::class, $cohort);
        $this->assertSame('tool_mucohort', $cohort->get_cohort()->component);
        $this->assertSame('Cohort 1', $cohort->get_cohort()->name);
        $this->assertSame('c1', $cohort->get_cohort()->idnumber);
        $this->assertNull($cohort->get_curated()->maxmembers);
        $this->assertEquals($members, $DB->get_records('cohort_members', ['cohortid' => $record->id], 'id ASC'));

        $mucohortid = $cohort->get_mucohort()->id;
        $DB->insert_record('tool_mucohort_access', ['mucohortid' => $mucohortid, 'userid' => $user1->id,
            'managemembers' => 1, 'manageexceptions' => 0, 'timecreated' => time(), 'timemodified' => time()]);
        $DB->insert_record('tool_mucohort_exception', ['mucohortid' => $mucohortid, 'userid' => $user2->id,
            'type' => 2, 'timecreated' => time(), 'timemodified' => time()]);
        $this->assertSame(['convert_loss_access' => 1, 'convert_loss_exceptions' => 1], conversion::get_losses($cohort, standard::TYPE));

        $cohort = conversion::convert($cohort, standard::TYPE);
        $this->assertInstanceOf(standard::class, $cohort);
        $this->assertSame('', $cohort->get_cohort()->component);
        $this->assertEquals($members, $DB->get_records('cohort_members', ['cohortid' => $record->id], 'id ASC'));
        $this->assertFalse($DB->record_exists('tool_mucohort', ['id' => $mucohortid]));
        $this->assertFalse($DB->record_exists('tool_mucohort_curated', ['mucohortid' => $mucohortid]));
        $this->assertFalse($DB->record_exists('tool_mucohort_access', ['mucohortid' => $mucohortid]));
        $this->assertFalse($DB->record_exists('tool_mucohort_exception', ['mucohortid' => $mucohortid]));
        $this->assertSame([], conversion::get_losses($cohort, curated::TYPE));

        try {
            conversion::convert($cohort, standard::TYPE);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\core\exception\coding_exception::class, $ex);
        }
    }

    public function test_can_convert(): void {
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);
        $standard = base::fetch($this->getDataGenerator()->create_cohort(['contextid' => $catcontext->id])->id);
        $curated = curated::create((object)['contextid' => $catcontext->id, 'name' => 'Curated']);
        $plugin = base::fetch($this->getDataGenerator()->create_cohort(['component' => 'tool_mutenancy'])->id);

        $both = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/cohort:manage', CAP_ALLOW, $roleid, $catcontext);
        assign_capability('tool/mucohort:manage', CAP_ALLOW, $roleid, $catcontext);
        role_assign($roleid, $both->id, $catcontext);
        $standardonly = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/cohort:manage', CAP_ALLOW, $roleid, $catcontext);
        role_assign($roleid, $standardonly->id, $catcontext);

        $this->setUser($both);
        $this->assertTrue(conversion::can_convert($standard, curated::TYPE));
        $this->assertFalse(conversion::can_convert($standard, standard::TYPE));
        $this->assertTrue(conversion::can_convert($curated, standard::TYPE));
        $this->assertFalse(conversion::can_convert($plugin, standard::TYPE));
        $this->assertSame([curated::TYPE, rules::TYPE], array_keys(conversion::get_target_types($standard)));
        $this->assertSame([standard::TYPE, rules::TYPE], array_keys(conversion::get_target_types($curated)));

        set_config('enabledtypes', 'standard', 'tool_mucohort');
        $this->assertFalse(conversion::can_convert($standard, curated::TYPE));
        set_config('enabledtypes', 'standard,curated', 'tool_mucohort');

        $this->setUser($standardonly);
        $this->assertFalse(conversion::can_convert($standard, curated::TYPE));
        $this->assertFalse(conversion::can_convert($curated, standard::TYPE));
    }

    public function test_convert_rules(): void {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $candidate = $this->getDataGenerator()->create_cohort();
        $curated = curated::create((object)[
            'contextid' => \core\context\system::instance()->id,
            'name' => 'Curated',
            'maxmembers' => 10,
            'candidatecohortid' => $candidate->id,
        ]);
        cohort_add_member($curated->get_id(), $user1->id);
        cohort_add_member($curated->get_id(), $user2->id);
        exception::set($curated, $user2->id, exception::TYPE_BLOCKED, null);
        cohort_add_member($curated->get_id(), $user2->id);
        $mucohortid = $curated->get_mucohort()->id;

        $this->assertSame(['convert_loss_maxmembers' => 10], conversion::get_losses($curated, rules::TYPE));

        // Curated to rule-based keeps access, exceptions and candidate cohort, no rules are active.
        $sink = $this->redirectEvents();
        $cohort = conversion::convert($curated, rules::TYPE, (object)['synctype' => rules::SYNCTYPE_ADDONLY, 'seedforced' => true]);
        $events = $sink->get_events();
        $sink->close();
        $this->assertInstanceOf(rules::class, $cohort);
        $this->assertSame((string)$mucohortid, (string)$cohort->get_mucohort()->id);
        $this->assertSame((string)$candidate->id, (string)$cohort->get_mucohort()->candidatecohortid);
        $this->assertSame(rules::SYNCTYPE_ADDONLY, $cohort->get_synctype());
        $this->assertSame(rules::STATE_INACTIVE, $cohort->get_state());
        $this->assertFalse($DB->record_exists('tool_mucohort_curated', ['mucohortid' => $mucohortid]));
        $this->assertSame(2, $DB->count_records('cohort_members', ['cohortid' => $cohort->get_id()]));
        // Members are added as forced users, existing blocked user stays blocked.
        $this->assertSame(exception::TYPE_FORCED, (int)exception::get_record($cohort, (int)$user1->id)->type);
        $this->assertSame(exception::TYPE_BLOCKED, (int)exception::get_record($cohort, (int)$user2->id)->type);
        $this->assertInstanceOf(\tool_mucohort\event\cohort_converted::class, $events[0]);

        // Rule-based to curated drops rules and history.
        $ruleset = new \tool_mucohort\local\ruleset();
        $ruleset->add_rule(\tool_mucohort\local\rule\base::KIND_INCLUDE, 'tool_mucohort:allusers', []);
        $cohort->set_pending_ruleset($ruleset);
        $cohort->approve_draft(sha1($cohort->get_rules_record()->pendingrulesjson));
        $cohort->set_pending_ruleset($ruleset);
        \tool_mucohort\local\sync::run($cohort, new \null_progress_trace());
        $this->assertSame(
            ['convert_loss_rules' => 2, 'convert_loss_synchistory' => 1],
            conversion::get_losses($cohort, curated::TYPE)
        );

        // Not possible while sync is waiting.
        $sync = \tool_mucohort\local\sync::queue($cohort);
        $this->setAdminUser();
        $this->assertFalse(conversion::can_convert($cohort, curated::TYPE));
        $DB->delete_records('tool_mucohort_sync', ['id' => $sync->id]);
        $this->assertTrue(conversion::can_convert($cohort, curated::TYPE));

        $cohort = conversion::convert($cohort, curated::TYPE);
        $this->assertInstanceOf(curated::class, $cohort);
        $this->assertNull($cohort->get_curated()->maxmembers);
        $this->assertFalse($DB->record_exists('tool_mucohort_rules', ['mucohortid' => $mucohortid]));
        $this->assertFalse($DB->record_exists('tool_mucohort_sync', ['mucohortid' => $mucohortid]));
        $this->assertSame(2, exception::count($cohort, exception::TYPE_FORCED) + exception::count($cohort, exception::TYPE_BLOCKED));

        // Standard to rule-based and back.
        $standard = conversion::convert($cohort, standard::TYPE);
        $this->assertSame('', $standard->get_cohort()->component);
        $cohort = conversion::convert($standard, rules::TYPE);
        $this->assertSame(rules::SYNCTYPE_FULL, $cohort->get_synctype());
        $this->assertSame(0, exception::count($cohort, exception::TYPE_FORCED));
        $this->assertSame('tool_mucohort', $cohort->get_cohort()->component);
        $standard = conversion::convert($cohort, standard::TYPE);
        $this->assertSame('', $standard->get_cohort()->component);
        $this->assertFalse($DB->record_exists('tool_mucohort', ['cohortid' => $standard->get_id()]));
        // The blocked user was removed by the sync above, conversions never change membership.
        $this->assertSame(1, $DB->count_records('cohort_members', ['cohortid' => $standard->get_id()]));
    }
}
