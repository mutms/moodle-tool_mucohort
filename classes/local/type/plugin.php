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
 * Plugin cohort - cohort owned by another plugin, read-only in cohort management.
 *
 * Invalid advanced cohorts (component tool_mucohort without tool_mucohort record)
 * are treated as plugin cohorts too.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plugin extends base {
    /** @var string type identifier */
    public const string TYPE = 'plugin';

    #[\Override]
    public static function is_enabled(): bool {
        return false;
    }

    /**
     * Returns type name including the owner plugin name.
     *
     * @param string $component cohort component
     * @return string plain text
     */
    public static function get_component_type_name(string $component): string {
        if (\core_component::get_component_directory($component) === null) {
            return get_string('type_plugin_missing', 'tool_mucohort', $component);
        }
        $sm = get_string_manager();
        if ($sm->string_exists('pluginname', $component)) {
            $name = get_string('pluginname', $component);
        } else {
            $name = $component;
        }
        return get_string('type_plugin_name', 'tool_mucohort', $name);
    }

    #[\Override]
    public function get_type_name(): string {
        return self::get_component_type_name($this->cohort->component);
    }

    /**
     * Returns description provided by the owner plugin.
     *
     * @return \tool_mucohort\hook\cohort_owner
     */
    public function get_owner_hook(): \tool_mucohort\hook\cohort_owner {
        $hook = new \tool_mucohort\hook\cohort_owner($this->cohort);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        return $hook;
    }

    #[\Override]
    public function add_overview_details(\tool_mulib\output\entity_details $details): void {
        parent::add_overview_details($details);

        $hook = $this->get_owner_hook();
        $description = $hook->get_description();
        if ($description === null) {
            return;
        }
        $description = s($description);
        if ($hook->get_url()) {
            $description = \core\output\html_writer::link($hook->get_url(), $description);
        }
        $details->add(get_string('owner', 'tool_mucohort'), $description);
    }

    #[\Override]
    public function can_manage(): bool {
        return false;
    }

    #[\Override]
    public function can_move(): bool {
        return false;
    }

    #[\Override]
    public function can_view_members(): bool {
        return has_any_capability(['moodle/cohort:assign', 'tool/mucohort:viewmembers'], $this->get_context());
    }

    #[\Override]
    public function update(stdClass $data): void {
        throw new \core\exception\coding_exception('Cohorts of other plugins cannot be updated');
    }

    #[\Override]
    public function move(int $contextid): void {
        throw new \core\exception\coding_exception('Cohorts of other plugins cannot be moved');
    }

    #[\Override]
    public function delete(): void {
        throw new \core\exception\coding_exception('Cohorts of other plugins cannot be deleted');
    }
}
