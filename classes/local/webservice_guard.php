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

/**
 * Prevents changes of cohorts owned by MuTMS plugins via core web services.
 *
 * Core cohort web services and inplace editing ignore cohort component,
 * this guard refuses changes of cohorts owned by MuTMS plugins only,
 * cohorts of other plugins keep the standard core behaviour.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class webservice_guard {
    /**
     * Check web service call before execution.
     *
     * @param stdClass $function external function info
     * @param array $params validated parameters in the order of definition
     */
    public static function check(stdClass $function, array $params): void {
        global $DB;

        $cohortids = [];
        $idnumbers = [];

        switch ($function->name) {
            case 'core_cohort_update_cohorts':
                foreach ($params[0] as $cohort) {
                    $cohortids[] = $cohort['id'];
                }
                break;
            case 'core_cohort_delete_cohorts':
                $cohortids = $params[0];
                break;
            case 'core_cohort_delete_cohort_members':
                foreach ($params[0] as $member) {
                    $cohortids[] = $member['cohortid'];
                }
                break;
            case 'core_cohort_add_cohort_members':
                foreach ($params[0] as $member) {
                    if ($member['cohorttype']['type'] === 'id') {
                        $cohortids[] = $member['cohorttype']['value'];
                    } else if ($member['cohorttype']['type'] === 'idnumber') {
                        $idnumbers[] = $member['cohorttype']['value'];
                    }
                }
                break;
            case 'core_update_inplace_editable':
                if ($params[0] === 'core_cohort') {
                    $cohortids[] = $params[2];
                }
                break;
            default:
                return;
        }

        foreach ($cohortids as $cohortid) {
            $component = $DB->get_field('cohort', 'component', ['id' => $cohortid]);
            if ($component && \tool_mulib\local\mulib::is_mutms_plugin($component)) {
                throw new \core\exception\moodle_exception('error_cohortcomponent', 'tool_mucohort');
            }
        }
        foreach ($idnumbers as $idnumber) {
            $component = $DB->get_field('cohort', 'component', ['idnumber' => $idnumber]);
            if ($component && \tool_mulib\local\mulib::is_mutms_plugin($component)) {
                throw new \core\exception\moodle_exception('error_cohortcomponent', 'tool_mucohort');
            }
        }
    }
}
