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

use tool_mucohort\local\type\advanced;
use tool_mucohort\local\type\base;
use stdClass;

/**
 * Exceptions of advanced cohorts - forced and blocked users.
 *
 * Use only in exceptional cases or during cohort type migration.
 * Forcing adds the user immediately, blocking removes the user immediately.
 * Removing of a forced user keeps the membership, removing of a blocked user does not add the user back.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class exception {
    /** @var int user is always a member */
    public const int TYPE_FORCED = 1;
    /** @var int user is never a member */
    public const int TYPE_BLOCKED = 2;

    /**
     * Returns exception type names.
     *
     * @return array type => name
     */
    public static function get_types(): array {
        return [
            self::TYPE_FORCED => get_string('exception_forced', 'tool_mucohort'),
            self::TYPE_BLOCKED => get_string('exception_blocked', 'tool_mucohort'),
        ];
    }

    /**
     * Returns exception of user.
     *
     * @param advanced $cohort
     * @param int $userid
     * @return stdClass|null
     */
    public static function get_record(advanced $cohort, int $userid): ?stdClass {
        global $DB;
        $params = ['mucohortid' => $cohort->get_mucohort()->id, 'userid' => $userid];
        return $DB->get_record('tool_mucohort_exception', $params) ?: null;
    }

    /**
     * Add or update exception and apply it to the membership.
     *
     * @param advanced $cohort
     * @param int $userid
     * @param int $type TYPE_FORCED or TYPE_BLOCKED
     * @param string|null $reason optional plain text reason
     * @return stdClass exception record
     */
    public static function set(advanced $cohort, int $userid, int $type, ?string $reason): stdClass {
        global $DB, $USER;

        if ($type !== self::TYPE_FORCED && $type !== self::TYPE_BLOCKED) {
            throw new \core\exception\coding_exception('Invalid exception type: ' . $type);
        }
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
        if ($reason !== null && trim($reason) === '') {
            $reason = null;
        }

        $now = time();
        $record = self::get_record($cohort, (int)$user->id);
        if ($record) {
            $record->type = $type;
            $record->reason = $reason;
            $record->timemodified = $now;
            $record->usermodified = $USER->id ?: null;
            $DB->update_record('tool_mucohort_exception', $record);
            $eventclass = \tool_mucohort\event\exception_updated::class;
        } else {
            $record = new stdClass();
            $record->mucohortid = $cohort->get_mucohort()->id;
            $record->userid = $user->id;
            $record->type = $type;
            $record->reason = $reason;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $record->usermodified = $USER->id ?: null;
            $record->id = $DB->insert_record('tool_mucohort_exception', $record);
            $eventclass = \tool_mucohort\event\exception_created::class;
        }
        $record = $DB->get_record('tool_mucohort_exception', ['id' => $record->id], '*', MUST_EXIST);
        $eventclass::create_from_record($record, $cohort)->trigger();

        if ($type === self::TYPE_FORCED) {
            membership_writer::add_member($cohort->get_id(), (int)$user->id);
        } else {
            membership_writer::remove_member($cohort->get_id(), (int)$user->id);
        }

        return $record;
    }

    /**
     * Remove exception, membership is not changed.
     *
     * @param int $exceptionid
     */
    public static function remove(int $exceptionid): void {
        global $DB;

        $record = $DB->get_record('tool_mucohort_exception', ['id' => $exceptionid]);
        if (!$record) {
            return;
        }
        $cohortid = $DB->get_field('tool_mucohort', 'cohortid', ['id' => $record->mucohortid], MUST_EXIST);
        $cohort = base::fetch((int)$cohortid);

        $DB->delete_records('tool_mucohort_exception', ['id' => $record->id]);
        \tool_mucohort\event\exception_deleted::create_from_record($record, $cohort)->trigger();
    }

    /**
     * Returns number of exceptions of given type.
     *
     * @param advanced $cohort
     * @param int $type
     * @return int
     */
    public static function count(advanced $cohort, int $type): int {
        global $DB;
        return $DB->count_records('tool_mucohort_exception', ['mucohortid' => $cohort->get_mucohort()->id, 'type' => $type]);
    }
}
