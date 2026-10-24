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
 * Update cohort settings.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\type\base;
use tool_mulib\muform\handler;
use tool_mulib\muform\util\file_area;
use core\url;

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */

require('../../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$cohort = base::fetch($id);
$record = $cohort->get_cohort();
$context = $cohort->get_context();
$formclass = $cohort->get_update_form_class();
if (!$formclass || !$cohort->can_manage()) {
    throw new required_capability_exception($context, 'moodle/cohort:manage', 'nopermissions', '');
}

$currenturl = new url('/admin/tool/mucohort/management/cohort_update.php', ['id' => $record->id]);
$returnurl = new url('/admin/tool/mucohort/cohort.php', ['id' => $record->id]);

$PAGE->set_context($context);
$PAGE->set_url($currenturl);
$title = get_string('cohort_update', 'tool_mucohort');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$handler = handler::from_request();

$current = $cohort->get_update_form_data();
$current['descriptionfilearea'] = new file_area($context, 'cohort', 'description', (int)$record->id);
$form = new $formclass($currenturl, $current);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}

if ($data = $form->get_data()) {
    // Custom fields and description files are saved by the form elements.
    $data = (object)array_filter((array)$data, fn($key) => !str_starts_with($key, 'customfield_'), ARRAY_FILTER_USE_KEY);
    $data->id = $record->id;
    $cohort->update($data);
    $form->get_element('description')->save_area();
    $form->get_element('customfields')->save((int)$record->id);
    $handler->submitted($returnurl);
}

$handler->render($form);
