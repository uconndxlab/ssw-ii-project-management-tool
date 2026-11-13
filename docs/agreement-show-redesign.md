# Agreement show page redesign: execution plan

**Status:** built 2026-11-12 (uncommitted on `feat/agreement-show`). Revised the same day after review: decisions 2, 3, 4 and 15 below changed, and Staff is grouped by team.
**Decided in:** design sessions on 2026-11-11 (drafts 1–4 plus follow-ups). Every decision below was confirmed by Will unless it's listed under Open questions.
**Ticket:** https://trello.com/c/H6D0Snzs (SSW II PM Tool board, Backlog, P2)
**Background:** `DESIGN.md`, `docs/design-audit.md`, `docs/access-rules.md`.

---

## 1. Goal

The Overview tab should answer, at a glance: where the agreement is in its period, whether deliverables are on track, how candidates are progressing, what work was logged recently, and who and what is connected. Every connected record is one obvious click away, and nobody has to leave the page to see a long list.

## 2. Decisions (locked)

1. **Layout:** header, then the record bar (tabs and actions), then the Overview in two columns. The wide column holds the work: Deliverables, Candidates, Activity. The narrow column holds connections: About, Organizations, Staff, Coverage.
2. **Header facts:** Period (with days left, over a thin elapsed-time track with a Today mark) and Principal investigator only. No year-to-date metric anywhere on the agreement page. Agreements use their own period.
3. **Record bar:** a full-width white card under the header. House underline tabs on the left (steel rule on the active tab); small action buttons on the right: Log activity (primary), Edit, ⋯. Stuck, it floats 0.5rem below the top with a hairline border and a heavy shadow so it stands apart from the content scrolling under it. The title row has no actions. *(Revised 2026-11-12: was right-aligned.)*
4. **Sticky bar:** when the header scrolls away, the bar sticks to the top and the record title grows in on the left, pushing the tabs right once (150ms). *(Revised 2026-11-12: tabs may shift once; was "never move".)*
5. **No counts on tabs.** Counts appear only beside section headings.
6. **One count style:** a neutral badge (pill). Status chips stay for statuses. Links and "Show all" carry no numbers.
7. **Restricted records:** the total includes them, with a lock badge "N restricted" beside it. "Restricted" means the viewer cannot open them. Use the lock icon, never eye-slash.
8. **Section headings sit on the page canvas**, above a white card. No card title bars, and no collapsible grey disclosure bands on the Overview.
9. **Clickable rows:** a row that opens one record has an underlined name, a light trailing chevron, and a full-row hover and click target. A row that opens nothing is plain body text with no chevron and no hover. A row with several destinations (candidate plus certificate) has separate underlined links and no row chevron.
10. **Deliverables and Certification stay separate.** Each card handles its own attention items; there is no combined "needs attention" list on this page.
11. **Needs attention** (Deliverables card): Off track, Needs attention, and No assignees. The selection takes a mix of statuses; the display groups them by status, never alternating. No footer counts.
12. **Candidates:** a small card showing every candidate in roster order. The tab name stays "Certification".
13. **Reporting** stays a tab only, with nothing on the Overview.
14. **Long lists:** scroll inside the card, using the existing pattern in `DESIGN.md`, plus an in-place filter above a size. Nobody navigates off the page. No drawer, modal, "Related" tab, or link to a filtered list page.
15. **Natural column heights.** *(Revised 2026-11-12: exact balancing made Staff awkwardly tall.)* No stretching; cards stay compact (one-line empty states, 5 recent activity rows, lists capped by the scroll threshold) and in-place reveals animate. Staff stays between Organizations and Coverage, grouped by team with one-line rows.
16. **About** comes first in the narrow column: the abstract clamped with "Read more", plus attachments.
17. **The Deliverables tab** status display switches to the same status chips (currently icon plus coloured text).
18. **Charts** follow the dataviz rules: no hatching or texture, narrow bars, a hover tooltip, and a text or table equivalent.

## 3. Out of scope (later)

- A separate full-list page for long relations ("maybe at some point").
- Applying this layout to other show pages. Rule already agreed: the wide column holds what the record exists for, which is connected records on organization, program, state and project pages. Whether activity comes first on those pages is not decided yet. Pages without tabs probably get an actions-only bar (leaning yes).
- The home page "needs attention" or notifications.
- Counting "No progress made" as needing attention: Trello #40 (P3).
- New header facts: the client is being asked (payor? others?). Don't add any until they answer.

