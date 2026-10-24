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

namespace tool_mucohort\local;

/**
 * Rules cannot be evaluated, for example because a referenced cohort was deleted.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class broken_rules_exception extends \core\exception\moodle_exception {
    /**
     * Constructor.
     *
     * @param string $errorcode tool_mucohort lang string identifier
     * @param mixed $a lang string parameter
     */
    public function __construct(string $errorcode, mixed $a = null) {
        parent::__construct($errorcode, 'tool_mucohort', '', $a);
    }
}
