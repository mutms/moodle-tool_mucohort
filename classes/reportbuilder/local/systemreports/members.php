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

use core_cohort\reportbuilder\local\entities\cohort_member;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\helpers\user_profile_fields;
use core_reportbuilder\system_report;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\local\filters\boolean_select;
use tool_mucohort\local\type\base;
use core\url;
use lang_string;
use stdClass;

/**
 * Cohort members.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class members extends system_report {
    /** @var stdClass */
    protected $cohort;
    /** @var user */
    protected $userentity;

    #[\Override]
    protected function initialise(): void {
        global $DB;

        // Make sure cohort and context match.
        $this->cohort = $DB->get_record('cohort', ['id' => $this->get_parameters()['cohortid']], '*', MUST_EXIST);
        if (base::from_record($this->cohort)->get_context()->id != $this->get_context()->id) {
            throw new \core\exception\invalid_parameter_exception('Cohort context mismatch');
        }

        $memberentity = new cohort_member();
        $memberalias = $memberentity->get_table_alias('cohort_members');
        $this->set_main_table('cohort_members', $memberalias);
        $this->add_entity($memberentity);

        $param = database::generate_param_name();
        $this->add_base_condition_sql("{$memberalias}.cohortid = :$param", [$param => $this->cohort->id]);

        $this->userentity = new user();
        $useralias = $this->userentity->get_table_alias('user');
        $this->add_entity($this->userentity);
        $this->add_join("JOIN {user} {$useralias} ON {$useralias}.id = {$memberalias}.userid");

        $mucohort = base::from_record($this->cohort)->get_mucohort();
        if ($mucohort) {
            $this->add_join("LEFT JOIN {tool_mucohort_exception} mce
                                ON mce.mucohortid = {$mucohort->id} AND mce.userid = {$memberalias}.userid
                                   AND mce.type = " . \tool_mucohort\local\exception::TYPE_FORCED);
            $this->add_base_fields("{$memberalias}.id, {$memberalias}.cohortid, {$memberalias}.userid, mce.id AS forcedid");
        } else {
            $this->add_base_fields("{$memberalias}.id, {$memberalias}.cohortid, {$memberalias}.userid, NULL AS forcedid");
        }

        $this->add_columns();
        $this->add_filters();
        $this->add_actions();

        $this->set_downloadable(true);
        $this->set_default_no_results_notice(new lang_string('error_nomembers', 'tool_mucohort'));
    }

    #[\Override]
    protected function can_view(): bool {
        $cohort = base::fetch((int)$this->get_parameters()['cohortid'], IGNORE_MISSING);
        return $cohort && $cohort->can_view_members();
    }

    /**
     * Adds the columns we want to display in the report.
     */
    protected function add_columns(): void {
        $this->add_column_from_entity('user:fullnamewithpicturelink');

        $identitycolumns = $this->userentity->get_identity_columns($this->get_context());
        foreach ($identitycolumns as $identitycolumn) {
            $this->add_column($identitycolumn);
        }

        $this->add_column_from_entity('user:suspended');

        if (base::from_record($this->cohort)->get_mucohort()) {
            $entityname = $this->userentity->get_entity_name();
            $this->add_column((new column('forced', new lang_string('member_forced', 'tool_mucohort'), $entityname))
                ->set_type(column::TYPE_BOOLEAN)
                ->add_field('CASE WHEN mce.id IS NULL THEN 0 ELSE 1 END', 'forced')
                ->set_is_sortable(true)
                ->add_callback(static function (?bool $value): string {
                    if (!$value) {
                        return get_string('no');
                    }
                    $text = get_string('exception_forced', 'tool_mucohort');
                    return \tool_mulib\local\reportbuilder_util::format_badge($text, 'text-bg-success');
                }));
        }

        $this->add_column_from_entity('cohort_member:timeadded');

        $this->set_initial_sort_column('user:fullnamewithpicturelink', SORT_ASC);
    }

    /**
     * Adds the filters we want to display in the report.
     */
    protected function add_filters(): void {
        $filters = [
            'user:fullname',
            'user:firstname',
            'user:lastname',
            'user:email',
            'user:idnumber',
            'user:username',
            'user:suspended',
            'cohort_member:timeadded',
        ];
        $this->add_filters_from_entities($filters);

        if (base::from_record($this->cohort)->get_mucohort()) {
            $this->add_filter(new filter(
                boolean_select::class,
                'forced',
                new lang_string('member_forced', 'tool_mucohort'),
                $this->userentity->get_entity_name(),
                'CASE WHEN mce.id IS NULL THEN 0 ELSE 1 END'
            ));
        }

        $useralias = $this->userentity->get_table_alias('user');
        $userprofilefields = new user_profile_fields($useralias . '.id', $this->userentity->get_entity_name());
        foreach ($userprofilefields->get_filters() as $filter) {
            $this->add_filter($filter);
        }
    }

    /**
     * Add the system report actions.
     */
    protected function add_actions(): void {
        if (\tool_mulib\local\reportbuilder_util::is_downloading()) {
            return;
        }

        $cohort = base::from_record($this->cohort);
        if (!$cohort->get_member_remove_form_class() || !$cohort->can_manage_members()) {
            return;
        }

        $url = new url('/admin/tool/mucohort/management/member_remove.php', ['id' => ':id']);
        $link = new \tool_mulib\output\muform\dialog\link($url, get_string('member_remove', 'tool_mucohort'), 'i/delete');
        $link->set_form_size('sm');
        $this->add_action($link->create_report_action(['class' => 'text-danger'])
            ->add_callback(static function (\stdClass $row): bool {
                // Forced users cannot be removed, the exception must be removed first.
                return empty($row->forcedid);
            }));
    }
}
