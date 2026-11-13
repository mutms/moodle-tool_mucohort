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
 * Cohort list.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\management;
use tool_mulib\output\header_actions;
use tool_mulib\output\muform\dialog\button;
use core\url;

/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */

require('../../../config.php');

$contextid = optional_param('contextid', 0, PARAM_INT);

if ($contextid) {
    $context = context::instance_by_id($contextid);
} else {
    $context = context_system::instance();
}
unset($contextid);
if ($context->contextlevel != CONTEXT_SYSTEM && $context->contextlevel != CONTEXT_COURSECAT) {
    throw new moodle_exception('invalidcontext');
}

require_login();
if (!management::can_view_index($context)) {
    require_capability('moodle/cohort:view', $context);
}

$currenturl = management::get_index_url($context);
management::setup_index_page($currenturl, $context);

$actions = new header_actions(get_string('management_actions', 'tool_mucohort'));
$cancreate = false;
foreach (\tool_mucohort\local\type\base::get_classes() as $class) {
    if ($class::is_enabled() && $class::can_create($context)) {
        $cancreate = true;
        break;
    }
}
if ($cancreate) {
    $url = new url('/admin/tool/mucohort/management/cohort_create.php', ['contextid' => $context->id]);
    $button = new button($url, get_string('cohort_create', 'tool_mucohort'));
    $button->set_form_size('xl');
    $button->set_submitted_action(\tool_mulib\muform\handler\dialog::ACTION_REDIRECT);
    $actions->add_button($button);
}
if (has_capability('moodle/cohort:assign', $context)) {
    $url = new url('/cohort/upload.php', ['contextid' => $context->id]);
    $icon = new \core\output\pix_icon('i/upload', '');
    $actions->get_dropdown()->add_item(get_string('legacyupload', 'tool_mucohort'), $url, $icon);
}
if (has_capability('moodle/site:config', context_system::instance())) {
    $url = new url('/admin/settings.php', ['section' => 'tool_mucohort_settings']);
    $actions->get_dropdown()->add_item(get_string('settings', 'tool_mucohort'), $url, new \core\output\pix_icon('i/settings', ''));
}
if ($actions->has_items()) {
    $PAGE->add_header_action($OUTPUT->render($actions));
}

echo $OUTPUT->header();

$report = \core_reportbuilder\system_report_factory::create(
    \tool_mucohort\reportbuilder\local\systemreports\cohorts::class,
    $context
);
echo $OUTPUT->render(new \tool_mulib\output\report_filter_bar($report, pinned: [
    'cohort:name' => null,
    'cohort:currentcontextonly' => null,
    'cohort:type' => null,
    'cohort:members' => null,
]));
echo $report->output();
echo $OUTPUT->render(new \tool_mulib\output\report_pager($report));

echo $OUTPUT->footer();
