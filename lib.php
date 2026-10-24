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

/**
 * Cohort management core APIs.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\management;

/**
 * Add cohort management to category navigation.
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param context $coursecategorycontext The context of the course category
 */
function tool_mucohort_extend_navigation_category_settings($navigation, $coursecategorycontext): void {
    if (get_config('tool_mucohort', 'takeover')) {
        // Core cohort link is redirected to the new management.
        return;
    }
    if (!management::can_view_index($coursecategorycontext)) {
        return;
    }
    $url = management::get_index_url($coursecategorycontext);

    $navigation->add(
        get_string('management', 'tool_mucohort'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'tool_mucohort_index',
        new pix_icon('i/cohort', '')
    );
}

/**
 * Refuse changes of cohorts owned by MuTMS plugins via core web services.
 *
 * @param stdClass $function external function info
 * @param array $params validated parameters
 * @return bool false means continue normal execution
 */
function tool_mucohort_override_webservice_execution($function, $params) {
    \tool_mucohort\local\webservice_guard::check($function, $params);
    return false;
}
