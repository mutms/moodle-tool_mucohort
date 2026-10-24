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

namespace tool_mucohort\callback;

use core\url;

/**
 * Core hook callbacks.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class core {
    /**
     * Redirect core cohort pages to the new cohort management if takeover is enabled.
     *
     * @param \core\hook\after_config $hook
     */
    public static function hook_after_config(\core\hook\after_config $hook): void {
        global $SCRIPT;

        if (CLI_SCRIPT || AJAX_SCRIPT || WS_SERVER) {
            return;
        }
        if (during_initial_install()) {
            return;
        }

        $url = self::get_takeover_url((string)$SCRIPT);
        if ($url) {
            redirect($url);
        }
    }

    /**
     * Returns new URL for legacy cohort pages when takeover is enabled.
     *
     * @param string $script
     * @return url|null
     */
    public static function get_takeover_url(string $script): ?url {
        if (!in_array($script, ['/cohort/index.php', '/cohort/edit.php', '/cohort/assign.php'], true)) {
            return null;
        }
        if (!get_config('tool_mucohort', 'takeover')) {
            return null;
        }

        $id = optional_param('id', 0, PARAM_INT);
        $contextid = optional_param('contextid', 0, PARAM_INT);

        $syscontextid = \core\context\system::instance()->id;
        if ($script === '/cohort/index.php') {
            return new url('/admin/tool/mucohort/index.php', ['contextid' => $contextid ?: $syscontextid]);
        }
        if ($script === '/cohort/edit.php') {
            if ($id) {
                return new url('/admin/tool/mucohort/cohort.php', ['id' => $id]);
            }
            if ($contextid) {
                return new url('/admin/tool/mucohort/management/cohort_create.php', ['contextid' => $contextid]);
            }
            return new url('/admin/tool/mucohort/index.php', ['contextid' => $syscontextid]);
        }
        if ($id) {
            return new url('/admin/tool/mucohort/cohort_members.php', ['id' => $id]);
        }
        return new url('/admin/tool/mucohort/index.php', ['contextid' => $syscontextid]);
    }
}
