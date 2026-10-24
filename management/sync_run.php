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
 * Run queued sync of rule-based cohort in this request with live output.
 *
 * The adhoc task of the queued sync then skips it. Leaving the page stops the sync,
 * it is then marked as interrupted and the next sync continues.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\management;
use tool_mucohort\local\sync;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\rules;
use core\url;

/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */

require('../../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();
require_sesskey();

$cohort = base::fetch($id);
$context = $cohort->get_context();
if (!($cohort instanceof rules) || !$cohort->can_manage_rules()) {
    throw new required_capability_exception($context, 'tool/mucohort:manage', 'nopermissions', '');
}

$pageurl = new url('/admin/tool/mucohort/management/sync_run.php', ['id' => $cohort->get_id()]);
$returnurl = new url('/admin/tool/mucohort/cohort_sync.php', ['id' => $cohort->get_id()]);

$current = sync::get_current($cohort);
if (!$current || (int)$current->status !== sync::STATUS_QUEUED) {
    redirect($returnurl);
}
management::setup_cohort_page($pageurl, $cohort, 'cohort_sync');
$title = get_string('sync_run', 'tool_mucohort');
$PAGE->navbar->add($title);

raise_memory_limit(MEMORY_HUGE);
core_php_time_limit::raise();

echo $OUTPUT->header();
echo $OUTPUT->heading($title, 2, 'mb-3');

// Output progress immediately, the same way as cron does.
while (ob_get_level()) {
    ob_end_flush();
}
echo html_writer::start_tag('pre', ['class' => 'tool_mucohort-sync-output']);
$result = sync::run($cohort, new html_progress_trace());
echo html_writer::end_tag('pre');

if ($result) {
    $a = (object)[
        'status' => sync::get_statuses()[(int)$result->status],
        'added' => $result->added,
        'removed' => $result->removed,
    ];
    echo $OUTPUT->notification(get_string('sync_run_result', 'tool_mucohort', $a), \core\output\notification::NOTIFY_INFO, false);
} else {
    echo $OUTPUT->notification(get_string('sync_running_already', 'tool_mucohort'), \core\output\notification::NOTIFY_ERROR, false);
}
echo $OUTPUT->continue_button($returnurl);

echo $OUTPUT->footer();
