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

use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\system_report;
use tool_mucohort\local\sync;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\rules;
use lang_string;

/**
 * Sync history of a rule-based cohort.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sync_history extends system_report {
    #[\Override]
    protected function initialise(): void {
        $cohort = base::fetch((int)$this->get_parameters()['cohortid']);
        if (!($cohort instanceof rules) || $cohort->get_context()->id != $this->get_context()->id) {
            throw new \core\exception\invalid_parameter_exception('Invalid cohort');
        }

        $this->set_main_table('tool_mucohort_sync', 's');
        $param = database::generate_param_name();
        $this->add_base_condition_sql("s.mucohortid = :$param", [$param => $cohort->get_mucohort()->id]);
        $this->add_base_fields('s.id');
        $this->annotate_entity('sync', new lang_string('tab_sync', 'tool_mucohort'));

        $this->add_columns();
        $this->add_filters();

        $this->set_downloadable(true);
        $this->set_default_no_results_notice(new lang_string('sync_history_none', 'tool_mucohort'));
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
        $entityname = 'sync';
        $statuses = sync::get_statuses();

        $this->add_column((new column('timequeued', new lang_string('sync_timequeued', 'tool_mucohort'), $entityname))
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field('s.timequeued')
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']));

        $this->add_column((new column('timestarted', new lang_string('sync_timestarted', 'tool_mucohort'), $entityname))
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field('s.timestarted')
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']));

        $this->add_column((new column('duration', new lang_string('sync_duration', 'tool_mucohort'), $entityname))
            ->set_type(column::TYPE_INTEGER)
            ->add_field('s.duration')
            ->set_is_sortable(true)
            ->add_callback(static fn(?int $value): string => ($value === null) ? '' : format_time($value)));

        $this->add_column((new column('status', new lang_string('sync_status', 'tool_mucohort'), $entityname))
            ->set_type(column::TYPE_INTEGER)
            ->add_field('s.status')
            ->set_is_sortable(true)
            ->add_callback(static function (?int $value) use ($statuses): string {
                if (!isset($statuses[$value])) {
                    return '';
                }
                $class = match ($value) {
                    sync::STATUS_OK => 'text-bg-success',
                    sync::STATUS_FAILED, sync::STATUS_BROKEN => 'text-bg-danger',
                    sync::STATUS_QUEUED, sync::STATUS_RUNNING, sync::STATUS_CANCELREQUESTED => 'text-bg-info',
                    default => 'text-bg-secondary',
                };
                return \tool_mulib\local\reportbuilder_util::format_badge($statuses[$value], $class);
            }));

        $this->add_column((new column('added', new lang_string('sync_added', 'tool_mucohort'), $entityname))
            ->set_type(column::TYPE_INTEGER)
            ->add_field('s.added')
            ->set_is_sortable(true));

        $this->add_column((new column('removed', new lang_string('sync_removed', 'tool_mucohort'), $entityname))
            ->set_type(column::TYPE_INTEGER)
            ->add_field('s.removed')
            ->set_is_sortable(true));

        $this->add_column((new column('message', new lang_string('sync_message', 'tool_mucohort'), $entityname))
            ->set_type(column::TYPE_LONGTEXT)
            ->add_field('s.message')
            ->add_callback(static fn(?string $value): string => ($value === null) ? '' : clean_string($value)));

        $this->set_initial_sort_column('sync:timequeued', SORT_DESC);
    }

    /**
     * Adds the filters we want to display in the report.
     */
    protected function add_filters(): void {
        $this->add_filter((new filter(
            select::class,
            'status',
            new lang_string('sync_status', 'tool_mucohort'),
            'sync',
            's.status'
        ))->set_options(sync::get_statuses()));

        $this->add_filter(new filter(
            date::class,
            'timequeued',
            new lang_string('sync_timequeued', 'tool_mucohort'),
            'sync',
            's.timequeued'
        ));
    }
}
