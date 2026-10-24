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
 * Approve draft rules.
 *
 * Membership changes are not calculated here, it might take minutes for complex rules.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\broken_rules_exception;
use tool_mucohort\local\form\rules_approve;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\rules;
use tool_mulib\muform\handler;
use core\url;

/** @var moodle_page $PAGE */

require('../../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$cohort = base::fetch($id);
$context = $cohort->get_context();
if (!($cohort instanceof rules) || !$cohort->can_manage_rules()) {
    throw new required_capability_exception($context, 'tool/mucohort:manage', 'nopermissions', '');
}

$currenturl = new url('/admin/tool/mucohort/management/rules_approve.php', ['id' => $cohort->get_id()]);
$returnurl = new url('/admin/tool/mucohort/cohort_rules.php', ['id' => $cohort->get_id()]);
$title = get_string('rules_approve', 'tool_mucohort');

$PAGE->set_context($context);
$PAGE->set_url($currenturl);
$PAGE->set_title($title);
$PAGE->set_heading($title);

$handler = handler::from_request();

$json = $cohort->get_rules_record()->pendingrulesjson;
if ($json === null) {
    $handler->cancelled($returnurl);
}
$currenthash = sha1($json);

try {
    $ruleset = $cohort->get_pending_ruleset();
    $problems = $cohort->get_problems($ruleset, false);
    $notready = (bool)$cohort->get_problems($ruleset);
    if (!$ruleset->get_rules()) {
        $problems[] = get_string('rules_approve_norules', 'tool_mucohort');
    }
} catch (broken_rules_exception $ex) {
    $problems = [$ex->getMessage()];
}

if ($problems) {
    $message = html_writer::div(clean_string(reset($problems)), 'alert alert-danger');
} else {
    $message = html_writer::tag('p', get_string('rules_approve_confirm', 'tool_mucohort'));
    if ($notready) {
        $message .= html_writer::div(get_string('rules_approve_notready', 'tool_mucohort'), 'alert alert-warning');
    }
    $message .= html_writer::tag('p', rules::get_synctype_descriptions()[$cohort->get_synctype()]);
}
// Sync makes sense only if membership may be changed and no other sync is waiting.
$cansync = ($cohort->get_synctype() !== rules::SYNCTYPE_NONE) && !\tool_mucohort\local\sync::get_current($cohort);

$extra = [
    'message' => $message,
    'canapprove' => !$problems,
    'cansync' => $cansync,
    'currenthash' => $currenthash,
];
$form = new rules_approve($currenturl, ['hash' => $currenthash], $extra);

if ($form->is_cancelled()) {
    $handler->cancelled(new url('/admin/tool/mucohort/cohort_draft.php', ['id' => $cohort->get_id()]));
}

if (($data = $form->get_data()) && !$problems) {
    $cohort->approve_draft($currenthash);
    if ($cansync && !empty($data->syncnow)) {
        \tool_mucohort\local\sync::queue($cohort);
        $returnurl = new url('/admin/tool/mucohort/cohort_sync.php', ['id' => $cohort->get_id()]);
    }
    $handler->submitted($returnurl);
}

$handler->render($form);