## 4. Open questions (use the default until answered)

| Question | Default for the build |
|---|---|
| Scroll and filter thresholds | Scroll after 6 rows, filter after 10 rows. Keep them as one set of constants and tune them in the browser. |
| Card limits | Needs attention 4 rows; About 4 lines; Activity at least 3 rows and up to 10; Staff box at least 4 rows; Coverage chips always shown in full (revised 2026-11-13; was 6 states then "Show all") |
| Activity bucket size for long periods | Monthly up to 24 months; quarterly beyond that |
| Extra header facts | None until the client answers |

## 5. Target layout

```
Agreements / Indiana FOCUS Implementation 2025
[▣] Indiana FOCUS Implementation 2025   ● Active
Period                                Principal investigator
Jan 1 – Dec 31, 2026 · 50 days left   David Thompson

                 ┌──────────────────────────────────────────────────────────────────────────┐
                 │ Overview  All activity  Deliverables  Certification  Reporting │ [+ Log activity] [Edit] [⋯] │
                 └──────────────────────────────────────────────────────────────────────────┘

Deliverables (11)          View deliverables →    About
┌──────────────────────────────────────────┐      ┌───────────────────────┐
│ ▬▬▬▬▬▬ ▬▬▬▬▬▬▬▬ ▬▬ ▬▬▬▬ ▬▬  thin status bar│      │ abstract, 4 lines…    │
│ ●3 Off track ●4 No assignees ●1 Progress… │      │ Read more             │
│ Needs attention                           │      │ 📎 files              │
│ Implementation Fidelity…   Off track    › │      └───────────────────────┘
│ FOCUS Supervisor Training  Off track    › │      Organizations (3)
│ Any Assessment & Review…   No assignees › │      ┌───────────────────────┐
│ FOCUS SRT Scoring Meeting  No assignees › │      │ rows with tags, PO, KFS│
└──────────────────────────────────────────┘      └───────────────────────┘
Candidates (3)            View certification →    Staff (5)        ← elastic
┌──────────────────────────────────────────┐      ┌───────────────────────┐
│ Michael Brown  Doc 3 Demo…  ● Endorsed    │      │ David Thompson [PI]  › │
│                TEMP Visual… ● Assigned    │      │ …fills to the line,    │
│ Sarah Martinez No certificate assigned    │      │   then scrolls         │
└──────────────────────────────────────────┘      └───────────────────────┘
Activity (22)            View all activity →      Coverage
┌──────────────────────────────────────────┐ ←    ┌───────────────────────┐
│ monthly bars across the period + Today    │ elastic Projects (2) chips  │
│ Date | Activity | Logged by | ⋯   (3–10)  │      │ Programs (4) chips    │
└──────────────────────────────────────────┘      │ States (15) chips…    │
──────────────── both columns end here ────────── └───────────────────────┘

Stuck state:
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│ [▣] Indiana FOCUS Implementat…    Overview  All activity  …  Reporting │ [+ Log activity] [Edit] [⋯] │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

## 6. Current code map

| Area | File | Notes |
|---|---|---|
| Page | `resources/views/agreements/show.blade.php` (392 lines) | Overview is lines 60–230; other tabs below |
| Controller | `app/Http/Controllers/AgreementController.php` `show()` (line 255) | Computes `ytdTotals`, `lifetimeTotals` (counts **all** activities), `recentActivities` (`visibleTo`, limit 5), `deliverableGroups`, `certificationRosterDisplay()` (line 354; drops enrollments for certificates the viewer can't see) |
| Deliverable statuses | `app/Support/AgreementDeliverableDisplay.php` | `buildGroupedProgress()` returns families → activity groups → program groups → `deliverables` items with `status`, `has_target`, `is_individual`, `rollup_on_track`, `allocation_notices`, `percent`, `metric_summary`, `unit_label`. `statusForAssignment()` (line ~1229) returns `null` when the target is ≤ 0. |
| Status enum | `app/Enums/DeliverableStatus.php` | Tone mapping: info for Progress made (changes, see §7.3) |
| Rollup | `app/Support/DeliverableAssignmentTargets.php::rollupIndividualStatus()` | Returns `null` when nobody has a share, which is the "No assignees" case |
| Deliverables chart | `app/Support/DeliverableActivityHistogram.php` | Counts **deliverable contributions**, not activities. Don't reuse it for the Overview chart. |
| Panels | `resources/views/components/relationship-scroll-panel.blade.php` | Used on 11 views; holds the eye-slash hidden note and the hidden footer row |
| Rows | `components/relationship-ledger-row.blade.php`, `relationship-list-item`, `organization-relation-row`, `staff-member-row`, `organization-contact-row` | |
| Counts | `.app-count` (`resources/css/app.css` ~2391), `components/entity-count-badge.blade.php` | `.app-count` is used in 12 views |
| Hidden or restricted | `.app-hidden-note`, `.app-hidden-pill`, `.app-more-hidden`, `.app-ledger-hidden-row` (app.css ~2554–2600); `certification-candidates/partials/hidden-count.blade.php`, `heading-count.blade.php`; `components/table-badge-list.blade.php` (`hiddenCount`) | |
| Chevron bug | app.css `.app-ledger-toggle .bi` (~2689) and the reduced-motion block (~2791) | Rotates every icon in the toggle, including eye-slash |
| Scroll pattern | `resources/js/relationship-scroll.js`, app.css `.app-ledger-body`, `.app-ledger-scroll-frame`, `.app-ledger-scroll-status` | Already does the edge cue, "N–M of X · more below" caption, and `tabindex`/`role`/`aria-label` when overflowing |
| Tabs | `components/section-tabs.blade.php`, `resources/js/section-tabs.js` | Hash tabs, `data-section-goto`, `navigateSection()`. Also used by the certificate and candidate show pages and the certificate form. |
| Header | `components/page-header.blade.php` | `facts` slot (line 286), `action-url`, `more` slot |
| Sticky reference | `resources/js/form-header.js` | IntersectionObserver plus `is-visible`; reuse the approach |
| Recent table | `components/recent-activity-table.blade.php` | Renders Log Activity only when `logActivityUrl` is set |
| Tests | `tests/Feature/AgreementShowTest.php`, `tests/Feature/RelationshipLedgerTest.php`, `tests/Feature/ActivityViewerScopeTest.php` | |
| Users list access | `app/Policies/UserPolicy.php::viewAny` | Viewers and supervisors only. That's one reason nothing links off to list pages. |

## 7. Specs

### 7.1 Counts and restricted records

- Restyle `.app-count` globally as the neutral badge: inline-flex, min-width 1.25rem, height 1.125rem, 0 0.375rem padding, full radius, `--bs-secondary-bg-subtle` background, `--bs-secondary-text-emphasis` text, 0.6875rem, weight 600, tabular figures. Every existing `.app-count` and `x-entity-count-badge` picks it up.
- Add a `x-restricted-count :count :kind` component that renders `<span class="app-count app-count--restricted" title="You don't have access to N {kind}"><i class="bi bi-lock-fill" aria-hidden="true"></i>N restricted</span>`. Nothing renders when the count is 0.
- Replace every eye-slash usage with it: the panel header and footer row (delete the footer row), the candidate `hidden-count` and `heading-count` partials, and the text inside `table-badge-list`'s menu. Delete the unused `.app-hidden-*` CSS afterwards.
- Rule: the badge shows the total, including restricted records; the restricted badge sits right after it.
- Activity count: the total is `$agreement->activities()->count()` (as today). Restricted = total − `activities()->visibleTo(actor)->count()`.
- Candidate certificates: count, per candidate, the enrollments dropped by `certificationRosterDisplay()` because the certificate isn't visible. Show "🔒 N restricted" on that candidate's row.

### 7.2 Clickable rows (`relationship-ledger-row`)

- With `href`: add class `app-ledger-row--link`, stretch the name link across the row (same technique as `.app-row-link`), add a trailing `bi-chevron-right` (decorative, `aria-hidden`, muted, darker on hover), and use a `--app-hover` fill on hover and focus-within. Tags, badges and inner links sit above the stretched link (z-index layer 2, as in the table stacking rules).
- Without `href`: no chevron, no hover, and the name in body colour.
- Add a `multiLink` prop (or detect an `actions`/`footer` slot holding links): no stretched link and no chevron.
- Keep the arrow (`bi-arrow-right`) only for section links ("View all activity →").

### 7.3 Deliverable buckets and status bar

- New support class (via `vendor/bin/sail artisan make:class Support/AgreementOverview`) flattens `buildGroupedProgress()` output into items and assigns each **exactly one** bucket:

| Bucket | Rule | Tone block |
|---|---|---|
| off_track | status OffTrack | danger |
| needs_attention | status NeedsAttention | warning |
| no_assignees | status null, `has_target`, `is_individual`, `rollup_on_track.total === 0` | warning |
| no_progress_made | status NoProgressMade | neutral |
| progress_made | status ProgressMade | progress (light green `#7fbf99`) |
| on_track | status OnTrack | success |
| complete | status Complete | success |
| no_target | status null and `has_target` false | neutral |
| not_applicable | status NotApplicable (no dates, or start in the future) | neutral |

