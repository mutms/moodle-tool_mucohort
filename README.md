# Advanced cohort management plugin for Moodle LMS

Advanced cohort management for standard Moodle™ LMS installations — fully open source under GPL 3.0,
with no restrictions on commercial use. Part of the [MuTMS suite](https://github.com/mutms).

Cohorts are the simplest and most predictable way to decide who sees and gets what in Moodle —
unlike role assignments with inheritance, overrides and prohibits, a cohort is an explicit list of users
that can be listed, compared and audited. This plugin adds the management tools that were always missing
in standard Moodle cohorts, without replacing them and without any core modifications.

## Features

* **One page per cohort** with tabs — overview of cohort state, members, exceptions, access, rules, roles and usage
* **Curated cohorts** — membership managed by people, with safe delegation:
    * per-cohort access records for cohort users, member viewers, member managers and exception managers,
      no custom roles needed
    * a curator — the person responsible for the cohort
    * candidate cohort limiting who may be added, without ever removing existing members
    * optional limit of the number of members
* **Rule-based cohorts** — membership calculated from simple rules:
    * flat list of include and exclude rules that everybody understands, no confusing AND/OR trees
    * user fields, custom profile fields, cohort membership, course enrolment and completion,
      program completion and certifications
    * external database rules with secure, administrator-defined queries
    * draft rules with a preview of membership changes before they are approved
    * full or add-only sync, scheduled sync with priorities, manual and CLI sync
    * instant updates on account creation, first login or every login
    * sync history with duration of each run
* **Exceptions** — forced and blocked users for exceptional cases or during migration between cohort types
* **Safe conversions** between standard, curated and rule-based cohorts without any membership changes
* **Usage overview** — see which enrolments, programs, certifications and other features use each cohort
* **Role synchronisation** — assign roles to cohort members in system or category contexts
* **Bulk changes** of members and exceptions from pasted or uploaded user lists with a dry run, and a compare tool
  for adding or removing differences between cohorts
* **Web services** for members and exceptions with the same rules as the user interface
* Works in parallel with the standard cohort UI, which can optionally be replaced
* Built for large sites — cohorts with hundreds of thousands of members and sites with thousands of cohorts
* The only cohort plugin natively compatible with MuTMS multi-tenancy — users of other tenants are never added

## Who may view cohort members

| Cohort | May list members |
|---|---|
| Standard cohorts and cohorts of other plugins | `moodle/cohort:assign` or `tool/mucohort:viewmembers` capability in the cohort context |
| Advanced cohorts (curated, rule-based) | `tool/mucohort:viewmembers` capability in the cohort context, or an access record with "View members" |

Cohort visibility, `moodle/cohort:view` and access records without "View members" allow finding and using
a cohort, not listing its members. Managing members or exceptions in an access record always includes "View members".
Without this plugin only `moodle/cohort:assign` allows listing of members, the same as in standard cohort pages.

For developers: the rule is `can_view_members()` of the cohort type classes. Other MuTMS plugins use
`tool_mulib\local\cohort_util::can_view_members()` and the SQL condition `cohort_util::get_view_members_sql()`,
for example in the "Cohort" filter of user pickers; the three must be kept in sync.

## Requirements

> This plugin will be included in the [MuTMS distribution](https://github.com/mutms/mutms) —
> no manual installation needed if you use the distribution.

Required plugins:

* [Additional tools library plugin](https://github.com/mutms/moodle-tool_mulib)

## AI disclosure

Parts of this plugin were written with the help of Claude (Anthropic). A human
maintainer reviewed, corrected and accepted everything before it was committed.
The design decisions and the final code are the maintainer's own.

---

> MuTMS is an independent open-source project, not affiliated with Moodle HQ.
