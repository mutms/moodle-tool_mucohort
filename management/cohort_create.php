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
 * Create cohort of any type.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\type\base;
use tool_mucohort\local\type\curated;
use tool_mucohort\local\management;
use tool_mulib\muform\handler;
use tool_mulib\muform\util\file_area;
use core\url;

/** @var moodle_page $PAGE */

require('../../../../config.php');

$contextid = required_param('contextid', PARAM_INT);
$type = optional_param('type', '', PARAM_ALPHA);
$context = context::instance_by_id($contextid);

require_login();

if ($context->contextlevel != CONTEXT_SYSTEM && $context->contextlevel != CONTEXT_COURSECAT) {
    throw new moodle_exception('invalidcontext');
}

// Types the current user may create here.
$types = [];
$descriptions = [];
foreach (base::get_classes() as $t => $c) {
    if ($c::is_enabled() && $c::can_create($context)) {
        $types[$t] = $c::get_name();
        $descriptions[$t] = $c::get_description();
    }
}
if (!$types) {
    throw new required_capability_exception($context, 'moodle/cohort:manage', 'nopermissions', '');
}

$title = get_string('cohort_create', 'tool_mucohort');
$PAGE->set_context($context);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$returnurl = management::get_index_url($context);

if ($type === '' && count($types) === 1) {
    $type = array_key_first($types);
}

if ($type === '') {
    // Let user select the cohort type first.
    $currenturl = new url('/admin/tool/mucohort/management/cohort_create.php', ['contextid' => $context->id]);
    $PAGE->set_url($currenturl);
    $handler = handler::from_request();
    $default = isset($types[curated::TYPE]) ? curated::TYPE : array_key_first($types);
    $extra = ['types' => $types, 'descriptions' => $descriptions];
    $form = new \tool_mucohort\local\form\cohort_type_select($currenturl, ['type' => $default], $extra);
    if ($form->is_cancelled()) {
        $handler->cancelled($returnurl);
    }
    if ($data = $form->get_data()) {
        $type = $data->type;
        if (!$handler->is_dialog()) {
            redirect(new url('/admin/tool/mucohort/management/cohort_create.php', ['contextid' => $context->id, 'type' => $type]));
        }
        // Continue with the create form in the same dialog, it is not submitted because it has a different form id.
    } else {
        $handler->render($form);
    }
}

if (!isset($types[$type])) {
    throw new invalid_parameter_exception('Invalid cohort type');
}
/** @var class-string<base> $class */
$class = base::get_classes()[$type];

$currenturl = new url('/admin/tool/mucohort/management/cohort_create.php', ['contextid' => $context->id, 'type' => $type]);
$PAGE->set_url($currenturl);
$handler = $handler ?? handler::from_request();

$current = [
    'contextid' => $context->id,
    'visible' => 1,
    'descriptionformat' => FORMAT_HTML,
    'descriptionfilearea' => new file_area($context, 'cohort', 'description', null),
];
$formclass = $class::get_create_form_class();
$form = new $formclass($currenturl, $current);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}

if ($data = $form->get_data()) {
    // Custom fields and description files are saved by the form elements.
    $record = (object)array_filter((array)$data, fn($key) => !str_starts_with($key, 'customfield_'), ARRAY_FILTER_USE_KEY);
    $cohort = $class::create($record);
    $description = $form->get_element('description');
    $description->get_file_area()->set_contextid($cohort->get_context()->id)->set_itemid($cohort->get_id());
    $description->save_area();
    $form->get_element('customfields')->save($cohort->get_id());
    $handler->submitted(new url('/admin/tool/mucohort/cohort.php', ['id' => $cohort->get_id()]));
}

$handler->render($form);
