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
use tool_mucohort\local\type\advanced;
use tool_mucohort\local\type\base;

/**
 * Returns forced and blocked users of an advanced cohort.
 *
 * Reasons are returned only to users who may manage exceptions.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_exceptions extends external_api {
    /**
     * Describes the external function parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cohortid' => new external_value(PARAM_INT, 'Cohort id'),
        ]);
    }

    /**
     * Returns exceptions.
     *
     * @param int $cohortid
     * @return array
     */
    public static function execute(int $cohortid): array {
        global $DB;

        ['cohortid' => $cohortid] = self::validate_parameters(self::execute_parameters(), ['cohortid' => $cohortid]);

        $cohort = base::fetch($cohortid);
        $context = $cohort->get_context();
        self::validate_context($context);
        if (!($cohort instanceof advanced)) {
            throw new \core\exception\invalid_parameter_exception('Exceptions are supported in advanced cohorts only');
        }
        if (!$cohort->can_view_members()) {
            throw new \core\exception\required_capability_exception($context, 'tool/mucohort:viewmembers', 'nopermissions', '');
        }
        $showreason = $cohort->can_manage_exceptions();

        $types = [exception::TYPE_FORCED => 'forced', exception::TYPE_BLOCKED => 'blocked'];
        $sql = "SELECT e.*
                  FROM {tool_mucohort_exception} e
                  JOIN {user} u ON u.id = e.userid AND u.deleted = 0
                 WHERE e.mucohortid = ?
              ORDER BY e.id ASC";
        $result = [];
        foreach ($DB->get_records_sql($sql, [$cohort->get_mucohort()->id]) as $record) {
            $result[] = [
                'userid' => (int)$record->userid,
                'type' => $types[(int)$record->type],
                'reason' => $showreason ? $record->reason : null,
                'timecreated' => (int)$record->timecreated,
                'timemodified' => (int)$record->timemodified,
            ];
        }
        return $result;
    }

    /**
     * Describes the external function result.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'User id'),
                'type' => new external_value(PARAM_ALPHA, 'Exception type: forced or blocked'),
                'reason' => new external_value(
                    PARAM_RAW,
                    'Plain text reason, null if not set or not visible to the current user',
                    VALUE_OPTIONAL,
                    null,
                    NULL_ALLOWED
                ),
                'timecreated' => new external_value(PARAM_INT, 'Time created'),
                'timemodified' => new external_value(PARAM_INT, 'Time modified'),
            ])
        );
    }
}
