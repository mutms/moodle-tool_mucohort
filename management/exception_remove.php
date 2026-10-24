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
 * Remove exception.
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

$id = required_param('id', PARAM_INT);

require_login();

$record = $DB->get_record('tool_mucohort_exception', ['id' => $id], '*', MUST_EXIST);
$cohortid = (int)$DB->get_field('tool_mucohort', 'cohortid', ['id' => $record->mucohortid], MUST_EXIST);
$user = $DB->get_record('user', ['id' => $record->userid], '*', MUST_EXIST);

$cohort = base::fetch($cohortid);
$context = $cohort->get_context();
if (!($cohort instanceof advanced) || !$cohort->can_manage_exceptions()) {
    throw new required_capability_exception($context, 'tool/mucohort:manageexceptions', 'nopermissions', '');
}

$currenturl = new url('/admin/tool/mucohort/management/exception_remove.php', ['id' => $record->id]);
$returnurl = new url('/admin/tool/mucohort/cohort_exceptions.php', ['id' => $cohort->get_id()]);

$PAGE->set_context($context);
$PAGE->set_url($currenturl);
$title = get_string('exception_remove', 'tool_mucohort');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$handler = handler::from_request();

$current = [
    'fullname' => fullname($user),
    'exceptiontype' => exception::get_types()[$record->type] ?? '',
    'consequence' => ($record->type == exception::TYPE_FORCED)
        ? get_string('exception_remove_forced', 'tool_mucohort')
        : get_string('exception_remove_blocked', 'tool_mucohort'),
];
$canremovemember = ($record->type == exception::TYPE_FORCED && $cohort instanceof \tool_mucohort\local\type\curated
    && $cohort->can_manage_members());
$current['removemember'] = 0;
$form = new \tool_mucohort\local\form\exception_remove($currenturl, $current, ['canremovemember' => $canremovemember]);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}

if ($data = $form->get_data()) {
    exception::remove((int)$record->id);
    if ($canremovemember && !empty($data->removemember)) {
        $cohort->remove_member((int)$record->userid);
    }
    $handler->submitted($returnurl);
}

$handler->render($form);
