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
 * Places where the cohort is used.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\management;
use tool_mucohort\local\type\base;
use tool_mucohort\local\usage;
use core\output\html_writer;
use core\url;

/** @var core_renderer $OUTPUT */
/** @var moodle_page $PAGE */

require('../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$cohort = base::fetch($id);
$context = $cohort->get_context();
if (!$cohort->can_view()) {
    throw new required_capability_exception($context, 'moodle/cohort:view', 'nopermissions', '');
}

$currenturl = new url('/admin/tool/mucohort/cohort_usage.php', ['id' => $cohort->get_id()]);
management::setup_cohort_page($currenturl, $cohort, 'cohort_usage');

echo $OUTPUT->header();

echo html_writer::tag('p', get_string('usage_info', 'tool_mucohort'), ['class' => 'text-muted']);

$sections = usage::get_usage($cohort)->get_sections();
if (!$sections) {
    echo $OUTPUT->notification(get_string('usage_none', 'tool_mucohort'), 'info', false);
}
foreach ($sections as $section => $items) {
    echo $OUTPUT->heading(s($section) . ' (' . count($items) . ')', 3, 'h5 mt-4');
    $list = [];
    $hidden = 0;
    foreach ($items as $item) {
        if (!$item->visible) {
            $hidden++;
            continue;
        }
        $html = s($item->name);
        if ($item->url) {
            $html = html_writer::link($item->url, $html);
        }
        if ($item->details !== null && $item->details !== '') {
            $html .= ' ' . html_writer::span('(' . s($item->details) . ')', 'text-muted');
        }
        $list[] = $html;
    }
    if ($hidden) {
        $list[] = html_writer::span(get_string('usage_hidden', 'tool_mucohort', $hidden), 'text-muted');
    }
    echo html_writer::alist($list, ['class' => 'mb-0']);
}

echo $OUTPUT->footer();
