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

use tool_mucohort\local\type\advanced;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\curated;
use tool_mulib\local\import_util;
use tool_mulib\local\search_util;
use tool_mulib\local\sql;
use stdClass;

/**
 * Bulk changes of cohort members and exceptions from a pasted or uploaded user list.
 *
 * One list is processed at a time and the same action applies to all users in it.
 * Users are matched the same way as in the user pickers, restrictions of the cohort type
 * (blocked users, candidate cohort, tenants, member limit, forced users) apply.
 *
 * Wizard state keys: rows (parsed data), headers and column (where the identifiers are),
 * action, identifiertype and reason (what to do).
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bulk_changes {
    /** @var string add members */
    public const string ACTION_ADD = 'add';
    /** @var string remove members */
    public const string ACTION_REMOVE = 'remove';
    /** @var string add forced exceptions */
    public const string ACTION_FORCE = 'force';
    /** @var string add blocked exceptions */
    public const string ACTION_BLOCK = 'block';
    /** @var string remove forced and blocked exceptions */
    public const string ACTION_UNEXCEPT = 'unexcept';

    /** @var string user id */
    public const string IDENTIFIER_USERID = 'userid';
    /** @var string username */
    public const string IDENTIFIER_USERNAME = 'username';
    /** @var string email address */
    public const string IDENTIFIER_EMAIL = 'email';
    /** @var string user ID number */
    public const string IDENTIFIER_IDNUMBER = 'idnumber';

    /** @var string the action will be applied */
    public const string RESULT_OK = 'ok';
    /** @var string nothing to do, for example user is already a member */
    public const string RESULT_ALREADY = 'already';
    /** @var string cohort restrictions do not allow the action */
    public const string RESULT_NOTALLOWED = 'notallowed';
    /** @var string no user matches */
    public const string RESULT_NOTFOUND = 'notfound';
    /** @var string more users match */
    public const string RESULT_AMBIGUOUS = 'ambiguous';
    /** @var string user was matched by another value already */
    public const string RESULT_DUPLICATE = 'duplicate';

    /** @var int rows shown in the data preview of columns stage */
    public const int DATA_PREVIEW_ROWS = 5;
    /** @var int rows shown in the dry run */
    public const int PREVIEW_ROWS = 100;

    /** @var int number of values in one IN query */
    private const int CHUNK_SIZE = 1000;

    /** @var array normalised header => identifier type */
    private const array HEADERS = [
        'userid' => self::IDENTIFIER_USERID,
        'id' => self::IDENTIFIER_USERID,
        'username' => self::IDENTIFIER_USERNAME,
        'email' => self::IDENTIFIER_EMAIL,
        'emailaddress' => self::IDENTIFIER_EMAIL,
        'idnumber' => self::IDENTIFIER_IDNUMBER,
    ];

    /**
     * Returns actions the current user may use in the cohort.
     *
     * @param base $cohort
     * @return array action => name
     */
    public static function get_actions(base $cohort): array {
        $actions = [];
        if ($cohort->can_manage_members()) {
            if ($cohort->get_member_add_form_class()) {
                $actions[self::ACTION_ADD] = get_string('member_add', 'tool_mucohort');
            }
            if ($cohort->get_member_remove_form_class()) {
                $actions[self::ACTION_REMOVE] = get_string('bulkchanges_remove', 'tool_mucohort');
            }
        }
        if ($cohort instanceof advanced && $cohort->can_manage_exceptions()) {
            $actions[self::ACTION_FORCE] = get_string('bulkchanges_force', 'tool_mucohort');
            $actions[self::ACTION_BLOCK] = get_string('bulkchanges_block', 'tool_mucohort');
            $actions[self::ACTION_UNEXCEPT] = get_string('bulkchanges_unexcept', 'tool_mucohort');
        }
        return $actions;
    }

    /**
     * Returns action descriptions.
     *
     * @param base $cohort
     * @return array action => description
     */
    public static function get_action_descriptions(base $cohort): array {
        $add = get_string('bulkchanges_add_desc', 'tool_mucohort');
        $remove = get_string('bulkchanges_remove_desc', 'tool_mucohort');
        if ($cohort instanceof curated) {
            $add = get_string('bulkchanges_add_curated_desc', 'tool_mucohort');
            $remove = get_string('bulkchanges_remove_curated_desc', 'tool_mucohort');
        }
        return [
            self::ACTION_ADD => $add,
            self::ACTION_REMOVE => $remove,
            self::ACTION_FORCE => get_string('exception_forced_desc', 'tool_mucohort'),
            self::ACTION_BLOCK => get_string('exception_blocked_desc', 'tool_mucohort'),
            self::ACTION_UNEXCEPT => get_string('bulkchanges_unexcept_desc', 'tool_mucohort'),
        ];
    }

    /**
     * Returns identifier types usable by the current user.
     *
     * User fields that are not visible to the user cannot be used for matching,
     * the dry run would reveal who they belong to.
     *
     * @param base $cohort
     * @return array type => name
     */
    public static function get_identifier_types(base $cohort): array {
        $identity = \core_user\fields::get_identity_fields($cohort->get_context(), false);
        $types = [self::IDENTIFIER_USERID => get_string('bulkchanges_userid', 'tool_mucohort')];
        if (
            in_array('username', $identity, true)
            || has_capability('moodle/user:viewalldetails', \core\context\system::instance())
        ) {
            $types[self::IDENTIFIER_USERNAME] = get_string('username');
        }
        if (in_array('email', $identity, true)) {
            $types[self::IDENTIFIER_EMAIL] = get_string('email');
        }
        if (in_array('idnumber', $identity, true)) {
            $types[self::IDENTIFIER_IDNUMBER] = get_string('idnumber');
        }
        return $types;
    }

    /**
     * Is the first row a header row?
     *
     * @param array $firstrow
     * @return bool
     */
    public static function guess_headers(array $firstrow): bool {
        foreach ($firstrow as $value) {
            if (isset(self::HEADERS[import_util::normalise_header($value)])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Guess identifier column, the most specific identifier wins.
     *
     * @param array $firstrow
     * @return int column index
     */
    public static function guess_column(array $firstrow): int {
        $priority = [self::IDENTIFIER_USERID, self::IDENTIFIER_USERNAME, self::IDENTIFIER_EMAIL, self::IDENTIFIER_IDNUMBER];
        $found = [];
        foreach ($firstrow as $index => $value) {
            $type = self::HEADERS[import_util::normalise_header($value)] ?? null;
            if ($type !== null && !isset($found[$type])) {
                $found[$type] = $index;
            }
        }
        foreach ($priority as $type) {
            if (isset($found[$type])) {
                return $found[$type];
            }
        }
        return 0;
    }

    /**
     * Guess identifier type from header or values.
     *
     * @param array $state wizard state with valid rows, headers and column
     * @param array $types allowed identifier types
     * @return string
     */
    public static function guess_identifier_type(array $state, array $types): string {
        if ($state['headers']) {
            $header = $state['rows'][0][$state['column']];
            $type = self::HEADERS[import_util::normalise_header($header)] ?? null;
            if ($type !== null && isset($types[$type])) {
                return $type;
            }
        }
        $values = array_slice(self::get_identifiers($state), 0, 10);
        if ($values && isset($types[self::IDENTIFIER_EMAIL])) {
            if (count(array_filter($values, fn($v) => str_contains($v, '@'))) === count($values)) {
                return self::IDENTIFIER_EMAIL;
            }
        }
        if (isset($types[self::IDENTIFIER_USERNAME])) {
            return self::IDENTIFIER_USERNAME;
        }
        return array_key_first($types);
    }

    /**
     * Returns state for new parsed rows, a single column needs no column stage.
     *
     * @param array $rows parsed rows
     * @return array
     */
    public static function save_source(array $rows): array {
        $state = ['rows' => $rows];
        if (count($rows[0]) === 1) {
            $state['headers'] = self::guess_headers($rows[0]);
            $state['column'] = 0;
        }
        return $state;
    }

    /**
     * Is the source stage valid?
     *
     * @param array $state
     * @return bool
     */
    public static function is_source_valid(array $state): bool {
        $rows = $state['rows'] ?? null;
        if (!is_array($rows) || !$rows || !array_is_list($rows) || !is_array($rows[0]) || !$rows[0]) {
            return false;
        }
        $count = count($rows[0]);
        foreach ($rows as $row) {
            if (!is_array($row) || count($row) !== $count) {
                return false;
            }
        }
        return true;
    }

    /**
     * Is the columns stage valid?
     *
     * @param array $state
     * @return bool
     */
    public static function is_columns_valid(array $state): bool {
        if (!self::is_source_valid($state)) {
            return false;
        }
        if (!isset($state['headers']) || !is_bool($state['headers'])) {
            return false;
        }
        if (!isset($state['column']) || !is_int($state['column']) || !isset($state['rows'][0][$state['column']])) {
            return false;
        }
        return true;
    }

    /**
     * Is the options stage valid?
     *
     * @param base $cohort
     * @param array $state
     * @return bool
     */
    public static function is_options_valid(base $cohort, array $state): bool {
        if (!self::is_columns_valid($state)) {
            return false;
        }
        if (!isset($state['action']) || !isset(self::get_actions($cohort)[$state['action']])) {
            return false;
        }
        if (!isset($state['identifiertype']) || !isset(self::get_identifier_types($cohort)[$state['identifiertype']])) {
            return false;
        }
        return true;
    }

    /**
     * Returns unique non-empty identifiers in the order of the data.
     *
     * @param array $state wizard state with valid columns stage
     * @return string[]
     */
    public static function get_identifiers(array $state): array {
        $rows = $state['rows'];
        if ($state['headers']) {
            array_shift($rows);
        }
        $result = [];
        foreach ($rows as $row) {
            $value = trim((string)$row[$state['column']]);
            if ($value !== '') {
                $result[$value] = $value;
            }
        }
        return array_values($result);
    }

    /**
     * Dry run of the import.
     *
     * @param base $cohort
     * @param string[] $identifiers
     * @param string $identifiertype
     * @param string $action
     * @return stdClass with outcomes (list of identifier, userid, result), counts per result,
     *      userids of users to process and capacity (remaining member limit or null)
     */
    public static function check(base $cohort, array $identifiers, string $identifiertype, string $action): stdClass {
        $matches = self::match_users($cohort, $identifiers, $identifiertype);

        $outcomes = [];
        $seen = [];
        foreach ($identifiers as $identifier) {
            $userids = $matches[self::normalise_identifier($identifier, $identifiertype)] ?? [];
            $outcome = (object)['identifier' => $identifier, 'userid' => null, 'result' => self::RESULT_OK];
            if (!$userids) {
                $outcome->result = self::RESULT_NOTFOUND;
            } else if (count($userids) > 1) {
                $outcome->result = self::RESULT_AMBIGUOUS;
            } else {
                $outcome->userid = reset($userids);
                if (isset($seen[$outcome->userid])) {
                    $outcome->result = self::RESULT_DUPLICATE;
                }
                $seen[$outcome->userid] = true;
            }
            $outcomes[] = $outcome;
        }

        $results = self::get_user_results($cohort, array_keys($seen), $action);
        $userids = [];
        foreach ($outcomes as $outcome) {
            if ($outcome->result === self::RESULT_OK) {
                $outcome->result = $results[$outcome->userid];
                if ($outcome->result === self::RESULT_OK) {
                    $userids[] = $outcome->userid;
                }
            }
        }

        $counts = array_fill_keys(array_keys(self::get_result_names()), 0);
        foreach ($outcomes as $outcome) {
            $counts[$outcome->result]++;
        }

        $capacity = null;
        if ($action === self::ACTION_ADD && $cohort instanceof curated) {
            $capacity = $cohort->get_remaining_capacity();
        }

        return (object)[
            'outcomes' => $outcomes,
            'counts' => $counts,
            'userids' => $userids,
            'capacity' => $capacity,
        ];
    }

    /**
     * May the import run with the result of the dry run?
     *
     * @param stdClass $check result of check()
     * @return bool
     */
    public static function is_importable(stdClass $check): bool {
        if (!$check->userids) {
            return false;
        }
        return $check->capacity === null || count($check->userids) <= $check->capacity;
    }

    /**
     * Returns result names.
     *
     * @return array result => name
     */
    public static function get_result_names(): array {
        return [
            self::RESULT_OK => get_string('bulkchanges_result_ok', 'tool_mucohort'),
            self::RESULT_ALREADY => get_string('bulkchanges_result_already', 'tool_mucohort'),
            self::RESULT_NOTALLOWED => get_string('bulkchanges_result_notallowed', 'tool_mucohort'),
            self::RESULT_NOTFOUND => get_string('bulkchanges_result_notfound', 'tool_mucohort'),
            self::RESULT_AMBIGUOUS => get_string('bulkchanges_result_ambiguous', 'tool_mucohort'),
            self::RESULT_DUPLICATE => get_string('bulkchanges_result_duplicate', 'tool_mucohort'),
        ];
    }

    /**
     * Import users, the dry run is repeated with current data first.
     *
     * @param base $cohort
     * @param array $state valid wizard state
     * @return int number of processed users
     */
    public static function import(base $cohort, array $state): int {
        $identifiers = self::get_identifiers($state);
        $check = self::apply($cohort, $identifiers, $state['identifiertype'], $state['action'], $state['reason'] ?? null);
        return count($check->userids);
    }

    /**
     * Apply the action to users that are ready in a new dry run, permissions are not checked here.
     *
     * @param base $cohort
     * @param string[] $identifiers
     * @param string $identifiertype
     * @param string $action
     * @param string|null $reason reason of new exceptions
     * @return stdClass result of check(), userids were processed
     */
    public static function apply(
        base $cohort,
        array $identifiers,
        string $identifiertype,
        string $action,
        ?string $reason = null
    ): stdClass {
        $check = self::check($cohort, $identifiers, $identifiertype, $action);
        if ($check->capacity !== null && count($check->userids) > $check->capacity) {
            throw new \core\exception\moodle_exception('error_maxmembers', 'tool_mucohort', '', $check->capacity);
        }

        if ($action === self::ACTION_ADD) {
            foreach (array_chunk($check->userids, self::CHUNK_SIZE) as $chunk) {
                $cohort->add_members($chunk);
            }
        } else if ($action === self::ACTION_REMOVE) {
            foreach ($check->userids as $userid) {
                $cohort->remove_member($userid);
            }
        } else if ($action === self::ACTION_UNEXCEPT && $cohort instanceof advanced) {
            foreach ($check->userids as $userid) {
                exception::remove((int)exception::get_record($cohort, $userid)->id);
            }
        } else if ($cohort instanceof advanced) {
            $type = ($action === self::ACTION_FORCE) ? exception::TYPE_FORCED : exception::TYPE_BLOCKED;
            foreach ($check->userids as $userid) {
                exception::set($cohort, $userid, $type, $reason);
            }
        }

        return $check;
    }

    /**
     * Normalise identifier for comparison with database values.
     *
     * @param string $identifier
     * @param string $identifiertype
     * @return string
     */
    private static function normalise_identifier(string $identifier, string $identifiertype): string {
        if ($identifiertype === self::IDENTIFIER_USERNAME || $identifiertype === self::IDENTIFIER_EMAIL) {
            return \core_text::strtolower($identifier);
        }
        return $identifier;
    }

    /**
     * Find users by identifiers, the same users as in the user pickers are considered.
     *
     * @param base $cohort
     * @param string[] $identifiers
     * @param string $identifiertype
     * @return array normalised identifier => list of user ids
     */
    private static function match_users(base $cohort, array $identifiers, string $identifiertype): array {
        global $DB, $CFG;

        $values = [];
        foreach ($identifiers as $identifier) {
            if ($identifiertype === self::IDENTIFIER_USERID && !preg_match('/^[1-9][0-9]{0,9}$/D', $identifier)) {
                continue;
            }
            $value = self::normalise_identifier($identifier, $identifiertype);
            $values[$value] = $value;
        }

        $field = match ($identifiertype) {
            self::IDENTIFIER_USERID => 'u.id',
            self::IDENTIFIER_USERNAME => 'u.username',
            self::IDENTIFIER_EMAIL => 'LOWER(u.email)',
            self::IDENTIFIER_IDNUMBER => 'u.idnumber',
        };
        $where = [new sql('u.deleted = 0 AND u.confirmed = 1 AND u.id <> ?', [(int)$CFG->siteguest])];
        if ($identifiertype === self::IDENTIFIER_USERNAME) {
            $where[] = new sql('u.mnethostid = ?', [(int)$CFG->mnet_localhost_id]);
        }
        $tenantwhere = search_util::get_tenant_related_users_where('u.id', $cohort->get_context());
        if ($tenantwhere->sql !== '') {
            $where[] = $tenantwhere;
        }

        $result = [];
        foreach (array_chunk($values, self::CHUNK_SIZE) as $chunk) {
            [$insql, $inparams] = $DB->get_in_or_equal($chunk);
            $conditions = array_merge($where, [new sql("$field $insql", $inparams)]);
            $sql = sql::join(' AND ', $conditions)->wrap("SELECT u.id, $field AS identifier FROM {user} u WHERE ", '');
            $rs = $DB->get_recordset_sql($sql->sql, $sql->params);
            foreach ($rs as $user) {
                $result[(string)$user->identifier][] = (int)$user->id;
            }
            $rs->close();
        }
        return $result;
    }

    /**
     * Results of the action for matched users.
     *
     * @param base $cohort
     * @param int[] $userids
     * @param string $action
     * @return array userid => result
     */
    private static function get_user_results(base $cohort, array $userids, string $action): array {
        global $DB;

        $results = [];
        foreach (array_chunk($userids, self::CHUNK_SIZE) as $chunk) {
            [$insql, $inparams] = $DB->get_in_or_equal($chunk);
            $members = $DB->get_fieldset_sql(
                "SELECT userid FROM {cohort_members} WHERE cohortid = ? AND userid $insql",
                array_merge([$cohort->get_id()], $inparams)
            );
            $members = array_fill_keys($members, true);

            if ($action === self::ACTION_ADD || $action === self::ACTION_REMOVE) {
                $allowedsql = ($action === self::ACTION_ADD) ? $cohort->get_new_member_sql() : $cohort->get_removable_member_sql();
                $sql = (new sql("SELECT u.id FROM {user} u WHERE u.id $insql /* allowed */", $inparams))
                    ->replace_comment('allowed', $allowedsql->wrap('AND (', ')'));
                $allowed = array_fill_keys($DB->get_fieldset_sql($sql->sql, $sql->params), true);
                foreach ($chunk as $userid) {
                    $done = ($action === self::ACTION_ADD) ? isset($members[$userid]) : !isset($members[$userid]);
                    if ($done) {
                        $results[$userid] = self::RESULT_ALREADY;
                    } else if (!isset($allowed[$userid])) {
                        $results[$userid] = self::RESULT_NOTALLOWED;
                    } else {
                        $results[$userid] = self::RESULT_OK;
                    }
                }
            } else {
                $existing = $DB->get_records_sql_menu(
                    "SELECT userid, type FROM {tool_mucohort_exception} WHERE mucohortid = ? AND userid $insql",
                    array_merge([$cohort->get_mucohort()->id], $inparams)
                );
                if ($action === self::ACTION_UNEXCEPT) {
                    foreach ($chunk as $userid) {
                        $results[$userid] = isset($existing[$userid]) ? self::RESULT_OK : self::RESULT_ALREADY;
                    }
                } else {
                    $type = ($action === self::ACTION_FORCE) ? exception::TYPE_FORCED : exception::TYPE_BLOCKED;
                    foreach ($chunk as $userid) {
                        $done = isset($existing[$userid]) && (int)$existing[$userid] === $type;
                        $results[$userid] = $done ? self::RESULT_ALREADY : self::RESULT_OK;
                    }
                }
            }
        }
        return $results;
    }
}
