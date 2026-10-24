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

/**
 * Cohort external functions.
 *
 * Core cohort web services cannot change cohorts owned by MuTMS plugins,
 * these functions apply the same rules as the cohort management UI.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'tool_mucohort_get_members' => [
        'classname' => tool_mucohort\external\get_members::class,
        'description' => 'Return members of a cohort.',
        'type' => 'read',
        'capabilities' => 'tool/mucohort:viewmembers',
    ],
    'tool_mucohort_add_members' => [
        'classname' => tool_mucohort\external\add_members::class,
        'description' => 'Add members to a standard or curated cohort.',
        'type' => 'write',
        'capabilities' => 'moodle/cohort:assign,tool/mucohort:managemembers',
    ],
    'tool_mucohort_remove_members' => [
        'classname' => tool_mucohort\external\remove_members::class,
        'description' => 'Remove members from a standard or curated cohort.',
        'type' => 'write',
        'capabilities' => 'moodle/cohort:assign,tool/mucohort:managemembers',
    ],
    'tool_mucohort_get_exceptions' => [
        'classname' => tool_mucohort\external\get_exceptions::class,
        'description' => 'Return forced and blocked users of an advanced cohort.',
        'type' => 'read',
        'capabilities' => 'tool/mucohort:viewmembers',
    ],
    'tool_mucohort_set_exceptions' => [
        'classname' => tool_mucohort\external\set_exceptions::class,
        'description' => 'Force or block users in an advanced cohort.',
        'type' => 'write',
        'capabilities' => 'tool/mucohort:manageexceptions',
    ],
    'tool_mucohort_remove_exceptions' => [
        'classname' => tool_mucohort\external\remove_exceptions::class,
        'description' => 'Remove forced and blocked exceptions in an advanced cohort.',
        'type' => 'write',
        'capabilities' => 'tool/mucohort:manageexceptions',
    ],
];
