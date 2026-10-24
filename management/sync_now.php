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
 * Queue sync of rule-based cohort.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\form\rules_confirm;
use tool_mucohort\local\sync;
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

$currenturl = new url('/admin/tool/mucohort/management/sync_now.php', ['id' => $cohort->get_id()]);
$returnurl = new url('/admin/tool/mucohort/cohort_sync.php', ['id' => $cohort->get_id()]);
$title = get_string('sync_now', 'tool_mucohort');

$PAGE->set_context($context);
$PAGE->set_url($currenturl);
$PAGE->set_title($title);
$PAGE->set_heading($title);

$handler = handler::from_request();

// Sync uses the active rules as they were when the dialog was opened.
$currenthash = sha1((string)$cohort->get_rules_record()->rulesjson);

if (sync::get_current($cohort)) {
    $message = html_writer::div(get_string('sync_already', 'tool_mucohort'), 'alert alert-info');
    $submit = null;
} else if (!sync::can_sync($cohort)) {
    $message = html_writer::div(get_string('rules_state_' . $cohort->get_state() . '_desc', 'tool_mucohort'), 'alert alert-danger');
    $submit = null;
} else {
    $synctype = $cohort->get_synctype();
    $message = html_writer::tag('p', get_string('sync_now_confirm', 'tool_mucohort'))
        . html_writer::tag('p', rules::get_synctype_descriptions()[$synctype]);
    $submit = get_string('sync_now', 'tool_mucohort');
}

$extra = [
    'message' => $message,
    'submit' => $submit,
    'currenthash' => $currenthash,
];
$form = new rules_confirm($currenturl, ['hash' => $currenthash], $extra);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}

if ($form->get_data() && $submit !== null) {
    sync::queue($cohort);
    $handler->submitted($returnurl);
}

$handler->render($form);