- A deliverable that is partly unassigned **and** has a status keeps its status bucket. The unassigned amount stays a note on its row; it is never a bar segment.
- Assert in tests that every item lands in a bucket and that bucket counts add up to the number of active (non-retired) deliverables.
- **Bar:** thin (8px), with 2px gaps and fully rounded ends. Five tone blocks in a fixed order: danger | warning | progress | success | neutral. Zero-count blocks draw nothing.
- **Chips** below the bar act as the legend, in the same order: one `.app-status` chip per non-empty bucket, reading "{count} {Label}".
- Add an `.app-status--progress` modifier (success-subtle background, success ink, `#7fbf99` dot).
- Colour check: the dataviz validator (`node scripts/validate_palette.js "#c62d3a,#b7791f,#7fbf99,#157347,#cfd5dd" --mode light`) passes CVD separation (worst ΔE 8.4) and the normal-vision floor (16.3). The gray block's low contrast is covered by the chip labels.
- If every item is not_applicable, replace the bar with a note: "Statuses need start and end dates." or "Statuses start on {start date}."
- The Overview always uses the default window (agreement start to effective end), even when the Deliverables tab has a `from`/`to` filter in the URL. Only compute a second time when such a filter is present.

### 7.4 Needs attention

- **Included:** off_track, needs_attention, no_assignees. Severity is in that order.
- **"Most behind" within a status:** for off_track and needs_attention, the lowest `percent` first, ties by name. For no_assignees, the highest unassigned share first (`allocation_notices.unassigned_remainder / target`), ties by name.
- **Selection (cap 4):** go round-robin through the statuses present, in severity order, taking each status's next most-behind item until 4 are taken or none remain.
- **Display:** sort the selected items by severity, then by most-behind. Statuses never alternate.
- **Row:** the title is the activity type label; when it's "Any activity type", use "Any {family} activity". The meta line is "{family} · {metric_summary} · {counted} of {target} {unit}", or "{remainder} {unit} unassigned" for no_assignees. A status chip sits on the right, then the chevron.
- **Row link:** `#deliverables` with `data-section-goto="deliverables"`. After switching tabs, scroll to `#deliverable-{id}` and expand that card (add the ids in the Deliverables tab partial; add a small handler in `section-tabs.js` or a new module).
- **Empty states:** if there are no deliverables, "No deliverables set up." If none need attention, "Nothing needs attention", with "No deliverables are off track or missing assignees."
- No footer counts. The header link "View deliverables →" goes to the tab.

