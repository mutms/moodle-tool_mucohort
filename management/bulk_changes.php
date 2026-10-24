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
 * Bulk changes of members or exceptions, one change is applied to a pasted or uploaded list of users.
 *
 * The page walks through three stages: source, identifier column, options with a dry run.
 * The parsed rows and the answers live in the muform wizard state between stages.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\form\bulk_changes_columns;
use tool_mucohort\local\form\bulk_changes_options;
use tool_mucohort\local\form\bulk_changes_source;
use tool_mucohort\local\management;
use tool_mucohort\local\bulk_changes;
use tool_mucohort\local\type\base;
use tool_mulib\muform\wizard;
use core\url;

/** @var core_renderer $OUTPUT */
/** @var moodle_page $PAGE */

require('../../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$cohort = base::fetch($id);
$actions = bulk_changes::get_actions($cohort);
if (!$actions) {
    throw new required_capability_exception($cohort->get_context(), 'moodle/cohort:assign', 'nopermissions', '');
}

$pageurl = new url('/admin/tool/mucohort/management/bulk_changes.php', ['id' => $cohort->get_id()]);
management::setup_cohort_page($pageurl, $cohort, 'cohort_members');
$PAGE->navbar->add(get_string('bulkchanges', 'tool_mucohort'));

// The state belongs to this cohort, it cannot be replayed against another one.
$wizardname = 'tool_mucohort_bulk_changes:' . $cohort->get_id();
$wizard = wizard::load($wizardname, optional_param(wizard::PARAM, 0, PARAM_INT));
if (!$wizard) {
    $wizard = wizard::start($wizardname);
    $action = optional_param('action', '', PARAM_ALPHA);
    if (isset($actions[$action])) {
        $wizard->set_data(['defaultaction' => $action]);
    }
    redirect($wizard->get_url($pageurl));
}

$state = $wizard->get_data();
$defaultaction = $state['defaultaction'] ?? null;
$action = $state['action'] ?? $defaultaction;
$exceptionactions = [bulk_changes::ACTION_FORCE, bulk_changes::ACTION_BLOCK, bulk_changes::ACTION_UNEXCEPT];
if (in_array($action, $exceptionactions, true)) {
    $returnurl = new url('/admin/tool/mucohort/cohort_exceptions.php', ['id' => $cohort->get_id()]);
} else {
    $returnurl = new url('/admin/tool/mucohort/cohort_members.php', ['id' => $cohort->get_id()]);
}

$labels = [
    'source' => get_string('users'),
    'columns' => get_string('bulkchanges_columns', 'tool_mucohort'),
    'options' => get_string('bulkchanges_changes', 'tool_mucohort'),
];
$isvalid = fn(string $stage): bool => match ($stage) {
    'source' => bulk_changes::is_source_valid($state),
    'columns' => bulk_changes::is_columns_valid($state),
    'options' => bulk_changes::is_options_valid($cohort, $state),
};
$requested = optional_param(wizard::STAGE_PARAM, null, PARAM_ALPHA);
$stages = wizard::resolve_stages($labels, $isvalid, $requested);
$stage = wizard::current_stage($stages);

if ($requested === null && $isvalid('options')) {
    // Everything was answered, the dry run is repeated with current data.
    $count = bulk_changes::import($cohort, $state);
    $wizard->delete();
    \core\notification::success(get_string('bulkchanges_done', 'tool_mucohort', $count));
    redirect($returnurl);
}

$formurl = $wizard->get_url($pageurl, $stage);
if ($stage === 'source') {
    $form = new bulk_changes_source($formurl, []);
} else if ($stage === 'columns') {
    $current = [];
    if (bulk_changes::is_columns_valid($state)) {
        $current = ['headers' => (int)$state['headers'], 'column' => $state['column']];
    }
    $form = new bulk_changes_columns($formurl, $current, ['rows' => $state['rows']]);
} else {
    // Options of an earlier confirmation are only defaults of the form.
    $current = array_filter([
        'action' => $action,
        'identifiertype' => $state['identifiertype'] ?? null,
        'reason' => $state['reason'] ?? null,
    ], fn($value) => $value !== null);
    $form = new bulk_changes_options($formurl, $current, ['cohort' => $cohort, 'state' => $state]);
}

if ($form->is_cancelled()) {
    $wizard->delete();
    redirect($returnurl);
}
if ($stage !== 'source' && $form->is_reloaded() && $form->get_element('back')->get_value()) {
    $previous = 'source';
    if ($stage === 'options' && count($state['rows'][0]) > 1) {
        $previous = 'columns';
    }
    redirect($wizard->get_url($pageurl, $previous));
}
if ($data = $form->get_data()) {
    $base = $defaultaction ? ['defaultaction' => $defaultaction] : [];
    if ($stage === 'source') {
        $state = $base + bulk_changes::save_source($form->get_rows());
    } else if ($stage === 'columns') {
        // New columns mean a new dry run.
        $state = $base + [
            'rows' => $state['rows'],
            'headers' => !empty($data->headers),
            'column' => (int)$data->column,
        ];
    } else {
        $state['action'] = $data->action;
        $state['identifiertype'] = $data->identifiertype;
        $reason = trim((string)($data->reason ?? ''));
        $isexception = ($data->action === bulk_changes::ACTION_FORCE || $data->action === bulk_changes::ACTION_BLOCK);
        $state['reason'] = ($isexception && $reason !== '') ? $reason : null;
    }
    $wizard->set_data($state);
    redirect($wizard->get_url($pageurl));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('bulkchanges', 'tool_mucohort'), 2, 'mb-3');
echo $wizard->render($OUTPUT, $stages, $pageurl, $form);
echo $OUTPUT->footer();
