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

namespace tool_mucohort\phpunit\local;

use tool_mucohort\local\exception;
use tool_mucohort\local\rule\base as rule;
use tool_mucohort\local\ruleset;
use tool_mucohort\local\sync;
use tool_mucohort\local\type\rules;

/**
 * Instant sync of one user tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\local\sync::sync_user
 * @covers \tool_mucohort\local\sync::sync_user_instantly
 * @covers \tool_mucohort\local\event_observer
 */
final class instant_sync_test extends \advanced_testcase {
    /**
     * Returns page renderer.
     *
     * @return \core_renderer
     */
    private function get_renderer(): \core_renderer {
        global $PAGE;
        return $PAGE->get_renderer('core');
    }

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create rule-based cohort with active rules matching users in department Sales.
     *
     * @param array $instantsync
     * @param int $synctype
     * @return rules
     */
    private function create_cohort(array $instantsync, int $synctype = rules::SYNCTYPE_FULL): rules {
        $cohort = rules::create((object)[
            'contextid' => \core\context\system::instance()->id,
            'name' => 'Sales',
            'synctype' => $synctype,
            'instantsync' => $instantsync,
        ]);
        $ruleset = new ruleset();
        $ruleset->add_rule(rule::KIND_INCLUDE, 'tool_mucohort:userfield', ['field' => 'department', 'operator' => 'equals', 'values' => ['Sales']]);
        $cohort->set_pending_ruleset($ruleset);
        $cohort->approve_draft(sha1($cohort->get_rules_record()->pendingrulesjson));
        return $cohort;
    }