### 7.5 Long lists: scroll and filter

- Applies to the Organizations, Staff and Candidates cards. Thresholds are constants in one place (a component prop default or `config/ui.php`): `scrollAfter = 6`, `filterAfter = 10`.
- **Up to 6 rows:** all rows show; no scrolling, no filter.
- **More than 6:** the list body gets a max height of 6 rows and reuses `relationship-scroll.js`: always-visible styled scrollbar, bottom edge shadow while more is below, caption "1–6 of N · more below", and `tabindex="0"`, `role="region"` and `aria-label` only while overflowing. Leave `overscroll-behavior` at its default so scrolling passes back to the page at either end.
- **More than 10:** a labelled search input ("Filter organizations") appears above the list. A new module, `resources/js/relationship-filter.js`:
  - matches case-insensitively on the row's `data-ledger-title` and meta text;
  - hides non-matching rows and wraps the match in `<mark>` in the name (built with text nodes, never innerHTML);
  - swaps the caption to "N of M match", and shows "No {kind} match "x"." when nothing matches;
  - clears on Esc;
  - announces the match count through a visually hidden polite live region (the scroll caption itself stays non-live).

### 7.6 Even column ending

- CSS: the Overview grid has two columns that stretch to the same height. Each column is a flex column with a 14px gap. The elastic section is `flex: 1 1 auto; min-height: 0` with its card filling it.
- **Elastic cards:** Activity (wide column) shows extra recent rows; Staff (narrow column) has a list box that grows.
- New module `resources/js/overview-balance.js`. On load, `document.fonts.ready`, resize (a `ResizeObserver` on both columns), and after any "Read more", "Show all" or filter toggle:
  1. Reset the elastic lists to their minimum: Activity 3 rows (render up to 10 and hide the extra rows), Staff box 4 rows.
  2. Measure the natural height of both columns.
  3. Wide column shorter: reveal hidden Activity rows one at a time while each **whole** row fits into the gap. Any leftover space stays inside the stretched card.
  4. Narrow column shorter: raise the Staff box's max height by the gap, capped at its content height. If Staff runs out of rows, the card keeps its leftover space.
  5. Batch writes in `requestAnimationFrame`; don't measure inside loops that also write.
