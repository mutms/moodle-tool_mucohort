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
 * Sync history of rule-based cohort.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\management;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\rules;
use core\url;

/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */

require('../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$cohort = base::fetch($id);
$context = $cohort->get_context();
if (!($cohort instanceof rules) || !$cohort->can_view()) {
    throw new required_capability_exception($context, 'moodle/cohort:view', 'nopermissions', '');
}

$currenturl = new url('/admin/tool/mucohort/cohort_sync.php', ['id' => $cohort->get_id()]);
management::setup_cohort_page($currenturl, $cohort, 'cohort_sync');

/** @var \tool_mucohort\output\renderer $renderer */
$renderer = $PAGE->get_renderer('tool_mucohort');

$actions = new \tool_mulib\output\header_actions(get_string('management_actions', 'tool_mucohort'));
$syncbutton = $renderer->render_sync_button($cohort);
if ($syncbutton !== '') {
    $actions->add_button($syncbutton);
}
if ($actions->has_items()) {
    $PAGE->add_header_action($OUTPUT->render($actions));
}

echo $OUTPUT->header();

$report = \core_reportbuilder\system_report_factory::create(
    \tool_mucohort\reportbuilder\local\systemreports\sync_history::class,
    $context,
    '',
    '',
    0,
    ['cohortid' => $cohort->get_id()]
);
echo $OUTPUT->render(new \tool_mulib\output\report_filter_bar($report));
echo $report->output();

echo $OUTPUT->footer();
