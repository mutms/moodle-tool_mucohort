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
 * Add or update exceptions.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\exception;
use tool_mucohort\local\type\advanced;
use tool_mucohort\local\type\base;
use tool_mulib\muform\handler;
use core\url;

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */

require('../../../../config.php');

$id = optional_param('id', 0, PARAM_INT);

require_login();

if ($id) {
    $record = $DB->get_record('tool_mucohort_exception', ['id' => $id], '*', MUST_EXIST);
    $cohortid = (int)$DB->get_field('tool_mucohort', 'cohortid', ['id' => $record->mucohortid], MUST_EXIST);
    $user = $DB->get_record('user', ['id' => $record->userid], '*', MUST_EXIST);
} else {
    $record = null;
    $cohortid = required_param('cohortid', PARAM_INT);
}

$cohort = base::fetch($cohortid);
$context = $cohort->get_context();
if (!($cohort instanceof advanced) || !$cohort->can_manage_exceptions()) {
    throw new required_capability_exception($context, 'tool/mucohort:manageexceptions', 'nopermissions', '');
}

if ($record) {
    $currenturl = new url('/admin/tool/mucohort/management/exception_edit.php', ['id' => $record->id]);
    $title = get_string('exception_update', 'tool_mucohort');
} else {
    $currenturl = new url('/admin/tool/mucohort/management/exception_edit.php', ['cohortid' => $cohort->get_id()]);
    $title = get_string('exception_add', 'tool_mucohort');
}
$returnurl = new url('/admin/tool/mucohort/cohort_exceptions.php', ['id' => $cohort->get_id()]);

$PAGE->set_context($context);
$PAGE->set_url($currenturl);
$PAGE->set_title($title);
$PAGE->set_heading($title);

$handler = handler::from_request();

if ($record) {
    $current = [
        'id' => $record->id,
        'fullname' => fullname($user),
        'type' => $record->type,
        'reason' => (string)$record->reason,
    ];
} else {
    $current = ['cohortid' => $cohort->get_id()];
}
$form = new \tool_mucohort\local\form\exception_edit($currenturl, $current);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}

if ($data = $form->get_data()) {
    $userids = $record ? [(int)$record->userid] : array_map('intval', $data->userids);
    foreach ($userids as $userid) {
        exception::set($cohort, $userid, (int)$data->type, $data->reason);
    }
    $handler->submitted($returnurl);
}

$handler->render($form);
