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

use tool_mucohort\local\type\base;
use tool_mulib\local\sql;

/**
 * Comparison of cohort membership with other cohorts.
 *
 * Members of the other cohorts are combined (union) and compared with members of the target cohort:
 * missing users are only in the other cohorts, shared users are in both, extra users are only in the target.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class compare {
    /** @var string users in other cohorts only */
    public const string MODE_MISSING = 'missing';
    /** @var string users in both */
    public const string MODE_SHARED = 'shared';
    /** @var string users in target cohort only */
    public const string MODE_EXTRA = 'extra';

    /** @var int[] other cohort ids */
    private array $withids;

    /**
     * Constructor.
     *
     * @param base $cohort target cohort
     * @param int[] $withids other cohort ids
     */
    public function __construct(
        /** @var base target cohort */
        private readonly base $cohort,
        array $withids
    ) {
        $withids = array_unique(array_filter(array_map('intval', $withids)));
        $withids = array_values(array_diff($withids, [$cohort->get_id()]));
        sort($withids);
        $this->withids = $withids;
    }

    /**
     * Parse cohort ids from comma separated list.
     *
     * @param string $with
     * @return int[]
     */
    public static function parse_withids(string $with): array {
        $ids = [];
        foreach (explode(',', $with) as $id) {
            if (preg_match('/^[1-9][0-9]*$/D', trim($id))) {
                $ids[] = (int)trim($id);
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Returns other cohort ids.
     *
     * @return int[]
     */
    public function get_withids(): array {
        return $this->withids;
    }

    /**
     * Returns valid modes.
     *
     * @return array mode => name
     */
    public static function get_modes(): array {
        return [
            self::MODE_MISSING => get_string('compare_missing', 'tool_mucohort'),
            self::MODE_SHARED => get_string('compare_shared', 'tool_mucohort'),
            self::MODE_EXTRA => get_string('compare_extra', 'tool_mucohort'),
        ];
    }

    /**
     * SQL condition on user table alias "u" for users of given mode.
     *
     * @param string $mode
     * @return sql
     */
    public function get_mode_sql(string $mode): sql {
        global $DB;

        if (!$this->withids) {
            return new sql('1=2');
        }
        [$insql, $inparams] = $DB->get_in_or_equal($this->withids);
        $inother = new sql(
            "EXISTS (SELECT 'x' FROM {cohort_members} ocm WHERE ocm.userid = u.id AND ocm.cohortid $insql)",
            $inparams
        );
        $intarget = new sql(
            "EXISTS (SELECT 'x' FROM {cohort_members} tcm WHERE tcm.userid = u.id AND tcm.cohortid = ?)",
            [$this->cohort->get_id()]
        );
        $deleted = new sql('u.deleted = 0');

        if ($mode === self::MODE_MISSING) {
            return sql::join(' AND ', [$deleted, $inother, $intarget->wrap('NOT ', '')]);
        } else if ($mode === self::MODE_SHARED) {
            return sql::join(' AND ', [$deleted, $inother, $intarget]);
        } else if ($mode === self::MODE_EXTRA) {
            return sql::join(' AND ', [$intarget, $inother->wrap('NOT ', '')]);
        }
        throw new \core\exception\invalid_parameter_exception('Invalid compare mode: ' . $mode);
    }

    /**
     * Count users of given mode.
     *
     * @param string $mode
     * @param sql|null $extra extra condition on user table alias "u"
     * @return int
     */
    public function count(string $mode, ?sql $extra = null): int {
        global $DB;
        $where = $this->get_mode_sql($mode);
        if ($extra) {
            $where = sql::join(' AND ', [$where, $extra->wrap('(', ')')]);
        }
        $sql = $where->wrap("SELECT COUNT('x') FROM {user} u WHERE ", '');
        return $DB->count_records_sql($sql->sql, $sql->params);
    }

    /**
     * Returns numbers for adding of all missing users.
     *
     * @return array{missing: int, addable: int, capacity: int|null}
     */
    public function get_add_plan(): array {
        $capacity = null;
        if ($this->cohort instanceof type\curated) {
            $capacity = $this->cohort->get_remaining_capacity();
        }
        return [
            'missing' => $this->count(self::MODE_MISSING),
            'addable' => $this->count(self::MODE_MISSING, $this->cohort->get_new_member_sql()),
            'capacity' => $capacity,
        ];
    }

    /**
     * Returns numbers for removing of all extra users.
     *
     * @return array{extra: int, removable: int}
     */
    public function get_remove_plan(): array {
        return [
            'extra' => $this->count(self::MODE_EXTRA),
            'removable' => $this->count(self::MODE_EXTRA, $this->cohort->get_removable_member_sql()),
        ];
    }

    /**
     * Add all missing users that may be added.
     *
     * @return int number of added users
     */
    public function add_missing(): int {
        global $DB;

        $plan = $this->get_add_plan();
        if ($plan['capacity'] !== null && $plan['addable'] > $plan['capacity']) {
            throw new \core\exception\moodle_exception('error_maxmembers', 'tool_mucohort', '', $plan['capacity']);
        }
        $where = sql::join(' AND ', [$this->get_mode_sql(self::MODE_MISSING), $this->cohort->get_new_member_sql()->wrap('(', ')')]);
        $sql = $where->wrap('SELECT u.id FROM {user} u WHERE ', ' ORDER BY u.id ASC');
        $count = 0;
        $rs = $DB->get_recordset_sql($sql->sql, $sql->params);
        foreach ($rs as $user) {
            if (membership_writer::add_member($this->cohort->get_id(), (int)$user->id)) {
                $count++;
            }
        }
        $rs->close();
        return $count;
    }

    /**
     * Remove all extra users that may be removed.
     *
     * @return int number of removed users
     */
    public function remove_extra(): int {
        global $DB;

        $removable = $this->cohort->get_removable_member_sql()->wrap('(', ')');
        $where = sql::join(' AND ', [$this->get_mode_sql(self::MODE_EXTRA), $removable]);
        $sql = $where->wrap('SELECT u.id FROM {user} u WHERE ', ' ORDER BY u.id ASC');
        $count = 0;
        $rs = $DB->get_recordset_sql($sql->sql, $sql->params);
        foreach ($rs as $user) {
            if (membership_writer::remove_member($this->cohort->get_id(), (int)$user->id)) {
                $count++;
            }
        }
        $rs->close();
        return $count;
    }
}
