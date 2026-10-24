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

namespace tool_mucohort\local;

use stdClass;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\rules;

/**
 * Synchronisation of rule-based cohort membership.
 *
 * Sync history rows are inserted as queued when sync is requested, the adhoc task
 * receives the row id. Only consistency of each individual user matters, errors
 * of one user do not stop the sync. Forced and blocked users are not processed here,
 * exceptions are applied immediately when they are changed.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sync {
    /** @var int sync is running */
    public const int STATUS_RUNNING = 0;
    /** @var int sync finished */
    public const int STATUS_OK = 1;
    /** @var int sync failed */
    public const int STATUS_FAILED = 2;
    /** @var int external data rejected, used by external database sources only */
    public const int STATUS_REJECTED = 3;
    /** @var int rules cannot be evaluated */
    public const int STATUS_BROKEN = 4;
    /** @var int sync was cancelled */
    public const int STATUS_CANCELLED = 5;
    /** @var int sync is waiting for adhoc task */
    public const int STATUS_QUEUED = 6;
    /** @var int running sync should stop as soon as possible */
    public const int STATUS_CANCELREQUESTED = 7;

    /** @var int number of users processed between cancellation checks */
    private const int CHECK_INTERVAL = 100;

    /**
     * Returns status names.
     *
     * @return array status => name
     */
    public static function get_statuses(): array {
        $result = [];
        foreach ([6, 0, 7, 1, 2, 4, 5, 3] as $status) {
            $result[$status] = get_string('sync_status_' . $status, 'tool_mucohort');
        }
        return $result;
    }

    /**
     * Returns queued or running sync of the cohort.
     *
     * @param rules $cohort
     * @return stdClass|null tool_mucohort_sync record
     */
    public static function get_current(rules $cohort): ?stdClass {
        global $DB;

        self::cleanup((int)$cohort->get_mucohort()->id);

        $params = [
            'mucohortid' => $cohort->get_mucohort()->id,
            'queued' => self::STATUS_QUEUED,
            'running' => self::STATUS_RUNNING,
            'cancel' => self::STATUS_CANCELREQUESTED,
        ];
        $records = $DB->get_records_select(
            'tool_mucohort_sync',
            "mucohortid = :mucohortid AND status IN (:queued, :running, :cancel)",
            $params,
            'timequeued DESC',
            '*',
            0,
            1
        );
        return $records ? reset($records) : null;
    }

    /**
     * Returns the latest sync of the cohort.
     *
     * @param rules $cohort
     * @return stdClass|null tool_mucohort_sync record
     */
    public static function get_last(rules $cohort): ?stdClass {
        global $DB;
        $params = ['mucohortid' => $cohort->get_mucohort()->id];
        $records = $DB->get_records('tool_mucohort_sync', $params, 'timequeued DESC', '*', 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Describe the latest sync in one sentence.
     *
     * @param rules $cohort
     * @return string html
     */
    public static function get_summary(rules $cohort): string {
        $current = self::get_current($cohort);
        if ($current) {
            $status = ((int)$current->status === self::STATUS_QUEUED) ? 'sync_queued' : 'sync_running';
            return get_string($status, 'tool_mucohort');
        }
        $last = self::get_last($cohort);
        if (!$last) {
            return get_string('sync_summary_none', 'tool_mucohort');
        }
        $a = (object)[
            'time' => userdate($last->timestarted ?? $last->timequeued, get_string('strftimedatetimeshort', 'core_langconfig')),
            'status' => self::get_statuses()[(int)$last->status] ?? '',
            'added' => $last->added,
            'removed' => $last->removed,
        ];
        return get_string('sync_summary', 'tool_mucohort', $a);
    }

    /**
     * Can the cohort be synchronised?
     *
     * @param rules $cohort
     * @return bool
     */
    public static function can_sync(rules $cohort): bool {
        return $cohort->get_state() === rules::STATE_ACTIVE;
    }

    /**
     * Queue sync of the cohort, sync is executed by an adhoc task.
     *
     * @param rules $cohort
     * @return stdClass|null new tool_mucohort_sync record, null if sync is already queued or running
     */
    public static function queue(rules $cohort): ?stdClass {
        $record = self::create_record($cohort);
        if (!$record) {
            return null;
        }

        $task = new \tool_mucohort\task\sync_cohort();
        $task->set_custom_data(['syncid' => (int)$record->id]);
        \core\task\manager::queue_adhoc_task($task);

        return $record;
    }

    /**
     * Sync cohort in this request, for example from CLI or in debug mode.
     *
     * A sync that is queued and waiting for an adhoc task is taken over,
     * the adhoc task then skips it.
     *
     * @param rules $cohort
     * @param \progress_trace $trace
     * @return stdClass|null finished tool_mucohort_sync record, null if sync is already running
     */
    public static function run(rules $cohort, \progress_trace $trace): ?stdClass {
        global $DB;

        $current = self::get_current($cohort);
        if ($current) {
            if ((int)$current->status !== self::STATUS_QUEUED) {
                return null;
            }
            $trace->output('Taking over queued sync ' . $current->id);
            $record = $current;
        } else {
            $record = self::create_record($cohort);
        }
        self::execute((int)$record->id, $trace);
        return $DB->get_record('tool_mucohort_sync', ['id' => $record->id], '*', MUST_EXIST);
    }

    /**
     * Insert queued sync history record.
     *
     * @param rules $cohort
     * @return stdClass|null new tool_mucohort_sync record, null if sync is already queued or running
     */
    private static function create_record(rules $cohort): ?stdClass {
        global $DB;

        if (self::get_current($cohort)) {
            return null;
        }

        $mucohortid = (int)$cohort->get_mucohort()->id;
        $last = (int)$DB->get_field('tool_mucohort_sync', 'MAX(timequeued)', ['mucohortid' => $mucohortid]);

        $record = new stdClass();
        $record->mucohortid = $mucohortid;
        // The time must be unique for each cohort.
        $record->timequeued = max(time(), $last + 1);
        $record->added = 0;
        $record->removed = 0;
        $record->status = self::STATUS_QUEUED;
        $record->id = $DB->insert_record('tool_mucohort_sync', $record);

        return $DB->get_record('tool_mucohort_sync', ['id' => $record->id], '*', MUST_EXIST);
    }

    /**
     * Execute queued sync.
     *
     * @param int $syncid
     * @param \progress_trace $trace
     */
    public static function execute(int $syncid, \progress_trace $trace): void {
        global $DB;

        $sync = $DB->get_record('tool_mucohort_sync', ['id' => $syncid]);
        if (!$sync || (int)$sync->status !== self::STATUS_QUEUED) {
            $trace->output('Sync ' . $syncid . ' is not queued, skipping');
            return;
        }

        $cohortid = $DB->get_field('tool_mucohort', 'cohortid', ['id' => $sync->mucohortid]);
        $cohort = $cohortid ? base::fetch((int)$cohortid, IGNORE_MISSING) : null;
        if (!($cohort instanceof rules)) {
            self::finish($sync, self::STATUS_FAILED, get_string('sync_error_cohort', 'tool_mucohort'));
            return;
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory('tool_mucohort_sync');
        $lock = $lockfactory->get_lock('cohort_' . $sync->mucohortid, 0);
        if (!$lock) {
            self::finish($sync, self::STATUS_FAILED, get_string('sync_error_locked', 'tool_mucohort'));
            return;
        }

        try {
            $sync->status = self::STATUS_RUNNING;
            $sync->timestarted = time();
            $DB->update_record('tool_mucohort_sync', $sync);
            $trace->output('Synchronising cohort ' . $cohort->get_id() . ': ' . $cohort->get_cohort()->name);
            self::process($cohort, $sync, $trace);
        } finally {
            $lock->release();
        }

        /** @var rules $cohort */
        $cohort = base::fetch($cohort->get_id());
        $cohort->update_schedule();
        extdb_source::remove_unused($cohort);
    }

    /**
     * Queue syncs of all cohorts that are due, high priority cohorts first, then the most overdue.
     *
     * @param \progress_trace $trace
     * @return int number of queued syncs
     */
    public static function queue_due(\progress_trace $trace): int {
        global $DB;

        $sql = "SELECT m.cohortid
                  FROM {tool_mucohort_rules} r
                  JOIN {tool_mucohort} m ON m.id = r.mucohortid
                 WHERE r.timenextsync IS NOT NULL AND r.timenextsync <= :now AND r.broken = 0
              ORDER BY r.syncpriority ASC, r.timenextsync ASC, r.id ASC";
        $cohortids = $DB->get_fieldset_sql($sql, ['now' => time()]);

        $count = 0;
        foreach ($cohortids as $cohortid) {
            $cohort = base::fetch((int)$cohortid, IGNORE_MISSING);
            if (!($cohort instanceof rules)) {
                continue;
            }
            if (!self::can_sync($cohort)) {
                if ($cohort->get_state() === rules::STATE_BROKEN) {
                    // Rules are not valid any more, the schedule is cleared until they are fixed.
                    $DB->set_field('tool_mucohort_rules', 'broken', 1, ['id' => $cohort->get_rules_record()->id]);
                    $trace->output('Rules of cohort ' . $cohort->get_id() . ' are broken');
                }
                $cohort->update_schedule();
                continue;
            }
            if (self::queue($cohort)) {
                $trace->output('Queued sync of cohort ' . $cohort->get_id() . ': ' . $cohort->get_cohort()->name);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Delete old sync history, the latest sync of each cohort is always kept.
     *
     * @param \progress_trace $trace
     * @return int number of deleted records
     */
    public static function purge_history(\progress_trace $trace): int {
        global $DB;

        $retention = (int)get_config('tool_mucohort', 'syncretention');
        if ($retention <= 0) {
            return 0;
        }
        $sql = "SELECT s.id
                  FROM {tool_mucohort_sync} s
                 WHERE s.timequeued < :cutoff AND s.status NOT IN (:queued, :running, :cancel)
                       AND EXISTS (SELECT 'x'
                                     FROM {tool_mucohort_sync} n
                                    WHERE n.mucohortid = s.mucohortid AND n.timequeued > s.timequeued)";
        $params = [
            'cutoff' => time() - $retention,
            'queued' => self::STATUS_QUEUED,
            'running' => self::STATUS_RUNNING,
            'cancel' => self::STATUS_CANCELREQUESTED,
        ];
        $ids = $DB->get_fieldset_sql($sql, $params);
        foreach (array_chunk($ids, 1000) as $chunk) {
            $DB->delete_records_list('tool_mucohort_sync', 'id', $chunk);
        }
        if ($ids) {
            $trace->output('Deleted old sync history records: ' . count($ids));
        }
        return count($ids);
    }

    /**
     * Apply membership changes.
     *
     * @param rules $cohort
     * @param stdClass $sync
     * @param \progress_trace $trace
     */
    private static function process(rules $cohort, stdClass $sync, \progress_trace $trace): void {
        global $DB;

        $rulesrecord = $cohort->get_rules_record();
        try {
            $ruleset = $cohort->get_active_ruleset();
            if (!$ruleset) {
                throw new broken_rules_exception('rules_state_inactive_desc');
            }
            $cohort->before_sync($ruleset, null, $trace);
            $problems = $cohort->get_problems($ruleset, false);
            if ($problems) {
                throw new broken_rules_exception('rules_state_broken_desc');
            }
            $additions = $cohort->get_additions_sql($ruleset)->wrap('SELECT u.id FROM {user} u WHERE ', ' ORDER BY u.id');
            $removals = $cohort->get_removals_sql($ruleset)->wrap('SELECT u.id FROM {user} u WHERE ', ' ORDER BY u.id');
        } catch (rule_not_ready_exception $ex) {
            // Rules are fine, sync continues normally when the data is ready.
            $trace->output('Rules are not ready: ' . $ex->getMessage(), 1);
            self::finish($sync, self::STATUS_BROKEN, $ex->getMessage());
            return;
        } catch (broken_rules_exception $ex) {
            $DB->set_field('tool_mucohort_rules', 'broken', 1, ['id' => $rulesrecord->id]);
            $trace->output('Rules are broken: ' . $ex->getMessage(), 1);
            self::finish($sync, self::STATUS_BROKEN, $ex->getMessage());
            return;
        }
        if ($rulesrecord->broken) {
            $DB->set_field('tool_mucohort_rules', 'broken', 0, ['id' => $rulesrecord->id]);
        }

        if ($cohort->get_synctype() === rules::SYNCTYPE_NONE) {
            $trace->output('Synchronisation is disabled', 1);
            self::finish($sync, self::STATUS_OK, get_string('rules_state_disabled_desc', 'tool_mucohort'));
            return;
        }

        $error = null;
        $processed = 0;
        // User ids are loaded into memory, the queries must not see the changes being made.
        foreach (['add' => $additions, 'remove' => $removals] as $action => $sql) {
            $userids = $DB->get_fieldset_sql($sql->sql, $sql->params);
            foreach ($userids as $userid) {
                // Stopping between users is safe, only consistency of each user matters.
                $stop = \core\local\cli\shutdown::should_gracefully_exit();
                if (!$stop && $processed > 0 && $processed % self::CHECK_INTERVAL === 0) {
                    $stop = self::is_cancel_requested((int)$sync->id);
                }
                if ($stop) {
                    $trace->output('Sync cancelled', 1);
                    self::finish($sync, self::STATUS_CANCELLED, null);
                    return;
                }
                $processed++;
                try {
                    // Any problem of one user must not stop the whole sync, including errors in observers of other plugins.
                    if ($action === 'add') {
                        if (membership_writer::add_member($cohort->get_id(), (int)$userid)) {
                            $sync->added++;
                        }
                    } else {
                        if (membership_writer::remove_member($cohort->get_id(), (int)$userid)) {
                            $sync->removed++;
                        }
                    }
                } catch (\Throwable $ex) {
                    $trace->output("Error processing user $userid: " . $ex->getMessage(), 1);
                    $error = $error ?? $ex->getMessage();
                }
            }
        }

        $trace->output("Added: {$sync->added}, removed: {$sync->removed}", 1);
        if ($error !== null) {
            self::finish($sync, self::STATUS_FAILED, $error);
        } else {
            self::finish($sync, self::STATUS_OK, null);
        }
    }

    /**
     * Update membership of one user immediately, used after login and user creation.
     *
     * No locks are used and any problem is ignored, the next regular sync fixes it.
     * This must never break or slow down login or user creation.
     *
     * @param rules $cohort
     * @param int $userid
     * @return bool true if membership was changed
     */
    public static function sync_user(rules $cohort, int $userid): bool {
        try {
            $record = $cohort->get_rules_record();
            if ($record->rulesjson === null || $record->broken || $cohort->get_synctype() === rules::SYNCTYPE_NONE) {
                return false;
            }
            $ruleset = $cohort->get_active_ruleset();
            $cohort->before_sync($ruleset, $userid, new \null_progress_trace());
            if (rules::count_users($cohort->get_additions_sql($ruleset, $userid))) {
                return membership_writer::add_member($cohort->get_id(), $userid);
            }
            if (rules::count_users($cohort->get_removals_sql($ruleset, $userid))) {
                return membership_writer::remove_member($cohort->get_id(), $userid);
            }
        } catch (\Throwable $ex) {
            debugging('Cohort membership update failed: ' . $ex->getMessage(), DEBUG_DEVELOPER);
        }
        return false;
    }

    /**
     * Update membership of user in cohorts with instant sync.
     *
     * @param int $userid
     * @param string[] $flags tool_mucohort_rules fields, any of them enables the instant sync
     */
    public static function sync_user_instantly(int $userid, array $flags): void {
        global $DB;

        if (!$flags) {
            return;
        }
        try {
            $conditions = array_map(fn($flag) => "r.$flag = 1", $flags);
            $sql = "SELECT m.cohortid
                      FROM {tool_mucohort_rules} r
                      JOIN {tool_mucohort} m ON m.id = r.mucohortid
                     WHERE (" . implode(' OR ', $conditions) . ")
                           AND r.rulesjson IS NOT NULL AND r.broken = 0 AND r.synctype <> :none
                  ORDER BY r.syncpriority ASC, r.id ASC";
            $cohortids = $DB->get_fieldset_sql($sql, ['none' => rules::SYNCTYPE_NONE]);
            foreach ($cohortids as $cohortid) {
                $cohort = base::fetch((int)$cohortid, IGNORE_MISSING);
                if ($cohort instanceof rules) {
                    self::sync_user($cohort, $userid);
                }
            }
        } catch (\Throwable $ex) {
            debugging('Cohort membership update failed: ' . $ex->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Was cancellation of the sync requested?
     *
     * @param int $syncid
     * @return bool
     */
    private static function is_cancel_requested(int $syncid): bool {
        global $DB;
        $status = $DB->get_field('tool_mucohort_sync', 'status', ['id' => $syncid]);
        return (int)$status === self::STATUS_CANCELREQUESTED;
    }

    /**
     * Store sync result.
     *
     * @param stdClass $sync
     * @param int $status
     * @param string|null $message
     */
    private static function finish(stdClass $sync, int $status, ?string $message): void {
        global $DB;

        $sync->status = $status;
        $sync->message = $message;
        if ($sync->timestarted) {
            $sync->duration = time() - $sync->timestarted;
        }
        $DB->update_record('tool_mucohort_sync', $sync);

        $cohortid = $DB->get_field('tool_mucohort', 'cohortid', ['id' => $sync->mucohortid]);
        $cohort = $cohortid ? base::fetch((int)$cohortid, IGNORE_MISSING) : null;
        if (!($cohort instanceof rules)) {
            return;
        }
        if ($status === self::STATUS_OK || $status === self::STATUS_CANCELLED) {
            \tool_mucohort\event\sync_completed::create_from_sync($sync, $cohort)->trigger();
        } else {
            \tool_mucohort\event\sync_failed::create_from_sync($sync, $cohort)->trigger();
        }
    }

    /**
     * Mark syncs that cannot finish any more as failed, so that they do not block new syncs.
     *
     * @param int $mucohortid
     */
    private static function cleanup(int $mucohortid): void {
        global $DB;

        // Lost adhoc tasks.
        $select = "mucohortid = :mucohortid AND status = :queued AND timequeued < :old";
        $params = ['mucohortid' => $mucohortid, 'queued' => self::STATUS_QUEUED, 'old' => time() - DAYSECS];
        foreach ($DB->get_records_select('tool_mucohort_sync', $select, $params) as $sync) {
            self::finish($sync, self::STATUS_FAILED, get_string('sync_error_lost', 'tool_mucohort'));
        }

        // Running syncs without a lock were interrupted.
        $select = "mucohortid = :mucohortid AND status IN (:running, :cancel)";
        $params = ['mucohortid' => $mucohortid, 'running' => self::STATUS_RUNNING, 'cancel' => self::STATUS_CANCELREQUESTED];
        $syncs = $DB->get_records_select('tool_mucohort_sync', $select, $params);
        if (!$syncs) {
            return;
        }
        $lockfactory = \core\lock\lock_config::get_lock_factory('tool_mucohort_sync');
        $lock = $lockfactory->get_lock('cohort_' . $mucohortid, 0);
        if (!$lock) {
            return;
        }
        try {
            foreach ($syncs as $sync) {
                self::finish($sync, self::STATUS_FAILED, get_string('sync_error_interrupted', 'tool_mucohort'));
            }
        } finally {
            $lock->release();
        }
    }
}
