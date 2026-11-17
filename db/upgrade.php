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
 * Advanced cohort plugin upgrades.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade tool_mucohort.
 *
 * @param mixed $oldversion
 * @return true
 */
function xmldb_tool_mucohort_upgrade($oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026111653) {
        // Define field usecohort to be added to tool_mucohort_access.
        $table = new xmldb_table('tool_mucohort_access');
        $field = new xmldb_field('usecohort', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'iscurator');

        // Conditionally launch add field usecohort.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);

            // Any access record allowed using of the cohort before the permission was added.
            $DB->set_field('tool_mucohort_access', 'usecohort', 1, []);
        }

        // Mucohort savepoint reached.
        upgrade_plugin_savepoint(true, 2026111653, 'tool', 'mucohort');
    }

    return true;
}