- While a filter is active, keep the Staff box's height fixed so the layout doesn't jump.
- Below the `lg` breakpoint there's one column: no balancing, minimum sizes only.

### 7.7 Record bar and sticky state

- Extend `x-section-tabs` with an optional `actions` slot and a `sticky-title` prop. When `actions` is given, render the record-bar layout. Keep `data-section-tabs`, `data-section-nav` and the hash behaviour unchanged so `section-tabs.js` keeps working. Pages that don't pass `actions` render exactly as today.
- **In place:** the bar is inline-flex and pushed right (`margin-left: auto`). It's a white surface with the card shadow, 8px radius and 40px height. It holds the tab links, then a hairline, then the actions.
- **Actions:**
  - Log activity: `btn-primary btn-sm`, linking to `route('activities.create', ['agreement_id' => $agreement->id])`. When the agreement is inactive the button is disabled, with the visible text "This agreement is inactive." right next to it.
  - Edit: `btn-outline-primary btn-sm`, only if the user `can('update')`.
  - ⋯ menu: Duplicate, reusing the existing form and confirm.
- **Sticky:** add a sentinel just above the bar and an IntersectionObserver (as in `form-header.js`). When the bar stops intersecting:
  - add `.is-stuck`: `position: sticky; top: var(--app-topbar-height, 0)`, full width, stronger shadow;
  - reveal the title block (record icon plus truncated name, max 40% of the width) on the left. Tabs and actions stay right-aligned, so they don't move sideways;
  - fade or slide the title in over 150ms; with `prefers-reduced-motion: reduce`, no transition.
- **Stacking:** above page content; below open dropdowns, drawers and the confirm dialog (follow the stacking rules in `DESIGN.md`).
- **Accessibility:** `<nav aria-label="Agreement sections">`, `aria-current` on the active tab, visible focus rings. The stuck title is `aria-hidden`, since the page h1 already names the record.
- **Narrow windows:** the title truncates first. Then the tabs overflow inside the nav with horizontal scrolling (phones are not a priority).
- **Page header:** stop passing `action-url` and `more` on the agreement page. Keep the breadcrumb, record icon, title, status chip and the `facts` slot.

### 7.8 Header facts

- **Period:** "{start} – {effective end}", followed by one of:
  - "· N days left";
  - "· Starts in N days";
  - "· Ended N days ago".
- **Extended:** add "· extended from {original end}" in muted text.
- **Missing dates:** "Not set".
- **Principal investigator:** `x-user-link` for each PI, comma-separated, or "Not assigned".
- Remove the Summary card, including the `app-record-period`/`app-record-metrics` YTD block. Stop computing `ytdTotals` in `show()`. Other pages keep their YTD for now.

### 7.9 Activity chart (Overview)

- **Data:** a new method on `AgreementOverview` groups **all** the agreement's activities by `engagement_date`, so the bars add up to the badge total.
- **Range:** agreement start to effective end, stretched to include any activity dated outside it. Monthly buckets up to 24 months, quarterly beyond.
- **Bars:**
  - one hue (`--app-accent`), at most 16px wide, 3px top radius, no labels on the bars;
  - the hovered or focused bar uses accent-ink and shows a tooltip "July 2026 · 9 activities";
  - each bar has an `aria-label`.
- **Today:** a 2px ink line labelled "Today" when today is inside the range. Months after today get a flat `--app-surface-fill` tint, with no hatching.
- **Text equivalent:** a visually hidden table (period, count) next to the chart, plus a caption "Activities logged per month in the agreement period". The container has `role="img"` with a summary `aria-label`.
- **Recent rows:** `x-recent-activity-table` with no `logActivityUrl`, and `view-all-section="activity"`. Order and visibility are unchanged (`visibleTo`).

