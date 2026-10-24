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
 * Compare cohort membership with other cohorts.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\compare;
use tool_mucohort\local\management;
use tool_mucohort\local\type\base;
use tool_mulib\output\header_actions;
use tool_mulib\output\muform\dialog\button;
use core\url;

/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */

require('../../../config.php');

$id = required_param('id', PARAM_INT);
$with = optional_param('with', '', PARAM_SEQUENCE);
$mode = optional_param('mode', compare::MODE_MISSING, PARAM_ALPHA);

require_login();

$cohort = base::fetch($id);
$context = $cohort->get_context();
if (!$cohort->can_view_members()) {
    throw new required_capability_exception($context, 'tool/mucohort:viewmembers', 'nopermissions', '');
}
if (!array_key_exists($mode, compare::get_modes())) {
    $mode = compare::MODE_MISSING;
}

$withids = [];
foreach (compare::parse_withids($with) as $withid) {
    $other = base::fetch($withid, IGNORE_MISSING);
    if ($other && $other->can_view_members() && $withid != $cohort->get_id()) {
        $withids[] = $withid;
    }
}
$compare = new compare($cohort, $withids);
$with = implode(',', $compare->get_withids());

$currenturl = new url('/admin/tool/mucohort/cohort_compare.php', ['id' => $cohort->get_id(), 'with' => $with, 'mode' => $mode]);
management::setup_cohort_page($currenturl, $cohort, 'cohort_members');
$PAGE->set_title(get_string('compare_cohorts', 'tool_mucohort') . \moodle_page::TITLE_SEPARATOR . $cohort->get_formatted_name());

$formurl = new url('/admin/tool/mucohort/cohort_compare.php', ['id' => $cohort->get_id()]);
$current = ['cohortid' => $cohort->get_id(), 'withids' => $compare->get_withids()];
$form = new \tool_mucohort\local\form\compare_select($formurl, $current);
if ($data = $form->get_data()) {
    $params = ['id' => $cohort->get_id(), 'with' => implode(',', $data->withids)];
    redirect(new url('/admin/tool/mucohort/cohort_compare.php', $params));
}

$actions = new header_actions(get_string('management_actions', 'tool_mucohort'));
if ($withids && $cohort->can_manage_members()) {
    if ($cohort->get_member_add_form_class()) {
        $url = new url(
            '/admin/tool/mucohort/management/compare_apply.php',
            ['id' => $cohort->get_id(), 'with' => $with, 'action' => 'add']
        );
        $button = new button($url, get_string('compare_addmissing', 'tool_mucohort'));
        $button->set_form_size('sm');
        $actions->add_button($button);
    }
    if ($cohort->get_member_remove_form_class()) {
        $params = ['id' => $cohort->get_id(), 'with' => $with, 'action' => 'remove'];
        $url = new url('/admin/tool/mucohort/management/compare_apply.php', $params);
        $button = new button($url, get_string('compare_removeextra', 'tool_mucohort'));
        $button->set_form_size('sm');
        $actions->add_button($button);
    }
}
if ($actions->has_items()) {
    $PAGE->add_header_action($OUTPUT->render($actions));
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('compare_cohorts', 'tool_mucohort'), 3);
echo $form->render($OUTPUT);

if ($withids) {
    $links = [];
    foreach (compare::get_modes() as $m => $name) {
        $a = (object)['name' => $name, 'count' => $compare->count($m)];
        $text = get_string('compare_modecount', 'tool_mucohort', $a);
        $url = new url('/admin/tool/mucohort/cohort_compare.php', ['id' => $cohort->get_id(), 'with' => $with, 'mode' => $m]);
        $class = 'nav-link' . (($m === $mode) ? ' active' : '');
        $attrs = ['class' => $class];
        if ($m === $mode) {
            $attrs['aria-current'] = 'page';
        }
        $links[] = html_writer::tag('li', html_writer::link($url, $text, $attrs), ['class' => 'nav-item']);
    }
    echo html_writer::tag('ul', implode('', $links), ['class' => 'nav nav-pills my-3']);
    echo html_writer::tag('p', get_string('compare_' . $mode . '_desc', 'tool_mucohort'), ['class' => 'text-muted']);

    $report = \core_reportbuilder\system_report_factory::create(
        \tool_mucohort\reportbuilder\local\systemreports\compare::class,
        $context,
        '',
        '',
        0,
        ['cohortid' => $cohort->get_id(), 'with' => $with, 'mode' => $mode]
    );
    echo $report->output();
}

echo $OUTPUT->footer();
