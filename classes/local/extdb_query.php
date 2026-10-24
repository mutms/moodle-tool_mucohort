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

use stdClass;

/**
 * External database query used in rules of rule-based cohorts.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class extdb_query extends \tool_mulib\local\extdb\query {
    /** @var string[] supported user identifier columns in priority order */
    public const array IDENTIFIERS = ['userid', 'username', 'useremail', 'useridnumber'];

    /**
     * Create query instance and connect external database.
     *
     * @param int $queryid
     * @param stdClass $cohort cohort record
     */
    public function __construct(int $queryid, stdClass $cohort) {
        parent::__construct($queryid);
        $this->parameters['cohortid'] = $cohort->id;
        $this->parameters['cohortidnumber'] = (string)$cohort->idnumber;
    }

    #[\Override]
    public static function get_component(): string {
        return 'tool_mucohort';
    }

    #[\Override]
    public static function get_type(): string {
        return 'rule';
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('extdb_query_rule', 'tool_mucohort');
    }

    #[\Override]
    public static function get_check_parameters(): array {
        return [
            'cohortid' => 1,
            'cohortidnumber' => 'abcdef',
        ];
    }

    #[\Override]
    public static function get_query_help(): string {
        // Do NOT localise!

        return <<<'EOT'
Cohort rule queries may contain following named parameters to restrict rows to the relevant cohort:

* `:cohortid` - id of synchronised cohort
* `:cohortidnumber` - idnumber of synchronised cohort

Each returned row must identify one user by including one of columns:

* `userid` - id from user database table
* `username` - login username
* `useremail` - email address
* `useridnumber` - user idnumber

Rows that do not match exactly one user are ignored.
EOT;
    }

    #[\Override]
    public static function is_query_used(int $queryid): bool {
        return in_array($queryid, extdb_source::get_used_queryids(), true);
    }

    /**
     * Query type registration callback.
     *
     * @param \tool_mulib\hook\extdb_query_classes $hook
     */
    public static function callback_extdb_query_classes(\tool_mulib\hook\extdb_query_classes $hook): void {
        $hook->register(self::get_component(), self::get_type(), self::class);
    }
}
