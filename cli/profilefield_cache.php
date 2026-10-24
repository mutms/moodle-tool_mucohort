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
 * Show or synchronise cache of custom profile field values used in cohort rules.
 *
 * @package    tool_mucohort
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\profilefield_cache;

define('CLI_SCRIPT', true);

/** @var moodle_database $DB */
/** @var stdClass $CFG */

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'list' => false,
        'sync' => false,
        'field' => null,
        'help' => false,
    ],
    [
        'h' => 'help',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] || (!$options['list'] && !$options['sync'])) {
    $help = <<<EOT
Show or synchronise cache of custom profile field values used in cohort rules.

Only fields used in rules are cached. Values are updated automatically when users
are created or updated and by a scheduled task; use --sync after bulk changes
of profile data that should be reflected immediately, for example after user upload.

Options:
--list                   List cached profile fields and their state
--sync                   Synchronise cached values of all users now, the duration of each field
                         is printed, so this can be also used to find slow fields
--field=SHORTNAME        With --sync synchronise only this profile field
-h, --help               Print out this help

Examples:
\$ php admin/tool/mucohort/cli/profilefield_cache.php --list
\$ php admin/tool/mucohort/cli/profilefield_cache.php --sync
\$ php admin/tool/mucohort/cli/profilefield_cache.php --sync --field=department

EOT;
    echo $help;
    exit($options['help'] ? 0 : 1);
}

$fields = profilefield_cache::get_fields();

if ($options['list']) {
    if (!$fields) {
        echo "No profile fields are cached.\n";
        exit(0);
    }
    foreach ($fields as $fieldid => $time) {
        $shortname = $DB->get_field('user_info_field', 'shortname', ['id' => $fieldid]);
        $format = get_string('strftimedatetimeshort', 'core_langconfig');
        $state = $time ? 'ready since ' . userdate($time, $format) : 'being prepared';
        $count = $DB->count_records('tool_mucohort_profilefield', ['fieldid' => $fieldid]);
        echo ($shortname === false ? "missing field $fieldid" : $shortname) . ": $state, cached users: $count\n";
    }
    exit(0);
}

if ($options['field'] !== null) {
    if (!is_string($options['field']) || trim($options['field']) === '') {
        cli_error('Option --field needs a profile field short name.');
    }
    $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => $options['field']]);
    if (!$fieldid) {
        cli_error('Profile field not found.');
    }
    if (!array_key_exists((int)$fieldid, $fields)) {
        cli_error('Profile field is not used in any rules, it is not cached.');
    }
    $fieldids = [(int)$fieldid];
} else {
    $fieldids = array_keys($fields);
}

core_php_time_limit::raise();
$trace = new null_progress_trace();
foreach ($fieldids as $fieldid) {
    // Duration of each field helps to find fields that make the cache slow.
    $start = microtime(true);
    profilefield_cache::populate_field($fieldid, $trace);
    $duration = round(microtime(true) - $start, 3);
    $shortname = $DB->get_field('user_info_field', 'shortname', ['id' => $fieldid]);
    echo ($shortname === false ? "missing field $fieldid" : $shortname) . ": synchronised in $duration seconds\n";
}
exit(0);
