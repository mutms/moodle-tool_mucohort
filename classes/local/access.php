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

use tool_mucohort\local\type\base;
use stdClass;

/**
 * Per-cohort access records of advanced cohorts.
 *
 * Any access record allows finding and using the cohort, flags add more operations.
 * The curator is an honorary title that does not grant any permissions automatically.
 * Access records are changed only by users with tool/mucohort:manageaccess capability.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /** @var string[] permission flags of access records */
    public const array FLAGS = ['viewmembers', 'managemembers', 'manageexceptions'];

    /**
     * Returns access record of the user.
     *
     * @param base $cohort
     * @param int|null $userid null means current user
     * @return stdClass|null
     */
    public static function get_record(base $cohort, ?int $userid = null): ?stdClass {
        global $DB, $USER;

        $mucohort = $cohort->get_mucohort();
        if (!$mucohort) {
            return null;
        }
        $userid = $userid ?? (int)$USER->id;
        if (!$userid || isguestuser($userid)) {
            return null;
        }
        return $DB->get_record('tool_mucohort_access', ['mucohortid' => $mucohort->id, 'userid' => $userid]) ?: null;
    }

    /**
     * Does the user have the access record flag?
     *
     * @param base $cohort
     * @param string $flag one of FLAGS
     * @param int|null $userid null means current user
     * @return bool
     */
    public static function has_flag(base $cohort, string $flag, ?int $userid = null): bool {
        if (!in_array($flag, self::FLAGS, true)) {
            throw new \core\exception\coding_exception('Invalid access flag: ' . $flag);
        }
        $record = self::get_record($cohort, $userid);
        return $record && $record->$flag;
    }

    /**
     * Add or update access record.
     *
     * @param base $cohort advanced cohort
     * @param int $userid
     * @param array $flags list of enabled flags, managing flags require viewmembers
     * @param bool $iscurator
     * @return stdClass access record
     */
    public static function set(base $cohort, int $userid, array $flags, bool $iscurator): stdClass {
        global $DB, $USER;

        $mucohort = $cohort->get_mucohort();
        if (!$mucohort) {
            throw new \core\exception\coding_exception('Access records are supported in advanced cohorts only');
        }
        foreach ($flags as $flag) {
            if (!in_array($flag, self::FLAGS, true)) {
                throw new \core\exception\coding_exception('Invalid access flag: ' . $flag);
            }
        }
        if (!self::are_flags_valid($flags)) {
            throw new \core\exception\coding_exception('Managing of members or exceptions requires viewmembers flag');
        }
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

        $now = time();
        $trans = $DB->start_delegated_transaction();

        $record = $DB->get_record('tool_mucohort_access', ['mucohortid' => $mucohort->id, 'userid' => $user->id]);
        if (!$record) {
            $record = new stdClass();
            $record->mucohortid = $mucohort->id;
            $record->userid = $user->id;
            $record->timecreated = $now;
        }
        foreach (self::FLAGS as $flag) {
            $record->$flag = (int)in_array($flag, $flags, true);
        }
        if ($iscurator) {
            // There is only one curator per cohort.
            $DB->set_field_select(
                'tool_mucohort_access',
                'iscurator',
                null,
                'mucohortid = ? AND userid <> ? AND iscurator = 1',
                [$mucohort->id, $user->id]
            );
            $record->iscurator = 1;
        } else {
            $record->iscurator = null;
        }
        $record->timemodified = $now;
        $record->usermodified = $USER->id ?: null;

        if (isset($record->id)) {
            $DB->update_record('tool_mucohort_access', $record);
            $eventclass = \tool_mucohort\event\access_updated::class;
        } else {
            $record->id = $DB->insert_record('tool_mucohort_access', $record);
            $eventclass = \tool_mucohort\event\access_granted::class;
        }

        $trans->allow_commit();

        $record = $DB->get_record('tool_mucohort_access', ['id' => $record->id], '*', MUST_EXIST);
        $eventclass::create_from_record($record, $cohort)->trigger();

        return $record;
    }

    /**
     * Are the flags consistent? Managing of members or exceptions requires viewing of members.
     *
     * @param array $flags list of enabled flags
     * @return bool
     */
    public static function are_flags_valid(array $flags): bool {
        if (in_array('viewmembers', $flags, true)) {
            return true;
        }
        return !in_array('managemembers', $flags, true) && !in_array('manageexceptions', $flags, true);
    }

    /**
     * Remove access record.
     *
     * @param int $accessid
     */
    public static function remove(int $accessid): void {
        global $DB;

        $record = $DB->get_record('tool_mucohort_access', ['id' => $accessid]);
        if (!$record) {
            return;
        }
        $cohortid = $DB->get_field('tool_mucohort', 'cohortid', ['id' => $record->mucohortid], MUST_EXIST);
        $cohort = base::fetch((int)$cohortid);

        $DB->delete_records('tool_mucohort_access', ['id' => $record->id]);
        \tool_mucohort\event\access_revoked::create_from_record($record, $cohort)->trigger();
    }

    /**
     * Returns curator record.
     *
     * @param base $cohort
     * @return stdClass|null user record
     */
    public static function get_curator(base $cohort): ?stdClass {
        global $DB;

        $mucohort = $cohort->get_mucohort();
        if (!$mucohort) {
            return null;
        }
        $sql = "SELECT u.*
                  FROM {user} u
                  JOIN {tool_mucohort_access} a ON a.userid = u.id
                 WHERE a.mucohortid = :mucohortid AND a.iscurator = 1 AND u.deleted = 0";
        return $DB->get_record_sql($sql, ['mucohortid' => $mucohort->id]) ?: null;
    }

    /**
     * Does the user have any access records in cohorts of the context or its subcontexts?
     *
     * @param \core\context $context system or category context
     * @param int|null $userid null means current user
     * @return bool
     */
    public static function has_any_in_context(\core\context $context, ?int $userid = null): bool {
        global $DB, $USER;

        $userid = $userid ?? (int)$USER->id;
        if (!$userid || isguestuser($userid)) {
            return false;
        }
        $sql = "SELECT 'x'
                  FROM {tool_mucohort_access} a
                  JOIN {tool_mucohort} mc ON mc.id = a.mucohortid
                  JOIN {cohort} c ON c.id = mc.cohortid AND c.component = 'tool_mucohort'
                  JOIN {context} ctx ON ctx.id = c.contextid
                 WHERE a.userid = :userid AND (ctx.id = :contextid OR " . $DB->sql_like('ctx.path', ':path') . ")";
        $params = ['userid' => $userid, 'contextid' => $context->id, 'path' => $context->path . '/%'];
        return $DB->record_exists_sql($sql, $params);
    }
}
