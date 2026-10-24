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
// phpcs:disable moodle.Files.LineLength.TooLong
// phpcs:disable moodle.Files.LineLength.MaxExceeded

/**
 * Synchronise rule-based cohort or preview the changes.
 *
 * @package    tool_mucohort
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\broken_rules_exception;
use tool_mucohort\local\sync;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\rules;

define('CLI_SCRIPT', true);

/** @var moodle_database $DB */
/** @var stdClass $CFG */

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'cohortid' => null,
        'idnumber' => null,
        'dry-run' => false,
        'csv' => false,
        'export' => 'changes',
        'force' => false,
        'verbose' => false,
        'help' => false,
    ],
    [
        'h' => 'help',
        'v' => 'verbose',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] || ($options['cohortid'] === null && $options['idnumber'] === null)) {
    $help = <<<EOT
Synchronise membership of a rule-based cohort with its active rules.

The sync runs in this process with the same per-cohort lock as background syncs,
it is recorded in the sync history of the cohort. Use this script from system cron
for cohorts with manual sync only or when the exact time of sync matters.
Ctrl-C stops the sync safely between users, the next sync continues.

Without --force cohorts with automatic sync are synchronised only when the next sync
is due. Cohorts with broken rules are never synchronised.

Options:
--cohortid=INT           Id of rule-based cohort
--idnumber=STRING        Idnumber of rule-based cohort, alternative to --cohortid
--dry-run                Do not change anything, print the number of users to add and remove
--csv                    With --dry-run print CSV list of users instead of the numbers
--export=TYPE            What to export with --csv:
                           changes  users to be added and removed (default)
                           result   cohort members after the sync
--force                  Synchronise even if the next automatic sync is not due yet
-v, --verbose            Print generated SQL queries
-h, --help               Print out this help

Examples:
\$ php admin/tool/mucohort/cli/sync.php --idnumber=staff --dry-run
\$ php admin/tool/mucohort/cli/sync.php --idnumber=staff --dry-run --csv --export=result > staff.csv
\$ php admin/tool/mucohort/cli/sync.php --cohortid=12 --force

EOT;
    echo $help;
    exit($options['help'] ? 0 : 1);
}

// An option given without a value is true, it must not be used as value "1".
foreach (['cohortid', 'idnumber', 'export'] as $name) {
    if ($options[$name] === true) {
        cli_error("Option --{$name} needs a value.");
    }
}
if ($options['cohortid'] !== null && $options['idnumber'] !== null) {
    cli_error('Use either --cohortid or --idnumber, not both.');
}
if ($options['cohortid'] !== null) {
    if (!preg_match('/^[1-9][0-9]*$/D', (string)$options['cohortid'])) {
        cli_error('Option --cohortid needs a cohort id.');
    }
    $record = $DB->get_record('cohort', ['id' => (int)$options['cohortid']]);
} else {
    // Never look for a cohort with empty idnumber.
    if (trim((string)$options['idnumber']) === '') {
        cli_error('Option --idnumber needs a cohort idnumber.');
    }
    $record = $DB->get_record('cohort', ['idnumber' => $options['idnumber']]);
}
if (!$record) {
    cli_error('Cohort not found.');
}
$cohort = base::from_record($record);
if (!($cohort instanceof rules)) {
    cli_error('Cohort is not a rule-based cohort.');
}
if (!in_array($options['export'], ['changes', 'result'], true)) {
    cli_error('Invalid --export value.');
}
if ($options['csv'] && !$options['dry-run']) {
    cli_error('Option --csv can be used only with --dry-run.');
}

try {
    $ruleset = $cohort->get_active_ruleset();
    if (!$ruleset) {
        throw new broken_rules_exception('rules_state_inactive_desc');
    }
    $problems = $cohort->get_problems($ruleset);
    if ($problems) {
        cli_error(get_string('rules_state_broken_desc', 'tool_mucohort') . "\n  " . implode("\n  ", $problems));
    }
    $additions = $cohort->get_additions_sql($ruleset);
    $removals = $cohort->get_removals_sql($ruleset);
} catch (broken_rules_exception $ex) {
    cli_error($ex->getMessage());
}

