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
 * Cohort management event observers.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => \core\event\user_deleted::class,
        'callback' => \tool_mucohort\local\event_observer::class . '::user_deleted',
    ],
    [
        'eventname' => \core\event\cohort_deleted::class,
        'callback' => \tool_mucohort\local\event_observer::class . '::cohort_deleted',
    ],
    [
        'eventname' => \core\event\cohort_member_added::class,
        'callback' => \tool_mucohort\local\event_observer::class . '::cohort_member_added',
    ],
    [
        'eventname' => \core\event\cohort_member_removed::class,
        'callback' => \tool_mucohort\local\event_observer::class . '::cohort_member_removed',
    ],
    [
        'eventname' => \core\event\user_loggedin::class,
        'callback' => \tool_mucohort\local\event_observer::class . '::user_loggedin',
    ],
    [
        'eventname' => \core\event\user_created::class,
        'callback' => \tool_mucohort\local\event_observer::class . '::user_created',
    ],
    [
        'eventname' => \core\event\user_info_field_deleted::class,
        'callback' => \tool_mucohort\local\event_observer::class . '::user_info_field_deleted',
    ],
];
