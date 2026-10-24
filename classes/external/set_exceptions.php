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

use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_value;
use tool_mucohort\local\bulk_changes;

/**
 * Force or block users in an advanced cohort, the membership is changed immediately.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class set_exceptions extends bulk_change_api {
    /**
     * Describes the external function parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cohortid' => new external_value(PARAM_INT, 'Cohort id'),
            'userids' => new external_multiple_structure(new external_value(PARAM_INT, 'User id'), 'Users'),
            'type' => new external_value(PARAM_ALPHA, 'Exception type: forced or blocked'),
            'reason' => new external_value(PARAM_RAW, 'Optional plain text reason', VALUE_DEFAULT, null, NULL_ALLOWED),
        ]);
    }

    /**
     * Add or update exceptions.
     *
     * @param int $cohortid
     * @param array $userids
     * @param string $type
     * @param string|null $reason
     * @return array
     */
    public static function execute(int $cohortid, array $userids, string $type, ?string $reason = null): array {
        ['cohortid' => $cohortid, 'userids' => $userids, 'type' => $type, 'reason' => $reason] = self::validate_parameters(
            self::execute_parameters(),
            ['cohortid' => $cohortid, 'userids' => $userids, 'type' => $type, 'reason' => $reason]
        );
        $actions = ['forced' => bulk_changes::ACTION_FORCE, 'blocked' => bulk_changes::ACTION_BLOCK];
        if (!isset($actions[$type])) {
            throw new \core\exception\invalid_parameter_exception('Invalid exception type: ' . $type);
        }
        if ($reason !== null) {
            $reason = trim(clean_param($reason, PARAM_TEXT));
        }
        return self::apply_action($cohortid, $userids, $actions[$type], $reason === '' ? null : $reason);
    }
}
