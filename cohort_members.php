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
 * Cohort members.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\type\base;
use tool_mucohort\local\management;
use tool_mucohort\local\bulk_changes;
use tool_mulib\output\header_actions;
use tool_mulib\output\muform\dialog\button;
use core\url;

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */

require('../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$cohort = base::fetch($id);
$record = $cohort->get_cohort();
$context = $cohort->get_context();
if (!$cohort->can_view_members()) {
    require_capability('tool/mucohort:viewmembers', $context);
}

$currenturl = new url('/admin/tool/mucohort/cohort_members.php', ['id' => $record->id]);
management::setup_cohort_page($currenturl, $cohort, 'cohort_members');

$actions = new header_actions(get_string('management_actions', 'tool_mucohort'));
if ($cohort->get_member_add_form_class() && $cohort->can_manage_members()) {
    $url = new url('/admin/tool/mucohort/management/member_add.php', ['cohortid' => $record->id]);
    $button = new button($url, get_string('member_add', 'tool_mucohort'));
    $actions->add_button($button);
}
if (array_intersect_key(bulk_changes::get_actions($cohort), [bulk_changes::ACTION_ADD => 1, bulk_changes::ACTION_REMOVE => 1])) {
    $url = new url('/admin/tool/mucohort/management/bulk_changes.php', ['id' => $record->id]);
    $actions->get_dropdown()->add_item(
        get_string('bulkchanges', 'tool_mucohort'),
        $url,
        new \core\output\pix_icon('i/upload', '')
    );
}
$url = new url('/admin/tool/mucohort/cohort_compare.php', ['id' => $record->id]);
$actions->get_dropdown()->add_item(
    get_string('compare_cohorts', 'tool_mucohort'),
    $url,
    new \core\output\pix_icon('i/cohort', '')
);
if ($actions->has_items()) {
    $PAGE->add_header_action($OUTPUT->render($actions));
}

echo $OUTPUT->header();

if ($cohort instanceof \tool_mucohort\local\type\advanced) {
    /** @var \tool_mucohort\output\renderer $renderer */
    $renderer = $PAGE->get_renderer('tool_mucohort');
    echo $renderer->render_members_info($cohort);
}

$report = \core_reportbuilder\system_report_factory::create(
    \tool_mucohort\reportbuilder\local\systemreports\members::class,
    $context,
    '',
    '',
    0,
    ['cohortid' => $record->id]
);
echo $report->output();

echo $OUTPUT->footer();
