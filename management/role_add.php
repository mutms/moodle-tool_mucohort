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
 * Add role assigned to members of advanced cohort.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\role_sync;
use tool_mucohort\local\type\advanced;
use tool_mucohort\local\type\base;
use tool_mulib\muform\handler;
use core\url;

/** @var moodle_page $PAGE */

require('../../../../config.php');

$cohortid = required_param('cohortid', PARAM_INT);

require_login();

$cohort = base::fetch($cohortid);
$context = $cohort->get_context();
if (!($cohort instanceof advanced) || !$cohort->can_manage_roles()) {
    throw new required_capability_exception($context, 'tool/mucohort:manageroles', 'nopermissions', '');
}

$currenturl = new url('/admin/tool/mucohort/management/role_add.php', ['cohortid' => $cohort->get_id()]);
$returnurl = new url('/admin/tool/mucohort/cohort_roles.php', ['id' => $cohort->get_id()]);
$title = get_string('role_add', 'tool_mucohort');

$PAGE->set_context($context);
$PAGE->set_url($currenturl);
$PAGE->set_title($title);
$PAGE->set_heading($title);

$handler = handler::from_request();

$form = new \tool_mucohort\local\form\role_add($currenturl, ['cohortid' => $cohort->get_id()]);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}

if ($data = $form->get_data()) {
    role_sync::add($cohort, (int)$data->roleid, empty($data->contextid) ? null : (int)$data->contextid);
    $handler->submitted($returnurl);
}

$handler->render($form);
