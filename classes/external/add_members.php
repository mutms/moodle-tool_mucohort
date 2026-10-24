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
 * Add members to a standard or curated cohort, restrictions of the cohort type apply.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class add_members extends bulk_change_api {
    /**
     * Describes the external function parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cohortid' => new external_value(PARAM_INT, 'Cohort id'),
            'userids' => new external_multiple_structure(new external_value(PARAM_INT, 'User id'), 'Users to add'),
        ]);
    }

    /**
     * Add members.
     *
     * @param int $cohortid
     * @param array $userids
     * @return array
     */
    public static function execute(int $cohortid, array $userids): array {
        ['cohortid' => $cohortid, 'userids' => $userids] = self::validate_parameters(
            self::execute_parameters(),
            ['cohortid' => $cohortid, 'userids' => $userids]
        );
        return self::apply_action($cohortid, $userids, bulk_changes::ACTION_ADD);
    }
}
