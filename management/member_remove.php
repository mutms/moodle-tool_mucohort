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
 * Remove cohort member.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\type\base;
use tool_mulib\muform\handler;
use core\url;

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */

require('../../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$member = $DB->get_record('cohort_members', ['id' => $id], '*', MUST_EXIST);
$cohort = base::fetch((int)$member->cohortid);
$record = $cohort->get_cohort();
$user = $DB->get_record('user', ['id' => $member->userid], '*', MUST_EXIST);
$context = $cohort->get_context();
$formclass = $cohort->get_member_remove_form_class();
if (!$formclass || !$cohort->can_manage_members()) {
    throw new required_capability_exception($context, 'moodle/cohort:assign', 'nopermissions', '');
}

$currenturl = new url('/admin/tool/mucohort/management/member_remove.php', ['id' => $member->id]);
$returnurl = new url('/admin/tool/mucohort/cohort_members.php', ['id' => $record->id]);

$PAGE->set_context($context);
$PAGE->set_url($currenturl);
$title = get_string('member_remove', 'tool_mucohort');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$handler = handler::from_request();

$form = new $formclass($currenturl, ['fullname' => fullname($user)]);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}

if ($form->get_data()) {
    $cohort->remove_member((int)$user->id);
    $handler->submitted($returnurl);
}

$handler->render($form);
