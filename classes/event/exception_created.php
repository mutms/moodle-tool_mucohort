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

use tool_mucohort\local\type\base;
use stdClass;

/**
 * Cohort exception created event.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class exception_created extends \core\event\base {
    /**
     * Helper for event creation.
     *
     * @param stdClass $record tool_mucohort_exception record
     * @param base $cohort
     * @return static
     */
    public static function create_from_record(stdClass $record, base $cohort): static {
        $data = [
            'context' => $cohort->get_context(),
            'objectid' => $record->id,
            'relateduserid' => $record->userid,
            'other' => ['cohortid' => $cohort->get_id()],
        ];
        /** @var static $event */
        $event = self::create($data);
        $event->add_record_snapshot('tool_mucohort_exception', $record);
        return $event;
    }

    #[\Override]
    public function get_description() {
        return "Exception for user with id '$this->relateduserid' was created in cohort with id '{$this->other['cohortid']}'";
    }

    #[\Override]
    public static function get_name() {
        return get_string('event_exception_created', 'tool_mucohort');
    }

    #[\Override]
    public function get_url() {
        return new \core\url('/admin/tool/mucohort/cohort_exceptions.php', ['id' => $this->other['cohortid']]);
    }

    #[\Override]
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'tool_mucohort_exception';
    }

    #[\Override]
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['cohortid'])) {
            throw new \core\exception\coding_exception('The \'cohortid\' value must be set in other.');
        }
    }
}
