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
 * Hook for describing cohorts owned by other plugins.
 *
 * Dispatched for cohorts with a component other than tool_mucohort, the owner plugin
 * checks the cohort component and explains where the cohort comes from, for example
 * "Tenant cohort of Tenant A" with a link to the tenant.
 *
 * Plugin cohorts are read-only in cohort management, the owner plugin keeps
 * the membership up to date.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to describe cohorts they own.')]
#[\core\attribute\tags('tool_mucohort', 'cohort')]
final class cohort_owner {
    /** @var string|null plain text description */
    private ?string $description = null;
    /** @var url|null link to the owner */
    private ?url $url = null;

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
     * Describe the cohort.
     *
     * @param string $description plain text, for example "Tenant cohort of Tenant A"
     * @param url|null $url link to the owner if the current user may see it
     */
    public function set_description(string $description, ?url $url = null): void {
        $this->description = $description;
        $this->url = $url;
    }

    /**
     * Returns plain text description.
     *
     * @return string|null
     */
    public function get_description(): ?string {
        return $this->description;
    }

    /**
     * Returns link to the owner.
     *
     * @return url|null
     */
    public function get_url(): ?url {
        return $this->url;
    }
}
