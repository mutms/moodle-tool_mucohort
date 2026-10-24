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

namespace tool_mucohort\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use tool_mucohort\local\exception;
use tool_mucohort\local\type\base;

/**
 * Returns members of a cohort, access records with view members flag are respected.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_members extends external_api {
    /**
     * Describes the external function parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cohortid' => new external_value(PARAM_INT, 'Cohort id'),
            'limitfrom' => new external_value(PARAM_INT, 'Skip this number of members', VALUE_DEFAULT, 0),
            'limitnum' => new external_value(PARAM_INT, 'Maximum number of returned members, 0 means all', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Returns members.
     *
     * @param int $cohortid
     * @param int $limitfrom
     * @param int $limitnum
     * @return array
     */
    public static function execute(int $cohortid, int $limitfrom = 0, int $limitnum = 0): array {
        global $DB;

        ['cohortid' => $cohortid, 'limitfrom' => $limitfrom, 'limitnum' => $limitnum] = self::validate_parameters(
            self::execute_parameters(),
            ['cohortid' => $cohortid, 'limitfrom' => $limitfrom, 'limitnum' => $limitnum]
        );

        $cohort = base::fetch($cohortid);
        $context = $cohort->get_context();
        self::validate_context($context);
        if (!$cohort->can_view_members()) {
            throw new \core\exception\required_capability_exception($context, 'tool/mucohort:viewmembers', 'nopermissions', '');
        }

        $mucohortid = $cohort->get_mucohort()->id ?? 0;
        $sql = "SELECT cm.id, cm.userid, cm.timeadded, e.id AS forcedid
                  FROM {cohort_members} cm
                  JOIN {user} u ON u.id = cm.userid AND u.deleted = 0
             LEFT JOIN {tool_mucohort_exception} e ON e.userid = cm.userid AND e.mucohortid = :mucohortid AND e.type = :forced
                 WHERE cm.cohortid = :cohortid
              ORDER BY cm.id ASC";
        $params = ['cohortid' => $cohort->get_id(), 'mucohortid' => $mucohortid, 'forced' => exception::TYPE_FORCED];
        $members = [];
        $rs = $DB->get_recordset_sql($sql, $params, max(0, $limitfrom), max(0, $limitnum));
        foreach ($rs as $member) {
            $members[] = [
                'userid' => (int)$member->userid,
                'timeadded' => (int)$member->timeadded,
                'forced' => (bool)$member->forcedid,
            ];
        }
        $rs->close();

        $total = $DB->count_records_sql(
            "SELECT COUNT('x')
               FROM {cohort_members} cm
               JOIN {user} u ON u.id = cm.userid AND u.deleted = 0
              WHERE cm.cohortid = ?",
            [$cohort->get_id()]
        );

        return ['total' => $total, 'members' => $members];
    }

    /**
     * Describes the external function result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'Total number of members'),
            'members' => new external_multiple_structure(
                new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'User id'),
                    'timeadded' => new external_value(PARAM_INT, 'Time when the user was added'),
                    'forced' => new external_value(PARAM_BOOL, 'User is a forced member'),
                ])
            ),
        ]);
    }
}
