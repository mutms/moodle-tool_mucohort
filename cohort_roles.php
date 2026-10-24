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
 * Roles assigned to members of advanced cohort.
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
use tool_mulib\output\muform\dialog\icon;
use core\url;

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */

require('../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$cohort = base::fetch($id);
$context = $cohort->get_context();
if (!($cohort instanceof advanced) || !$cohort->can_view()) {
    throw new required_capability_exception($context, 'moodle/cohort:view', 'nopermissions', '');
}

$currenturl = new url('/admin/tool/mucohort/cohort_roles.php', ['id' => $cohort->get_id()]);
management::setup_cohort_page($currenturl, $cohort, 'cohort_roles');

$canmanage = $cohort->can_manage_roles();
$actions = new header_actions(get_string('management_actions', 'tool_mucohort'));
if ($canmanage) {
    $url = new url('/admin/tool/mucohort/management/role_add.php', ['cohortid' => $cohort->get_id()]);
    $actions->add_button(new button($url, get_string('role_add', 'tool_mucohort'), true));
}
if ($actions->has_items()) {
    $PAGE->add_header_action($OUTPUT->render($actions));
}

echo $OUTPUT->header();

echo html_writer::tag('p', get_string('roles_info', 'tool_mucohort'), ['class' => 'text-muted']);

$records = $DB->get_records('tool_mucohort_role', ['mucohortid' => $cohort->get_mucohort()->id], 'id ASC');
if (!$records) {
    echo html_writer::tag('p', get_string('roles_none', 'tool_mucohort'));
    echo $OUTPUT->footer();
    die;
}

$roles = role_fix_names(get_all_roles());
$table = new html_table();
$table->id = 'tool_mucohort-roles';
$table->head = [
    get_string('role'),
    get_string('role_context', 'tool_mucohort'),
    get_string('role_assigned', 'tool_mucohort'),
    '',
];
foreach ($records as $record) {
    $rolename = get_string('role_missing', 'tool_mucohort');
    if (isset($roles[$record->roleid])) {
        $rolename = s($roles[$record->roleid]->localname);
    }
    if ($record->contextid === null) {
        $contextname = get_string('role_context_cohort', 'tool_mucohort', $context->get_context_name(false));
    } else if ($rolecontext = \core\context::instance_by_id($record->contextid, IGNORE_MISSING)) {
        $contextname = $rolecontext->get_context_name(false);
    } else {
        // Assignments were deleted together with the context, nothing is synchronised.
        $contextname = html_writer::span(get_string('error_contextmissing', 'tool_mucohort'), 'text-danger');
    }
    $assigned = $DB->count_records('role_assignments', ['component' => 'tool_mucohort', 'itemid' => $record->id]);
    $action = '';
    if ($canmanage) {
        $url = new url('/admin/tool/mucohort/management/role_remove.php', ['id' => $record->id]);
        $icon = new icon($url, get_string('role_remove', 'tool_mucohort'), 'i/delete');
        $icon->set_form_size('sm');
        $action = $OUTPUT->render($icon);
    }
    $table->data[] = [$rolename, $contextname, $assigned, $action];
}
echo html_writer::table($table);

echo $OUTPUT->footer();
