# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Three primary groups, all signed-in staff of the Innovate Impact / UConn SSW II program. The README and `AccessProfile` enum identify the roles.

- **Staff and consultants** log activities against agreements, and view the projects, programs, teams, agreements, and records they are assigned to. An "Input User" profile limits some people to Home, Profile, and activity logging only.
- **Supervisors and project leads** review supervisees' work and progress against agreements.
- **Admins and enhanced viewers** view or edit records in an assigned project, program, or system-wide scope, and maintain reference data, certificates, tools, logging fields, and users.

Users learn the app **self-serve**: new users must understand it from the interface alone, so the first screen and each page have to explain themselves.

## Product Purpose

An administrative tool for tracking and reporting staff activity to fulfill agreements between organizations across states, programs, and projects. Success means the four jobs below are fast and trustworthy, and a user can tell where they are and how records connect.

1. Fast, accurate activity logging (the most frequent task).
2. Proving agreements are fulfilled: deliverables, time tracking, funding sources, and reports.
3. Tracking certification: certificates, tools, requirements, and who has met them.
4. Understanding how organizations, states, projects, programs, teams, agreements, and people relate.

## Operating Context

Desktop-first office work in long sessions with many records. Users move between connected records and return to earlier work, so orientation and return navigation matter. Forms are long and some record shapes are complex (agreements with deliverables of different shapes, certificates, certification tools).

## Capabilities and Constraints

- Entities: agreements (with deliverables, attachments, KFS funding accounts, memberships), activities and activity types, logging fields, organizations and contacts, states, projects, programs, teams, users, certificates and certification tools, reports.
- Navigation and visibility are role-dependent. `AppNav` is the single source for primary and admin destinations and must remain the one permission map.
- Stack: Laravel on PHP 8.4, Blade, Bootstrap 5.3, htmx, Vite. Existing server-rendered tables use htmx with pushed URLs, so filtered and sorted views stay shareable.
- Accessibility benchmark used by the design audit: WCAG 2.2 AA.
- Undecided: whether recent-record history follows a user across devices; mobile usage level.

## Brand Commitments

Product name: TA Tracker (organization: Innovate Impact, UConn SSW II). No logo, brand color, or voice commitments are confirmed; the current Bootstrap blue is a default, not a brand asset.

## Evidence on Hand

Live app with seeded demo data (`DemoSeeder`). `docs/design-audit.md` lists current findings; `docs/form-conventions.md` records form rules; `docs/sidebar-navigation-design.md` records the floating-rail proposal. There are no user research notes, analytics, or testimonials; do not invent any.

## Product Principles

1. **Orient first.** At any page a user can answer where they are, what this record is, and what to do next.
2. **Logging is the hot path.** Recording an activity should stay one action away and never be buried by reference or admin features.
3. **Show relationships, don't dump them.** Summarize connected records and let people follow them, rather than showing every field and chip at once.
4. **One pattern per page type.** Lists, record pages, and forms each follow a single template so learned behavior transfers across entities.
5. **Permissions shape the interface.** Each role sees only what it can use, with no dead ends.

## Accessibility & Inclusion

Target WCAG 2.2 AA. Keyboard-only and screen-reader use are in scope; icon-only controls need accessible names and meaning must not rely on color alone.
