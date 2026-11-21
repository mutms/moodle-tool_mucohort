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

use tool_mucohort\local\profilefield_util;
use tool_mulib\local\profilefield_cache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Profile fields in cohort rules tests.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mucohort\local\profilefield_util::class)]
#[CoversClass(\tool_mucohort\hook\profilefield_handlers::class)]
#[Group('MuTMS')]
final class profilefield_util_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_handlers(): void {
        $generator = $this->getDataGenerator();
        $text = $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'dept', 'name' => 'Dept']);
        $menu = $generator->create_custom_profile_field(['datatype' => 'menu', 'shortname' => 'level', 'name' => 'Level', 'param1' => "A\nB"]);
        $textarea = $generator->create_custom_profile_field(['datatype' => 'textarea', 'shortname' => 'notes', 'name' => 'Notes']);

        $this->assertSame(['text', 'menu', 'checkbox', 'datetime'], array_keys(profilefield_util::get_handler_classes()));
        $this->assertInstanceOf(\tool_mucohort\local\profilefield\text::class, profilefield_util::get_handler($text));
        $this->assertNull(profilefield_util::get_handler($textarea));
        $this->assertSame([(int)$text->id, (int)$menu->id], array_keys(profilefield_util::get_supported_fields()));
        $this->assertSame([], profilefield_util::get_supported_fields(true));

        profilefield_cache::add_field((int)$menu->id);
        profilefield_cache::populate_pending_fields(new \null_progress_trace());
        $this->assertSame([(int)$menu->id], array_keys(profilefield_util::get_supported_fields(true)));

        $this->redirectHook(\tool_mucohort\hook\profilefield_handlers::class, function ($hook) {
            $hook->unregister('text');
            $hook->register('textarea', \tool_mucohort\local\profilefield\text::class);
        });
        $this->assertNull(profilefield_util::get_handler($text));
        $this->assertNull(profilefield_util::get_handler($textarea));

        $this->redirectHook(\tool_mulib\hook\profilefield_handlers::class, function ($hook) {
            $hook->register('textarea', \tool_mulib\local\profilefield\text::class);
        });
        $this->assertNotNull(profilefield_util::get_handler($textarea));
    }

    public function test_get_rules_fieldids(): void {
        $ruleset = new \tool_mucohort\local\ruleset();
        $ruleset->add_rule('include', 'tool_mucohort:profilefield', ['fieldid' => 7, 'operator' => 'empty', 'values' => []]);
        $ruleset->add_rule('exclude', 'tool_mucohort:profilefield', ['fieldid' => 3, 'operator' => 'empty', 'values' => []]);
        $ruleset->add_rule('include', 'tool_mucohort:profilefield', ['fieldid' => '9']);
        $ruleset->add_rule('include', 'tool_mucohort:userfield', ['fieldid' => 5]);
        $this->assertSame([3, 7], profilefield_util::get_rules_fieldids([$ruleset->to_json(), null, 'invalid']));
    }
}
