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
use tool_mucohort\local\type\advanced;
use tool_mucohort\local\type\base;

/**
 * Synchronisation of roles assigned to members of advanced cohorts.
 *
 * Each tool_mucohort_role record defines a role assigned in a system or category context
 * to all cohort members, no context means the cohort context which follows the cohort
 * when it is moved. Role assignments use component tool_mucohort and itemid
 * of the record, so they cannot be changed manually and are removed together with the record.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class role_sync {
    /**
     * Returns roles that may be assigned to cohort members in a context by the current user.
     *
     * @param \core\context $context system or category context
     * @return array roleid => role name
     */
    public static function get_assignable_roles(\core\context $context): array {
        $result = [];
        foreach (get_assignable_roles($context, ROLENAME_BOTH) as $roleid => $name) {
            if (in_array($context->contextlevel, get_role_contextlevels($roleid))) {
                $result[$roleid] = $name;
            }
        }
        return $result;
    }

    /**
     * Returns record of role assigned to cohort members.
     *
     * @param advanced $cohort
     * @param int $roleid
     * @param int|null $contextid null means cohort context
     * @return stdClass|null tool_mucohort_role record
     */
    public static function get_record(advanced $cohort, int $roleid, ?int $contextid): ?stdClass {
        global $DB;
        $params = ['mucohortid' => $cohort->get_mucohort()->id, 'roleid' => $roleid];
        if ($contextid === null) {
            $select = 'mucohortid = :mucohortid AND roleid = :roleid AND contextid IS NULL';
        } else {
            $select = 'mucohortid = :mucohortid AND roleid = :roleid AND contextid = :contextid';
            $params['contextid'] = $contextid;
        }
        return $DB->get_record_select('tool_mucohort_role', $select, $params) ?: null;
    }

    /**
     * Add role assigned to all cohort members, existing members get the role from adhoc task.
     *
     * @param advanced $cohort
     * @param int $roleid
     * @param int|null $contextid null means cohort context
     * @return stdClass tool_mucohort_role record
     */
    public static function add(advanced $cohort, int $roleid, ?int $contextid): stdClass {
        global $DB, $USER;

        $record = self::get_record($cohort, $roleid, $contextid);
        if (!$record) {
            $record = (object)['mucohortid' => $cohort->get_mucohort()->id, 'roleid' => $roleid, 'contextid' => $contextid];
            $record->timecreated = time();
            $record->usercreated = $USER->id ?: null;
            $record->id = $DB->insert_record('tool_mucohort_role', $record);
            $record = $DB->get_record('tool_mucohort_role', ['id' => $record->id], '*', MUST_EXIST);
            \tool_mucohort\event\role_added::create_from_record($record, $cohort)->trigger();
        }

        $task = new \tool_mucohort\task\role_sync();
        $task->set_custom_data(['roleid' => (int)$record->id]);
        \core\task\manager::queue_adhoc_task($task, true);

        return $record;
    }

    /**
     * Remove role of cohort members, all role assignments are removed.
     *
     * @param int $id tool_mucohort_role id
     */
    public static function remove(int $id): void {
        global $DB;

        $record = $DB->get_record('tool_mucohort_role', ['id' => $id]);
        if (!$record) {
            return;
        }
        role_unassign_all(['component' => 'tool_mucohort', 'itemid' => $record->id]);
        $DB->delete_records('tool_mucohort_role', ['id' => $record->id]);

        $cohortid = $DB->get_field('tool_mucohort', 'cohortid', ['id' => $record->mucohortid]);
        $cohort = $cohortid ? base::fetch((int)$cohortid, IGNORE_MISSING) : null;
        if ($cohort) {
            \tool_mucohort\event\role_removed::create_from_record($record, $cohort)->trigger();
        }
    }

    /**
     * Remove all roles of advanced cohort, used when cohort is deleted or converted to standard cohort.
     *
     * @param int $mucohortid
     */
    public static function remove_all(int $mucohortid): void {
        global $DB;
        foreach ($DB->get_records('tool_mucohort_role', ['mucohortid' => $mucohortid]) as $record) {
            role_unassign_all(['component' => 'tool_mucohort', 'itemid' => $record->id]);
        }
        $DB->delete_records('tool_mucohort_role', ['mucohortid' => $mucohortid]);
    }

    /**
     * Synchronise role assignments with cohort members.
     *
     * Roles in deleted contexts are skipped, assignments were deleted with the context.
     *
     * @param int $id tool_mucohort_role id
     * @param \progress_trace $trace
     */
    public static function sync(int $id, \progress_trace $trace): void {
        global $DB;

        $sql = "SELECT r.*, c.id AS cohortid, COALESCE(r.contextid, c.contextid) AS effectivecontextid
                  FROM {tool_mucohort_role} r
                  JOIN {tool_mucohort} m ON m.id = r.mucohortid
                  JOIN {cohort} c ON c.id = m.cohortid
                 WHERE r.id = :id";
        $record = $DB->get_record_sql($sql, ['id' => $id]);
        if (!$record || !\core\context::instance_by_id($record->effectivecontextid, IGNORE_MISSING)) {
            return;
        }

        $sql = "SELECT cm.userid
                  FROM {cohort_members} cm
                  JOIN {user} u ON u.id = cm.userid AND u.deleted = 0
             LEFT JOIN {role_assignments} ra ON ra.userid = cm.userid AND ra.roleid = :roleid AND ra.contextid = :contextid
                                            AND ra.component = 'tool_mucohort' AND ra.itemid = :itemid
                 WHERE cm.cohortid = :cohortid AND ra.id IS NULL";
        $params = [
            'roleid' => $record->roleid,
            'contextid' => $record->effectivecontextid,
            'itemid' => $record->id,
            'cohortid' => $record->cohortid,
        ];
        $missing = $DB->get_fieldset_sql($sql, $params);
        foreach ($missing as $userid) {
            role_assign($record->roleid, $userid, $record->effectivecontextid, 'tool_mucohort', $record->id);
        }

        // Assignments of former members and assignments in previous cohort context.
        $sql = "SELECT ra.id, ra.userid, ra.contextid
                  FROM {role_assignments} ra
             LEFT JOIN {cohort_members} cm ON cm.userid = ra.userid AND cm.cohortid = :cohortid
                 WHERE ra.component = 'tool_mucohort' AND ra.itemid = :itemid
                       AND (cm.id IS NULL OR ra.contextid <> :contextid)";
        $params = ['cohortid' => $record->cohortid, 'itemid' => $record->id, 'contextid' => $record->effectivecontextid];
        $extra = $DB->get_records_sql($sql, $params);
        foreach ($extra as $ra) {
            role_unassign($record->roleid, $ra->userid, $ra->contextid, 'tool_mucohort', $record->id);
        }

        if ($missing || $extra) {
            $trace->output("Role sync $record->id: assigned " . count($missing) . ', unassigned ' . count($extra));
        }
    }

    /**
     * Queue synchronisation of roles in cohort context, used after cohort is moved.
     *
     * @param advanced $cohort
     */
    public static function cohort_moved(advanced $cohort): void {
        global $DB;
        $select = 'mucohortid = :mucohortid AND contextid IS NULL';
        $ids = $DB->get_fieldset_select('tool_mucohort_role', 'id', $select, ['mucohortid' => $cohort->get_mucohort()->id]);
        foreach ($ids as $id) {
            $task = new \tool_mucohort\task\role_sync();
            $task->set_custom_data(['roleid' => (int)$id]);
            \core\task\manager::queue_adhoc_task($task, true);
        }
    }

    /**
     * Synchronise all role assignments, fixes changes missed by event observers.
     *
     * @param \progress_trace $trace
     */
    public static function sync_all(\progress_trace $trace): void {
        global $DB;
        foreach ($DB->get_fieldset('tool_mucohort_role', 'id', []) as $id) {
            self::sync((int)$id, $trace);
        }
    }

    /**
     * Update roles of one user in one cohort, called after membership change.
     *
     * @param int $cohortid
     * @param int $userid
     * @param bool $member
     */
    public static function update_user(int $cohortid, int $userid, bool $member): void {
        global $DB;

        $sql = "SELECT r.*, COALESCE(r.contextid, c.contextid) AS effectivecontextid
                  FROM {tool_mucohort_role} r
                  JOIN {tool_mucohort} m ON m.id = r.mucohortid
                  JOIN {cohort} c ON c.id = m.cohortid
                 WHERE m.cohortid = :cohortid";
        foreach ($DB->get_records_sql($sql, ['cohortid' => $cohortid]) as $record) {
            if (!\core\context::instance_by_id($record->effectivecontextid, IGNORE_MISSING)) {
                continue;
            }
            if ($member) {
                role_assign($record->roleid, $userid, $record->effectivecontextid, 'tool_mucohort', $record->id);
            } else {
                role_unassign($record->roleid, $userid, $record->effectivecontextid, 'tool_mucohort', $record->id);
            }
        }
    }
}
