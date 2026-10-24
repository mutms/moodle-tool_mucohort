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
 * Convert cohort to a different type.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\conversion;
use tool_mucohort\local\type\base;
use tool_mulib\muform\handler;
use core\url;

/** @var moodle_page $PAGE */

require('../../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$cohort = base::fetch($id);
$record = $cohort->get_cohort();
$context = $cohort->get_context();
$targets = conversion::get_target_types($cohort);
if (!$targets) {
    throw new required_capability_exception($context, 'tool/mucohort:manage', 'nopermissions', '');
}

$currenturl = new url('/admin/tool/mucohort/management/cohort_convert.php', ['id' => $record->id]);
$returnurl = new url('/admin/tool/mucohort/cohort.php', ['id' => $record->id]);

$PAGE->set_context($context);
$PAGE->set_url($currenturl);
$title = get_string('convert_cohorttype', 'tool_mucohort');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$handler = handler::from_request();

$types = [];
$descriptions = [];
foreach ($targets as $type => $class) {
    $types[$type] = $class::get_name();
    $consequences = [$class::get_description(), get_string('convert_nomembershipchange', 'tool_mucohort')];
    if ($type === \tool_mucohort\local\type\rules::TYPE) {
        $consequences[] = get_string('convert_rules_info', 'tool_mucohort');
    }
    foreach (conversion::get_losses($cohort, $type) as $identifier => $count) {
        $consequences[] = get_string($identifier, 'tool_mucohort', $count);
    }
    $descriptions[$type] = implode(' ', $consequences);
}
$current = [
    'name' => $record->name,
    'fromtype' => $cohort->get_type_name(),
    'type' => (count($types) === 1) ? array_key_first($types) : null,
];
$extra = ['types' => $types, 'descriptions' => $descriptions];
$form = new \tool_mucohort\local\form\cohort_convert($currenturl, $current, $extra);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}

if ($data = $form->get_data()) {
    $options = new stdClass();
    if ($data->type === \tool_mucohort\local\type\rules::TYPE) {
        $options->synctype = $data->synctype;
        $options->seedforced = in_array('seedforced', (array)($data->seedforced ?? []), true);
    }
    conversion::convert($cohort, $data->type, $options);
    $handler->submitted($returnurl);
}

$handler->render($form);
