# Floating navigation and Recent records

**Status:** Implemented (merged in PR #39). [DESIGN.md](../DESIGN.md) (Navigation) is the source of truth for the shipped design; this document keeps the reasoning and the rules for active state, breadcrumbs and Recent retention. See "As built" below for where the build differs from the original proposal.
**Date:** 2026-10-16
**Mode:** Operate — help clients and staff move between connected records and return to earlier work.
**Visual reference:** The supplied compact floating icon rail that reveals navigation labels when expanded. Keep TA Tracker's existing Working Ledger colors, type, buttons, and restrained surfaces from [DESIGN.md](../DESIGN.md).

## Purpose

TA Tracker has many deep links between agreements, activities, projects, programs, states, organizations, and people. Users need both a reliable sense of location and an easy way to return to records they explored. Breadcrumbs describe location; a separate Recent panel provides return navigation.

The floating rail uses the left gutter without permanently taking a full sidebar's width from record pages or forms. Its expanded state reveals labels and grouped navigation. Searchable history is available from the rail, rather than embedded in the breadcrumb trail.

## Confirmed decisions

- Expand desktop navigation when the mouse enters the rail. A click is not required to reveal labels.
- Highlight navigation from the current route hierarchy, regardless of the entry point. An agreement show page highlights Agreements, even when reached from a project or Recent.
- Keep breadcrumbs as route hierarchy on show, create, and edit pages. Cancel goes to the parent crumb.
- Recent contains at most 30 unique records visited within the last seven days. Revisiting a record moves it to the top and refreshes its last-opened time.
- Ship a searchable list first. A graph, branching exploration trail, and per-click history are outside the initial proposal.

## As built (differences from the original proposal)

- **Pinned or peeking, not hover-only.** The sidebar is pinned open (docked, the page makes room) or unpinned (icons only, the page does not move). Unpinned, it peeks open over the page on hover (120ms in, 250ms out), keyboard focus, the `/` search shortcut, or while Recent or the account menu is showing; Escape dismisses a peek. Unpinning with the mouse collapses the sidebar immediately, and it peeks again only after the pointer leaves and re-enters. The pin choice is stored per user in `localStorage`; new users start pinned at 1200px and wider.
- **Docked, not floating (2026-10-22).** The enterprise restyle made the sidebar a flush, full-height white column with a hairline on its right edge; the Recent panel stays a rounded floating card beside it. Only floating things (the peeking sidebar, Recent, menus) carry a shadow.
- **No desktop top bar.** Search, Log Activity, and the account menu (Profile, Log out) live in the sidebar; phones keep a slim top bar with the menu button, brand, and Log Activity.
- **Admin groups are always listed.** They do not collapse; the sidebar scrolls when the list is taller than the window.
- **Collapsed tooltips were dropped.** Peeking reveals the labels, so icons need no separate tooltip.
- **Recent is stored in the database** (`recent_records`, unique per user, record type and id; pruned to 30 records and seven days on read and write), so it follows a user across devices. The question left open below is resolved.
- **Form actions moved into the page header** (Save, Cancel, status, Ctrl/⌘ + S), and the bottom save bar is gone. See `form-conventions.md`.

## Proposed shell

Use a floating, rounded rail at the left of desktop content, separated from the page edge and aligned near the top of the working area. Reserve room for its collapsed width; expansion overlays the gutter/content edge without changing the main content width or shifting form controls.

The rail contains the user's permitted primary destinations, grouped administrative navigation where applicable, and a separate Recent button with a clock icon. Preserve the destinations and permission rules supplied by `AppNav::primaryLinks()` and `AppNav::adminSections()`; do not create a second permission map for the sidebar. Compact rendering must still accommodate the longer navigation available to viewers and administrators through a scrollable navigation region.

Search (where allowed), Log Activity, and the account menu must stay available when section navigation moves into the rail (as built, they sit in the sidebar). Recent search only filters visited records; global search remains the way to find records that have never been opened.

Follow the current Bootstrap Icons vocabulary. Use Action Blue for the active section and existing neutral surfaces for the floating navigation and panel. Keep content headings and breadcrumb styling from the current header work.

## Expansion and dismissal

### Mouse

1. Entering the collapsed rail expands it and reveals labels and available groups. Opening it does not navigate or change the active section.
2. It remains expanded while the pointer is inside the rail, an expanded navigation group, or its attached Recent panel. The pointer can cross the gap between attached surfaces without closing them.
3. Leaving the combined area collapses transient navigation after a short grace period, provided no keyboard focus or explicitly opened panel requires it to remain open.
4. Navigation links activate on click. Hover reveals navigation, not destination content.
5. Recent opens on click, remains usable while its search has focus, and closes with its close button, Escape, or an outside click. Navigation expansion must not cause Recent to open accidentally.

### Keyboard and touch

- Focusing a rail control expands navigation so keyboard users can see its label. It remains expanded while focus stays within the navigation or an associated panel.
- Provide an explicit navigation toggle for touch and pointer devices without hover. Expose its name, controlled region, and expanded state.
- Escape dismisses the topmost open panel and returns focus to its trigger. Collapsing navigation must never hide the focused control.
- Every compact icon has an accessible name. Collapsed labels or tooltips must not be the only accessible name.
- Use visible focus rings, clear active styling beyond color alone, and touch targets at least 44px high.
- Honor reduced motion. Any width/reveal transition is short and must not delay access to the links.

## Hierarchy-based active state

Use the named route's owning section and the existing `AppNav` active patterns. Do not derive active navigation from the referrer, the most recently selected history item, or the last clicked navigation link.

Examples:

- `agreements.index`, `agreements.show`, `agreements.create`, and `agreements.edit` highlight Agreements.
- An activity opened from an agreement highlights Activities, not Agreements.
- A related organization highlights Organizations when that destination is visible. If it lives within the user's Admin group, indicate the containing group and reveal the active destination when expanded.
- Profile and administrative user pages follow their existing account/administrative route ownership.

Match a visible destination or its containing group. Do not expose otherwise hidden navigation entries just to show an active state. Opening Recent must preserve the underlying section highlight; its open state needs a separate treatment from the active page indicator.

## Breadcrumbs and Back/Cancel

Retain the `RouteBreadcrumbs` helper and its connection to `x-page-header`. For resource routes with the relevant index/show routes, the intended hierarchy is:

- Index → record on show pages.
- Index → Create on create pages.
- Index → record → Edit on edit pages with a show route.
- Index → Edit when the resource has no show route; Profile → Edit for profile editing.

Breadcrumbs do not include an unrelated record just because it was visited earlier. The current item exposes `aria-current="page"`. A destination must remain authorized when followed; the breadcrumb is not an authorization mechanism.

Form Cancel uses `RouteBreadcrumbs::cancelUrl` (the previous record or list page, else the parent crumb); there is no session back trail. Preserve the page-header props and slots; the proposed component API refactor remains deferred. Preserve the agreement type badge and the other completed header/section-heading improvements.

## Recent panel

The clock button opens a panel next to the desktop rail. The panel includes a Recent heading, a named close button, a search field labeled "Search recent records", a last-opened list, and a Clear recent action.

Each row shows a record title, entity type, and last-opened time. Mark the current record explicitly. Use the same record names and entity vocabulary as the rest of the app. Search matches title and entity type case-insensitively within the retained list. Opening a row navigates to the record's canonical show page, closes the panel, and updates the route-derived section highlight.

Show a helpful first-use message when the list is empty, and a distinct "No recent records match" result when filtering finds nothing. Loading, network failure, and a record becoming unavailable must have understandable recovery states. A permission failure must not leave a stale title visible in the list.

### Retention and identity

- Identify a record by its entity type and stable record ID (or equivalent canonical show-route identity), not by route name alone. Different agreements must remain distinct entries.
- Keep only the latest visit for each record. Ignore filters, pagination, fragments, and query strings when identifying the record.
- Record successful full record-page visits. Do not record create/edit forms, search queries, attachment URLs, API calls, or background/htmx requests as separate history entries.
- Store only the record identity and most recent visit time needed for the list. Resolve current display labels and canonical URLs through the application.
- Remove entries older than seven days, using the latest visit time. Sort newest first and trim to 30 entries after deduplication and expiry.
- Scope storage to the signed-in user. Recent must not expose a previous user's entries on a shared browser or after an account switch.
- Recheck access before displaying entries, and keep the normal destination authorization checks. Hide deleted or inaccessible records according to the application's access rules. An inactive record that remains viewable need not be hidden merely for being inactive.
- Clear recent removes this user's retained Recent entries, including any loaded UI copy. It does not clear unrelated application state.

As built, the store is the `recent_records` table, which supports the seven-day window and follows the user across devices.

## Responsive behavior

On wide screens, keep the collapsed rail in the left gutter. The expanded rail and Recent panel must remain within the viewport and avoid clipping inside scrollable ancestors. An open panel must not silently shrink or reflow the record page.

When the gutter is insufficient, use an explicit compact navigation control and a touch-friendly overlay or sheet. Mobile does not depend on hover and does not reserve a permanent desktop sidebar. Keep Recent search and close controls visible when its list scrolls, accommodate the on-screen keyboard, and retain access to the form actions (now in the sticky page header).

Choose the breakpoint from the existing content/container widths during implementation. Verify index tables, long record titles, centered create/edit forms, and short viewports rather than assuming every page has unused space on the left.

## Connection diagrams: deferred

A list of unique records cannot reconstruct an exploration path. Connecting list rows would incorrectly imply either chronological transitions or actual entity relationships, especially after revisits reorder the list.

If a later version needs connections, first define whether an edge represents a visited link or an actual relationship. A bounded "last opened from" reference per record is a possible starting point, but it is not part of the accepted first version and must not quietly become a full event log.

## Acceptance checks

Status: the retention, access, clear, role-navigation, active-state and control checks are covered by feature tests (`RecentRecordsTest`, `AppShellNavigationTest`). Hover, pin, peek, Recent and the mobile menu were checked in a browser at 1440px and 390px. Not verified: screen readers, real touch hardware, Safari and Firefox, and reduced-motion behavior.

- Hover reveals labels; leaving the combined navigation/panel area does not prematurely close the active interaction. Expanding navigation does not shift content.
- Keyboard focus and touch can open and use the same destinations without hover. Escape and focus return work for navigation groups and Recent.
- Direct links, browser Back/Forward, breadcrumb links, relationship links, and Recent visits all select the route's owning section.
- Existing role-specific navigation, global search, Log Activity, account controls, skip link, and form save behavior remain available.
- Seven-day expiry, the 30-record cap, deduplication, revisits, title/type search, empty results, and Clear recent behave as specified.
- Deleted records, revoked access, and account switches do not disclose stale names or links.
- Browser checks cover desktop and mobile, long labels, scrollable navigation/history, reduced motion, overflow, and console errors.
- Add focused regression tests for the changed state/permission/retention behavior. Do not add tests that only repeat CSS declarations.

## Boundary and what is still deferred

The shell is the official navigation system and is described in [DESIGN.md](../DESIGN.md). Still deferred: connection diagrams (above) and pinned or favorite destinations, which would belong on the Home page.
