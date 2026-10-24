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

use tool_mucohort\local\profilefield_cache;

/**
 * Profile field cache tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\local\profilefield_cache
 * @covers \tool_mucohort\local\profilefield\text
 * @covers \tool_mucohort\local\profilefield\menu
 * @covers \tool_mucohort\local\profilefield\checkbox
 * @covers \tool_mucohort\local\profilefield\datetime
 * @covers \tool_mucohort\hook\profilefield_handlers
 * @covers \tool_mucohort\task\cleanup
 */
final class profilefield_cache_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Returns cached values of user.
     *
     * @param int $userid
     * @param int $fieldid
     * @return array|null [charvalue, intvalue]
     */
    private function get_cached(int $userid, int $fieldid): ?array {
        global $DB;
        $record = $DB->get_record('tool_mucohort_profilefield', ['userid' => $userid, 'fieldid' => $fieldid]);
        if (!$record) {
            return null;
        }
        return [$record->charvalue, $record->intvalue === null ? null : (int)$record->intvalue];
    }

    public function test_handlers(): void {
        $generator = $this->getDataGenerator();
        $text = $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'dept', 'name' => 'Dept']);
        $textarea = $generator->create_custom_profile_field(['datatype' => 'textarea', 'shortname' => 'notes', 'name' => 'Notes']);

        $this->assertSame(['text', 'menu', 'checkbox', 'datetime'], array_keys(profilefield_cache::get_handler_classes()));
        $this->assertInstanceOf(\tool_mucohort\local\profilefield\text::class, profilefield_cache::get_handler($text));
        $this->assertNull(profilefield_cache::get_handler($textarea));
        $this->assertSame([(int)$text->id], array_keys(profilefield_cache::get_supported_fields()));

        $this->redirectHook(\tool_mucohort\hook\profilefield_handlers::class, function ($hook) {
            $hook->unregister('text');
            $hook->register('textarea', \tool_mucohort\local\profilefield\text::class);
        });
        $this->assertNull(profilefield_cache::get_handler($text));
        $this->assertNotNull(profilefield_cache::get_handler($textarea));
    }

    public function test_populate_and_update(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $text = $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'dept', 'name' => 'Dept', 'defaultdata' => 'None']);
        $menu = $generator->create_custom_profile_field(['datatype' => 'menu', 'shortname' => 'level', 'name' => 'Level', 'param1' => "Junior\nSenior", 'defaultdata' => 'Junior']);
        $checkbox = $generator->create_custom_profile_field(['datatype' => 'checkbox', 'shortname' => 'vip', 'name' => 'VIP', 'defaultdata' => 0]);
        $datetime = $generator->create_custom_profile_field(['datatype' => 'datetime', 'shortname' => 'hired', 'name' => 'Hired', 'param1' => 2000, 'param2' => 2050]);

        $user1 = $generator->create_user(['profile_field_dept' => 'Sales', 'profile_field_level' => 'Senior', 'profile_field_vip' => 1, 'profile_field_hired' => 1767225600]);
        $user2 = $generator->create_user();

        // Nothing is cached until rules populate the field.
        $this->assertSame([], profilefield_cache::get_fields());
        $this->assertFalse(profilefield_cache::is_ready((int)$text->id));
        foreach ([$text, $menu, $checkbox, $datetime] as $field) {
            profilefield_cache::populate_field((int)$field->id, new \null_progress_trace());
        }
        $this->assertSame([(int)$text->id, (int)$menu->id, (int)$checkbox->id, (int)$datetime->id], array_keys(profilefield_cache::get_fields()));
        $this->assertTrue(profilefield_cache::is_ready((int)$text->id));
        set_config('profilefieldlifetime', HOURSECS, 'tool_mucohort');
        $this->assertFalse(profilefield_cache::is_outdated((int)$text->id));
        set_config('profilefieldlifetime', 0, 'tool_mucohort');
        $this->assertTrue(profilefield_cache::is_outdated((int)$text->id));
        $this->assertTrue(profilefield_cache::is_outdated(-1));

        $this->assertSame(['Sales', null], $this->get_cached((int)$user1->id, (int)$text->id));
        $this->assertSame(['Senior', null], $this->get_cached((int)$user1->id, (int)$menu->id));
        $this->assertSame([null, 1], $this->get_cached((int)$user1->id, (int)$checkbox->id));
        $this->assertSame([null, 1767225600], $this->get_cached((int)$user1->id, (int)$datetime->id));
        // Defaults for users without data.
        $this->assertSame(['None', null], $this->get_cached((int)$user2->id, (int)$text->id));
        $this->assertSame(['Junior', null], $this->get_cached((int)$user2->id, (int)$menu->id));
        $this->assertSame([null, 0], $this->get_cached((int)$user2->id, (int)$checkbox->id));
        $this->assertSame([null, 0], $this->get_cached((int)$user2->id, (int)$datetime->id));

        // Single user updates.
        $user3 = $generator->create_user(['profile_field_dept' => 'HR']);
        $this->assertNull($this->get_cached((int)$user3->id, (int)$text->id));
        profilefield_cache::update_user((int)$user3->id, (int)$text->id);
        $this->assertSame(['HR', null], $this->get_cached((int)$user3->id, (int)$text->id));
        $DB->set_field('user_info_data', 'data', 'IT', ['userid' => $user3->id, 'fieldid' => $text->id]);
        profilefield_cache::update_user((int)$user3->id, (int)$text->id);
        $this->assertSame(['IT', null], $this->get_cached((int)$user3->id, (int)$text->id));

        // Populating again updates changed values and defaults and removes deleted users.
        $DB->set_field('user_info_data', 'data', 'Support', ['userid' => $user3->id, 'fieldid' => $text->id]);
        $DB->set_field('user_info_field', 'defaultdata', 'Nobody', ['id' => $text->id]);
        $user4 = $generator->create_user();
        delete_user($user1);
        profilefield_cache::populate_field((int)$text->id, new \null_progress_trace());
        $this->assertSame(['Support', null], $this->get_cached((int)$user3->id, (int)$text->id));
        $this->assertSame(['Nobody', null], $this->get_cached((int)$user2->id, (int)$text->id));
        $this->assertSame(['Nobody', null], $this->get_cached((int)$user4->id, (int)$text->id));
        $this->assertNull($this->get_cached((int)$user1->id, (int)$text->id));

        profilefield_cache::remove_field((int)$menu->id);
        $this->assertArrayNotHasKey((int)$menu->id, profilefield_cache::get_fields());
        $this->assertSame(0, $DB->count_records('tool_mucohort_profilefield', ['fieldid' => $menu->id]));
    }

    public function test_remove_unused_fields(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $field1 = $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'f1', 'name' => 'F1']);
        $field2 = $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'f2', 'name' => 'F2']);

        $cohort = \tool_mucohort\local\type\rules::create((object)['contextid' => \core\context\system::instance()->id, 'name' => 'R']);
        $ruleset = new \tool_mucohort\local\ruleset();
        $ruleset->add_rule('include', 'tool_mucohort:profilefield', ['fieldid' => (int)$field1->id, 'operator' => 'empty', 'values' => []]);
        $cohort->set_pending_ruleset($ruleset);
        $this->assertSame([(int)$field1->id], profilefield_cache::get_used_fieldids());

        profilefield_cache::populate_field((int)$field1->id, new \null_progress_trace());
        profilefield_cache::populate_field((int)$field2->id, new \null_progress_trace());
        $this->assertGreaterThan(0, $DB->count_records('tool_mucohort_profilefield', ['fieldid' => $field2->id]));

        // Unused fields are removed by the cleanup task.
        ob_start();
        (new \tool_mucohort\task\cleanup())->execute();
        ob_end_clean();
        $this->assertSame([(int)$field1->id], array_keys(profilefield_cache::get_fields()));
        $this->assertSame(0, $DB->count_records('tool_mucohort_profilefield', ['fieldid' => $field2->id]));
        $this->assertSame([(int)$field1->id], profilefield_cache::remove_unused_fields(new \null_progress_trace()));
    }
}
