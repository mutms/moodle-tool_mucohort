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
 * Add or update access record.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\access;
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
    $record = $DB->get_record('tool_mucohort_access', ['id' => $id], '*', MUST_EXIST);
    $cohortid = (int)$DB->get_field('tool_mucohort', 'cohortid', ['id' => $record->mucohortid], MUST_EXIST);
    $user = $DB->get_record('user', ['id' => $record->userid], '*', MUST_EXIST);
} else {
    $record = null;
    $cohortid = required_param('cohortid', PARAM_INT);
}

$cohort = base::fetch($cohortid);
$context = $cohort->get_context();
if (!($cohort instanceof advanced) || !$cohort->can_manage_access()) {
    throw new required_capability_exception($context, 'tool/mucohort:manageaccess', 'nopermissions', '');
}

if ($record) {
    $currenturl = new url('/admin/tool/mucohort/management/access_edit.php', ['id' => $record->id]);
    $title = get_string('access_update', 'tool_mucohort');
} else {
    $currenturl = new url('/admin/tool/mucohort/management/access_edit.php', ['cohortid' => $cohort->get_id()]);
    $title = get_string('access_add', 'tool_mucohort');
}
$returnurl = new url('/admin/tool/mucohort/cohort_access.php', ['id' => $cohort->get_id()]);

$PAGE->set_context($context);
$PAGE->set_url($currenturl);
$PAGE->set_title($title);
$PAGE->set_heading($title);

$handler = handler::from_request();

$current = [];
if ($record) {
    $current['id'] = $record->id;
    $current['fullname'] = fullname($user);
    $current['iscurator'] = (int)$record->iscurator;
    $current['flags'] = array_values(array_filter(access::FLAGS, fn($flag) => !empty($record->$flag)));
}
$form = new \tool_mucohort\local\form\access_edit($currenturl, $current, ['cohort' => $cohort]);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}

if ($data = $form->get_data()) {
    $userid = $record ? (int)$record->userid : (int)$data->userid;
    access::set($cohort, $userid, $data->flags, (bool)$data->iscurator);
    $handler->submitted($returnurl);
}

$handler->render($form);
