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
 * Cohort overview.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\type\base;
use tool_mucohort\local\management;
use tool_mulib\output\header_actions;
use tool_mulib\output\muform\dialog\button;
use tool_mulib\output\muform\dialog\icon;
use tool_mulib\output\muform\dialog\link;
use core\url;

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */

require('../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$cohort = base::fetch($id);
$record = $cohort->get_cohort();
$context = $cohort->get_context();
if (!$cohort->can_view()) {
    require_capability('moodle/cohort:view', $context);
}

$currenturl = new url('/admin/tool/mucohort/cohort.php', ['id' => $record->id]);
management::setup_cohort_page($currenturl, $cohort, 'cohort_overview');


$actions = new header_actions(get_string('management_actions', 'tool_mucohort'));
if (\tool_mucohort\local\conversion::get_target_types($cohort)) {
    $url = new url('/admin/tool/mucohort/management/cohort_convert.php', ['id' => $record->id]);
    $link = new link($url, get_string('convert_cohorttype', 'tool_mucohort'), 'i/settings');
    $link->set_form_size('lg');
    $actions->get_dropdown()->add_dialog($link);
}
if ($cohort->can_delete()) {
    $url = new url('/admin/tool/mucohort/management/cohort_delete.php', ['id' => $record->id]);
    $link = new link($url, get_string('cohort_delete', 'tool_mucohort'), 'i/delete');
    $link->add_class('text-danger');
    $link->set_form_size('sm');
    $link->set_submitted_action(\tool_mulib\muform\handler\dialog::ACTION_REDIRECT);
    $actions->get_dropdown()->add_dialog($link);
}
if ($actions->has_items()) {
    $PAGE->add_header_action($OUTPUT->render($actions));
}

echo $OUTPUT->header();

$details = new \tool_mulib\output\entity_details();
$details->add(get_string('name', 'core_cohort'), format_string($record->name, true, ['context' => $context]));
$details->add(get_string('idnumber', 'core_cohort'), s((string)$record->idnumber));

if ($cohort->is_context_missing()) {
    $contextname = get_string('error_contextmissing', 'tool_mucohort');
} else {
    $contextname = $context->get_context_name(false);
}
if ($cohort->can_move()) {
    $url = new url('/admin/tool/mucohort/management/cohort_move.php', ['id' => $record->id]);
    $action = new icon($url, get_string('cohort_move', 'tool_mucohort'), 'i/edit');
    $contextname .= $OUTPUT->render($action);
}
$details->add(get_string('category'), $contextname);
$details->add(get_string('type', 'tool_mucohort'), s($cohort->get_type_name()));
$cohort->add_overview_details($details);
$details->add(get_string('visible', 'core_cohort'), $record->visible ? get_string('yes') : get_string('no'));
if (!empty($CFG->allowcohortthemes) && $record->theme !== '') {
    $details->add(get_string('forcetheme'), s($record->theme));
}
$memberscount = $DB->count_records('cohort_members', ['cohortid' => $record->id]);
$details->add(get_string('memberscount', 'core_cohort'), (string)$memberscount);

$description = file_rewrite_pluginfile_urls(
    $record->description,
    'pluginfile.php',
    $context->id,
    'cohort',
    'description',
    $record->id
);
$description = format_text($description, $record->descriptionformat, ['context' => $context]);
$details->add(get_string('description'), $description);

$handler = \core_cohort\customfield\cohort_handler::create();
foreach ($handler->get_instance_data($record->id) as $data) {
    $value = $data->export_value();
    if ($value === null || $value === '') {
        continue;
    }
    $details->add($data->get_field()->get('name'), $value);
}

echo $OUTPUT->render($details);

if ($cohort->get_update_form_class() && $cohort->can_manage()) {
    $url = new url('/admin/tool/mucohort/management/cohort_update.php', ['id' => $record->id]);
    $editbutton = new button($url, get_string('edit'));
    $editbutton->set_form_size('xl');
    echo $OUTPUT->box($OUTPUT->render($editbutton), 'buttons');
}

echo $OUTPUT->footer();
