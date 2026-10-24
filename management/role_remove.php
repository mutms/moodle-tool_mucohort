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
 * Remove role assigned to members of advanced cohort.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\form\rules_confirm;
use tool_mucohort\local\role_sync;
use tool_mucohort\local\type\advanced;
use tool_mucohort\local\type\base;
use tool_mulib\muform\handler;
use core\url;

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */

require('../../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$record = $DB->get_record('tool_mucohort_role', ['id' => $id], '*', MUST_EXIST);
$cohortid = $DB->get_field('tool_mucohort', 'cohortid', ['id' => $record->mucohortid], MUST_EXIST);
$cohort = base::fetch((int)$cohortid);
$context = $cohort->get_context();
if (!($cohort instanceof advanced) || !$cohort->can_manage_roles()) {
    throw new required_capability_exception($context, 'tool/mucohort:manageroles', 'nopermissions', '');
}

$currenturl = new url('/admin/tool/mucohort/management/role_remove.php', ['id' => $record->id]);
$returnurl = new url('/admin/tool/mucohort/cohort_roles.php', ['id' => $cohort->get_id()]);
$title = get_string('role_remove', 'tool_mucohort');

$PAGE->set_context($context);
$PAGE->set_url($currenturl);
$PAGE->set_title($title);
$PAGE->set_heading($title);

$handler = handler::from_request();

$count = $DB->count_records('role_assignments', ['component' => 'tool_mucohort', 'itemid' => $record->id]);
$extra = [
    'message' => html_writer::tag('p', get_string('role_remove_confirm', 'tool_mucohort', $count)),
    'submit' => get_string('role_remove', 'tool_mucohort'),
    'currenthash' => sha1($record->id),
];
$form = new rules_confirm($currenturl, ['hash' => sha1($record->id)], $extra);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}

if ($form->get_data()) {
    role_sync::remove((int)$record->id);
    $handler->submitted($returnurl);
}

$handler->render($form);
