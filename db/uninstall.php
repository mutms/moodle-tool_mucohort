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
 * Advanced cohort management uninstallation support.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Convert advanced cohorts to standard cohorts and remove synchronised roles before uninstall.
 *
 * Cohorts and their members are kept, data in plugin tables is deleted by the uninstallation.
 * Core removes role assignments of enrolment plugins only, so roles of role sync are removed here.
 *
 * @return bool always true
 */
function xmldb_tool_mucohort_uninstall() {
    global $DB;

    $DB->set_field('cohort', 'component', '', ['component' => 'tool_mucohort']);

    role_unassign_all(['component' => 'tool_mucohort']);

    return true;
}
