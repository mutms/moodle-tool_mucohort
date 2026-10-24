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
 * Delete rule, the change is written to draft rules.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\form\rules_confirm;
use tool_mucohort\local\ruleset;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\rules;
use tool_mulib\muform\handler;
use core\url;

/** @var moodle_page $PAGE */

require('../../../../config.php');

$id = required_param('id', PARAM_INT);
$uuid = required_param('rule', PARAM_ALPHANUMEXT);

require_login();

$cohort = base::fetch($id);
$context = $cohort->get_context();
if (!($cohort instanceof rules) || !$cohort->can_manage_rules()) {
    throw new required_capability_exception($context, 'tool/mucohort:manage', 'nopermissions', '');
}

$currenturl = new url('/admin/tool/mucohort/management/rule_delete.php', ['id' => $cohort->get_id(), 'rule' => $uuid]);
$returnurl = new url('/admin/tool/mucohort/cohort_draft.php', ['id' => $cohort->get_id()]);
$title = get_string('rule_delete', 'tool_mucohort');

$PAGE->set_context($context);
$PAGE->set_url($currenturl);
$PAGE->set_title($title);
$PAGE->set_heading($title);

$handler = handler::from_request();

$ruleset = $cohort->get_pending_ruleset();
$current = $ruleset?->get_rule($uuid);
if (!$current) {
    $handler->cancelled($returnurl);
}
$currenthash = ruleset::get_rule_hash($current);

$extra = [
    'message' => get_string('rule_delete_confirm', 'tool_mucohort', $cohort->get_rule_description($current)),
    'submit' => get_string('rule_delete', 'tool_mucohort'),
    'currenthash' => $currenthash,
];
$form = new rules_confirm($currenturl, ['hash' => $currenthash], $extra);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}

if ($form->get_data()) {
    $ruleset->delete_rule($uuid);
    $cohort->set_pending_ruleset($ruleset);
    $handler->submitted($returnurl);
}

$handler->render($form);
