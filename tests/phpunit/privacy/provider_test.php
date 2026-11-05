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

namespace tool_mucohort\phpunit\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use tool_mucohort\local\access;
use tool_mucohort\local\exception;
use tool_mucohort\local\type\curated;
use tool_mucohort\privacy\provider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Privacy provider tests.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mucohort\privacy\provider::class)]
#[Group('MuTMS')]
final class provider_test extends \core_privacy\tests\provider_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_get_metadata(): void {
        $collection = new \core_privacy\local\metadata\collection('tool_mucohort');
        $collection = provider::get_metadata($collection);
        $this->assertCount(8, $collection->get_collection());
    }

    public function test_export_and_delete(): void {
        global $DB;

        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);
        $cohort1 = curated::create((object)['contextid' => $catcontext->id, 'name' => 'Curated 1']);
        $cohort2 = curated::create((object)['contextid' => $catcontext->id, 'name' => 'Curated 2']);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        access::set($cohort1, $user1->id, ['viewmembers'], true);
        exception::set($cohort1, $user2->id, exception::TYPE_BLOCKED, 'Cheated');
        exception::set($cohort2, $user1->id, exception::TYPE_FORCED, 'Pilot');
        exception::set($cohort2, $user2->id, exception::TYPE_FORCED, null);
        $admin = get_admin();
        $extdbid = $DB->insert_record('tool_mucohort_extdb', [
            'mucohortid' => $cohort1->get_mucohort()->id, 'queryid' => 1, 'timecreated' => time(), 'usercreated' => $admin->id,
        ]);
        foreach ([$user1, $user2] as $user) {
            $DB->insert_record('tool_mucohort_extdb_user', [
                'extdbid' => $extdbid, 'identifiertype' => 'username', 'useridentifier' => $user->username,
                'userid' => $user->id, 'stale' => 0, 'timecreated' => time(),
            ]);
        }

        $contextlist = provider::get_contexts_for_userid($user1->id);
        $this->assertSame([$catcontext->id], array_map('intval', $contextlist->get_contextids()));

        $userlist = new userlist($catcontext, 'tool_mucohort');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $admin->id], $userlist->get_userids());
        $contextlist = provider::get_contexts_for_userid($admin->id);
        $this->assertSame([$catcontext->id], array_map('intval', $contextlist->get_contextids()));

        $approved = new approved_contextlist($user1, 'tool_mucohort', [$catcontext->id]);
        provider::export_user_data($approved);
        $writer = writer::with_context($catcontext);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([get_string('privacy:cohort', 'tool_mucohort', $cohort1->get_id())]);
        $this->assertSame('Yes', $data->access['curator']);
        $data = $writer->get_data([get_string('privacy:cohort', 'tool_mucohort', $cohort2->get_id())]);
        $this->assertSame('Pilot', $data->exception['reason']);
        $data = $writer->get_data([get_string('privacy:cohort', 'tool_mucohort', $cohort1->get_id())]);
        $this->assertSame([$user1->username], $data->externaldatabase);

        // Delete for user1 - access and forced exceptions are removed.
        provider::delete_data_for_user($approved);
        $this->assertFalse($DB->record_exists('tool_mucohort_access', ['userid' => $user1->id]));
        $this->assertFalse($DB->record_exists('tool_mucohort_exception', ['userid' => $user1->id]));
        $this->assertFalse($DB->record_exists('tool_mucohort_extdb_user', ['userid' => $user1->id]));
        $this->assertTrue($DB->record_exists('tool_mucohort_extdb_user', ['userid' => $user2->id]));
        $this->assertSame('Cheated', $DB->get_field('tool_mucohort_exception', 'reason', ['userid' => $user2->id, 'type' => exception::TYPE_BLOCKED]));

        // Delete for user2 - blocked stays without reason.
        $approved = new approved_userlist($catcontext, 'tool_mucohort', [$user2->id]);
        provider::delete_data_for_users($approved);
        $blocked = $DB->get_record('tool_mucohort_exception', ['userid' => $user2->id], '*', MUST_EXIST);
        $this->assertSame((string)exception::TYPE_BLOCKED, (string)$blocked->type);
        $this->assertNull($blocked->reason);

        // Delete for admin - references to changes are removed.
        $this->assertTrue($DB->record_exists('tool_mucohort_exception', ['usermodified' => $admin->id]));
        $approved = new approved_userlist($catcontext, 'tool_mucohort', [$admin->id]);
        provider::delete_data_for_users($approved);
        $this->assertFalse($DB->record_exists('tool_mucohort_exception', ['usermodified' => $admin->id]));
        $this->assertFalse($DB->record_exists('tool_mucohort_extdb', ['usercreated' => $admin->id]));
        $this->assertTrue($DB->record_exists('tool_mucohort_exception', ['userid' => $user2->id]));

        // Delete all in context.
        access::set($cohort1, $user1->id, [], false);
        provider::delete_data_for_all_users_in_context($catcontext);
        $this->assertFalse($DB->record_exists('tool_mucohort_access', []));
        $this->assertSame(1, $DB->count_records('tool_mucohort_exception'));
    }

    public function test_profilefield_cache(): void {
        global $DB;

        $field = $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'dept', 'name' => 'Dept']);
        $user1 = $this->getDataGenerator()->create_user(['profile_field_dept' => 'Sales']);
        $user2 = $this->getDataGenerator()->create_user(['profile_field_dept' => 'HR']);
        \tool_mucohort\local\profilefield_cache::populate_field((int)$field->id, new \null_progress_trace());
        $usercontext = \core\context\user::instance($user1->id);

        $contextlist = provider::get_contexts_for_userid($user1->id);
        $this->assertSame([$usercontext->id], array_map('intval', $contextlist->get_contextids()));
        $userlist = new userlist($usercontext, 'tool_mucohort');
        provider::get_users_in_context($userlist);
        $this->assertSame([(int)$user1->id], array_map('intval', $userlist->get_userids()));

        provider::export_user_data(new approved_contextlist($user1, 'tool_mucohort', [$usercontext->id]));
        $data = writer::with_context($usercontext)->get_data([get_string('privacy:profilefields', 'tool_mucohort')]);
        $this->assertSame('Sales', $data->profilefields[0]['charvalue']);

        provider::delete_data_for_user(new approved_contextlist($user1, 'tool_mucohort', [$usercontext->id]));
        $this->assertFalse($DB->record_exists('tool_mucohort_profilefield', ['userid' => $user1->id]));
        $this->assertTrue($DB->record_exists('tool_mucohort_profilefield', ['userid' => $user2->id]));
    }
}
