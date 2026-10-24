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
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\system_report;
use tool_mucohort\local\type\advanced;
use tool_mucohort\local\type\base;
use core\url;
use lang_string;
use stdClass;

/**
 * Access records of an advanced cohort.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access extends system_report {
    /** @var advanced */
    protected $cohort;
    /** @var user */
    protected $userentity;

    #[\Override]
    protected function initialise(): void {
        $cohort = base::fetch((int)$this->get_parameters()['cohortid']);
        if (!($cohort instanceof advanced) || $cohort->get_context()->id != $this->get_context()->id) {
            throw new \core\exception\invalid_parameter_exception('Invalid cohort');
        }
        $this->cohort = $cohort;

        $this->set_main_table('tool_mucohort_access', 'a');
        $param = database::generate_param_name();
        $this->add_base_condition_sql("a.mucohortid = :$param", [$param => $cohort->get_mucohort()->id]);

        $this->userentity = new user();
        $useralias = $this->userentity->get_table_alias('user');
        $this->add_entity($this->userentity);
        $this->add_join("JOIN {user} {$useralias} ON {$useralias}.id = a.userid");

        $this->add_base_fields("a.id, a.userid");

        $this->add_columns();
        $this->add_filters();
        $this->add_actions();

        $this->set_downloadable(true);
        $this->set_default_no_results_notice(new lang_string('error_noaccess', 'tool_mucohort'));
    }

    #[\Override]
    protected function can_view(): bool {
        $cohort = base::fetch((int)$this->get_parameters()['cohortid'], IGNORE_MISSING);
        return $cohort && $cohort->can_view();
    }

    /**
     * Adds the columns we want to display in the report.
     */
    protected function add_columns(): void {
        $entityname = $this->userentity->get_entity_name();

        $this->add_column_from_entity('user:fullnamewithpicturelink');

        $yesno = static function (?bool $value): string {
            return $value ? get_string('yes') : get_string('no');
        };

        $this->add_column((new column('iscurator', new lang_string('curator', 'tool_mucohort'), $entityname))
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field('a.iscurator')
            ->set_is_sortable(true)
            ->add_callback($yesno));

        foreach ($this->cohort->get_access_flags() as $flag => $name) {
            $this->add_column((new column($flag, new lang_string('access_' . $flag, 'tool_mucohort'), $entityname))
                ->set_type(column::TYPE_BOOLEAN)
                ->add_field("a.$flag")
                ->set_is_sortable(true)
                ->add_callback($yesno));
        }

        $this->add_column((new column('timemodified', new lang_string('timemodified', 'core_reportbuilder'), $entityname))
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field('a.timemodified')
            ->set_is_sortable(true)
            ->add_callback([\core_reportbuilder\local\helpers\format::class, 'userdate']));

        $this->set_initial_sort_column('user:fullnamewithpicturelink', SORT_ASC);
    }

    /**
     * Adds the filters we want to display in the report.
     */
    protected function add_filters(): void {
        $entityname = $this->userentity->get_entity_name();

        $this->add_filters_from_entities(['user:fullname', 'user:email', 'user:idnumber', 'user:username']);

        $this->add_filter(new filter(
            boolean_select::class,
            'iscurator',
            new lang_string('curator', 'tool_mucohort'),
            $entityname,
            'COALESCE(a.iscurator, 0)'
        ));
        foreach (array_keys($this->cohort->get_access_flags()) as $flag) {
            $this->add_filter(new filter(
                boolean_select::class,
                $flag,
                new lang_string('access_' . $flag, 'tool_mucohort'),
                $entityname,
                "a.$flag"
            ));
        }
        $this->add_filters_from_entities(['user:suspended']);
    }

    /**
     * Add the system report actions.
     */
    protected function add_actions(): void {
        if (\tool_mulib\local\reportbuilder_util::is_downloading()) {
            return;
        }
        if (!$this->cohort->can_manage_access()) {
            return;
        }

        $url = new url('/admin/tool/mucohort/management/access_edit.php', ['id' => ':id']);
        $link = new \tool_mulib\output\muform\dialog\link($url, get_string('access_update', 'tool_mucohort'), 'i/settings');
        $this->add_action($link->create_report_action());

        $url = new url('/admin/tool/mucohort/management/access_remove.php', ['id' => ':id']);
        $link = new \tool_mulib\output\muform\dialog\link($url, get_string('access_remove', 'tool_mucohort'), 'i/delete');
        $link->set_form_size('sm');
        $this->add_action($link->create_report_action(['class' => 'text-danger']));
    }
}