### 7.10 Other cards

- **Candidates:**
  - rows come from `certificationRosterDisplay()`, already in roster order (`sort_order`, then id);
  - the name links to `certification-candidates.show` when the viewer can `view` that candidate, otherwise plain text;
  - each enrollment shows the certificate name, linking to `certificate-enrollments.show` with `agreement_id` as today, a `lifecycleBadge()` chip, and meta "Coach {name}" or "No coach selected";
  - no enrollments: muted "No certificate assigned";
  - restricted certificates per §7.1;
  - header link "View certification →" goes to `#certification`.
- **About:**
  - the abstract is clamped to 4 lines. A "Read more"/"Show less" button (`aria-expanded`) appears only when the text overflows, and expands in place;
  - attachments are listed below it: filename link to `download_url` (new tab) with a `bi-download` icon, or muted "No files attached";
  - if the agreement has no abstract and no attachments, the About card shows "No summary or files added." (revised 2026-11-13; it was hidden).
- **Organizations:**
  - rows use `x-organization-relation-row` (tags, PO, KFS);
  - sort payor sources first, then recipients, then the rest, alphabetical within each.
- **Staff:**
  - unique users from direct assignment plus team membership; nobody appears twice;
  - meta is the team names, or "Added directly";
  - PI tag; PIs first, then alphabetical;
  - rows link via `UserProfileLink::route()`, or are plain when that's null;
  - when the agreement has teams, a first row reads "Teams: [team chips]", linking to `teams.show`.
- **Coverage:**
  - groups for Projects, Programs and States, each with a count badge, as linked chips (`a.badge.app-chip`, or plain `.app-chip` when not viewable);
  - Programs adds "Also logged under {links}" for the activity-only programs (replaces the "Programs in Activity" panel), which don't count toward Programs;
  - States shows the first 6 chips, then a "Show all" button that expands in place (`aria-expanded`).

## 8. Phases

Each phase leaves the app working. Run the phase's tests before moving on.

### Phase 0: Prep
1. Branch from the agreed base (Will picks it).
2. Take baseline screenshots at 1440 and 1280 wide: the agreement Overview and Deliverables tab, plus one show page per user of `relationship-scroll-panel` (organization, program, project, state, team, user, activity, logging field, candidate). Save them to `.artifacts/`.
3. Run `vendor/bin/sail artisan test --compact tests/Feature/AgreementShowTest.php tests/Feature/RelationshipLedgerTest.php` as the baseline.

### Phase 1: Shared fixes (every page that uses panels)
1. Fix the chevron rotation in app.css (`.app-ledger-toggle > .bi-chevron-right` only, including the reduced-motion block).
2. Apply the count badge style from §7.1.
3. Build `x-restricted-count`, replace the eye-slash usages, delete the panel's footer row and the dead `.app-hidden-*` CSS.
4. Apply the clickable row rules from §7.2 in `relationship-ledger-row`.
5. Restyle the collapsible panel itself as the contained-box design from the first draft (white box, inner hairline header, count badge, lock). Other pages keep collapsible panels until their own redesign.

