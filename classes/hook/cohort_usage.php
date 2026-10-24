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

namespace tool_mucohort\hook;

use core\url;
use stdClass;

/**
 * Hook for listing of places where a cohort is used.
 *
 * Callbacks add items grouped in sections, for example course enrolments or program allocations.
 * Items that the current user may not see are added as not visible, only their number is shown,
 * so that people know the cohort is used before they delete it or change its members.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to list places where a cohort is used.')]
#[\core\attribute\tags('tool_mucohort', 'cohort')]
final class cohort_usage {
    /** @var array section name => list of items */
    private array $sections = [];

    /**
     * Constructor.
     *
     * @param stdClass $cohort cohort record
     */
    public function __construct(
        /** @var stdClass cohort record */
        private readonly stdClass $cohort
    ) {
    }

    /**
     * Returns cohort record.
     *
     * @return stdClass
     */
    public function get_cohort(): stdClass {
        return clone($this->cohort);
    }

    /**
     * Add a place where the cohort is used.
     *
     * @param string $section plain text section name, for example "Course enrolments"
     * @param string $name plain text name of the item, for example course name
     * @param url|null $url link to the item
     * @param string|null $details plain text details, for example "Disabled"
     * @param bool $visible false means the current user may not see the item, only the count is shown
     */
    public function add_item(string $section, string $name, ?url $url = null, ?string $details = null, bool $visible = true): void {
        $this->sections[$section][] = (object)[
            'name' => $name,
            'url' => $url,
            'details' => $details,
            'visible' => $visible,
        ];
    }

    /**
     * Returns usage sections sorted by name.
     *
     * @return array section name => list of items with name, url, details and visible properties
     */
    public function get_sections(): array {
        $sections = $this->sections;
        \core_collator::ksort($sections);
        return $sections;
    }
}
