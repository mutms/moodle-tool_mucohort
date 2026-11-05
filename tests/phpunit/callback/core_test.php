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

namespace tool_mucohort\phpunit\callback;

use tool_mucohort\callback\core;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Core callbacks tests.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mucohort\callback\core::class)]
#[Group('MuTMS')]
final class core_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_get_takeover_url(): void {
        $this->assertNull(core::get_takeover_url('/cohort/index.php'));

        set_config('takeover', 1, 'tool_mucohort');

        $this->assertNull(core::get_takeover_url('/cohort/upload.php'));
        $this->assertNull(core::get_takeover_url('/admin/index.php'));

        $this->assertSame('https://www.example.com/moodle/admin/tool/mucohort/index.php?contextid=1', core::get_takeover_url('/cohort/index.php')->out(false));

        $_GET['contextid'] = '33';
        $this->assertSame('https://www.example.com/moodle/admin/tool/mucohort/index.php?contextid=33', core::get_takeover_url('/cohort/index.php')->out(false));
        $this->assertSame('https://www.example.com/moodle/admin/tool/mucohort/management/cohort_create.php?contextid=33', core::get_takeover_url('/cohort/edit.php')->out(false));
        unset($_GET['contextid']);

        $_GET['id'] = '7';
        $this->assertSame('https://www.example.com/moodle/admin/tool/mucohort/cohort.php?id=7', core::get_takeover_url('/cohort/edit.php')->out(false));
        $this->assertSame('https://www.example.com/moodle/admin/tool/mucohort/cohort_members.php?id=7', core::get_takeover_url('/cohort/assign.php')->out(false));
        unset($_GET['id']);

        $this->assertSame('https://www.example.com/moodle/admin/tool/mucohort/index.php?contextid=1', core::get_takeover_url('/cohort/assign.php')->out(false));
    }
}
