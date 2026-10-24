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

/**
 * Cohort management settings.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\url;

defined('MOODLE_INTERNAL') || die();

$settings = new admin_settingpage(
    'tool_mucohort_settings',
    new lang_string('settings', 'tool_mucohort'),
    'moodle/site:config'
);
/** @var admin_root $ADMIN */
$ADMIN->add('accounts', $settings);

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox(
        'tool_mucohort/takeover',
        new lang_string('setting_takeover', 'tool_mucohort'),
        new lang_string('setting_takeover_desc', 'tool_mucohort'),
        0
    ));

    $settings->add(new admin_setting_configmulticheckbox(
        'tool_mucohort/enabledtypes',
        new lang_string('setting_enabledtypes', 'tool_mucohort'),
        new lang_string('setting_enabledtypes_desc', 'tool_mucohort'),
        ['standard' => 1, 'curated' => 1, 'rules' => 1],
        [
            'standard' => new lang_string('type_standard', 'tool_mucohort'),
            'curated' => new lang_string('type_curated', 'tool_mucohort'),
            'rules' => new lang_string('type_rules', 'tool_mucohort'),
        ]
    ));

    $settings->add(new admin_setting_configcheckbox(
        'tool_mucohort/allowsynconlogin',
        new lang_string('setting_allowsynconlogin', 'tool_mucohort'),
        new lang_string('setting_allowsynconlogin_desc', 'tool_mucohort'),
        1
    ));

    $settings->add(new admin_setting_configduration(
        'tool_mucohort/profilefieldlifetime',
        new lang_string('setting_profilefieldlifetime', 'tool_mucohort'),
        new lang_string('setting_profilefieldlifetime_desc', 'tool_mucohort'),
        HOURSECS,
        MINSECS
    ));

    $settings->add(new admin_setting_configduration(
        'tool_mucohort/syncretention',
        new lang_string('setting_syncretention', 'tool_mucohort'),
        new lang_string('setting_syncretention_desc', 'tool_mucohort'),
        YEARSECS,
        DAYSECS
    ));
}

if (!get_config('tool_mucohort', 'takeover')) {
    // With takeover enabled the core cohort page is redirected to the new management instead.
    $ADMIN->add('accounts', new admin_externalpage(
        'tool_mucohort_index',
        new lang_string('management', 'tool_mucohort'),
        new url('/admin/tool/mucohort/index.php', ['contextid' => context_system::instance()->id]),
        ['moodle/cohort:manage', 'moodle/cohort:view']
    ), 'tool_mucohort_settings');
}
