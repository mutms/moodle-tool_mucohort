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
 * Add rule wizard - type, settings and check, the rule is added to draft rules.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\broken_rules_exception;
use tool_mucohort\local\form\rule_check;
use tool_mucohort\local\form\rule_edit;
use tool_mucohort\local\form\rule_type_select;
use tool_mucohort\local\management;
use tool_mucohort\local\rule\base as rule;
use tool_mucohort\local\ruleset;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\rules;
use tool_mulib\muform\wizard;
use core\url;

/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */

require('../../../../config.php');

$id = required_param('id', PARAM_INT);
$kind = required_param('kind', PARAM_ALPHA);

require_login();

$cohort = base::fetch($id);
$context = $cohort->get_context();
if (!($cohort instanceof rules) || !$cohort->can_manage_rules()) {
    throw new required_capability_exception($context, 'tool/mucohort:manage', 'nopermissions', '');
}
if ($kind !== rule::KIND_INCLUDE && $kind !== rule::KIND_EXCLUDE) {
    throw new invalid_parameter_exception('Invalid rule kind');
}

$pageurl = new url('/admin/tool/mucohort/management/rule_add.php', ['id' => $cohort->get_id(), 'kind' => $kind]);
$returnurl = new url('/admin/tool/mucohort/cohort_draft.php', ['id' => $cohort->get_id()]);
management::setup_cohort_page($pageurl, $cohort, 'cohort_draft');
$title = get_string('rule_add_' . $kind, 'tool_mucohort');
$PAGE->navbar->add($title);

if ($cohort->get_rules_record()->pendingrulesjson === null) {
    // Rules must be modified via draft rules.
    redirect(new url('/admin/tool/mucohort/cohort_rules.php', ['id' => $cohort->get_id()]));
}

$wizardname = 'tool_mucohort_rule_add:' . $cohort->get_id() . ':' . $kind;
$wizard = wizard::load($wizardname, optional_param(wizard::PARAM, 0, PARAM_INT));
if (!$wizard) {
    $wizard = wizard::start($wizardname);
    redirect($wizard->get_url($pageurl));
}

$classes = $cohort->get_rule_types()->get_classes($kind);
$state = $wizard->get_data();
$type = $state['type'] ?? null;
$rule = isset($classes[$type]) ? new $classes[$type]($cohort->get_cohort(), $cohort->get_mucohort()) : null;

// The configuration is valid if the rule can be evaluated.
$isconfigvalid = function (?rule $rule, array $state): bool {
    if (!$rule || !is_array($state['config'] ?? null)) {
        return false;
    }
    try {
        $rule->get_sql($state['config'], null);
    } catch (\tool_mucohort\local\rule_not_ready_exception $ex) {
        // Data for the rule is prepared when the rule is saved.
        return true;
    } catch (broken_rules_exception $ex) {
        return false;
    }
    return true;
};

$labels = [
    'type' => get_string('rule_type', 'tool_mucohort'),
    'settings' => get_string('rule_settings', 'tool_mucohort'),
    'check' => get_string('rule_check', 'tool_mucohort'),
];
$isvalid = fn(string $stage): bool => match ($stage) {
    'type' => $rule !== null,
    'settings' => $isconfigvalid($rule, $state),
    'check' => false,
};
$requested = optional_param(wizard::STAGE_PARAM, null, PARAM_ALPHA);
$stages = wizard::resolve_stages($labels, $isvalid, $requested);
$stage = wizard::current_stage($stages);

$formurl = $wizard->get_url($pageurl, $stage);
if ($stage === 'type') {
    $types = [];
    $descriptions = [];
    foreach ($classes as $t => $classname) {
        $types[$t] = $classname::get_name();
        $descriptions[$t] = $classname::get_type_description();
    }
    $current = $type ? ['type' => $type] : [];
    $form = new rule_type_select($formurl, $current, ['types' => $types, 'descriptions' => $descriptions]);
} else if ($stage === 'settings') {
    $current = $rule->get_form_data($state['config'] ?? []);
    $form = new rule_edit($formurl, $current, ['rule' => $rule, 'wizard' => true, 'currenthash' => null]);
} else {
    $ruleset = new ruleset();
    $uuid = $ruleset->add_rule($kind, $type, $state['config']);
    $cohort->before_preview($ruleset);
    try {
        $count = (string)rules::count_users($cohort->get_rule_match_sql($ruleset, $uuid));
    } catch (\tool_mucohort\local\rule_not_ready_exception $ex) {
        $count = $ex->getMessage();
    }
    $extra = [
        'description' => $cohort->get_rule_description($ruleset->get_rule($uuid)),
        'count' => $count,
    ];
    $form = new rule_check($formurl, [], $extra);
}

if ($form->is_cancelled()) {
    $wizard->delete();
    redirect($returnurl);
}
if ($stage !== 'type' && $form->is_reloaded() && $form->get_element('back')->get_value()) {
    $previous = ($stage === 'check' && $rule->has_settings()) ? 'settings' : 'type';
    redirect($wizard->get_url($pageurl, $previous));
}
if ($data = $form->get_data()) {
    if ($stage === 'type') {
        if ($data->type !== $type) {
            $rule = new $classes[$data->type]($cohort->get_cohort(), $cohort->get_mucohort());
            // Rules without settings go straight to the check.
            $state = ['type' => $data->type, 'config' => $rule->has_settings() ? null : []];
        }
    } else if ($stage === 'settings') {
        $state['config'] = $rule->get_config($data);
    } else {
        $ruleset = $cohort->start_draft();
        $ruleset->add_rule($kind, $type, $state['config']);
        $cohort->set_pending_ruleset($ruleset);
        $wizard->delete();
        redirect($returnurl);
    }
    $wizard->set_data($state);
    redirect($wizard->get_url($pageurl));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title, 2, 'mb-3');
echo $wizard->render($OUTPUT, $stages, $pageurl, $form);
echo $OUTPUT->footer();
