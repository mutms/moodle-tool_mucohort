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

namespace tool_mucohort\event;

use stdClass;
use tool_mucohort\local\type\base;

/**
 * Cohort role added event.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class role_added extends \core\event\base {
    /**
     * Helper for event creation.
     *
     * @param stdClass $record tool_mucohort_role record
     * @param base $cohort
     * @return static
     */
    public static function create_from_record(stdClass $record, base $cohort): static {
        $data = [
            'context' => $cohort->get_context(),
            'objectid' => $record->id,
            'other' => [
                'cohortid' => $cohort->get_id(),
                'roleid' => (int)$record->roleid,
                'contextid' => (int)$record->contextid,
            ],
        ];
        /** @var static $event */
        $event = self::create($data);
        $event->add_record_snapshot('tool_mucohort_role', $record);
        return $event;
    }

    #[\Override]
    public function get_description() {
        return "The user with id '$this->userid' added role with id '{$this->other['roleid']}' "
            . "in context with id '{$this->other['contextid']}' for members of cohort with id '{$this->other['cohortid']}'";
    }

    #[\Override]
    public static function get_name() {
        return get_string('event_role_added', 'tool_mucohort');
    }

    #[\Override]
    public function get_url() {
        return new \core\url('/admin/tool/mucohort/cohort_roles.php', ['id' => $this->other['cohortid']]);
    }

    #[\Override]
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'tool_mucohort_role';
    }

    #[\Override]
    protected function validate_data() {
        parent::validate_data();
        foreach (['cohortid', 'roleid', 'contextid'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \core\exception\coding_exception("The '$key' value must be set in other.");
            }
        }
    }
}
