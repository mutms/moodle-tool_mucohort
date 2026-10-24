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

use core_cohort\reportbuilder\local\entities\cohort as cohort_entity;
use core_reportbuilder\system_report;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\select;
use tool_mucohort\local\type;
use tool_mucohort\local\management;
use core\url;
use lang_string;
use stdClass;

/**
 * Cohorts in a context and its subcontexts.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cohorts extends system_report {
    /** @var int members filter value for cohorts without members */
    public const int MEMBERS_NONE = 1;
    /** @var int members filter value for cohorts with suspended members only */
    public const int MEMBERS_SUSPENDED = 2;
    /** @var int members filter value for cohorts with active members */
    public const int MEMBERS_ACTIVE = 3;

    /** @var cohort_entity */
    protected $cohortentity;
    /** @var string */
    protected $cohortalias;

    #[\Override]
    protected function initialise(): void {
        global $USER;

        $this->cohortentity = new cohort_entity();
        $this->cohortalias = $this->cohortentity->get_table_alias('cohort');
        $c = $this->cohortalias;

        $this->set_main_table('cohort', $c);
        $this->add_entity($this->cohortentity);

        // Actions need full cohort records.
        $this->add_base_fields("{$c}.*");
        $this->add_join("LEFT JOIN {tool_mucohort} mc ON mc.cohortid = {$c}.id");

        $context = $this->get_context();
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            // Only cohorts from the context and its subcontexts.
            $this->add_join("JOIN {tool_mulib_context_map} mcm
                                ON mcm.contextid = {$c}.contextid AND mcm.relatedcontextid = {$context->id}");
        }

        // Report builder accepts only its own parameter names.
        $where = management::get_viewable_cohorts_sql($USER->id, $c)->rename_params(fn() => database::generate_param_name());
        $this->add_base_condition_sql($where->sql, $where->params);

        $this->add_columns();
        $this->add_filters();
        $this->add_actions();

        $this->set_downloadable(true);
        $this->set_default_no_results_notice(new lang_string('error_nocohorts', 'tool_mucohort'));
    }

    #[\Override]
    protected function can_view(): bool {
        return \tool_mucohort\local\management::can_view_index($this->get_context());
    }

    /**
     * SQL expression with cohort type.
     *
     * @return string
     */
    protected function get_type_sql(): string {
        $c = $this->cohortalias;
        return "CASE
                  WHEN {$c}.component = '' THEN '" . type\standard::TYPE . "'
                  WHEN {$c}.component = 'tool_mucohort' AND mc.type IS NOT NULL THEN mc.type
                  ELSE '" . type\plugin::TYPE . "'
                END";
    }

    /**
     * SQL expression with cohort members state.
     *
     * @return string
     */
    protected function get_members_sql(): string {
        $c = $this->cohortalias;
        return "CASE
                  WHEN NOT EXISTS (SELECT 'x' FROM {cohort_members} cmn WHERE cmn.cohortid = {$c}.id)
                       THEN " . self::MEMBERS_NONE . "
                  WHEN NOT EXISTS (SELECT 'x'
                                     FROM {cohort_members} cma
                                     JOIN {user} ua ON ua.id = cma.userid AND ua.deleted = 0 AND ua.suspended = 0
                                    WHERE cma.cohortid = {$c}.id) THEN " . self::MEMBERS_SUSPENDED . "
                  ELSE " . self::MEMBERS_ACTIVE . "
                END";
    }

    /**
     * Adds the columns we want to display in the report.
     */
    protected function add_columns(): void {
        $c = $this->cohortalias;
        $entityname = $this->cohortentity->get_entity_name();

        $this->add_column((new column(
            'name',
            new lang_string('name', 'core_cohort'),
            $entityname
        ))
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$c}.name, {$c}.id, {$c}.contextid")
            ->set_is_sortable(true)
            ->add_callback(static function (?string $name, stdClass $row): string {
                if ($name === null) {
                    return '';
                }
                $context = \core\context::instance_by_id($row->contextid, IGNORE_MISSING);
                $name = format_string($name, true, ['context' => $context ?: \core\context\system::instance()]);
                $url = new url('/admin/tool/mucohort/cohort.php', ['id' => $row->id]);
                return \html_writer::link($url, $name);
            }));

        $this->add_column_from_entity('cohort:idnumber');

        if ($this->get_context()->contextlevel == CONTEXT_SYSTEM || $this->has_subcategories()) {
            $this->add_column_from_entity('cohort:context')
                ->add_callback(static function (string $value, stdClass $row): string {
                    if ($row->ctxid === null) {
                        return get_string('error_contextmissing', 'tool_mucohort');
                    }
                    $context = \core\context::instance_by_id($row->ctxid);
                    if (!\tool_mucohort\local\management::can_view_index($context)) {
                        return $value;
                    }
                    return \html_writer::link(\tool_mucohort\local\management::get_index_url($context), $value);
                });
        }

        $this->add_column((new column(
            'type',
            new lang_string('type', 'tool_mucohort'),
            $entityname
        ))
            ->set_type(column::TYPE_TEXT)
            ->add_field($this->get_type_sql(), 'type')
            ->add_field("{$c}.component", 'typecomponent')
            ->set_is_sortable(true)
            ->add_callback(static function (?string $type, stdClass $row): string {
                if ($type === null) {
                    return '';
                }
                if ($type === type\plugin::TYPE) {
                    return s(type\plugin::get_component_type_name($row->typecomponent));
                }
                return s(get_string('type_' . $type, 'tool_mucohort'));
            }));

        $this->add_column_from_entity('cohort:visible');

        $this->add_column((new column(
            'members',
            new lang_string('memberscount', 'core_cohort'),
            $entityname
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_field("(SELECT COUNT('x') FROM {cohort_members} cmc WHERE cmc.cohortid = {$c}.id)", 'members')
            ->set_is_sortable(true));

        $this->set_initial_sort_column('cohort:name', SORT_ASC);
    }

    /**
     * Are there any subcategories in the report context?
     *
     * @return bool
     */
    protected function has_subcategories(): bool {
        global $DB;
        $context = $this->get_context();
        return $DB->record_exists_select('context', "path LIKE ? AND contextlevel = ?", [$context->path . '/%', CONTEXT_COURSECAT]);
    }

    /**
     * Adds the filters we want to display in the report.
     */
    protected function add_filters(): void {
        $c = $this->cohortalias;
        $entityname = $this->cohortentity->get_entity_name();
        $context = $this->get_context();

        $this->add_filters_from_entity('cohort', ['name', 'idnumber']);

        $types = [
            type\standard::TYPE => type\standard::get_name(),
            type\curated::TYPE => type\curated::get_name(),
            type\rules::TYPE => type\rules::get_name(),
            type\plugin::TYPE => get_string('type_plugin', 'tool_mucohort'),
        ];
        $this->add_filter((new filter(
            select::class,
            'type',
            new lang_string('type', 'tool_mucohort'),
            $entityname,
            $this->get_type_sql()
        ))->set_options($types));

        $this->add_filters_from_entity('cohort', ['visible']);

        $members = [
            self::MEMBERS_NONE => get_string('members_none', 'tool_mucohort'),
            self::MEMBERS_SUSPENDED => get_string('members_suspended', 'tool_mucohort'),
            self::MEMBERS_ACTIVE => get_string('members_active', 'tool_mucohort'),
        ];
        $this->add_filter((new filter(
            select::class,
            'members',
            new lang_string('tab_members', 'tool_mucohort'),
            $entityname,
            $this->get_members_sql()
        ))->set_options($members));

        if ($context->contextlevel != CONTEXT_SYSTEM || $this->has_subcategories()) {
            $this->add_filter(new filter(
                boolean_select::class,
                'currentcontextonly',
                new lang_string('currentcontextonly', 'tool_mucohort'),
                $entityname,
                "CASE WHEN {$c}.contextid = {$context->id} THEN 1 ELSE 0 END"
            ));
        }

        $this->add_filters_from_entity('cohort', ['customfield*']);
    }

    /**
     * Add the system report actions.
     */
    protected function add_actions(): void {
        if (\tool_mulib\local\reportbuilder_util::is_downloading()) {
            return;
        }

        $url = new url('/admin/tool/mucohort/management/cohort_update.php', ['id' => ':id']);
        $link = new \tool_mulib\output\muform\dialog\link($url, get_string('cohort_update', 'tool_mucohort'), 'i/settings');
        $link->set_form_size('xl');
        $this->add_action($link->create_report_action()
            ->add_callback(static function (stdClass $row): bool {
                return type\base::from_record($row)->can_manage();
            }));

        $url = new url('/admin/tool/mucohort/management/cohort_delete.php', ['id' => ':id']);
        $link = new \tool_mulib\output\muform\dialog\link($url, get_string('cohort_delete', 'tool_mucohort'), 'i/delete');
        $link->set_form_size('sm');
        $this->add_action($link->create_report_action(['class' => 'text-danger'])
            ->add_callback(static function (stdClass $row): bool {
                return type\base::from_record($row)->can_delete();
            }));
    }
}
