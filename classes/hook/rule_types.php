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

use stdClass;
use tool_mucohort\local\rule\base;

/**
 * Hook for registering of rule types available in a rule-based cohort.
 *
 * Built-in rule types are registered before dispatching, callbacks may add
 * more rule types or remove the built-in ones, for example depending
 * on the cohort context or tenant.
 *
 * Rule type identifiers have the format "component:type", the include
 * and exclude rule classes of one type share the identifier.
 *
 * Rules of types that are not available make the cohort rules broken,
 * the cohort is then not synchronised.
 *
 * NOTE: the cohort and mucohort records may have id === null when the cohort
 * is not saved in the database yet (clone wizard, future import), the intended
 * contextid, name and type are always known.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add or remove rule types of rule-based cohorts.')]
#[\core\attribute\tags('tool_mucohort', 'cohort')]
final class rule_types {
    /** @var array kind => [type => rule class name] */
    private array $classes = [
        base::KIND_INCLUDE => [],
        base::KIND_EXCLUDE => [],
    ];

    /**
     * Constructor.
     *
     * @param stdClass $cohort cohort record, id may be null
     * @param stdClass $mucohort tool_mucohort record, id may be null
     */
    public function __construct(
        /** @var stdClass cohort record */
        private readonly stdClass $cohort,
        /** @var stdClass tool_mucohort record */
        private readonly stdClass $mucohort
    ) {
    }

    /**
     * Returns cohort record.
     *
     * @return stdClass id may be null
     */
    public function get_cohort(): stdClass {
        return clone($this->cohort);
    }

    /**
     * Returns tool_mucohort record.
     *
     * @return stdClass id may be null
     */
    public function get_mucohort(): stdClass {
        return clone($this->mucohort);
    }

    /**
     * Register rule type, the kind is defined by the rule class.
     *
     * @param string $component frankenstyle name of the plugin that implements the rule type
     * @param string $type type name unique within the component
     * @param string $classname rule class name, subclass of \tool_mucohort\local\rule\base
     */
    public function register(string $component, string $type, string $classname): void {
        if (!preg_match('/^[a-z][a-z0-9_]*$/D', $component) || !preg_match('/^[a-z][a-z0-9_]*$/D', $type)) {
            throw new \core\exception\coding_exception('Invalid rule type name');
        }
        if (!is_subclass_of($classname, base::class)) {
            throw new \core\exception\coding_exception('Invalid rule class: ' . $classname);
        }
        if (!$classname::is_available()) {
            return;
        }
        $this->classes[$classname::get_kind()][$component . ':' . $type] = $classname;
    }

    /**
     * Remove registered rule type.
     *
     * @param string $component
     * @param string $type
     * @param string|null $kind null means both kinds
     */
    public function unregister(string $component, string $type, ?string $kind = null): void {
        foreach (array_keys($this->classes) as $k) {
            if ($kind === null || $kind === $k) {
                unset($this->classes[$k][$component . ':' . $type]);
            }
        }
    }

    /**
     * Returns rule class.
     *
     * @param string $kind
     * @param string $type full type identifier "component:type"
     * @return string|null class name, null if not available
     */
    public function get_class(string $kind, string $type): ?string {
        return $this->classes[$kind][$type] ?? null;
    }

    /**
     * Returns available rule classes of given kind.
     *
     * @param string $kind
     * @return array type => class name
     */
    public function get_classes(string $kind): array {
        return $this->classes[$kind] ?? [];
    }
}
