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

namespace tool_mucohort\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use tool_mucohort\local\exception;

/**
 * Cohort management privacy provider.
 *
 * Cohort membership itself is handled by the core cohort privacy provider,
 * synchronised role assignments by the core role privacy provider.
 * Blocked exceptions are kept on deletion requests because they protect the user
 * from being added again, only their reason is removed.
 * External database snapshot rows of the user are deleted, they are fetched again
 * if the external database still returns the user.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('tool_mucohort_access', [
            'userid' => 'privacy:metadata:field:userid',
            'iscurator' => 'privacy:metadata:field:iscurator',
            'viewmembers' => 'privacy:metadata:field:viewmembers',
            'managemembers' => 'privacy:metadata:field:managemembers',
            'manageexceptions' => 'privacy:metadata:field:manageexceptions',
            'timecreated' => 'privacy:metadata:field:timecreated',
            'timemodified' => 'privacy:metadata:field:timemodified',
            'usermodified' => 'privacy:metadata:field:usermodified',
        ], 'privacy:metadata:table:tool_mucohort_access');

        $collection->add_database_table('tool_mucohort_exception', [
            'userid' => 'privacy:metadata:field:userid',
            'type' => 'privacy:metadata:field:type',
            'reason' => 'privacy:metadata:field:reason',
            'timecreated' => 'privacy:metadata:field:timecreated',
            'timemodified' => 'privacy:metadata:field:timemodified',
            'usermodified' => 'privacy:metadata:field:usermodified',
        ], 'privacy:metadata:table:tool_mucohort_exception');

        $collection->add_database_table('tool_mucohort_extdb_user', [
            'useridentifier' => 'privacy:metadata:field:useridentifier',
            'userid' => 'privacy:metadata:field:userid',
        ], 'privacy:metadata:table:tool_mucohort_extdb_user');

        $collection->add_database_table('tool_mucohort_role', [
            'usercreated' => 'privacy:metadata:field:usercreated',
        ], 'privacy:metadata:table:tool_mucohort_role');

        $collection->add_database_table('tool_mucohort_extdb', [
            'usercreated' => 'privacy:metadata:field:usercreated',
        ], 'privacy:metadata:table:tool_mucohort_extdb');

        $collection->add_database_table('tool_mucohort_profilefield', [
            'userid' => 'privacy:metadata:field:userid',
            'charvalue' => 'privacy:metadata:field:charvalue',
            'intvalue' => 'privacy:metadata:field:intvalue',
        ], 'privacy:metadata:table:tool_mucohort_profilefield');

        $collection->add_subsystem_link('core_cohort', [], 'privacy:metadata:core_cohort');
        $collection->add_subsystem_link('core_role', [], 'privacy:metadata:core_role');

        return $collection;
    }

    /**
     * SQL fragment returning cohort context ids with user data.
     *
     * @return string
     */
    private static function get_cohort_contexts_sql(): string {
        return "SELECT c.contextid
                  FROM {cohort} c
                  JOIN {tool_mucohort} mc ON mc.cohortid = c.id
                 WHERE EXISTS (SELECT 'x'
                                 FROM {tool_mucohort_access} a
                                WHERE a.mucohortid = mc.id AND (a.userid = :userid1 OR a.usermodified = :userid2))
                       OR EXISTS (SELECT 'x'
                                    FROM {tool_mucohort_exception} e
                                   WHERE e.mucohortid = mc.id AND (e.userid = :userid3 OR e.usermodified = :userid4))
                       OR EXISTS (SELECT 'x'
                                    FROM {tool_mucohort_extdb} x
                                    JOIN {tool_mucohort_extdb_user} xu ON xu.extdbid = x.id
                                   WHERE x.mucohortid = mc.id AND xu.userid = :userid5)
                       OR EXISTS (SELECT 'x' FROM {tool_mucohort_extdb} x WHERE x.mucohortid = mc.id AND x.usercreated = :userid6)
                       OR EXISTS (SELECT 'x' FROM {tool_mucohort_role} r WHERE r.mucohortid = mc.id AND r.usercreated = :userid7)";
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $params = [];
        for ($i = 1; $i <= 7; $i++) {
            $params['userid' . $i] = $userid;
        }
        $contextlist->add_from_sql(self::get_cohort_contexts_sql(), $params);

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {tool_mucohort_profilefield} pf ON pf.userid = ctx.instanceid AND ctx.contextlevel = :userlevel
                 WHERE pf.userid = :userid";
        $contextlist->add_from_sql($sql, ['userlevel' => CONTEXT_USER, 'userid' => $userid]);

        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if ($context->contextlevel == CONTEXT_USER) {
            $sql = "SELECT userid FROM {tool_mucohort_profilefield} WHERE userid = :userid";
            $userlist->add_from_sql('userid', $sql, ['userid' => $context->instanceid]);
            return;
        }
        if ($context->contextlevel != CONTEXT_SYSTEM && $context->contextlevel != CONTEXT_COURSECAT) {
            return;
        }

        $params = ['contextid' => $context->id];
        $fields = [
            'tool_mucohort_access' => ['userid', 'usermodified'],
            'tool_mucohort_exception' => ['userid', 'usermodified'],
            'tool_mucohort_extdb' => ['usercreated'],
            'tool_mucohort_role' => ['usercreated'],
        ];
        foreach ($fields as $table => $columns) {
            foreach ($columns as $column) {
                $sql = "SELECT t.$column
                          FROM {{$table}} t
                          JOIN {tool_mucohort} mc ON mc.id = t.mucohortid
                          JOIN {cohort} c ON c.id = mc.cohortid
                         WHERE c.contextid = :contextid AND t.$column IS NOT NULL";
                $userlist->add_from_sql($column, $sql, $params);
            }
        }
        $sql = "SELECT xu.userid
                  FROM {tool_mucohort_extdb_user} xu
                  JOIN {tool_mucohort_extdb} x ON x.id = xu.extdbid
                  JOIN {tool_mucohort} mc ON mc.id = x.mucohortid
                  JOIN {cohort} c ON c.id = mc.cohortid
                 WHERE c.contextid = :contextid AND xu.userid IS NOT NULL";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        $types = exception::get_types();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER) {
                $values = $DB->get_records('tool_mucohort_profilefield', ['userid' => $userid], 'fieldid ASC');
                if ($values) {
                    $data = array_map(fn($v) => [
                        'fieldid' => $v->fieldid,
                        'charvalue' => $v->charvalue,
                        'intvalue' => $v->intvalue,
                    ], array_values($values));
                    writer::with_context($context)->export_data(
                        [get_string('privacy:profilefields', 'tool_mucohort')],
                        (object)['profilefields' => $data]
                    );
                }
                continue;
            }

            $cohorts = $DB->get_records_sql(
                "SELECT c.id, c.name, mc.id AS mucohortid
                   FROM {cohort} c
                   JOIN {tool_mucohort} mc ON mc.cohortid = c.id
                  WHERE c.contextid = :contextid
               ORDER BY c.id ASC",
                ['contextid' => $context->id]
            );
            foreach ($cohorts as $cohort) {
                $data = [];
                $access = $DB->get_record('tool_mucohort_access', ['mucohortid' => $cohort->mucohortid, 'userid' => $userid]);
                if ($access) {
                    $data['access'] = [
                        'curator' => transform::yesno((bool)$access->iscurator),
                        'viewmembers' => transform::yesno((bool)$access->viewmembers),
                        'managemembers' => transform::yesno((bool)$access->managemembers),
                        'manageexceptions' => transform::yesno((bool)$access->manageexceptions),
                        'timecreated' => transform::datetime($access->timecreated),
                        'timemodified' => transform::datetime($access->timemodified),
                    ];
                }
                $exc = $DB->get_record('tool_mucohort_exception', ['mucohortid' => $cohort->mucohortid, 'userid' => $userid]);
                if ($exc) {
                    $data['exception'] = [
                        'type' => $types[$exc->type] ?? $exc->type,
                        'reason' => $exc->reason,
                        'timecreated' => transform::datetime($exc->timecreated),
                        'timemodified' => transform::datetime($exc->timemodified),
                    ];
                }
                $identifiers = $DB->get_fieldset_sql(
                    "SELECT xu.useridentifier
                       FROM {tool_mucohort_extdb_user} xu
                       JOIN {tool_mucohort_extdb} x ON x.id = xu.extdbid
                      WHERE x.mucohortid = :mucohortid AND xu.userid = :userid",
                    ['mucohortid' => $cohort->mucohortid, 'userid' => $userid]
                );
                if ($identifiers) {
                    $data['externaldatabase'] = $identifiers;
                }
                if (!$data) {
                    continue;
                }
                $data['cohort'] = format_string($cohort->name, true, ['context' => $context]);
                writer::with_context($context)->export_data(
                    [get_string('privacy:cohort', 'tool_mucohort', $cohort->id)],
                    (object)$data
                );
            }
        }
    }

    /**
     * Delete user data from advanced cohorts in a context.
     *
     * @param \context $context
     * @param array|null $userids null means all users
     */
    private static function delete_cohort_data(\context $context, ?array $userids): void {
        global $DB;

        $mucohortids = $DB->get_fieldset_sql(
            "SELECT mc.id
               FROM {tool_mucohort} mc
               JOIN {cohort} c ON c.id = mc.cohortid
              WHERE c.contextid = :contextid",
            ['contextid' => $context->id]
        );
        if (!$mucohortids || ($userids !== null && !$userids)) {
            return;
        }
        [$cohortsql, $params] = $DB->get_in_or_equal($mucohortids, SQL_PARAMS_NAMED, 'mc');
        $usersql = '';
        if ($userids !== null) {
            [$insql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
            $usersql = "AND userid $insql";
            $params += $userparams;
        }

        $DB->delete_records_select('tool_mucohort_access', "mucohortid $cohortsql $usersql", $params);
        $DB->delete_records_select(
            'tool_mucohort_exception',
            "mucohortid $cohortsql $usersql AND type = :forced",
            $params + ['forced' => exception::TYPE_FORCED]
        );
        // Blocked users stay blocked, only the reason is removed.
        $DB->set_field_select('tool_mucohort_exception', 'reason', null, "mucohortid $cohortsql $usersql", $params);
        $DB->delete_records_select(
            'tool_mucohort_extdb_user',
            "extdbid IN (SELECT id FROM {tool_mucohort_extdb} WHERE mucohortid $cohortsql) $usersql",
            $params
        );

        // References to users who made changes.
        $references = [
            'tool_mucohort_access' => 'usermodified',
            'tool_mucohort_exception' => 'usermodified',
            'tool_mucohort_extdb' => 'usercreated',
            'tool_mucohort_role' => 'usercreated',
        ];
        foreach ($references as $table => $column) {
            $select = "mucohortid $cohortsql AND $column IS NOT NULL";
            if ($userids !== null) {
                $select .= ' ' . str_replace('userid', $column, $usersql);
            }
            $DB->set_field_select($table, $column, null, $select, $params);
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel == CONTEXT_USER) {
            $DB->delete_records('tool_mucohort_profilefield', ['userid' => $context->instanceid]);
            return;
        }
        self::delete_cohort_data($context, null);
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER) {
                if ($context->instanceid == $userid) {
                    $DB->delete_records('tool_mucohort_profilefield', ['userid' => $userid]);
                }
                continue;
            }
            self::delete_cohort_data($context, [$userid]);
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if ($context->contextlevel == CONTEXT_USER) {
            if (in_array($context->instanceid, $userids)) {
                $DB->delete_records('tool_mucohort_profilefield', ['userid' => $context->instanceid]);
            }
            return;
        }
        self::delete_cohort_data($context, $userids);
    }
}
