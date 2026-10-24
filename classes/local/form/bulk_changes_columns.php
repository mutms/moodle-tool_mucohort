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
use tool_mulib\muform\element\checkbox;
use tool_mulib\muform\element\info;
use tool_mulib\muform\element\inforawhtml;
use tool_mulib\muform\element\reload;
use tool_mulib\muform\element\select;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;

/**
 * Bulk changes, stage two: which column contains the users.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bulk_changes_columns extends form {
    #[\Override]
    protected function definition(): void {
        $rows = $this->get_extra_data()['rows'];
        $first = $rows[0];

        $this->add(new info('info', '', get_string('bulkchanges_columns_info', 'tool_mucohort', count($rows))));
        $this->add(new inforawhtml('datapreview', '', self::render_data($rows)));

        // Guesses are only defaults, coming back to this stage shows what was chosen before.
        $headers = (new checkbox('headers', get_string('bulkchanges_headers', 'tool_mucohort')))
            ->set_default((int)bulk_changes::guess_headers($first));
        $this->add($headers);

        $columns = [];
        foreach ($first as $index => $value) {
            $a = (object)['number' => $index + 1, 'value' => \core_text::substr($value, 0, 30)];
            $columns[$index] = get_string('bulkchanges_column', 'tool_mucohort', $a);
        }
        $column = (new select('column', get_string('bulkchanges_identifiercolumn', 'tool_mucohort'), $columns))
            ->set_default(bulk_changes::guess_column($first));
        $this->add($column);

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('continue')), 'buttons');
        $this->add(new reload('back', get_string('muform_back', 'tool_mulib')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }

    /**
     * First rows of the data as they were read.
     *
     * @param array $rows
     * @return string html
     */
    private static function render_data(array $rows): string {
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable mb-0';
        foreach (array_keys($rows[0]) as $index) {
            $table->head[] = get_string('bulkchanges_columnnumber', 'tool_mucohort', $index + 1);
        }
        $shown = array_slice($rows, 0, bulk_changes::DATA_PREVIEW_ROWS);
        foreach ($shown as $row) {
            $table->data[] = array_map('s', $row);
        }
        $html = \core\output\html_writer::table($table);
        if (count($rows) > count($shown)) {
            $more = count($rows) - count($shown);
            $html .= \core\output\html_writer::div(get_string('bulkchanges_morerows', 'tool_mucohort', $more), 'text-muted');
        }
        return $html;
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        $rows = $this->get_extra_data()['rows'];
        if (!empty($data['headers']) && count($rows) < 2) {
            $allerrors['headers'][] = get_string('bulkchanges_nodatarows', 'tool_mucohort');
        }
    }
}
