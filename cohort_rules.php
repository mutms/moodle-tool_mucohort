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
 * Active rules of rule-based cohort.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\broken_rules_exception;
use tool_mucohort\local\management;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\rules;
use core\url;

/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */

require('../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$cohort = base::fetch($id);
$context = $cohort->get_context();
if (!($cohort instanceof rules) || !$cohort->can_view()) {
    throw new required_capability_exception($context, 'moodle/cohort:view', 'nopermissions', '');
}

$currenturl = new url('/admin/tool/mucohort/cohort_rules.php', ['id' => $cohort->get_id()]);
management::setup_cohort_page($currenturl, $cohort, 'cohort_rules');

/** @var \tool_mucohort\output\renderer $renderer */
$renderer = $PAGE->get_renderer('tool_mucohort');

$syncbutton = $renderer->render_sync_button($cohort);
if ($syncbutton !== '') {
    $actions = new \tool_mulib\output\header_actions(get_string('management_actions', 'tool_mucohort'));
    $actions->add_button($syncbutton);
    $PAGE->add_header_action($OUTPUT->render($actions));
}

echo $OUTPUT->header();

echo html_writer::tag('p', get_string('rules_state_' . $cohort->get_state() . '_desc', 'tool_mucohort'));

$hasdraft = ($cohort->get_rules_record()->pendingrulesjson !== null);

if ($hasdraft) {
    $link = html_writer::link(
        new url('/admin/tool/mucohort/cohort_draft.php', ['id' => $cohort->get_id()]),
        get_string('tab_rules_draft', 'tool_mucohort')
    );
    $message = get_string('rules_draft_exists', 'tool_mucohort', $link);
    echo $OUTPUT->notification($message, \core\output\notification::NOTIFY_INFO, false);
}

try {
    $ruleset = $cohort->get_active_ruleset();
    if ($ruleset) {
        echo $renderer->render_ruleset($cohort, $ruleset, false);
    }
} catch (broken_rules_exception $ex) {
    echo $OUTPUT->notification($ex->getMessage(), \core\output\notification::NOTIFY_ERROR, false);
}

$buttons = '';
if (!$hasdraft && $cohort->can_manage_rules()) {
    $url = new url('/admin/tool/mucohort/management/rules_modify.php', ['id' => $cohort->get_id()]);
    $label = get_string('rules_modify', 'tool_mucohort');
    $buttons .= $OUTPUT->single_button($url, $label, 'post', ['type' => single_button::BUTTON_PRIMARY]);
}
if ($buttons !== '') {
    echo $OUTPUT->box($buttons, 'buttons d-flex gap-2 border-top pt-3');
}

echo $OUTPUT->footer();