**Tests:** update `RelationshipLedgerTest` (no footer hidden row; restricted badge text and lock; link rows have the chevron, plain rows don't). Run the candidate tests that cover hidden counts.

**Done when:** every page from the Phase 0 list renders without regressions (check the screenshots).

### Phase 2: Overview data (no UI yet)
1. Create `app/Support/AgreementOverview.php` with `deliverableSummary()` (buckets, bar blocks, attention selection), `activityTimeline()`, `staff()`, `organizations()`, and `restrictedActivityCount()`. Add PHPDoc array shapes and strict return types.
2. Wire it into `AgreementController::show()`. Remove `ytdTotals` for agreements and keep everything else.

**Tests:** new `tests/Feature/AgreementOverviewTest.php` (use factories and existing states):
- buckets add up to the active count, and retired deliverables are excluded;
- no_assignees and no_target are classified correctly, and a partly-unassigned deliverable keeps its status;
- needs attention: round-robin selection, grouped display, a cap of 4, and No progress made excluded;
- the timeline sums to the total, the range stretches for out-of-period activity, and buckets go quarterly past 24 months;
- staff are unique with PIs first;
- organizations are ordered payor, recipient, then others;
- the restricted activity count matches `visibleTo`.

### Phase 3: Header and record bar
1. Add the header facts per §7.8 and remove the Summary card.
2. Extend `x-section-tabs` per §7.7 and add `resources/js/record-bar.js` (registered in `resources/js/app.js`).
3. Move Log activity, Edit and Duplicate into the bar, and remove them from the page header and the activity card.

**Tests:** in `AgreementShowTest`:
- facts text for active, not-started, ended and extended agreements;
- no YTD markup;
- tabs carry no count markup;
- Log activity is disabled with its reason on inactive agreements;
- Edit is hidden without update permission;
- the Reporting tab is still gated (existing tests stay green);
- certificate and candidate show pages still render their tabs unchanged.

### Phase 4: Overview layout
1. Add partials under `resources/views/agreements/partials/overview/`: `deliverables`, `candidates`, `activity`, `about`, `organizations`, `staff`, `coverage`. Add a small `x-record-section` component (heading on canvas, count badge, optional restricted badge, optional section link, and a white card slot).
2. Add the deliverable ids and the deep-link handler (§7.4), and the Read more and States "Show all" toggles.
3. Status bar markup and the `.app-status--progress` CSS (§7.3); chart markup (§7.9).

**Tests:** the Overview renders each section with real factory data: empty states, the candidate link gating, restricted badges, and the "Also logged under" programs not counted.

### Phase 5: Long lists
1. Add scroll thresholds to the Organizations, Staff and Candidates lists, reusing `relationship-scroll.js`.
2. Add `relationship-filter.js` per §7.5.

**Tests:** above 6 rows the list has the scroll-region markup and caption hook; above 10 rows the labelled filter input renders; at or below 6, neither does.

### Phase 6: Even ending
Add `overview-balance.js` per §7.6. This is browser-verified only, at 1440 and 1280, with the left column longer, the right column longer, and a sparse agreement.

### Phase 7: Other tab touch-ups
1. In the Deliverables tab, replace `x-deliverable-status` with `.app-status` chips using the §7.3 tones.
2. Rename the Deliverables tab chart caption to "Deliverable progress logged over the agreement period", since it counts contributions, not activities.
3. In the Certification tab, link candidate names to `certification-candidates.show` when the viewer can view them.

### Phase 8: DESIGN.md
Update these sections, keeping the existing tone:
- **Page headers:** actions live in the record bar; the sticky record bar; agreement facts are Period and PI; YTD no longer leads on agreements.
- **Cards and Containers:** section headings on the canvas for record overviews.
- **Tables and Relationship Ledgers:**
  - row signifiers (chevron vs arrow);
  - the count badge and the restricted lock rule;
  - long lists (thresholds and filter) and even column ending;
  - Overview sections are not collapsible.
- **Colours:** the Progress made step and the tone-block order for status bars.
- **Do's and Don'ts:** no counts on tabs or links; counts are badges; statuses are chips.

Then refresh the design sidecar with `impeccable document`.

### Phase 9: Verification
1. Run `vendor/bin/sail bin pint --dirty --format agent`.
2. Run `vendor/bin/sail artisan test --compact` on the changed test files, then ask Will to run the full suite.
3. Run `vendor/bin/sail composer check` (audit, pint check, phpstan, tests) and `vendor/bin/sail npm run build`.
4. Browser check with the Playwright MCP at 1440 and 1280:
   - every tab;
   - the sticky bar entering and leaving;
   - keyboard tab order (bar, then sections, then focusable scroll regions, then filters);
   - reduced motion;
   - console errors;
   - one before/after screenshot pair per changed component in `.artifacts/`.
5. Run an `impeccable critique` pass on the screenshots, re-run the dataviz validator if any colour changed, and run `ponytail-review` on the diff.
6. Update the Trello card per the tickets skill.

## 9. Risks

- **Phase 1 touches shared components** used on 11 show views and 12 count usages. Do the screenshot pass before moving on.
- **Computing deliverable progress twice** only when the Deliverables tab has a date filter in the URL; otherwise reuse `deliverableGroups`.
- **The sticky bar** must not cover dropdown menus, the rail or the phone top bar; check the stacking at every tab.
- **The balance script** has to avoid layout thrash and must re-run after toggles and filtering.
- **Activity totals include restricted activities**, so the monthly bars can reveal how many restricted activities happened in a month. That follows the "total includes restricted" decision; flag it to Will if the client objects.
