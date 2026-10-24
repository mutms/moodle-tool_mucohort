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

namespace tool_mucohort\reportbuilder\local\systemreports;

use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\helpers\user_profile_fields;
use core_reportbuilder\system_report;
use tool_mucohort\local\compare as compare_util;
use tool_mucohort\local\type\base;
use lang_string;

/**
 * Users found by comparison of cohort membership with other cohorts.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class compare extends system_report {
    /** @var user */
    protected $userentity;
    /** @var base target cohort */
    protected base $cohort;
    /** @var string compare mode */
    protected string $mode;
    /** @var string other cohort ids */
    protected string $with;

    #[\Override]
    protected function initialise(): void {
        $params = $this->get_parameters();
        $cohort = base::fetch((int)$params['cohortid']);
        if ($cohort->get_context()->id != $this->get_context()->id) {
            throw new \core\exception\invalid_parameter_exception('Invalid cohort');
        }
        $withids = compare_util::parse_withids((string)$params['with']);
        foreach ($withids as $withid) {
            $other = base::fetch($withid, IGNORE_MISSING);
            if (!$other || !$other->can_view_members()) {
                throw new \core\exception\invalid_parameter_exception('Invalid cohort');
            }
        }
        $compare = new compare_util($cohort, $withids);

        $this->userentity = new user();
        $useralias = $this->userentity->get_table_alias('user');
        $this->set_main_table('user', $useralias);
        $this->add_entity($this->userentity);

        $where = $this->convert_sql($compare->get_mode_sql((string)$params['mode']), $useralias);
        $this->add_base_condition_sql($where->sql, $where->params);

        $this->add_base_fields("{$useralias}.id");

        $this->cohort = $cohort;
        $this->mode = (string)$params['mode'];
        $this->with = implode(',', $compare->get_withids());
        $this->add_actions();

        $this->add_column_from_entity('user:fullnamewithpicturelink');
        foreach ($this->userentity->get_identity_columns($this->get_context()) as $identitycolumn) {
            $this->add_column($identitycolumn);
        }
        $this->add_column_from_entity('user:suspended');
        $this->set_initial_sort_column('user:fullnamewithpicturelink', SORT_ASC);

        $this->add_filters_from_entities(['user:fullname', 'user:email', 'user:idnumber', 'user:username', 'user:suspended']);
        $userprofilefields = new user_profile_fields($useralias . '.id', $this->userentity->get_entity_name());
        foreach ($userprofilefields->get_filters() as $filter) {
            $this->add_filter($filter);
        }

        $this->set_downloadable(true);
        $this->set_default_no_results_notice(new lang_string('error_nousers', 'tool_mucohort'));
    }

    /**
     * Convert SQL fragment using "u" user alias to report alias and parameter names.
     *
     * @param \tool_mulib\local\sql $sql
     * @param string $useralias
     * @return \tool_mulib\local\sql
     */
    private function convert_sql(\tool_mulib\local\sql $sql, string $useralias): \tool_mulib\local\sql {
        $converted = preg_replace('/\bu\./', $useralias . '.', $sql->sql);
        return (new \tool_mulib\local\sql($converted, $sql->params))->rename_params(fn() => database::generate_param_name());
    }

    /**
     * Add row actions for missing and extra users.
     */
    private function add_actions(): void {
        if (\tool_mulib\local\reportbuilder_util::is_downloading()) {
            return;
        }
        if (!$this->cohort->can_manage_members()) {
            return;
        }
        $cohort = $this->cohort;
        $params = ['id' => $cohort->get_id(), 'with' => $this->with, 'userid' => ':id'];
        if ($this->mode === compare_util::MODE_MISSING && $cohort->get_member_add_form_class()) {
            $url = new \core\url('/admin/tool/mucohort/management/compare_member.php', $params + ['action' => 'add']);
            $name = get_string('compare_addmember', 'tool_mucohort');
            $link = new \tool_mulib\output\muform\dialog\link($url, $name, 'i/enrolusers');
            $link->set_form_size('sm');
            $callback = static fn(\stdClass $row): bool => self::is_user_allowed($cohort->get_new_member_sql(), (int)$row->id);
            $this->add_action($link->create_report_action()->add_callback($callback));
        } else if ($this->mode === compare_util::MODE_EXTRA && $cohort->get_member_remove_form_class()) {
            $url = new \core\url('/admin/tool/mucohort/management/compare_member.php', $params + ['action' => 'remove']);
            $name = get_string('compare_removemember', 'tool_mucohort');
            $link = new \tool_mulib\output\muform\dialog\link($url, $name, 'i/delete');
            $link->set_form_size('sm');
            $callback = static function (\stdClass $row) use ($cohort): bool {
                return self::is_user_allowed($cohort->get_removable_member_sql(), (int)$row->id);
            };
            $this->add_action($link->create_report_action(['class' => 'text-danger'])->add_callback($callback));
        }
    }

    /**
     * Does the user match the condition?
     *
     * @param \tool_mulib\local\sql $condition condition on user table alias "u"
     * @param int $userid
     * @return bool
     */
    public static function is_user_allowed(\tool_mulib\local\sql $condition, int $userid): bool {
        global $DB;
        $sql = (new \tool_mulib\local\sql("SELECT 'x' FROM {user} u WHERE u.id = ? /* condition */", [$userid]))
            ->replace_comment('condition', $condition->wrap('AND (', ')'));
        return $DB->record_exists_sql($sql->sql, $sql->params);
    }

    #[\Override]
    protected function can_view(): bool {
        $cohort = base::fetch((int)$this->get_parameters()['cohortid'], IGNORE_MISSING);
        return $cohort && $cohort->can_view_members();
    }
}
