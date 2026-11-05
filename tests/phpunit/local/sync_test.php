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

use tool_mucohort\local\exception;
use tool_mucohort\local\rule\base as rule;
use tool_mucohort\local\ruleset;
use tool_mucohort\local\sync;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\rules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Sync of rule-based cohorts tests.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mucohort\local\sync::class)]
#[CoversClass(\tool_mucohort\task\sync_cohort::class)]
#[CoversClass(\tool_mucohort\task\sync_scheduled::class)]
#[CoversClass(\tool_mucohort\task\cleanup::class)]
#[Group('MuTMS')]
final class sync_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create rule-based cohort with active rules.
     *
     * @param ruleset|null $ruleset
     * @param int $synctype
     * @return rules
     */
    private function create_cohort(?ruleset $ruleset, int $synctype = rules::SYNCTYPE_FULL): rules {
        $cohort = rules::create((object)[
            'contextid' => \core\context\system::instance()->id,
            'name' => 'Rules cohort',
            'synctype' => $synctype,
        ]);
        if ($ruleset) {
            $cohort->set_pending_ruleset($ruleset);
            $cohort->approve_draft(sha1($cohort->get_rules_record()->pendingrulesjson));
        }
        /** @var rules $cohort */
        $cohort = base::fetch($cohort->get_id());
        return $cohort;
    }

    /**
     * Returns cohort member ids.
     *
     * @param rules $cohort
     * @return int[]
     */
    private function get_memberids(rules $cohort): array {
        global $DB;
        $ids = $DB->get_fieldset('cohort_members', 'userid', ['cohortid' => $cohort->get_id()]);
        $ids = array_map('intval', $ids);
        sort($ids);
        return $ids;
    }

    public function test_queue_and_execute(): void {
        global $DB;

        $other = $this->getDataGenerator()->create_cohort();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        cohort_add_member($other->id, $user1->id);
        cohort_add_member($other->id, $user2->id);

        $ruleset = new ruleset();
        $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:cohort', ['cohortids' => [(int)$other->id]]);
        $cohort = $this->create_cohort($ruleset);
        cohort_add_member($cohort->get_id(), $user3->id);
        $this->assertTrue(sync::can_sync($cohort));
        $this->assertNull(sync::get_current($cohort));
        $this->assertNull(sync::get_last($cohort));
        $this->assertSame('Not synchronised yet', sync::get_summary($cohort));

        $sync = sync::queue($cohort);
        $this->assertSame('Sync queued', sync::get_summary($cohort));
        $this->assertEquals($sync, sync::get_last($cohort));

        $this->assertSame(sync::STATUS_QUEUED, (int)$sync->status);
        $this->assertEquals($sync, sync::get_current($cohort));
        // Only one sync may be queued.
        $this->assertNull(sync::queue($cohort));

        ob_start();
        $this->runAdhocTasks(\tool_mucohort\task\sync_cohort::class);
        ob_end_clean();

        $this->assertSame([(int)$user1->id, (int)$user2->id], $this->get_memberids($cohort));
        $sync = $DB->get_record('tool_mucohort_sync', ['id' => $sync->id]);
        $this->assertSame(sync::STATUS_OK, (int)$sync->status);
        $this->assertSame('2', (string)$sync->added);
        $this->assertSame('1', (string)$sync->removed);
        $this->assertNotNull($sync->timestarted);
        $this->assertNotNull($sync->duration);
        $this->assertNull(sync::get_current($cohort));
        $this->assertStringContainsString('(Finished, added: 2, removed: 1)', sync::get_summary($cohort));

        // Executing a finished sync again does nothing.
        sync::execute((int)$sync->id, new \null_progress_trace());
        $this->assertEquals($sync, $DB->get_record('tool_mucohort_sync', ['id' => $sync->id]));

        // Second sync gets a unique time.
        $sync2 = sync::queue($cohort);
        $this->assertGreaterThan((int)$sync->timequeued, (int)$sync2->timequeued);
        sync::execute((int)$sync2->id, new \null_progress_trace());
        $sync2 = $DB->get_record('tool_mucohort_sync', ['id' => $sync2->id]);
        $this->assertSame(sync::STATUS_OK, (int)$sync2->status);
        $this->assertSame('0', (string)$sync2->added);
        $this->assertSame('0', (string)$sync2->removed);
    }

    public function test_synctype(): void {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user(['suspended' => 1]);
        $ruleset = new ruleset();
        $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:allusers', []);
        $ruleset->add_rule(rule::KIND_EXCLUDE, 'tool_mucohort:suspended', []);

        // Add-only keeps members not matching rules, but removes blocked ones.
        $cohort = $this->create_cohort($ruleset, rules::SYNCTYPE_ADDONLY);
        cohort_add_member($cohort->get_id(), $user2->id);
        $user3 = $this->getDataGenerator()->create_user();
        exception::set($cohort, $user3->id, exception::TYPE_BLOCKED, null);
        cohort_add_member($cohort->get_id(), $user3->id);
        $sync = sync::queue($cohort);
        sync::execute((int)$sync->id, new \null_progress_trace());
        $this->assertSame([2, (int)$user1->id, (int)$user2->id], $this->get_memberids($cohort));

        // Disabled sync does not change anything.
        $cohort->update((object)['synctype' => rules::SYNCTYPE_NONE]);
        $this->assertFalse(sync::can_sync($cohort));
        $DB->delete_records('cohort_members', ['cohortid' => $cohort->get_id()]);
        $sync = sync::queue($cohort);
        sync::execute((int)$sync->id, new \null_progress_trace());
        $this->assertSame([], $this->get_memberids($cohort));
        $sync = $DB->get_record('tool_mucohort_sync', ['id' => $sync->id]);
        $this->assertSame(sync::STATUS_OK, (int)$sync->status);
        $this->assertSame(get_string('rules_state_disabled_desc', 'tool_mucohort'), $sync->message);
    }

    public function test_broken(): void {
        global $DB;

        $other = $this->getDataGenerator()->create_cohort();
        $ruleset = new ruleset();
        $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:cohort', ['cohortids' => [(int)$other->id]]);
        $cohort = $this->create_cohort($ruleset);
        $user = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->get_id(), $user->id);

        cohort_delete_cohort($other);
        $this->assertFalse(sync::can_sync($cohort));
        $sync = sync::queue($cohort);
        sync::execute((int)$sync->id, new \null_progress_trace());
        $sync = $DB->get_record('tool_mucohort_sync', ['id' => $sync->id]);
        $this->assertSame(sync::STATUS_BROKEN, (int)$sync->status);
        $this->assertSame('1', (string)base::fetch($cohort->get_id())->get_rules_record()->broken);
        // Members are not touched.
        $this->assertSame([(int)$user->id], $this->get_memberids($cohort));

        // Inactive cohorts are broken too.
        $inactive = $this->create_cohort(null);
        $sync = sync::queue($inactive);
        sync::execute((int)$sync->id, new \null_progress_trace());
        $this->assertSame(sync::STATUS_BROKEN, (int)$DB->get_field('tool_mucohort_sync', 'status', ['id' => $sync->id]));

        // Deleted cohort.
        $sync = sync::queue($inactive);
        $inactive->delete();
        sync::execute((int)$sync->id, new \null_progress_trace());
        $this->assertFalse($DB->record_exists('tool_mucohort_sync', ['id' => $sync->id]));
    }

    public function test_cleanup(): void {
        global $DB;

        $ruleset = new ruleset();
        $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:allusers', []);
        $cohort = $this->create_cohort($ruleset);

        // Running sync without lock was interrupted.
        $sync = sync::queue($cohort);
        $DB->set_field('tool_mucohort_sync', 'status', sync::STATUS_RUNNING, ['id' => $sync->id]);
        $this->assertNull(sync::get_current($cohort));
        $record = $DB->get_record('tool_mucohort_sync', ['id' => $sync->id]);
        $this->assertSame(sync::STATUS_FAILED, (int)$record->status);
        $this->assertSame(get_string('sync_error_interrupted', 'tool_mucohort'), $record->message);

        // Lost task.
        $sync = sync::queue($cohort);
        $DB->set_field('tool_mucohort_sync', 'timequeued', time() - DAYSECS - 10, ['id' => $sync->id]);
        $this->assertNull(sync::get_current($cohort));
        $record = $DB->get_record('tool_mucohort_sync', ['id' => $sync->id]);
        $this->assertSame(sync::STATUS_FAILED, (int)$record->status);
        $this->assertSame(get_string('sync_error_lost', 'tool_mucohort'), $record->message);

        // Cancel request.
        $sync = sync::queue($cohort);
        $DB->set_field('tool_mucohort_sync', 'status', sync::STATUS_CANCELREQUESTED, ['id' => $sync->id]);
        $this->assertNull(sync::get_current($cohort));
        $this->assertSame(sync::STATUS_FAILED, (int)$DB->get_field('tool_mucohort_sync', 'status', ['id' => $sync->id]));

        $this->assertSame([sync::STATUS_QUEUED, sync::STATUS_RUNNING, sync::STATUS_CANCELREQUESTED, sync::STATUS_OK,
            sync::STATUS_FAILED, sync::STATUS_BROKEN, sync::STATUS_CANCELLED, sync::STATUS_REJECTED], array_keys(sync::get_statuses()));
    }

    public function test_schedule(): void {
        global $DB;

        $ruleset = new ruleset();
        $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:allusers', []);

        // Manual sync only by default when created via API.
        $cohort = $this->create_cohort($ruleset);
        $this->assertNull($cohort->get_rules_record()->syncinterval);
        $this->assertNull($cohort->get_rules_record()->timenextsync);

        // Never synchronised cohorts are due immediately.
        $now = time();
        $cohort->update((object)['syncinterval' => DAYSECS, 'syncpriority' => rules::SYNCPRIORITY_HIGH]);
        $record = $cohort->get_rules_record();
        $this->assertSame((string)DAYSECS, (string)$record->syncinterval);
        $this->assertSame((string)rules::SYNCPRIORITY_HIGH, (string)$record->syncpriority);
        $this->assertGreaterThanOrEqual($now, (int)$record->timenextsync);
        $this->assertLessThanOrEqual(time(), (int)$record->timenextsync);
        $this->assertStringContainsString('Every 1 day, next sync: ', $cohort->format_schedule());

        // Next sync is one interval after the latest sync.
        $sync = sync::queue($cohort);
        sync::execute((int)$sync->id, new \null_progress_trace());
        $cohort = base::fetch($cohort->get_id());
        $this->assertSame((int)$sync->timequeued + DAYSECS, (int)$cohort->get_rules_record()->timenextsync);

        // Short intervals are not allowed.
        $cohort->update((object)['syncinterval' => 10]);
        $this->assertSame((string)HOURSECS, (string)$cohort->get_rules_record()->syncinterval);

        // Not scheduled without automatic sync, with disabled sync or broken rules.
        $cohort->update((object)['syncinterval' => '']);
        $this->assertNull($cohort->get_rules_record()->timenextsync);
        $this->assertSame('Manual sync only', $cohort->format_schedule());
        $cohort->update((object)['syncinterval' => DAYSECS, 'synctype' => rules::SYNCTYPE_NONE]);
        $this->assertNull($cohort->get_rules_record()->timenextsync);
        $cohort->update((object)['synctype' => rules::SYNCTYPE_FULL]);
        $this->assertNotNull($cohort->get_rules_record()->timenextsync);
        $DB->set_field('tool_mucohort_rules', 'broken', 1, ['id' => $cohort->get_rules_record()->id]);
        $cohort->update_schedule();
        $this->assertNull($cohort->get_rules_record()->timenextsync);

        // Approval of rules fixes broken flag and schedules sync.
        $cohort->set_pending_ruleset($ruleset);
        $cohort->approve_draft(sha1($cohort->get_rules_record()->pendingrulesjson));
        $this->assertNotNull($cohort->get_rules_record()->timenextsync);

        // Inactive cohorts are not scheduled.
        $inactive = $this->create_cohort(null);
        $inactive->update((object)['syncinterval' => HOURSECS]);
        $this->assertNull($inactive->get_rules_record()->timenextsync);
    }

    public function test_queue_due(): void {
        global $DB;

        $ruleset = new ruleset();
        $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:allusers', []);
        $cohort1 = $this->create_cohort($ruleset);
        $cohort2 = $this->create_cohort($ruleset);
        $cohort3 = $this->create_cohort($ruleset);
        $cohort1->update((object)['syncinterval' => DAYSECS, 'syncpriority' => rules::SYNCPRIORITY_LOW]);
        $cohort2->update((object)['syncinterval' => DAYSECS, 'syncpriority' => rules::SYNCPRIORITY_HIGH]);
        $cohort3->update((object)['syncinterval' => DAYSECS]);
        $DB->set_field('tool_mucohort_rules', 'timenextsync', time() + 100, ['id' => $cohort3->get_rules_record()->id]);

        $this->assertSame(2, sync::queue_due(new \null_progress_trace()));
        $queued = $DB->get_records('tool_mucohort_sync', null, 'id ASC');
        $expected = [(string)$cohort2->get_mucohort()->id, (string)$cohort1->get_mucohort()->id];
        $this->assertSame($expected, array_values(array_map(fn($r) => (string)$r->mucohortid, $queued)));

        // Queued cohorts are not queued again.
        $this->assertSame(0, sync::queue_due(new \null_progress_trace()));

        ob_start();
        $this->runAdhocTasks(\tool_mucohort\task\sync_cohort::class);
        ob_end_clean();
        $this->assertSame(0, sync::queue_due(new \null_progress_trace()));

        // Cohorts with broken rules are not queued and their schedule is cleared.
        $other = $this->getDataGenerator()->create_cohort();
        $brokenrules = new ruleset();
        $brokenrules->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:cohort', ['cohortids' => [(int)$other->id]]);
        $broken = $this->create_cohort($brokenrules);
        $broken->update((object)['syncinterval' => DAYSECS]);
        cohort_delete_cohort($other);
        $this->assertSame(0, sync::queue_due(new \null_progress_trace()));
        $this->assertNull(base::fetch($broken->get_id())->get_rules_record()->timenextsync);

        $task = new \tool_mucohort\task\sync_scheduled();
        $this->assertNotEmpty($task->get_name());
        ob_start();
        $task->execute();
        ob_end_clean();
    }

    public function test_purge_history(): void {
        global $DB;

        $ruleset = new ruleset();
        $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:allusers', []);
        $cohort1 = $this->create_cohort($ruleset);
        $cohort2 = $this->create_cohort($ruleset);

        $old = time() - YEARSECS - DAYSECS;
        $rows = [
            [$cohort1, $old - 10, sync::STATUS_OK],
            [$cohort1, $old, sync::STATUS_FAILED],
            [$cohort1, time() - 10, sync::STATUS_OK],
            [$cohort2, $old - 10, sync::STATUS_RUNNING],
            [$cohort2, $old, sync::STATUS_OK],
        ];
        $ids = [];
        foreach ($rows as [$cohort, $time, $status]) {
            $ids[] = $DB->insert_record('tool_mucohort_sync', [
                'mucohortid' => $cohort->get_mucohort()->id, 'timequeued' => $time, 'status' => $status, 'added' => 0, 'removed' => 0,
            ]);
        }

        set_config('syncretention', 0, 'tool_mucohort');
        $this->assertSame(0, sync::purge_history(new \null_progress_trace()));

        set_config('syncretention', YEARSECS, 'tool_mucohort');
        $this->assertSame(2, sync::purge_history(new \null_progress_trace()));
        // Running sync and the latest sync of each cohort are kept.
        $this->assertSame([$ids[2], $ids[3], $ids[4]], array_map('intval', array_keys($DB->get_records('tool_mucohort_sync', null, 'id ASC'))));

        $task = new \tool_mucohort\task\cleanup();
        $this->assertNotEmpty($task->get_name());
        $task->execute();
        $this->assertSame(3, $DB->count_records('tool_mucohort_sync'));
    }

    public function test_run(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $ruleset = new ruleset();
        $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:allusers', []);
        $cohort = $this->create_cohort($ruleset);

        $result = sync::run($cohort, new \null_progress_trace());
        $this->assertSame(sync::STATUS_OK, (int)$result->status);
        $this->assertSame('2', (string)$result->added);
        $this->assertSame([2, (int)$user->id], $this->get_memberids($cohort));
        // No adhoc task is used.
        $this->assertSame(0, $DB->count_records('task_adhoc', ['classname' => '\\tool_mucohort\\task\\sync_cohort']));

        // Queued sync is taken over, the adhoc task skips it.
        $queued = sync::queue($cohort);
        $result = sync::run($cohort, new \null_progress_trace());
        $this->assertSame((int)$queued->id, (int)$result->id);
        $this->assertSame(sync::STATUS_OK, (int)$result->status);
        ob_start();
        $this->runAdhocTasks(\tool_mucohort\task\sync_cohort::class);
        ob_end_clean();
        $this->assertEquals($result, $DB->get_record('tool_mucohort_sync', ['id' => $result->id]));
    }
}
