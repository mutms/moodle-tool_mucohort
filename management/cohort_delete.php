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
 * Delete cohort.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\type\base;
use tool_mucohort\local\management;
use tool_mulib\muform\handler;
use core\url;

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */

require('../../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$cohort = base::fetch($id);
$record = $cohort->get_cohort();
$context = $cohort->get_context();
if (!$cohort->can_delete()) {
    throw new required_capability_exception($context, 'moodle/cohort:manage', 'nopermissions', '');
}

$currenturl = new url('/admin/tool/mucohort/management/cohort_delete.php', ['id' => $record->id]);
$returnurl = management::get_index_url($context);

$PAGE->set_context($context);
$PAGE->set_url($currenturl);
$title = get_string('cohort_delete', 'tool_mucohort');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$handler = handler::from_request();

$current = (array)$record;
$current['memberscount'] = (string)$DB->count_records('cohort_members', ['cohortid' => $record->id]);
$form = new \tool_mucohort\local\form\cohort_delete($currenturl, $current);

if ($form->is_cancelled()) {
    $handler->cancelled(new url('/admin/tool/mucohort/cohort.php', ['id' => $record->id]));
}

if ($form->get_data()) {
    $cohort->delete();
    $handler->submitted($returnurl);
}

$handler->render($form);
