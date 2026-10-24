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
 * Add or remove one user found by cohort comparison.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\type\base;
use tool_mucohort\reportbuilder\local\systemreports\compare;
use tool_mulib\muform\handler;
use core\url;

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */

require('../../../../config.php');

$id = required_param('id', PARAM_INT);
$with = optional_param('with', '', PARAM_SEQUENCE);
$userid = required_param('userid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

require_login();

$cohort = base::fetch($id);
$context = $cohort->get_context();
if (!$cohort->can_view_members() || !$cohort->can_manage_members()) {
    throw new required_capability_exception($context, 'moodle/cohort:assign', 'nopermissions', '');
}
$user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
if ($action === 'add') {
    $allowed = $cohort->get_member_add_form_class() && compare::is_user_allowed($cohort->get_new_member_sql(), (int)$user->id);
    if (!$allowed) {
        throw new moodle_exception('error_membernotallowed', 'tool_mucohort');
    }
    $title = get_string('compare_addmember', 'tool_mucohort');
    $summary = get_string('compare_addmember_desc', 'tool_mucohort', fullname($user));
} else if ($action === 'remove') {
    $allowed = $cohort->get_member_remove_form_class()
        && compare::is_user_allowed($cohort->get_removable_member_sql(), (int)$user->id);
    if (!$allowed) {
        throw new moodle_exception('error_memberforced', 'tool_mucohort');
    }
    $title = get_string('compare_removemember', 'tool_mucohort');
    $summary = get_string('compare_removemember_desc', 'tool_mucohort', fullname($user));
} else {
    throw new invalid_parameter_exception('Invalid action');
}

$params = ['id' => $cohort->get_id(), 'with' => $with, 'userid' => $user->id, 'action' => $action];
$currenturl = new url('/admin/tool/mucohort/management/compare_member.php', $params);
$returnurl = new url('/admin/tool/mucohort/cohort_compare.php', ['id' => $cohort->get_id(), 'with' => $with]);

$PAGE->set_context($context);
$PAGE->set_url($currenturl);
$PAGE->set_title($title);
$PAGE->set_heading($title);

$handler = handler::from_request();

$form = new \tool_mucohort\local\form\compare_apply(
    $currenturl,
    ['summary' => $summary],
    ['allowed' => true, 'submitlabel' => $title]
);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}

if ($form->get_data()) {
    if ($action === 'add') {
        $cohort->add_members([(int)$user->id]);
    } else {
        $cohort->remove_member((int)$user->id);
    }
    $handler->submitted($returnurl);
}

$handler->render($form);
