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

use core\context;
use core\url;
use tool_mucohort\local\type\base;
use tool_mulib\local\context_map;
use tool_mulib\local\sql;

/**
 * Cohort management UI helper.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class management {
    /**
     * Can the current user see the cohort list in the context?
     *
     * @param context $context
     * @return bool
     */
    public static function can_view_index(context $context): bool {
        if (has_any_capability(['moodle/cohort:view', 'moodle/cohort:manage'], $context)) {
            return true;
        }
        // Users with access records may see the list of their advanced cohorts.
        return access::has_any_in_context($context);
    }

    /**
     * Returns SQL condition for cohorts the user may see in cohort management.
     *
     * @param int $userid
     * @param string $alias cohort table alias
     * @return sql boolean condition
     */
    public static function get_viewable_cohorts_sql(int $userid, string $alias): sql {
        if (is_siteadmin($userid)) {
            return new sql('1=1');
        }
        $levels = new sql("(ctx.contextlevel = ? OR ctx.contextlevel = ?)", [CONTEXT_SYSTEM, CONTEXT_COURSECAT]);
        $view = context_map::get_contexts_by_capability_query('moodle/cohort:view', $userid, $levels);
        $manage = context_map::get_contexts_by_capability_query('moodle/cohort:manage', $userid, $levels);
        // Users with access records may see their advanced cohorts.
        $access = new sql(
            "EXISTS (SELECT 'x'
                       FROM {tool_mucohort} amc
                       JOIN {tool_mucohort_access} aa ON aa.mucohortid = amc.id AND aa.userid = :accessuserid
                      WHERE amc.cohortid = {$alias}.id AND {$alias}.component = 'tool_mucohort')",
            ['accessuserid' => $userid]
        );
        return sql::join(' OR ', [
            $view->wrap("{$alias}.contextid IN (", ')'),
            $manage->wrap("{$alias}.contextid IN (", ')'),
            $access,
        ])->wrap('(', ')');
    }

    /**
     * Returns cohort list URL.
     *
     * @param context $context system or category context
     * @return url
     */
    public static function get_index_url(context $context): url {
        return new url('/admin/tool/mucohort/index.php', ['contextid' => $context->id]);
    }

    /**
     * Returns cohort page URL if the current user may view the cohort.
     *
     * @param \stdClass $cohort cohort record
     * @return url|null
     */
    public static function get_cohort_url(\stdClass $cohort): ?url {
        if (!base::from_record($cohort)->can_view()) {
            return null;
        }
        return new url('/admin/tool/mucohort/cohort.php', ['id' => $cohort->id]);
    }

    /**
     * Set up $PAGE for the cohort list.
     *
     * @param url $pageurl
     * @param context $context
     */
    public static function setup_index_page(url $pageurl, context $context): void {
        global $PAGE;

        $PAGE->set_pagelayout('admin');
        $PAGE->set_context($context);
        $PAGE->set_url($pageurl);
        $PAGE->set_title(get_string('management', 'tool_mucohort'));
        $PAGE->set_heading(get_string('cohorts', 'core_cohort'));
        $PAGE->set_secondary_navigation(false);

        $parentcontextids = array_reverse($context->get_parent_context_ids(true));
        foreach ($parentcontextids as $parentcontextid) {
            $parentcontext = context::instance_by_id($parentcontextid);
            if ($parentcontext instanceof \core\context\system) {
                $name = get_string('cohorts', 'core_cohort');
            } else {
                $name = $parentcontext->get_context_name(false);
            }
            $url = null;
            if (self::can_view_index($parentcontext)) {
                $url = self::get_index_url($parentcontext);
            }
            $PAGE->navbar->add($name, $url);
        }
    }

    /**
     * Set up $PAGE for one of cohort tabs.
     *
     * @param url $pageurl
     * @param base $cohort
     * @param string $secondarytab
     */
    public static function setup_cohort_page(url $pageurl, base $cohort, string $secondarytab): void {
        global $PAGE;

        $context = $cohort->get_context();

        $PAGE->set_pagelayout('admin');
        $PAGE->set_context($context);
        $PAGE->set_url($pageurl);

        $cohortname = $cohort->get_formatted_name();
        $PAGE->set_title($cohortname . \moodle_page::TITLE_SEPARATOR . get_string('cohorts', 'core_cohort'));
        $PAGE->set_heading($cohortname);

        $secondarynav = new \tool_mucohort\navigation\views\cohort_secondary($PAGE, $cohort);
        $PAGE->set_secondarynav($secondarynav);
        $PAGE->set_secondary_active_tab($secondarytab);
        $secondarynav->initialise();

        $parentcontextids = array_reverse($context->get_parent_context_ids(true));
        foreach ($parentcontextids as $parentcontextid) {
            $parentcontext = context::instance_by_id($parentcontextid);
            if ($parentcontext instanceof \core\context\system) {
                $name = get_string('cohorts', 'core_cohort');
            } else {
                $name = $parentcontext->get_context_name(false);
            }
            $url = null;
            if (self::can_view_index($parentcontext)) {
                $url = self::get_index_url($parentcontext);
            }
            $PAGE->navbar->add($name, $url);
        }

        $PAGE->navbar->add($cohortname, new url('/admin/tool/mucohort/cohort.php', ['id' => $cohort->get_id()]));
    }
}
