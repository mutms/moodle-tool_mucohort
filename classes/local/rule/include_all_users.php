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

namespace tool_mucohort\local\rule;

use tool_mulib\local\sql;

/**
 * Include rule matching all users.
 *
 * Deleted, unconfirmed and guest users are never cohort members.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class include_all_users extends base {
    #[\Override]
    public static function get_kind(): string {
        return self::KIND_INCLUDE;
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('rule_allusers', 'tool_mucohort');
    }

    #[\Override]
    public static function get_type_description(): string {
        return get_string('rule_allusers_type', 'tool_mucohort');
    }

    #[\Override]
    public function get_description(array $config): string {
        return get_string('rule_allusers_desc', 'tool_mucohort');
    }

    #[\Override]
    public function get_sql(array $config, ?int $userid): sql {
        return new sql('1=1');
    }
}
