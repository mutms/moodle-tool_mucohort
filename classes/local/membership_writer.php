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

/**
 * Low level writer of cohort membership changes of advanced cohorts.
 *
 * Membership is changed via core cohort API one user at a time, so that core events
 * are triggered for each change. Only consistency of each individual user matters,
 * there is no all-or-nothing transaction.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class membership_writer {
    /**
     * Add member, existing membership is not an error.
     *
     * @param int $cohortid
     * @param int $userid
     * @return bool true if user was added
     */
    public static function add_member(int $cohortid, int $userid): bool {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/cohort/lib.php');

        if ($DB->record_exists('cohort_members', ['cohortid' => $cohortid, 'userid' => $userid])) {
            return false;
        }
        try {
            cohort_add_member($cohortid, $userid);
        } catch (\dml_write_exception $ex) {
            // Somebody else added the user meanwhile, this is fine.
            if ($DB->record_exists('cohort_members', ['cohortid' => $cohortid, 'userid' => $userid])) {
                return false;
            }
            throw $ex;
        }
        return true;
    }

    /**
     * Remove member, missing membership is not an error.
     *
     * @param int $cohortid
     * @param int $userid
     * @return bool true if user was removed
     */
    public static function remove_member(int $cohortid, int $userid): bool {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/cohort/lib.php');

        if (!$DB->record_exists('cohort_members', ['cohortid' => $cohortid, 'userid' => $userid])) {
            return false;
        }
        cohort_remove_member($cohortid, $userid);
        return true;
    }
}
