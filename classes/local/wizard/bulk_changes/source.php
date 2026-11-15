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

namespace tool_mucohort\local\wizard\bulk_changes;

use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\info;
use tool_mulib\muform\element\inforawhtml;
use tool_mulib\muform\element\wizardcontinue;
use tool_mulib\muform\form;

/**
 * Bulk changes, stage one: pasted or uploaded user list.
 *
 * The stage is locked once completed, extra data 'rows' then shows the read data instead.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class source extends form {
    use \tool_mulib\muform\util\import_source_trait;

    #[\Override]
    protected function definition(): void {
        $rows = $this->get_extra_data()['rows'] ?? null;
        if ($rows !== null) {
            $this->add(new info('info', '', get_string('import_source_locked', 'tool_mulib', count($rows))));
            $this->add(new inforawhtml('datapreview', '', columns::render_data($rows)));
        } else {
            $this->add(new info('info', '', get_string('bulkchanges_source_info', 'tool_mucohort')));
            $this->add_import_source_elements(true);
        }

        $this->add(new buttons('buttons'));
        $this->add(new wizardcontinue(), 'buttons');
        $this->add(new cancel(), 'buttons');
    }

    /**
     * Returns parsed rows of valid submitted data.
     *
     * @return array
     */
    public function get_rows(): array {
        $data = $this->get_data();
        return \tool_mulib\local\import_util::parse($this->get_import_source_content(), $data->encoding, $data->delimiter_name);
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        if (isset($this->get_extra_data()['rows'])) {
            // Locked, nothing is submitted.
            return;
        }
        $this->validate_import_source($data, $allerrors);
    }
}
