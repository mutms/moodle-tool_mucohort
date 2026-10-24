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

use tool_mucohort\local\type\rules;

/**
 * Draft rules updated event.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rules_draft_updated extends \core\event\base {
    /**
     * Helper for event creation.
     *
     * @param rules $cohort
     * @return static
     */
    public static function create_from_cohort(rules $cohort): static {
        $data = [
            'context' => $cohort->get_context(),
            'objectid' => $cohort->get_id(),
        ];
        /** @var static $event */
        $event = self::create($data);
        $event->add_record_snapshot('cohort', $cohort->get_cohort());
        return $event;
    }

    #[\Override]
    public function get_description() {
        return "The user with id '$this->userid' updated draft rules of cohort with id '$this->objectid'";
    }

    #[\Override]
    public static function get_name() {
        return get_string('event_rules_draft_updated', 'tool_mucohort');
    }

    #[\Override]
    public function get_url() {
        return new \core\url('/admin/tool/mucohort/cohort_rules.php', ['id' => $this->objectid]);
    }

    #[\Override]
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'cohort';
    }
}
