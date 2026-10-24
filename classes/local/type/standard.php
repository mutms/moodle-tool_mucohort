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

namespace tool_mucohort\local\type;

use stdClass;

/**
 * Standard cohort - cohort without component, managed via core capabilities.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class standard extends base {
    /** @var string type identifier */
    public const string TYPE = 'standard';

    #[\Override]
    public static function get_manage_capability(): string {
        return 'moodle/cohort:manage';
    }

    #[\Override]
    public static function get_create_form_class(): ?string {
        return \tool_mucohort\local\form\standard_cohort_create::class;
    }

    #[\Override]
    public static function create(stdClass $data): standard {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $context = self::validate_context((int)$data->contextid);

        $record = new stdClass();
        $record->contextid = $context->id;
        $record->name = $data->name;
        $record->idnumber = self::normalise_idnumber($data->idnumber ?? null);
        $record->description = $data->description ?? '';
        $record->descriptionformat = $data->descriptionformat ?? FORMAT_HTML;
        $record->visible = (int)(bool)($data->visible ?? 1);
        $record->component = '';
        $record->theme = $data->theme ?? '';

        $id = cohort_add_cohort($record);

        /** @var standard $result */
        $result = self::fetch($id);
        return $result;
    }

    #[\Override]
    public function get_update_form_class(): ?string {
        return \tool_mucohort\local\form\standard_cohort_update::class;
    }

    #[\Override]
    public function get_member_add_form_class(): ?string {
        return \tool_mucohort\local\form\standard_member_add::class;
    }

    #[\Override]
    public function get_member_remove_form_class(): ?string {
        return \tool_mucohort\local\form\member_remove::class;
    }

    #[\Override]
    public function add_members(array $userids): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        foreach ($userids as $userid) {
            cohort_add_member($this->cohort->id, (int)$userid);
        }
    }

    #[\Override]
    public function remove_member(int $userid): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        cohort_remove_member($this->cohort->id, $userid);
    }


    #[\Override]
    public function can_view_members(): bool {
        return has_any_capability(['moodle/cohort:assign', 'tool/mucohort:viewmembers'], $this->get_context());
    }

    #[\Override]
    public function can_manage_members(): bool {
        return has_capability('moodle/cohort:assign', $this->get_context());
    }
}
