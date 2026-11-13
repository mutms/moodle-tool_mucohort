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
 * Exceptions of advanced cohort.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\management;
use tool_mucohort\local\type\advanced;
use tool_mucohort\local\type\base;
use tool_mulib\output\header_actions;
use tool_mulib\output\muform\dialog\button;
use core\url;

/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */

require('../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$cohort = base::fetch($id);
$context = $cohort->get_context();
if (!($cohort instanceof advanced) || !$cohort->can_view_members()) {
    throw new required_capability_exception($context, 'tool/mucohort:viewmembers', 'nopermissions', '');
}

$currenturl = new url('/admin/tool/mucohort/cohort_exceptions.php', ['id' => $cohort->get_id()]);
management::setup_cohort_page($currenturl, $cohort, 'cohort_exceptions');

$actions = new header_actions(get_string('management_actions', 'tool_mucohort'));
if ($cohort->can_manage_exceptions()) {
    $url = new url('/admin/tool/mucohort/management/exception_edit.php', ['cohortid' => $cohort->get_id()]);
    $actions->add_button(new button($url, get_string('exception_add', 'tool_mucohort')));
    $url = new url('/admin/tool/mucohort/management/bulk_changes.php', ['id' => $cohort->get_id(), 'action' => 'force']);
    $actions->get_dropdown()->add_item(
        get_string('bulkchanges', 'tool_mucohort'),
        $url,
        new \core\output\pix_icon('i/upload', '')
    );
}
if ($actions->has_items()) {
    $PAGE->add_header_action($OUTPUT->render($actions));
}

echo $OUTPUT->header();

echo html_writer::tag('p', get_string('exceptions_info', 'tool_mucohort'), ['class' => 'text-muted']);

$report = \core_reportbuilder\system_report_factory::create(
    \tool_mucohort\reportbuilder\local\systemreports\exceptions::class,
    $context,
    '',
    '',
    0,
    ['cohortid' => $cohort->get_id()]
);
echo $OUTPUT->render(new \tool_mulib\output\report_filter_bar($report, pinned: [
    'user:fullname' => null,
    'user:type' => null,
    'user:member' => null,
]));
echo $report->output();
echo $OUTPUT->render(new \tool_mulib\output\report_pager($report));

echo $OUTPUT->footer();
