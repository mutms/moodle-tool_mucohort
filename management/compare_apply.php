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
 * Add missing or remove extra users found by cohort comparison.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\compare;
use tool_mucohort\local\type\base;
use tool_mulib\muform\handler;
use core\url;

/** @var moodle_page $PAGE */

require('../../../../config.php');

$id = required_param('id', PARAM_INT);
$with = required_param('with', PARAM_SEQUENCE);
$action = required_param('action', PARAM_ALPHA);

require_login();

$cohort = base::fetch($id);
$context = $cohort->get_context();
if (!$cohort->can_view_members() || !$cohort->can_manage_members()) {
    throw new required_capability_exception($context, 'moodle/cohort:assign', 'nopermissions', '');
}
if ($action === 'add' && !$cohort->get_member_add_form_class()) {
    throw new invalid_parameter_exception('Members cannot be added');
}
if ($action === 'remove' && !$cohort->get_member_remove_form_class()) {
    throw new invalid_parameter_exception('Members cannot be removed');
}
if ($action !== 'add' && $action !== 'remove') {
    throw new invalid_parameter_exception('Invalid action');
}
$withids = compare::parse_withids($with);
foreach ($withids as $withid) {
    $other = base::fetch($withid, IGNORE_MISSING);
    if (!$other || !$other->can_view_members()) {
        throw new invalid_parameter_exception('Invalid cohort');
    }
}
$compare = new compare($cohort, $withids);

$currenturl = new url(
    '/admin/tool/mucohort/management/compare_apply.php',
    ['id' => $cohort->get_id(), 'with' => $with, 'action' => $action]
);
$returnurl = new url('/admin/tool/mucohort/cohort_compare.php', ['id' => $cohort->get_id(), 'with' => $with]);

$PAGE->set_context($context);
$PAGE->set_url($currenturl);
$title = get_string(($action === 'add') ? 'compare_addmissing' : 'compare_removeextra', 'tool_mucohort');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$handler = handler::from_request();

if ($action === 'add') {
    $plan = $compare->get_add_plan();
    $lines = [get_string('compare_plan_missing', 'tool_mucohort', $plan['missing'])];
    if ($plan['addable'] != $plan['missing']) {
        $lines[] = get_string('compare_plan_skipped', 'tool_mucohort', $plan['missing'] - $plan['addable']);
    }
    $allowed = ($plan['addable'] > 0);
    if ($plan['capacity'] !== null && $plan['addable'] > $plan['capacity']) {
        $lines[] = get_string('error_maxmembers', 'tool_mucohort', $plan['capacity']);
        $allowed = false;
    }
    $lines[] = get_string('compare_plan_add', 'tool_mucohort', $plan['addable']);
    $submitlabel = get_string('compare_addmissing', 'tool_mucohort');
} else {
    $plan = $compare->get_remove_plan();
    $lines = [get_string('compare_plan_extra', 'tool_mucohort', $plan['extra'])];
    if ($plan['removable'] != $plan['extra']) {
        $lines[] = get_string('compare_plan_forced', 'tool_mucohort', $plan['extra'] - $plan['removable']);
    }
    $lines[] = get_string('compare_plan_remove', 'tool_mucohort', $plan['removable']);
    $allowed = ($plan['removable'] > 0);
    $submitlabel = get_string('compare_removeextra', 'tool_mucohort');
}

$form = new \tool_mucohort\local\form\compare_apply(
    $currenturl,
    ['summary' => implode("\n", $lines)],
    ['allowed' => $allowed, 'submitlabel' => $submitlabel]
);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}

if ($form->get_data()) {
    if ($action === 'add') {
        $compare->add_missing();
    } else {
        $compare->remove_extra();
    }
    $handler->submitted($returnurl);
}

$handler->render($form);
