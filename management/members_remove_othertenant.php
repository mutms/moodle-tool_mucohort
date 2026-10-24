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
// phpcs:disable moodle.Commenting.InlineComment.TypeHintingMatch

/**
 * Remove members of other tenants from curated cohort.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mucohort\local\type\base;
use tool_mucohort\local\type\curated;
use tool_mulib\muform\handler;
use core\url;

/** @var moodle_page $PAGE */

require('../../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$cohort = base::fetch($id);
$context = $cohort->get_context();
if (!($cohort instanceof curated) || !$cohort->can_manage_members()) {
    throw new required_capability_exception($context, 'tool/mucohort:managemembers', 'nopermissions', '');
}

$currenturl = new url('/admin/tool/mucohort/management/members_remove_othertenant.php', ['id' => $id]);
$returnurl = new url('/admin/tool/mucohort/cohort.php', ['id' => $id]);

$PAGE->set_context($context);
$PAGE->set_url($currenturl);
$title = get_string('members_remove_othertenant', 'tool_mucohort');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$handler = handler::from_request();

$count = count($cohort->get_other_tenant_memberids());
$form = new \tool_mucohort\local\form\members_remove_othertenant($currenturl, ['count' => $count]);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}

if ($form->get_data()) {
    $cohort->remove_other_tenant_members();
    $handler->submitted($returnurl);
}

$handler->render($form);
