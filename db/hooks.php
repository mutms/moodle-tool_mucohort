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
 * Cohort management hook callbacks.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\after_config::class,
        'callback' => [\tool_mucohort\callback\core::class, 'hook_after_config'],
    ],
    [
        'hook' => \tool_mulib\hook\extdb_query_classes::class,
        'callback' => [\tool_mucohort\local\extdb_query::class, 'callback_extdb_query_classes'],
    ],
    [
        'hook' => \tool_mucohort\hook\cohort_usage::class,
        'callback' => [\tool_mucohort\local\usage::class, 'callback_cohort_usage'],
    ],
];
