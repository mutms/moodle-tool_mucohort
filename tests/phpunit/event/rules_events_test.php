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
// phpcs:disable moodle.Files.LineLength.TooLong
// phpcs:disable moodle.Commenting.DocblockDescription.Missing

namespace tool_mucohort\phpunit\event;

use tool_mucohort\local\rule\base as rule;
use tool_mucohort\local\ruleset;
use tool_mucohort\local\sync;
use tool_mucohort\local\type\rules;

/**
 * Rules and sync events tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\event\rules_draft_updated
 * @covers \tool_mucohort\event\rules_draft_discarded
 * @covers \tool_mucohort\event\rules_approved
 * @covers \tool_mucohort\event\sync_completed
 * @covers \tool_mucohort\event\sync_failed
 */
final class rules_events_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_rules_events(): void {
        $this->setAdminUser();
        $cohort = rules::create((object)['contextid' => \core\context\system::instance()->id, 'name' => 'Rules']);

        $sink = $this->redirectEvents();
        $ruleset = $cohort->start_draft();
        $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:allusers', []);
        $cohort->set_pending_ruleset($ruleset);
        $cohort->set_pending_ruleset(null);
        // Discarding of missing draft does nothing.
        $cohort->set_pending_ruleset(null);
        $cohort->set_pending_ruleset($ruleset);
        $cohort->approve_draft(sha1($cohort->get_rules_record()->pendingrulesjson));
        $events = $sink->get_events();
        $sink->close();

        $classes = array_map(fn($event) => get_class($event), $events);
        $this->assertSame([
            \tool_mucohort\event\rules_draft_updated::class,
            \tool_mucohort\event\rules_draft_updated::class,
            \tool_mucohort\event\rules_draft_discarded::class,
            \tool_mucohort\event\rules_draft_updated::class,
            \tool_mucohort\event\rules_approved::class,
        ], $classes);

        $event = end($events);
        $this->assertSame($cohort->get_context()->id, $event->contextid);
        $this->assertSame($cohort->get_id(), (int)$event->objectid);
        $this->assertSame('Cohort rules approved', $event::get_name());
        $this->assertStringContainsString("approved rules of cohort with id '{$cohort->get_id()}'", $event->get_description());
        $url = new \core\url('/admin/tool/mucohort/cohort_rules.php', ['id' => $cohort->get_id()]);
        $this->assertSame($url->out(false), $event->get_url()->out(false));
    }

    public function test_sync_events(): void {
        $other = $this->getDataGenerator()->create_cohort();
        $ruleset = new ruleset();
        $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:cohort', ['cohortids' => [(int)$other->id]]);
        $cohort = rules::create((object)['contextid' => \core\context\system::instance()->id, 'name' => 'Rules']);
        $cohort->set_pending_ruleset($ruleset);
        $cohort->approve_draft(sha1($cohort->get_rules_record()->pendingrulesjson));

        $sink = $this->redirectEvents();
        $sync = sync::run($cohort, new \null_progress_trace());
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof \tool_mucohort\event\sync_completed));
        $sink->close();
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame((int)$sync->id, (int)$event->objectid);
        $this->assertSame(['cohortid' => $cohort->get_id(), 'status' => sync::STATUS_OK, 'added' => 0, 'removed' => 0], $event->other);
        $url = new \core\url('/admin/tool/mucohort/cohort_sync.php', ['id' => $cohort->get_id()]);
        $this->assertSame($url->out(false), $event->get_url()->out(false));
        $this->assertStringContainsString('finished', $event->get_description());

        cohort_delete_cohort($other);
        $sink = $this->redirectEvents();
        $sync = sync::run(\tool_mucohort\local\type\base::fetch($cohort->get_id()), new \null_progress_trace());
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof \tool_mucohort\event\sync_failed));
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertSame(sync::STATUS_BROKEN, $events[0]->other['status']);
        $this->assertSame('Cohort sync failed', $events[0]::get_name());
    }
}
