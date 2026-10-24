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

namespace tool_mucohort\output;

use core\output\html_writer;
use core\url;
use tool_mucohort\local\exception;
use tool_mucohort\local\rule\base as rule;
use tool_mucohort\local\ruleset;
use tool_mucohort\local\type\advanced;
use tool_mucohort\local\type\curated;
use tool_mucohort\local\type\rules;
use tool_mulib\output\muform\dialog\icon;

/**
 * Cohort management renderer.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class renderer extends \plugin_renderer_base {
    /**
     * Render rules of rule-based cohort as plain sentences.
     *
     * @param rules $cohort
     * @param ruleset $ruleset active or draft rules
     * @param bool $editable true means add and edit actions are added to draft rules
     * @return string html
     */
    public function render_ruleset(rules $cohort, ruleset $ruleset, bool $editable): string {
        $problems = $cohort->get_problems($ruleset);
        $result = '';

        $result .= $this->heading(get_string('rules_include', 'tool_mucohort'), 4);
        $includes = $ruleset->get_rules(rule::KIND_INCLUDE);
        if ($includes) {
            $result .= $this->render_rule_list($cohort, $includes, $problems, $editable);
        } else {
            $result .= html_writer::tag('p', get_string('rules_include_none', 'tool_mucohort'), ['class' => 'text-muted']);
        }
        if ($editable) {
            $result .= $this->render_add_button($cohort, rule::KIND_INCLUDE);
        }

        $mucohort = $cohort->get_mucohort();
        if ($mucohort->candidatecohortid) {
            $a = rules::format_candidate_cohort($mucohort, true);
            $result .= html_writer::tag('p', get_string('rules_candidate', 'tool_mucohort', $a));
        }

        $result .= $this->heading(get_string('rules_exclude', 'tool_mucohort'), 4);
        $excludes = $ruleset->get_rules(rule::KIND_EXCLUDE);
        if ($excludes) {
            $result .= $this->render_rule_list($cohort, $excludes, $problems, $editable);
        } else {
            $result .= html_writer::tag('p', get_string('rules_exclude_none', 'tool_mucohort'), ['class' => 'text-muted']);
        }
        if ($editable) {
            $result .= $this->render_add_button($cohort, rule::KIND_EXCLUDE);
        }

        $synctype = $cohort->get_synctype();
        $result .= html_writer::tag('p', $cohort::get_synctype_descriptions()[$synctype]);

        $a = (object)[
            'forced' => exception::count($cohort, exception::TYPE_FORCED),
            'blocked' => exception::count($cohort, exception::TYPE_BLOCKED),
        ];
        $text = get_string('exceptions_count', 'tool_mucohort', $a);
        if ($cohort->can_view_members()) {
            $text = html_writer::link(new url('/admin/tool/mucohort/cohort_exceptions.php', ['id' => $cohort->get_id()]), $text);
        }
        $result .= html_writer::tag('p', get_string('tab_exceptions', 'tool_mucohort') . ': ' . $text);

        return $result;
    }

    /**
     * Render list of rules with problems and actions.
     *
     * @param rules $cohort
     * @param array $rules uuid => rule
     * @param array $problems uuid => problem
     * @param bool $editable
     * @return string html
     */
    private function render_rule_list(rules $cohort, array $rules, array $problems, bool $editable): string {
        $items = [];
        foreach ($rules as $uuid => $rule) {
            $content = html_writer::span($cohort->get_rule_description($rule));
            if (isset($problems[$uuid])) {
                $content .= html_writer::div(clean_string($problems[$uuid]), 'text-danger small');
            }
            if ($editable) {
                $content .= html_writer::span($this->render_rule_actions($cohort, $uuid, $rule), 'ms-auto text-nowrap');
            }
            $items[] = html_writer::tag('li', $content, ['class' => 'list-group-item d-flex flex-wrap align-items-baseline gap-2']);
        }
        return html_writer::tag('ul', implode('', $items), ['class' => 'list-group mb-3']);
    }

    /**
     * Render edit and delete actions of a draft rule.
     *
     * @param rules $cohort
     * @param string $uuid
     * @param array $rule
     * @return string html
     */
    private function render_rule_actions(rules $cohort, string $uuid, array $rule): string {
        $params = ['id' => $cohort->get_id(), 'rule' => $uuid];
        $result = '';
        $available = $cohort->get_rule_types()->get_class($rule['kind'], $rule['type']);
        if ($available && $cohort->get_rule_instance($rule)->has_settings()) {
            $url = new url('/admin/tool/mucohort/management/rule_edit.php', $params);
            $result .= $this->render(new icon($url, get_string('rule_update', 'tool_mucohort'), 'i/edit'));
        }
        $url = new url('/admin/tool/mucohort/management/rule_delete.php', $params);
        $icon = new icon($url, get_string('rule_delete', 'tool_mucohort'), 'i/delete');
        $icon->set_form_size('sm');
        $result .= $this->render($icon);
        return $result;
    }

    /**
     * Render button for adding of a new draft rule.
     *
     * @param rules $cohort
     * @param string $kind
     * @return string html
     */
    private function render_add_button(rules $cohort, string $kind): string {
        $url = new url('/admin/tool/mucohort/management/rule_add.php', ['id' => $cohort->get_id(), 'kind' => $kind]);
        $button = new \core\output\single_button($url, get_string('rule_add_' . $kind, 'tool_mucohort'), 'get');
        return html_writer::div($this->render($button), 'mb-4');
    }

    /**
     * Render "Sync now" button, or the state of queued or running sync.
     *
     * @param rules $cohort
     * @return string html
     */
    public function render_sync_button(rules $cohort): string {
        $current = \tool_mucohort\local\sync::get_current($cohort);
        if ($current) {
            $queued = ((int)$current->status === \tool_mucohort\local\sync::STATUS_QUEUED);
            if ($queued && $cohort->can_manage_rules()) {
                // Do not wait for cron, run the queued sync in this request.
                $url = new url('/admin/tool/mucohort/management/sync_run.php', ['id' => $cohort->get_id()]);
                $button = new \core\output\single_button($url, get_string('sync_run', 'tool_mucohort'), 'post');
                return $this->render($button);
            }
            $url = new url('/admin/tool/mucohort/cohort_sync.php', ['id' => $cohort->get_id()]);
            $status = $queued ? 'sync_queued' : 'sync_running';
            return html_writer::link($url, get_string($status, 'tool_mucohort'), ['class' => 'btn btn-outline-secondary']);
        }
        if (!$cohort->can_manage_rules() || !\tool_mucohort\local\sync::can_sync($cohort)) {
            return '';
        }
        $url = new url('/admin/tool/mucohort/management/sync_now.php', ['id' => $cohort->get_id()]);
        $button = new \tool_mulib\output\muform\dialog\button($url, get_string('sync_now', 'tool_mucohort'), true);
        $button->set_form_size('sm');
        $button->set_submitted_action(\tool_mulib\muform\handler\dialog::ACTION_REDIRECT);
        return $this->render($button);
    }

    /**
     * Render settings that affect membership of advanced cohorts, so that they are visible at a glance.
     *
     * @param advanced $cohort
     * @return string html
     */
    public function render_members_info(advanced $cohort): string {
        $details = new \tool_mulib\output\entity_details();

        if ($cohort instanceof rules) {
            $url = new url('/admin/tool/mucohort/cohort_rules.php', ['id' => $cohort->get_id()]);
        } else {
            $url = new url('/admin/tool/mucohort/cohort.php', ['id' => $cohort->get_id()]);
        }
        $details->add(get_string('type', 'tool_mucohort'), html_writer::link($url, $cohort->get_type_name()));

        $candidate = $cohort::format_candidate_cohort($cohort->get_mucohort(), true);
        $details->add(get_string('candidatecohort', 'tool_mucohort'), $candidate);

        if ($cohort instanceof rules) {
            $details->add(get_string('rules_synctype', 'tool_mucohort'), rules::get_synctypes()[$cohort->get_synctype()]);
        } else if ($cohort instanceof curated) {
            $details->add(get_string('maxmembers', 'tool_mucohort'), $cohort->format_maxmembers());
        }

        $blocked = (string)exception::count($cohort, exception::TYPE_BLOCKED);
        $url = new url('/admin/tool/mucohort/cohort_exceptions.php', ['id' => $cohort->get_id()]);
        $details->add(get_string('members_blocked', 'tool_mucohort'), html_writer::link($url, $blocked));

        if ($cohort instanceof rules) {
            $url = new url('/admin/tool/mucohort/cohort_sync.php', ['id' => $cohort->get_id()]);
            $summary = \tool_mucohort\local\sync::get_summary($cohort);
            $details->add(get_string('sync_last', 'tool_mucohort'), html_writer::link($url, $summary));
        }

        return html_writer::div($this->render($details), 'mb-3');
    }
}