    /**
     * Is user cohort member?
     *
     * @param rules $cohort
     * @param int $userid
     * @return bool
     */
    private function is_member(rules $cohort, int $userid): bool {
        global $DB;
        return $DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $userid]);
    }

    /**
     * Trigger login event.
     *
     * @param \stdClass $user
     * @param int $lastlogin
     */
    private function login(\stdClass $user, int $lastlogin): void {
        global $DB;
        $DB->set_field('user', 'lastlogin', $lastlogin, ['id' => $user->id]);
        $event = \core\event\user_loggedin::create([
            'userid' => $user->id,
            'objectid' => $user->id,
            'other' => ['username' => $user->username],
        ]);
        $event->trigger();
    }

    public function test_sync_user(): void {
        global $DB;

        $cohort = $this->create_cohort([]);
        $this->assertSame([], array_values(array_filter(rules::INSTANT_SYNC_FLAGS, fn($f) => $cohort->get_rules_record()->$f)));
        $user1 = $this->getDataGenerator()->create_user(['department' => 'Sales']);
        $user2 = $this->getDataGenerator()->create_user(['department' => 'HR']);

        $this->assertTrue(sync::sync_user($cohort, (int)$user1->id));
        $this->assertTrue($this->is_member($cohort, (int)$user1->id));
        $this->assertFalse(sync::sync_user($cohort, (int)$user1->id));
        $this->assertFalse(sync::sync_user($cohort, (int)$user2->id));

        $DB->set_field('user', 'department', 'HR', ['id' => $user1->id]);
        $this->assertTrue(sync::sync_user($cohort, (int)$user1->id));
        $this->assertFalse($this->is_member($cohort, (int)$user1->id));

        // Add-only keeps members, blocked are removed.
        cohort_add_member($cohort->get_id(), $user1->id);
        $cohort->update((object)['synctype' => rules::SYNCTYPE_ADDONLY]);
        $this->assertFalse(sync::sync_user($cohort, (int)$user1->id));
        $this->assertTrue($this->is_member($cohort, (int)$user1->id));

        // Disabled sync and broken rules do nothing.
        $cohort->update((object)['synctype' => rules::SYNCTYPE_NONE]);
        $DB->set_field('user', 'department', 'Sales', ['id' => $user2->id]);
        $this->assertFalse(sync::sync_user($cohort, (int)$user2->id));
        $cohort->update((object)['synctype' => rules::SYNCTYPE_FULL]);
        $DB->set_field('tool_mucohort_rules', 'broken', 1, ['id' => $cohort->get_rules_record()->id]);
        $cohort = \tool_mucohort\local\type\base::fetch($cohort->get_id());
        $this->assertFalse(sync::sync_user($cohort, (int)$user2->id));
        $this->assertFalse($this->is_member($cohort, (int)$user2->id));
    }

    public function test_login(): void {
        $first = $this->create_cohort(['synconfirstlogin']);
        $every = $this->create_cohort(['synconlogin']);
        $none = $this->create_cohort([]);
        $this->assertSame(['synconfirstlogin'], $first->get_update_form_data()['instantsync']);

        $user = $this->getDataGenerator()->create_user(['department' => 'Sales']);

        set_config('allowsynconlogin', 1, 'tool_mucohort');
        $this->login($user, 0);
        $this->assertTrue($this->is_member($first, (int)$user->id));
        $this->assertTrue($this->is_member($every, (int)$user->id));
        $this->assertFalse($this->is_member($none, (int)$user->id));

        // Only every login cohorts are updated later.
        cohort_remove_member($first->get_id(), $user->id);
        cohort_remove_member($every->get_id(), $user->id);
        $this->login($user, time() - 100);
        $this->assertFalse($this->is_member($first, (int)$user->id));
        $this->assertTrue($this->is_member($every, (int)$user->id));

        // Lockout protection.
        cohort_remove_member($every->get_id(), $user->id);
        set_config('allowsynconlogin', 0, 'tool_mucohort');
        $this->login($user, time() - 100);
        $this->assertFalse($this->is_member($every, (int)$user->id));

        // First login is not affected by the site setting.
        $this->login($user, 0);
        $this->assertTrue($this->is_member($first, (int)$user->id));

        // Exceptions are respected.
        cohort_remove_member($first->get_id(), $user->id);
        exception::set($first, (int)$user->id, exception::TYPE_BLOCKED, null);
        $this->login($user, 0);
        $this->assertFalse($this->is_member($first, (int)$user->id));
    }

    public function test_user_created(): void {
        global $SCRIPT;

        $cohort = $this->create_cohort(['synconusercreated']);

        // CLI and other scripts are ignored.
        $user1 = $this->getDataGenerator()->create_user(['department' => 'Sales']);
        $this->assertFalse($this->is_member($cohort, (int)$user1->id));

        $sink = $this->redirectEvents();
        $user2 = $this->getDataGenerator()->create_user(['department' => 'Sales']);
        $events = $sink->get_events();
        $sink->close();
        $this->assertFalse($this->is_member($cohort, (int)$user2->id));

        // Manual account creation page.
        $SCRIPT = '/user/editadvanced.php';
        $event = \core\event\user_created::create_from_userid($user2->id);
        \tool_mucohort\local\event_observer::user_created($event);
        $this->assertTrue($this->is_member($cohort, (int)$user2->id));
        $this->assertNotEmpty($events);
    }

    public function test_cohort_settings(): void {
        $cohort = $this->create_cohort(['synconlogin', 'synconusercreated']);
        $record = $cohort->get_rules_record();
        $this->assertSame('1', (string)$record->synconlogin);
        $this->assertSame('1', (string)$record->synconusercreated);
        $this->assertSame('0', (string)$record->synconfirstlogin);

        $cohort->update((object)['instantsync' => ['synconfirstlogin']]);
        $this->assertSame(['synconfirstlogin'], $cohort->get_update_form_data()['instantsync']);
        $details = new \tool_mulib\output\entity_details();
        $cohort->add_overview_details($details);
        $html = $this->get_renderer()->render($details);
        $this->assertStringContainsString('First login', $html);
        $this->assertStringContainsString('Automatic sync', $html);

        $cohort->update((object)['synctype' => rules::SYNCTYPE_NONE]);
        $details = new \tool_mulib\output\entity_details();
        $cohort->add_overview_details($details);
        $html = $this->get_renderer()->render($details);
        $this->assertStringNotContainsString('First login', $html);
        $this->assertStringNotContainsString('Automatic sync', $html);
        $cohort->update((object)['synctype' => rules::SYNCTYPE_FULL]);
        $this->assertCount(3, rules::get_instant_sync_options());
        $this->assertCount(3, rules::get_instant_sync_descriptions());
    }
}