if ($options['verbose']) {
    cli_heading('Users to add');
    echo $additions->wrap('SELECT u.id FROM {user} u WHERE ', '')->export_debug_query() . "\n\n";
    cli_heading('Members to remove');
    echo $removals->wrap('SELECT u.id FROM {user} u WHERE ', '')->export_debug_query() . "\n\n";
}

if ($options['dry-run']) {
    if (!$options['csv']) {
        echo 'Cohort: ' . $cohort->get_cohort()->name . "\n";
        echo 'Synchronisation: ' . rules::get_synctypes()[$cohort->get_synctype()] . "\n";
        echo 'Users to add: ' . rules::count_users($additions) . "\n";
        echo 'Members to remove: ' . rules::count_users($removals) . "\n";
        exit(0);
    }

    $fields = 'u.id, u.username, u.email, u.idnumber, u.firstname, u.lastname';
    $csv = fopen('php://stdout', 'w');
    if ($options['export'] === 'changes') {
        fputcsv($csv, ['action', 'userid', 'username', 'email', 'idnumber', 'firstname', 'lastname'], ',', '"', '');
        foreach (['add' => $additions, 'remove' => $removals] as $action => $condition) {
            $sql = $condition->wrap("SELECT $fields FROM {user} u WHERE ", ' ORDER BY u.id');
            $rs = $DB->get_recordset_sql($sql->sql, $sql->params);
            foreach ($rs as $user) {
                fputcsv($csv, [$action, $user->id, $user->username, $user->email, $user->idnumber, $user->firstname, $user->lastname], ',', '"', '');
            }
            $rs->close();
        }
    } else {
        // Members after sync are current members without removals plus additions.
        $member = new \tool_mulib\local\sql(
            "EXISTS (SELECT 'x' FROM {cohort_members} cm WHERE cm.cohortid = ? AND cm.userid = u.id)",
            [$cohort->get_id()]
        );
        $kept = \tool_mulib\local\sql::join(' AND ', [$member, $removals->wrap('NOT (', ')')]);
        $result = \tool_mulib\local\sql::join(' OR ', [$kept->wrap('(', ')'), $additions->wrap('(', ')')]);
        $sql = $result->wrap("SELECT $fields FROM {user} u WHERE ", ' ORDER BY u.id');
        fputcsv($csv, ['userid', 'username', 'email', 'idnumber', 'firstname', 'lastname'], ',', '"', '');
        $rs = $DB->get_recordset_sql($sql->sql, $sql->params);
        foreach ($rs as $user) {
            fputcsv($csv, [$user->id, $user->username, $user->email, $user->idnumber, $user->firstname, $user->lastname], ',', '"', '');
        }
        $rs->close();
    }
    fclose($csv);
    exit(0);
}

$schedule = $cohort->get_rules_record();
if (!$options['force'] && $schedule->timenextsync !== null && (int)$schedule->timenextsync > time()) {
    cli_error('Next sync is not due yet, use --force to synchronise now.');
}

\core\local\cli\shutdown::script_supports_graceful_exit();
raise_memory_limit(MEMORY_HUGE);
core_php_time_limit::raise();

$result = sync::run($cohort, new text_progress_trace());
if (!$result) {
    cli_error(get_string('sync_running_already', 'tool_mucohort'));
}
$status = sync::get_statuses()[(int)$result->status];
echo "Status: $status, added: {$result->added}, removed: {$result->removed}, duration: " . format_time((int)$result->duration) . "\n";
if ($result->message !== null) {
    echo $result->message . "\n";
}
exit(in_array((int)$result->status, [sync::STATUS_OK], true) ? 0 : 1);
