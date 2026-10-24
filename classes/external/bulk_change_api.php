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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use tool_mucohort\local\bulk_changes;
use tool_mucohort\local\type\advanced;
use tool_mucohort\local\type\base;

/**
 * Base of web services that change members or exceptions of a list of users.
 *
 * The same rules as in bulk changes apply, users are processed only when ready,
 * the result of each user is returned.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class bulk_change_api extends external_api {
    /**
     * Apply bulk change action to users.
     *
     * @param int $cohortid
     * @param int[] $userids
     * @param string $action bulk_changes::ACTION_* constant
     * @param string|null $reason reason of new exceptions
     * @return array list of userid and result
     */
    protected static function apply_action(int $cohortid, array $userids, string $action, ?string $reason = null): array {
        $cohort = base::fetch($cohortid);
        $context = $cohort->get_context();
        self::validate_context($context);

        if (!isset(bulk_changes::get_actions($cohort)[$action])) {
            if (in_array($action, [bulk_changes::ACTION_ADD, bulk_changes::ACTION_REMOVE], true)) {
                $capability = ($cohort instanceof advanced) ? 'tool/mucohort:managemembers' : 'moodle/cohort:assign';
            } else {
                $capability = 'tool/mucohort:manageexceptions';
            }
            throw new \core\exception\required_capability_exception($context, $capability, 'nopermissions', '');
        }

        $identifiers = array_map('strval', $userids);
        $check = bulk_changes::apply($cohort, $identifiers, bulk_changes::IDENTIFIER_USERID, $action, $reason);

        $result = [];
        foreach ($check->outcomes as $outcome) {
            $result[] = ['userid' => (int)$outcome->identifier, 'result' => $outcome->result];
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
                'result' => new external_value(
                    PARAM_ALPHA,
                    'Result: ok = done, already = nothing to do, notallowed = cohort restrictions do not allow it, ' .
                    'notfound = user does not exist or is not available, duplicate = user id was listed already'
                ),
            ]),
            'Result for each listed user'
        );
    }
}
