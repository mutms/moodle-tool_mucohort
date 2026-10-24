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
// phpcs:disable moodle.Commenting.InlineComment.TypeHintingMatch

/**
 * Preview of draft rules - matching users and expected membership changes with query times.
 *
 * Generated SQL is shown only to users with tool/mucohort:debug capability.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\broken_rules_exception;
use tool_mucohort\local\management;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\rules;
use tool_mulib\local\sql;
use core\url;

/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */

require('../../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$cohort = base::fetch($id);
$context = $cohort->get_context();
if (!($cohort instanceof rules) || !$cohort->can_manage_rules()) {
    throw new required_capability_exception($context, 'tool/mucohort:manage', 'nopermissions', '');
}

$pageurl = new url('/admin/tool/mucohort/management/rules_preview.php', ['id' => $cohort->get_id()]);
$returnurl = new url('/admin/tool/mucohort/cohort_draft.php', ['id' => $cohort->get_id()]);
if ($cohort->get_rules_record()->pendingrulesjson === null) {
    redirect($returnurl);
}
management::setup_cohort_page($pageurl, $cohort, 'cohort_draft');
$title = get_string('rules_preview', 'tool_mucohort');
$PAGE->navbar->add($title);

core_php_time_limit::raise();

$showsql = has_capability('tool/mucohort:debug', $context);

// Count users and measure the query time.
$count = function (sql $condition): array {
    $start = microtime(true);
    $count = rules::count_users($condition);
    return [$count, round(microtime(true) - $start, 3)];
};

// Render SQL query in a collapsed block.
$rendersql = function (sql $condition) use ($showsql): string {
    if (!$showsql) {
        return '';
    }
    $query = $condition->wrap('SELECT u.id FROM {user} u WHERE ', '')->export_debug_query();
    $summary = html_writer::tag('summary', get_string('rules_preview_sql', 'tool_mucohort'));
    return html_writer::tag('details', $summary . html_writer::tag('pre', s($query), ['class' => 'border p-2 bg-light']));
};

echo $OUTPUT->header();
echo $OUTPUT->heading($title, 2, 'mb-3');
echo html_writer::tag('p', get_string('rules_preview_info', 'tool_mucohort'));

try {
    $ruleset = $cohort->get_pending_ruleset();
    // Rules may fetch or cache data first, problems are reported by rule evaluation.
    $cohort->before_preview($ruleset);
    $problems = $cohort->get_problems($ruleset);
    $broken = $cohort->get_problems($ruleset, false);
} catch (broken_rules_exception $ex) {
    echo $OUTPUT->notification($ex->getMessage(), \core\output\notification::NOTIFY_ERROR, false);
    echo $OUTPUT->footer();
    die;
}

$table = new html_table();
$table->id = 'tool_mucohort-preview-rules';
$table->head = [
    get_string('rule', 'tool_mucohort'),
    get_string('rule_matchcount', 'tool_mucohort'),
    get_string('rules_preview_time', 'tool_mucohort'),
];
$table->data = [];
$sqls = '';
foreach ($ruleset->get_rules() as $uuid => $rule) {
    $kind = get_string('rules_preview_' . $rule['kind'], 'tool_mucohort');
    $description = $kind . ': ' . $cohort->get_rule_description($rule);
    if (isset($problems[$uuid])) {
        $description .= html_writer::div(clean_string($problems[$uuid]), 'text-danger small');
        $table->data[] = [$description, '', ''];
        continue;
    }
    $condition = $cohort->get_rule_match_sql($ruleset, $uuid);
    [$users, $time] = $count($condition);
    $table->data[] = [$description . $rendersql($condition), $users, format_float($time, 3)];
}
// Candidate cohort limits additions only.
$candidate = rules::format_candidate_cohort($cohort->get_mucohort(), true);
$condition = $cohort->get_candidate_match_sql();
if ($condition) {
    $description = get_string('rules_candidate', 'tool_mucohort', $candidate);
    [$users, $time] = $count($condition);
    $table->data[] = [$description . $rendersql($condition), $users, format_float($time, 3)];
} else {
    $table->data[] = [get_string('candidatecohort', 'tool_mucohort') . ': ' . $candidate, '', ''];
}
echo html_writer::table($table);

if ($broken) {
    echo $OUTPUT->notification(get_string('rules_approve_broken', 'tool_mucohort'), \core\output\notification::NOTIFY_ERROR, false);
} else if ($problems) {
    $message = get_string('rules_approve_notready', 'tool_mucohort');
    echo $OUTPUT->notification($message, \core\output\notification::NOTIFY_WARNING, false);
} else if ($ruleset->get_rules()) {
    $table = new html_table();
    $table->id = 'tool_mucohort-preview-changes';
    $table->head = [
        get_string('rules_preview_change', 'tool_mucohort'),
        get_string('users'),
        get_string('rules_preview_time', 'tool_mucohort'),
    ];
    $changes = [
        'rules_preview_additions' => $cohort->get_additions_sql($ruleset),
        'rules_preview_removals' => $cohort->get_removals_sql($ruleset),
    ];
    if ($cohort->get_synctype() === rules::SYNCTYPE_ADDONLY) {
        $changes['rules_preview_removals_full'] = $cohort->get_removals_sql($ruleset, null, rules::SYNCTYPE_FULL);
    }
    $total = 0;
    foreach ($changes as $identifier => $condition) {
        [$users, $time] = $count($condition);
        $total += $time;
        $table->data[] = [get_string($identifier, 'tool_mucohort') . $rendersql($condition), $users, format_float($time, 3)];
    }
    echo html_writer::table($table);
    $synctype = rules::get_synctypes()[$cohort->get_synctype()];
    echo html_writer::tag('p', get_string('rules_preview_synctype', 'tool_mucohort', $synctype));
}

echo $OUTPUT->single_button($returnurl, get_string('back'), 'get');

echo $OUTPUT->footer();
