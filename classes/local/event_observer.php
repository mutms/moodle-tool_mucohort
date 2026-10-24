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
 * Event observers.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class event_observer {
    /** @var string[] scripts for manual creation of single user accounts */
    public const array USER_CREATE_SCRIPTS = [
        '/user/editadvanced.php',
        '/admin/tool/mutenancy/management/member_create.php',
    ];

    /**
     * User deleted - remove all data of the user, no userid of a deleted user is kept anywhere.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;

        $userid = $event->objectid;

        $DB->delete_records('tool_mucohort_access', ['userid' => $userid]);
        $DB->delete_records('tool_mucohort_exception', ['userid' => $userid]);
        $DB->delete_records('tool_mucohort_profilefield', ['userid' => $userid]);
        $DB->set_field('tool_mucohort_extdb_user', 'userid', null, ['userid' => $userid]);

        $DB->set_field('tool_mucohort_access', 'usermodified', null, ['usermodified' => $userid]);
        $DB->set_field('tool_mucohort_exception', 'usermodified', null, ['usermodified' => $userid]);
        $DB->set_field('tool_mucohort_role', 'usercreated', null, ['usercreated' => $userid]);
        $DB->set_field('tool_mucohort_extdb', 'usercreated', null, ['usercreated' => $userid]);
    }

    /**
     * Cohort deleted - remove all data of advanced cohort if any.
     *
     * Advanced cohorts are deleted via the cohort type API normally,
     * this cleans up after cohorts deleted in any other way.
     *
     * @param \core\event\cohort_deleted $event
     */
    public static function cohort_deleted(\core\event\cohort_deleted $event): void {
        global $DB;

        $mucohortid = $DB->get_field('tool_mucohort', 'id', ['cohortid' => $event->objectid]);
        if ($mucohortid) {
            type\base::delete_mucohort_data((int)$mucohortid);
        }
    }

    /**
     * User logged in - update membership in rule-based cohorts with instant sync on login.
     *
     * @param \core\event\user_loggedin $event
     */
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        global $DB;

        $userid = (int)$event->objectid;
        $flags = [];
        if (get_config('tool_mucohort', 'allowsynconlogin')) {
            $flags[] = 'synconlogin';
        }
        // Login times are updated before the event, previous login is in lastlogin.
        if ((int)$DB->get_field('user', 'lastlogin', ['id' => $userid]) === 0) {
            $flags[] = 'synconfirstlogin';
        }
        sync::sync_user_instantly($userid, $flags);
    }

    /**
     * User created - update membership in rule-based cohorts with instant sync on user creation.
     *
     * Only manual creation of single accounts is supported, bulk operations are left to scheduled syncs.
     *
     * @param \core\event\user_created $event
     */
    public static function user_created(\core\event\user_created $event): void {
        global $SCRIPT;

        // CLI scripts, web services and other pages do not match.
        if (!in_array($SCRIPT, self::USER_CREATE_SCRIPTS, true)) {
            return;
        }
        sync::sync_user_instantly((int)$event->objectid, ['synconusercreated']);
    }

    /**
     * Profile field deleted - remove cached values, rules using the field become broken.
     *
     * @param \core\event\user_info_field_deleted $event
     */
    public static function user_info_field_deleted(\core\event\user_info_field_deleted $event): void {
        profilefield_cache::remove_field((int)$event->objectid);
    }

    /**
     * Cohort member added - assign roles of the cohort.
     *
     * @param \core\event\cohort_member_added $event
     */
    public static function cohort_member_added(\core\event\cohort_member_added $event): void {
        role_sync::update_user((int)$event->objectid, (int)$event->relateduserid, true);
    }

    /**
     * Cohort member removed - unassign roles of the cohort.
     *
     * @param \core\event\cohort_member_removed $event
     */
    public static function cohort_member_removed(\core\event\cohort_member_removed $event): void {
        role_sync::update_user((int)$event->objectid, (int)$event->relateduserid, false);
    }
}
