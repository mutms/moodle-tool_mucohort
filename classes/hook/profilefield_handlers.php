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

use tool_mucohort\local\profilefield\base;

/**
 * Hook for registering of custom profile field handlers used in cohort rules.
 *
 * Handlers for core profile field types are registered before dispatching,
 * plugins may add handlers for their own profile field types or remove the built-in ones.
 * Fields of types without handler cannot be used in cohort rules.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add handlers of custom profile field types for cohort rules.')]
#[\core\attribute\tags('tool_mucohort', 'user')]
final class profilefield_handlers {
    /** @var array datatype => handler class name */
    private array $classes = [];

    /**
     * Register handler of profile field type.
     *
     * @param string $datatype profile field type, such as "text" for profilefield_text
     * @param string $classname subclass of \tool_mucohort\local\profilefield\base
     */
    public function register(string $datatype, string $classname): void {
        if (!is_subclass_of($classname, base::class)) {
            throw new \core\exception\coding_exception('Invalid profile field handler class: ' . $classname);
        }
        $this->classes[$datatype] = $classname;
    }

    /**
     * Remove handler of profile field type.
     *
     * @param string $datatype
     */
    public function unregister(string $datatype): void {
        unset($this->classes[$datatype]);
    }

    /**
     * Returns registered handlers.
     *
     * @return array datatype => handler class name
     */
    public function get_classes(): array {
        return $this->classes;
    }
}
