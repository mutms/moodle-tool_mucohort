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

namespace tool_mucohort\local\form;

use tool_mucohort\local\bulk_changes;
use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\inforawhtml;
use tool_mulib\muform\element\radiocards;
use tool_mulib\muform\element\reload;
use tool_mulib\muform\element\select;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\element\textarea;
use tool_mulib\muform\form;
use stdClass;

/**
 * Bulk changes, stage three: what to do with the users and the dry run result.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bulk_changes_options extends form {
    #[\Override]
    protected function definition(): void {
        /** @var \tool_mucohort\local\type\base $cohort */
        $cohort = $this->get_extra_data()['cohort'];
        $state = $this->get_extra_data()['state'];

        $actions = bulk_changes::get_actions($cohort);
        $descriptions = array_intersect_key(bulk_changes::get_action_descriptions($cohort), $actions);
        $action = (new radiocards('action', get_string('bulkchanges_action', 'tool_mucohort'), $actions, $descriptions))
            ->set_default(array_key_first($actions));
        $action->set_required(true);
        $this->add($action);

        $types = bulk_changes::get_identifier_types($cohort);
        $identifiertype = (new select('identifiertype', get_string('bulkchanges_identifiertype', 'tool_mucohort'), $types))
            ->set_default(bulk_changes::guess_identifier_type($state, $types));
        $this->add($identifiertype);

        $this->add(new textarea('reason', get_string('exception_reason', 'tool_mucohort'), ['rows' => 3]));
        $this->get_display_manager()->hide_if(
            'reason',
            'action',
            'notin',
            [bulk_changes::ACTION_FORCE, bulk_changes::ACTION_BLOCK]
        );

        // The preview always shows what the options in the form would do right now.
        $check = null;
        $actionvalue = (string)$this->get_element('action')->get_value();
        $typevalue = (string)$this->get_element('identifiertype')->get_value();
        if (isset($actions[$actionvalue]) && isset($types[$typevalue])) {
            $check = bulk_changes::check($cohort, bulk_changes::get_identifiers($state), $typevalue, $actionvalue);
            $this->add(new inforawhtml('preview', '', self::render_preview($check)));
        }

        // The import button comes first so that Enter never skips the dry run of other options.
        $this->add(new buttons('buttons'));
        if ($check && bulk_changes::is_importable($check)) {
            $submit = get_string('bulkchanges_confirm', 'tool_mucohort', count($check->userids));
            $this->add(new submit('submit', $submit), 'buttons');
        }
        $this->add(new reload('refresh', get_string('bulkchanges_refresh', 'tool_mucohort')), 'buttons');
        $this->add(new reload('back', get_string('muform_back', 'tool_mulib')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }

    /**
     * Counts of the dry run and the first problem rows.
     *
     * @param stdClass $check
     * @return string html
     */
    private static function render_preview(stdClass $check): string {
        global $OUTPUT, $DB;

        $names = bulk_changes::get_result_names();
        $items = [];
        foreach ($check->counts as $result => $count) {
            if ($count) {
                $a = (object)['name' => $names[$result], 'count' => $count];
                $items[] = get_string('bulkchanges_count', 'tool_mucohort', $a);
            }
        }
        $html = '';
        if ($check->capacity !== null && count($check->userids) > $check->capacity) {
            $html .= $OUTPUT->notification(get_string('error_maxmembers', 'tool_mucohort', $check->capacity), 'error', false);
        } else if (!$check->userids) {
            $html .= $OUTPUT->notification(get_string('bulkchanges_nothing', 'tool_mucohort'), 'warning', false);
        }
        $html .= \core\output\html_writer::alist($items, ['class' => 'list-unstyled']);

        // Only problems are listed, long lists are not read row by row anyway.
        $problems = array_filter($check->outcomes, fn($o) => $o->result !== bulk_changes::RESULT_OK);
        $shown = array_slice($problems, 0, bulk_changes::PREVIEW_ROWS);
        if (!$shown) {
            return $html;
        }
        $userids = array_filter(array_map(fn($o) => $o->userid, $shown));
        $users = [];
        if ($userids) {
            $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
            [$insql, $params] = $DB->get_in_or_equal($userids);
            $users = $DB->get_records_sql("SELECT u.id, $fields FROM {user} u WHERE u.id $insql", $params);
        }

        $table = new \html_table();
        $table->attributes['class'] = 'generaltable mb-0';
        $table->head = [
            get_string('bulkchanges_identifier', 'tool_mucohort'),
            get_string('user'),
            get_string('status'),
        ];
        foreach ($shown as $outcome) {
            $user = $outcome->userid ? ($users[$outcome->userid] ?? null) : null;
            $table->data[] = [
                s($outcome->identifier),
                $user ? s(fullname($user)) : '',
                s($names[$outcome->result]),
            ];
        }
        $html .= \core\output\html_writer::table($table);
        if (count($problems) > count($shown)) {
            $more = count($problems) - count($shown);
            $html .= \core\output\html_writer::div(get_string('bulkchanges_morerows', 'tool_mucohort', $more), 'text-muted');
        }
        return $html;
    }
}
