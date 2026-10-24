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
 * Draft rules of rule-based cohort.
 *
 * All rule changes are written to draft rules, they take effect only after approval.
 * Draft rules are created via "Modify rules" on the active rules page.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\broken_rules_exception;
use tool_mucohort\local\management;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\rules;
use tool_mulib\output\muform\dialog\button;
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

$currenturl = new url('/admin/tool/mucohort/cohort_draft.php', ['id' => $cohort->get_id()]);
management::setup_cohort_page($currenturl, $cohort, 'cohort_draft');

if ($cohort->get_rules_record()->pendingrulesjson === null) {
    redirect(new url('/admin/tool/mucohort/cohort_rules.php', ['id' => $cohort->get_id()]));
}
$canmanage = $cohort->can_manage_rules();

/** @var \tool_mucohort\output\renderer $renderer */
$renderer = $PAGE->get_renderer('tool_mucohort');

echo $OUTPUT->header();

echo html_writer::tag('p', get_string('rules_draft_waiting', 'tool_mucohort'));

// Draft rules can be approved only if there is at least one rule.
$hasrules = false;
try {
    $ruleset = $cohort->get_pending_ruleset();
    echo $renderer->render_ruleset($cohort, $ruleset, $canmanage);
    $hasrules = (bool)$ruleset->get_rules();
} catch (broken_rules_exception $ex) {
    echo $OUTPUT->notification($ex->getMessage(), \core\output\notification::NOTIFY_ERROR, false);
}

if ($canmanage) {
    $buttons = '';
    if ($hasrules) {
        $approve = new button(
            new url('/admin/tool/mucohort/management/rules_approve.php', ['id' => $cohort->get_id()]),
            get_string('rules_approve', 'tool_mucohort'),
            true
        );
        $approve->set_submitted_action(\tool_mulib\muform\handler\dialog::ACTION_REDIRECT);
        $buttons .= $OUTPUT->render($approve);
        $url = new url('/admin/tool/mucohort/management/rules_preview.php', ['id' => $cohort->get_id()]);
        $buttons .= $OUTPUT->single_button($url, get_string('rules_preview', 'tool_mucohort'), 'get');
    }
    $discard = new button(
        new url('/admin/tool/mucohort/management/rules_discard.php', ['id' => $cohort->get_id()]),
        get_string('rules_discard', 'tool_mucohort')
    );
    $discard->set_form_size('sm');
    $discard->set_submitted_action(\tool_mulib\muform\handler\dialog::ACTION_REDIRECT);
    $buttons .= $OUTPUT->render($discard);
    echo $OUTPUT->box($buttons, 'buttons d-flex gap-2 border-top pt-3');
}

echo $OUTPUT->footer();
