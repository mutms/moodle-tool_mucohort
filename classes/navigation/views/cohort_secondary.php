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

namespace tool_mucohort\navigation\views;

use tool_mucohort\local\type\base;
use core\url;

/**
 * Cohort page tabs.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cohort_secondary extends \core\navigation\views\secondary {
    /**
     * Constructor.
     *
     * @param \moodle_page $page
     * @param base $cohort
     */
    public function __construct(
        \moodle_page $page,
        /** @var base cohort */
        protected base $cohort
    ) {
        parent::__construct($page);
    }

    #[\Override]
    public function initialise(): void {
        $this->id = 'secondary_navigation';
        $this->headertitle = get_string('menu');

        $cohort = $this->cohort;

        $url = new url('/admin/tool/mucohort/cohort.php', ['id' => $cohort->get_id()]);
        $this->add(get_string('tab_overview', 'tool_mucohort'), $url, \navigation_node::TYPE_SETTING, null, 'cohort_overview');

        if ($cohort->can_view()) {
            $url = new url('/admin/tool/mucohort/cohort_usage.php', ['id' => $cohort->get_id()]);
            $this->add(get_string('tab_usage', 'tool_mucohort'), $url, \navigation_node::TYPE_SETTING, null, 'cohort_usage');
        }

        if ($cohort instanceof \tool_mucohort\local\type\advanced && $cohort->can_view()) {
            $url = new url('/admin/tool/mucohort/cohort_access.php', ['id' => $cohort->get_id()]);
            $this->add(get_string('tab_access', 'tool_mucohort'), $url, \navigation_node::TYPE_SETTING, null, 'cohort_access');
        }

        if ($cohort instanceof \tool_mucohort\local\type\advanced && $cohort->can_view()) {
            $url = new url('/admin/tool/mucohort/cohort_roles.php', ['id' => $cohort->get_id()]);
            $this->add(get_string('tab_roles', 'tool_mucohort'), $url, \navigation_node::TYPE_SETTING, null, 'cohort_roles');
        }

        if ($cohort instanceof \tool_mucohort\local\type\rules && $cohort->can_view()) {
            $url = new url('/admin/tool/mucohort/cohort_rules.php', ['id' => $cohort->get_id()]);
            $name = get_string('tab_rules_active', 'tool_mucohort');
            $this->add($name, $url, \navigation_node::TYPE_SETTING, null, 'cohort_rules');
            $url = new url('/admin/tool/mucohort/cohort_sync.php', ['id' => $cohort->get_id()]);
            $name = get_string('tab_sync', 'tool_mucohort');
            $this->add($name, $url, \navigation_node::TYPE_SETTING, null, 'cohort_sync');
            if ($cohort->get_rules_record()->pendingrulesjson !== null) {
                $url = new url('/admin/tool/mucohort/cohort_draft.php', ['id' => $cohort->get_id()]);
                $name = get_string('tab_rules_draft', 'tool_mucohort');
                $this->add($name, $url, \navigation_node::TYPE_SETTING, null, 'cohort_draft');
            }
        }

        // Exceptions are always the second last tab, right before members.
        if ($cohort instanceof \tool_mucohort\local\type\advanced && $cohort->can_view_members()) {
            $url = new url('/admin/tool/mucohort/cohort_exceptions.php', ['id' => $cohort->get_id()]);
            $name = get_string('tab_exceptions', 'tool_mucohort');
            $this->add($name, $url, \navigation_node::TYPE_SETTING, null, 'cohort_exceptions');
        }

        // Members are always the last tab, the same as users in programs.
        if ($cohort->can_view_members()) {
            $url = new url('/admin/tool/mucohort/cohort_members.php', ['id' => $cohort->get_id()]);
            $this->add(get_string('tab_members', 'tool_mucohort'), $url, \navigation_node::TYPE_SETTING, null, 'cohort_members');
        }

        $this->scan_for_active_node($this);
        $this->initialised = true;
    }
}
