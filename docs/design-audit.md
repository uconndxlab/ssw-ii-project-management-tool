# Design Audit: Shared Components and Layouts

**Date:** 2026-10-15
**Scope:** App shell and navigation, page headers and breadcrumbs, the badge system, form fields and pickers, save bar and drawer, index and embedded tables, and buttons.
**Benchmarks:** [`DESIGN.md`](../DESIGN.md) ("The Working Ledger"), [`form-conventions.md`](form-conventions.md), WCAG 2.2 AA, and current conventions for operational web apps.
**Method:** I read every shared Blade component, `resources/css/app.css`, `app/Support/EntityBadge.php`, and the layout. I loaded `/agreements` (at 1440px and 390px), `/agreements/4`, and `/agreements/create` locally with Playwright, measuring contrast, accessible names, and element sizes in the browser. I also ran the impeccable detector over components, layouts, and CSS.

The original audit was documentation only. Resolution statuses below track subsequent implementation work; unchecked findings remain open. Severity counts and health scores describe the original audit, not a new assessment.

**Update (2026-10-21, after PR #39).** The shell changed after this audit was written: the top navbar was replaced by a floating sidebar (pinned or peeking, with search, Log Activity and the account menu inside it), the bottom save bar was removed (Save, Cancel and status now live in the sticky form header), and `SessionBackTargetService` was deleted. Findings that cite `app-navbar.blade.php` or `save-bar.blade.php` describe code that no longer exists. A later pass fixed the field and table accessibility findings and widened lists (see the section status lines). Sections 3, 5, 6 and 7 were then addressed by the enterprise restyle below (2026-10-22). The "Roadmap" section at the end replaces the original recommended order.

**Update (2026-10-22, enterprise restyle).** The visual world was replaced: a cool neutral ramp, one cobalt accent, hairline borders, a 4/6/8 radius scale and 14px type, applied through Bootstrap's variables and `resources/css/app.css` (see [`DESIGN.md`](../DESIGN.md)). The sidebar is docked, the compact form bar is light, entity hues are gone in favor of quiet chips, index tables got one-line rows with a visible "+N", and the relationship ledgers are flat disclosures. Sections 3, 5, 6 and 7 are resolved except where marked open below. A review the same day found the restyle too plain and too hard to scan in places; section 8 lists what is left and the roadmap orders it.

## Severity

| Level | Meaning | Count |
|---|---|---|
| **BLOCKER** | Some users cannot complete a task, or a WCAG 2.2 Level A failure. Fix before the next release. | 4 |
| **SHOULD-FIX** | A WCAG AA failure, a real usability cost, or drift from DESIGN.md. Plan it in the next pass. | 36 |
| **NIT** | Polish or consistency. Fix it while you're working in that file. | 25 |

## Audit health score

| # | Dimension | Score | Key finding |
|---|---|---|---|
| 1 | Accessibility | 2/4 | Unnamed buttons, unlabeled picker inputs, and a modal drawer that doesn't trap focus |
| 2 | Performance | 3/4 | Fine for the app's size; render-blocking CDN CSS/JS and `<style>` blocks repeated per component |
| 3 | Theming | 2/4 | Tokens exist, but there are many inline styles and hex values, plus a second styling system in `repeater-rows-script` |
| 4 | Responsive | 2/4 | Works on mobile, but wide tables only scroll sideways, actions end up off-screen, and targets are small |
| 5 | Implementation integrity | 3/4 | Consistent, deliberate shared components; drift is limited to a few files |
| | **Total** | **12/20** | **Acceptable**: the foundation is sound, with specific gaps |

## The short answer to "are my designs OK?"

The **system** is better than you think. Shared page headers, a single form shell, section cards, a sticky header that carries the form actions, and a central `EntityBadge` color map are exactly how mature operational apps are built. DESIGN.md describes what is actually implemented, and the detector found very little drift.

What makes it feel dated:

1. **It looks like stock Bootstrap.** The blue is Bootstrap's `#0d6efd` default and the components are default Bootstrap components. Nothing is *wrong*, but nothing says *TA Tracker* either. This is a brand and identity decision, not a defect, so no finding below covers it. If you modernize, start with `$impeccable shape` on tokens (accent hue, neutral ramp, radius, type scale), not individual components.
2. **Everything is a badge.** Projects, programs, states, agreements, roles, statuses, counts, and type labels all use the same 12px bold chip. In tables this adds up to a wall of underlined pills, and the useful signal (status, the thing you're looking for) has to compete with it. Current practice reserves chips for status and short categorical values and shows related records as plain links or "+N" summaries.
3. **Tables carry too much.** Nine columns and multi-row badge stacks put roughly five agreements on a 900px screen. Modern list views show fewer columns, keep row height to one or two lines, and push detail into the record page or a preview.
4. **Accessibility gaps** in pickers, the drawer, and icon-only controls. These are the real blockers, and each is a small fix.

---

## 1. App shell and navigation

`layouts/app.blade.php`, `components/app-navbar.blade.php`, `resources/css/app.css`

**Status: resolved, except dark theme (intentionally deferred).** Both BLOCKERs, all five SHOULD-FIX items, and the two implementation NITs are fixed. The original findings are retained below as a checklist.

Implemented: named navigation and dismiss controls; a keyboard skip link to focusable main content; a 50%-white search border, 65%-white placeholder, and visible focus outline; the shorter “Search…” placeholder; shared navbar/content containers; SRI and anonymous CORS on all four pinned CDN assets; a flex shell using `100dvh`; and Bootstrap button hover/focus states. Mobile navigation controls have 44px minimum heights, dropdown triggers are native buttons, and Log Activity exposes its current-page state.

Verification: Playwright checked aligned desktop edges, mobile expansion, keyboard skip-link focus, Space-key dropdown activation, activity-button focus, and absence of shell overflow. The final production-built browser pass had no console errors or failed requests. The Impeccable detector reported no findings. Component props and slots were preserved.

### BLOCKER

- [x] **The mobile menu toggle has no accessible name.** `app-navbar.blade.php:4`: the `.navbar-toggler` button contains only an icon span, and measured `aria-label` is `null`. On mobile it is the only way into navigation, and screen readers announce "button". *WCAG 4.1.2 (A).* **Fix:** `aria-label="Toggle navigation" aria-controls="appNavbar" aria-expanded="false"`.
- [x] **The flash-message close buttons have no accessible name.** `layouts/app.blade.php:30, 42, 49`: `<button class="btn-close">` with no `aria-label`. *WCAG 4.1.2 (A).* **Fix:** `aria-label="Close"`.

### SHOULD-FIX

- [x] **There's no skip link.** Every page repeats 8 or more nav stops before the content. *WCAG 2.4.1 (A, partly met by the `<nav>`/`<main>` landmarks).* **Fix:** add a `visually-hidden-focusable` "Skip to content" link targeting `<main id="main">`.
- [x] **The navbar search input's boundary is invisible.** `app.css:215`: a `rgba(255,255,255,.1)` border on `#212529` is about 1.3:1, and the placeholder at 40% white is about 3.9:1. *WCAG 1.4.11 (AA).* **Fix:** use a border of at least 30% white and a placeholder of at least 60%.
- [x] **The search placeholder is truncated.** At 1440px it reads "Search agreements, o". Shorten it to "Search…" or widen the field. It's also the field's only visible label.
- [x] **Navbar and content edges don't align.** The navbar uses `container-fluid` (brand at x≈12px) and content uses `container` (x≈65px at 1440), so the shell looks unanchored. **Fix:** use the same container for both, or make both fluid with a shared max width.
- [x] **Frameworks come from public CDNs, unpinned to integrity hashes.** `app.blade.php:10–18`: Bootstrap 5.3.2, Icons 1.11.3, and htmx 1.9.10 are loaded from jsDelivr and unpkg without `integrity`, while app CSS goes through Vite. That's a supply-chain risk, three render-blocking origins, and versions that lag. **Fix:** move to npm plus Vite, or at least add SRI hashes.

### NIT

- [x] `main { min-height: calc(100vh - 56px) }` hard-codes the navbar height. Use `100dvh` with a flex column layout.
- [x] `.app-navbar-cta` reimplements a small primary button, using `filter: brightness` on hover and no `.btn` focus ring. Prefer `btn btn-primary btn-sm` plus a modifier.
- [ ] **Deferred:** No dark theme. That's acceptable for this product, so note it as a conscious choice in DESIGN.md.

## 2. Page headers and breadcrumbs

`components/page-header.blade.php`, `components/page-breadcrumbs.blade.php`, `components/entity-show.blade.php`

**Status: resolved, except the component API refactor (deferred) and agreement type badge (intentionally unchanged).** All three in-scope SHOULD-FIX findings and the four approved NITs are fixed. Existing props and slots are preserved.

Implemented: `RouteBreadcrumbs` derives location from named routes, including record ancestors for edit pages with a show route and Profile for profile editing. Breadcrumbs no longer read session history, and the current item exposes `aria-current="page"`; form Cancel returns to the previous record or list page, else the parent crumb (`RouteBreadcrumbs::cancelUrl`). Show-card titles are sentence-case `h2` elements using the same 1.25rem medium-weight style as form section cards. Page titles use a fixed 1.75rem scale (2rem for dashboards), and header actions align with the title's top edge. Active/Inactive stays in header metadata; duplicate Summary status rows were removed from agreements, organizations, projects, programs, and teams. A single card loop preserves both `activityFirst` orders and every existing content/meta/action slot.

Verification: focused feature tests cover hierarchy after record visits, direct create visits, edit ancestors, and preserved Cancel history. Playwright checked the agreement show/create/edit pages at desktop/mobile widths, fixed heading sizes, semantic section headings, action alignment, and no page overflow. Production-built pages had no console errors. One screenshot pair is saved in `.artifacts/page-header-before.png` and `.artifacts/page-header-after.png`. Impeccable critique used independent design and detector assessments: no remaining in-scope BLOCKERs or SHOULD-FIX findings; the documentation NIT was addressed here. The detector returned zero findings for the three shared components. `vendor/bin/sail composer check` passed (including all 130 tests), as did Pint and the production build. Ponytail review removed unused history-breadcrumb code and confirmed the shared-card and status-row cuts.

The original findings remain below as a resolution checklist.

### SHOULD-FIX

- [x] **Breadcrumbs describe history, not hierarchy.** On `/agreements/create`, coming from an agreement, the trail reads *Agreements / Connecticut TAN2 Technical Assistance / Create Agreement*, which implies the new agreement belongs to that one. Breadcrumbs are expected to show location, and a "Back" link shows history. **Fix:** derive breadcrumbs from the route hierarchy, and let Cancel use the parent crumb.
- [x] **Show pages have no section headings.** `entity-show.blade.php:30, 44, 62, 80, 98`: "Summary", "Recent Activity", and "Relationships" are `<span>`s, so the page outline is one `<h1>` and nothing else. *WCAG 1.3.1 (A-adjacent; heading navigation).* **Fix:** use `<h2 class="…">` with the same styling.
- [x] **There are two card-header languages.** Show pages use tracked uppercase 0.875rem muted labels in a gray `card-header`. Forms use `x-section-card` with a sentence-case `h5`. DESIGN.md's Label Hierarchy Rule reserves uppercase for subsections and entity types, so the show pages break it. **Fix:** pick one card-title style for both.
- [ ] **Deferred by request:** **`page-header` has 27 props and nested ternaries.** Title, breadcrumb label, and action label each have three-level fallbacks (lines 41–126). It works, but it is hard to predict, and it's why variants creep in. **Fix:** collapse it to `context`, `title`, `entityKind`, `active`, `actionUrl`, and slots, and move the inference into a small PHP class with tests.

### NIT

- [x] On show pages "Active" appears twice: as a header badge and as the Status row in Summary.
- [ ] **Intentionally unchanged by request:** The `AGREEMENT` type badge under the title of an agreement page repeats what the breadcrumb and URL already say. It helps on mixed lists (search results), but adds little here.
- [x] Headings use Bootstrap's fluid `calc()` sizes. Product UI reads better on a fixed rem scale (for example 1.75 / 1.25 / 1rem).
- [x] The header action is `align-self-end` against a variable-height title block, so Edit floats beside the badges rather than the title.
- [x] `entity-show.blade.php` duplicates the two card blocks for `activityFirst`. Render them in a loop or use `order-*` classes.

## 3. Badge system

**Status: resolved (2026-10-22).** Chips are reserved for status and clickable related records; related records in tables are one linked chip plus a visible "+N" button that opens the full list as deep links (plain text where the user cannot open the record, using the same `isLinkable()` check). Entity hues were dropped, so kind is shown by label and icon and nothing collides. Linked chips are 24px high, type labels are 0.75rem, status chips carry a tone, a dot and text, and `EntityBadge` collapsed to four class strings.

`app/Support/EntityBadge.php`, `entity-relation-badge`, `entity-type-badge`, `entity-count-badge`, `category-badge`, `status-badge`, `table-badge-list`

### SHOULD-FIX

- [x] **Badge density drowns the signal.** One agreement row shows up to 15 chips (2 projects, 4 programs, 6 states, an overflow marker, a status, and a PI). Every chip is bold, bordered, colored, and underlined. **Fix:** in tables, render related records as a comma-joined text link list or "Alabama, Alaska +4", and keep chips for status and single-value categories. Keep colored chips in the token pickers, where they act as selection tokens.
- [x] **The overflow marker says "…" instead of a count.** `table-badge-list.blade.php:69`: the count only appears in a `title` tooltip that keyboard and touch users can't see. **Fix:** show "+4" visibly, ideally as a button or link to the full list.
- [x] **Entity colors collide.** `team`, `activity`, `activity-type`, `contact-family`, `logging-field`, and `default` all map to the same secondary gray, so the "entity color conveys record kind" rule only holds for 5 of 11 kinds. DESIGN.md also permits cyan activity-type badges in activity tables, and cyan is the State color. **Fix:** either give each first-class entity a distinct hue, or drop the claim that color identifies kind and rely on labels. Remove the cyan exception.
- [x] **Badges are too small and too tight for touch.** Linked chips measure 22px high with 4px gaps, which is below the 24px target-size minimum with no spacing exemption. *WCAG 2.5.8 (AA).* Type badges are 0.7rem (11.2px) bold uppercase, under the roughly 12px floor for legibility. **Fix:** set `min-height: 24px` on linked chips (or use 6px gaps), and make type labels at least 0.75rem.
- [x] **"Active" contrast is marginal.** White on `#198754` at 12px bold measures 4.53:1, which passes by 0.03. Any tint change will fail. **Fix:** use a darker green (`#157347` gives about 5.8:1) or the subtle background plus emphasis text, like the other chips.
- [x] **Status semantics are overloaded.** `status-badge` treats "Cancelled" (recent-activity-table:101) as the same gray as "Inactive", so a cancelled activity looks archived. **Fix:** add a `tone` prop (success / neutral / danger / warning) and an optional icon, so status isn't conveyed by color alone.

### NIT

- [x] `relationClasses()` and `typeClasses()` are near-identical match tables. Derive one from the other.
- [x] `categoryClasses()` has five arms that all return `bg-light text-muted border`. Collapse them to `default`.
- [x] `entity-type-badge` puts its font size and tracking in an inline `style`. Move them to a `.entity-type-badge` class in `app.css`, using the `entity-type` token.
- [x] Underlining linked chips in addition to the border and fill is redundant decoration. Use the chip as the affordance, with underline on hover and focus.

## 4. Fields and form controls

**Status: mostly resolved.** Both BLOCKERs are fixed: token pickers are named comboboxes with a listbox, arrow-key and Enter selection, named remove buttons and a live announcement; open drawers make the rest of the page inert and return focus. Also fixed: errors and help are linked to their fields (with `aria-invalid` and `aria-required`), radio groups are fieldsets or named radio groups, index filters have accessible names, control outlines are 3:1, and the two save bar findings (the bar is gone). Also fixed: the drawer's focus, page lock, close buttons and Escape now live in the shared `form-drawer.js` (a page only toggles `.is-open`), labels without a `for` are linked to the control beside them (the certificate requirement rows, including rows added by script), and editing only a token picker now marks the form as having unsaved changes. The restyle also aligned the picker's minimum height with the 36px fields and set labels to 500 weight. Still open: the drawer's reduced-motion override and the subsection rule (now 2px, still flagged by the detector as intentional).

`form-field`, `form-switch`, `form-radio-card`, `form-subsection`, `form-options`, `form-errors`, `token-picker`, `save-bar`, `form-drawer`

### BLOCKER

- [x] **The token picker's search inputs have no label, and the picker has no combobox semantics.** `token-picker.blade.php:84`: on `/agreements/create`, 7 of the visible inputs (Projects, Programs, States, Organizations, Teams, Users, and others) have no `<label for>`, `aria-label`, or `aria-labelledby`. The results list is a plain `div` with no `role="listbox"`, `aria-expanded`, or `aria-activedescendant`, and the remove buttons all read "Remove" without naming the item. Screen reader users can't tell which picker they're in or what they're removing. *WCAG 1.3.1, 4.1.2 (A).* **Fix:** pass the `x-form-field` `for` id to the search input, add the ARIA combobox pattern, and use `aria-label="Remove {{ name }}"`.
- [x] **The drawer claims to be modal but doesn't manage focus.** `form-drawer.blade.php:18` sets `role="dialog" aria-modal="true"`, but the open/close script (`agreements/partials/deliverables-section.blade.php:419–434`) only focuses the panel. Tab moves into the page behind the backdrop, and focus doesn't return to the trigger on close. *WCAG 2.4.3 (A), 2.4.11 (AA).* **Fix:** set `inert` on the page while it's open, return focus on close, and move the logic into the component. A native `<dialog>` with `showModal()` provides all of this.

### SHOULD-FIX

- [x] **Error and help text aren't linked to inputs.** `form-field.blade.php:18–25` renders help and errors as loose `div`s. There are 0 `aria-describedby` and 0 `aria-invalid` attributes across all views, and only 60 `is-invalid` uses against 160 `x-form-field` uses, so many errors show text without a red field. **Fix:** have `x-form-field` generate ids and emit `aria-describedby`. Have the slot control add `is-invalid`, or have `x-form-field` wrap the error as `invalid-feedback`.
- [x] **Required is visual only.** There are 10 `required-label` asterisks on the create form but 4 `required` attributes and 0 `aria-required`. The asterisk is CSS `content`, which screen readers announce inconsistently. **Fix:** set `required` or `aria-required` on the control whenever `x-form-field :required` is true.
- [x] **Unselected radios, switches, and checkboxes are nearly invisible.** The border measures `#dee2e6` on white, about 1.3:1 (see "Program Scope: All" and the Time Tracking cards in the screenshot). *WCAG 1.4.11 (AA).* **Fix:** use a border of at least `#8c959f` (3:1) for `.form-check-input` and for the field outline. The same value raises text inputs to modern legibility.
- [x] **Radio groups have no group label.** Program Scope and Time Tracking aren't wrapped in a `fieldset`/`legend` (the create form has 0 fieldsets), so "All" and "None" are read without their question. *WCAG 1.3.1.* **Fix:** add `x-form-radio-group` with a `fieldset` and `legend` styled as `.form-label`.
- [x] **Index filters have no labels.** On `/agreements`, all 7 filter controls have no label. The selects rely on their first option ("All States"). **Fix:** add `visually-hidden` labels, or visible small labels above the controls.
- [x] **The save bar's primary action is undersized and misaligned.** "Create Agreement" is `btn-sm` (31px high) in a 54px bar, and the bar uses `.container` while the form uses the narrower `col-lg-10` shell, so Cancel and Save sit off the form's right edge. **Fix:** use the default button size, and match the bar's inner width to the form shell.
- [x] **Save bar status relies on symbol glyphs and color.** `save-bar.blade.php:83–86`: `●`, `✓`, and `⚠` are typed characters inside colored spans. They're announced literally ("black circle"), and "Unsaved changes" is `text-warning` amber on white at about 1.6:1. **Fix:** use Bootstrap Icons with `aria-hidden` and `text-warning-emphasis` for text.
- [x] **Drawer behavior lives in one feature partial.** The only open/close/Escape implementation is inside `deliverables-section`, so the next drawer will copy it. **Fix:** ship the behavior with `x-form-drawer`, covered by the BLOCKER above.

### NIT

- `form-switch` and `form-field` emit errors as `text-danger small`, while `form-errors` uses an alert. Keep both, but put a link from each summary item to its field.
- [x] Every label is 600 weight, so labels compete with `h5` section titles. Current product UIs use 500 for labels.
- `.form-subsection`'s 4px left rule (flagged by the detector as `side-tab`) is documented and intentional. It's fine, but the same accent on dropdown items (`app.css:285`) adds a second meaning for the same mark.
- The drawer has no `prefers-reduced-motion` override for its 0.2s slide. That's low risk, but DESIGN.md already notes the gap.
- [x] `token-picker` hard-codes `min-height: 42px`, while `.form-control` is 38px, so pickers and inputs in the same row don't line up.

## 5. Tables

**Status: resolved except `confirm()` dialogs (2026-10-22).** Sort state, pagination names and htmx loading feedback were fixed earlier. The restyle added one-line cells with two-line name clamps (rows 48–56px; 63px for a two-line name), phone cards and a collapsed "Filters (n)" panel for the agreements and activities lists, an embedded activity table without its Agreements column, one link style, a three-dots row menu in place of icon strips, and quiet sort carets. The other index tables keep a scrolling wrapper on phones. Still open: the 18 native `confirm()` calls.

`agreements/partials/table.blade.php` (representative of the index partials), `recent-activity-table`, `table-sort-link`, `table-filter-clear`, `htmx-pagination`

### SHOULD-FIX

- [x] **On desktop, the embedded activity table hides its own actions.** `recent-activity-table.blade.php:74` forces `min-width: 860px` inside the `col-lg-8` card. At 1440px, "Logged By" is clipped and the Actions column is off-screen behind a horizontal scrollbar (see `/agreements/4`). **Fix:** remove the min-width, let Activity wrap, and drop the Agreements column when the table is embedded on an agreement page, where it repeats the page subject.
- [x] **Rows are too tall to scan.** Multi-line names, a truncated abstract, and stacked badges make rows 85–120px high, so about five agreements fit per screen. **Fix:** cap names at two lines, move the abstract into the record page or a tooltip, and summarize related records (see Badges). Target 48–56px rows.
- [x] **Mobile is sideways-scroll only.** At 390px, filters fill the first screen and the table shows 1.5 of 9 columns, with no sticky first column and no cue that more columns exist. **Fix:** collapse filters behind a "Filters (n)" button below `md`, make the name column sticky, or render rows as stacked cards below `md`.
- [x] **Sort state isn't exposed.** `table-sort-link` changes only the icon, and there are 0 `aria-sort` attributes in views. **Fix:** set `aria-sort="ascending|descending"` on the active `<th>`, and give the link a visually-hidden "sort by …" hint.
- [x] **Pagination arrows are unnamed.** `htmx-pagination.blade.php:19, 27, 91, 94` use the bare glyphs `‹ ›`. **Fix:** `aria-label="Previous page" / "Next page"`, and `aria-current="page"` on the active item.
- [x] **htmx updates give no feedback.** Filter typing (400ms debounce) and sort clicks swap the table with no loading state, and the result count isn't announced. **Fix:** add `hx-indicator` with a subtle table opacity or progress bar, plus an `aria-live="polite"` region for "Showing 1–14 of 14".
- [x] **Link styles are inconsistent within a row.** Record names are dark and not underlined, user names are default blue, and related records are underlined chips. **Fix:** pick one table-link treatment, for example ink with underline on hover for every link.
- **Destructive actions use `confirm()`.** There are 18 native `confirm()` calls, and names are escaped with `addslashes` into inline JS. Native confirms can't be styled and are easy to click through. **Fix:** use a shared confirm modal component, or undo for reversible actions.

### NIT

- [x] Row action groups of 4 icon-only buttons (view, edit, duplicate, delete) rely on tooltips. Since the whole row already links to the record, a single overflow "⋯" menu is the current convention and frees about 100px.
- [x] Clear-filters uses `btn-outline-danger` red for a non-destructive action. Use outline-secondary.
- [x] Unsorted columns show a 50%-opacity double caret on every header, which adds noise. Show the caret on hover or focus only.
- [x] `recent-activity-table` and `relationship-scroll-panel` restyle WebKit scrollbars with off-palette grays (`#8d98a5`, `#97a3af`). Native scrollbars are the expectation in product UI.
- Dates and counts aren't `font-variant-numeric: tabular-nums`, so columns jitter.
- [x] Column widths are set inline in each table partial (`style="min-width:140px"`) instead of shared classes.

## 6. Buttons

**Status: resolved (2026-10-22).** Log Activity is the accent button everywhere, including `recent-activity-table`, which now gives a reason beside it when it is disabled. Buttons are 36px (32px small, 40px on touch), with a soft flat lift. DESIGN.md documents the rule: the accent button is the one primary action, outline buttons are ink on white, and destructive outline buttons fill red on hover. The accent is 6.97:1 with white.

### SHOULD-FIX

- [x] **The same action has different colors.** "Log Activity" is blue (`.app-navbar-cta`) in the navbar and green (`btn-success`) in `recent-activity-table.blade.php:62`. DESIGN.md says Action Blue marks primary actions. **Fix:** use `btn-primary`. Green is only used twice in the whole app, so drop it as an action color.
- [x] **Button sizes aren't consistent.** Measured primary actions are 38px (page header), 33px (navbar), and 31px (save bar, table actions, section actions). On touch devices 31px is small, and the most consequential button (save) is the smallest. **Fix:** define two sizes, default (at least 38px) for primary and commit actions and small (at least 32px) for inline row actions, and enlarge targets on touch with `@media (pointer: coarse)`.
- [x] **Disabled buttons don't explain why.** `recent-activity-table.blade.php:64` renders a disabled `span.btn` with no reason. **Fix:** add a tooltip or adjacent text ("No active agreements") so the user knows what unblocks it.

### NIT

- [x] White on `#0d6efd` measures exactly 4.50:1. That passes, but there's no margin for 14px small-button text. If you retune the brand blue, aim for at least 5:1 (for example `#0b5ed7` as the base).
- [x] The button variant mix across views is 40 `outline-secondary`, 27 `primary`, 23 `outline-primary`, 22 `outline-danger`, 15 `link`, 11 `danger`, 4 `secondary`, 3 `success`. Document in DESIGN.md when to use outline-primary versus outline-secondary, since they're currently interchangeable.

## 7. Token and theming drift (cross-cutting)

**Status: resolved (2026-10-22).** `repeater-rows-script` styles now live in `app.css` on the tokens (hairline rows, 4/6/8 radii, no slate shadows); every other component `<style>` block moved there too, so no view ships a `<style>` block. Hard-coded hex values in views were mapped to tokens. The inline `style=""` attributes that remain are data-driven (progress widths, chart heights, show/hide state) or one-off sizes.

### SHOULD-FIX

- [x] **`repeater-rows-script` is a second design system.** The detector flagged 11 off-system values there, including radii 0.3, 0.45, 0.5, and 0.65rem (DESIGN.md has 0.25, 0.375, and pill) and slate `rgba(15,23,42,…)` shadows and borders that appear nowhere else. **Fix:** map them to DESIGN.md tokens, or promote them into DESIGN.md if they're the direction you want.
- [x] **Component CSS is scattered.** Nine components ship `<style>` blocks via `@once` and about 60 inline `style=""` attributes. Hex values such as `#e9ecef`, `#e9edf2`, `#0d6efd` (hard-coded in team-picker), and `#fff` bypass the tokens, so a future palette change won't reach them. **Fix:** move component styles into `app.css`, or a `resources/css/components/` split, using `var(--bs-*)`/`var(--app-*)` tokens.

---

## 8. Post-restyle review (2026-10-22)

**Method.** Review of the restyled app against seven screenshots (the collapsed sidebar, a record page's header and Summary, the organization and staff ledgers, the Relationships card, an organization's Summary, and the activities list), plus a read of the components behind them (`relationship-scroll-panel`, `relationship-ledger-row`, `table-badge-list`, the index table partials). Benchmarks as above, plus the product's Operate mode: scanability and obvious affordances over expression.

**Verdict.** The restyle fixed density, color collisions and consistency, but it removed too much hierarchy and too many cues. Layers are hard to tell apart, accordions do not look interactive, ledgers with detail are stacks of boxes and bullets, and clickable things in tables are harder to hit than before. These need design decisions, not one-line patches, so most items below start with a mock.

### BLOCKER (both fixed 2026-10-22)

- [x] **The collapsed sidebar's search showed the "/" hint, cramped against the icon.** `.d-lg-inline` (`!important`) beat the rule that hides the hint when the rail is collapsed, so the hint was always visible. **Fixed:** the hint is hidden by default and shown only when the rail is open at desktop widths.
- [x] **Viewing an activity meant opening the row menu.** The restyle replaced the eye icon with a menu, and on the activities list neither the date nor the type was a link. **Fixed:** the date is now a link. The broader fix is finding 7.

### SHOULD-FIX

1. [x] **Layers were too close together.** Pale grey title strips, the near-white canvas and faint borders made card titles read as grey on grey. **Resolved:** the user chose option C, soft elevation: canvas `#f0f2f5`, white card titles, internal lines `#d4dbe3`, transparent outer card borders and the shared `--app-card-shadow`. Semantic danger and selected borders remain intact. Applied to shared cards, Home and search results; forms retain white unbanded titles. The other options remain in the layering study for reference.
2. [x] **Ledgers with extra detail are stacks of boxes and bullets.** Organization rows put the PO and KFS numbers in a bulleted list with the Payor source and Recipient tags on their own lines; staff rows stack name, role tags, email and agreement chips one per line; linked agreements are a chip plus a paragraph. This wastes vertical space and hides what matters. There are five variants today: name-only (chip cloud), title and description, person (role tags and email), organization (tags plus PO and KFS), and staff group (a group title plus people), across 42 panels on six show pages. **Fix:** design one detail row (title link, dot-separated meta on one line, tags aligned right, a two-column grid when the card is wide) and map the five variants onto it. Resolved through the shared ledger row, including staff and contact adapters: wrapping titles, compact metadata, separate roles/actions, hairline rows and wide-panel columns.
3. [x] **Accordions do not look interactive.** The chevron is small and faint, there is no hover cue, a collapsed group shows nothing about its contents, and groups with a count of 0 still expand into nothing. **Fix:** make the whole header row the button with a hover fill and a larger chevron, preview the first two names when collapsed ("Default Project, SSW II Project +1"), open short lists (about 6 or fewer) by default, and make empty groups non-interactive and muted. **Resolved:** the shared panel now implements these behaviors, keeping navigation and add/edit actions outside the disclosure button.
4. [x] **Scrolling inside a ledger is not obvious.** The body scrolls under a `max-height` with overlay scrollbars that hide on macOS and no edge cue. **Fix:** a fade or shadow at the clipped edge and an "n of N" caption, or drop nested scrolling in favor of "Show all N" expanding in place for lists up to about 30 and a scroll region only beyond that. **Resolved:** the user selected Scrolling B (native scrollbar, range caption and bottom cue), now applied across the shared panels.
5. [x] **The app reads as plain.** Everything is ink or grey at one weight: page headers, Summary, ledgers, table headers and the Year-to-Date block carry about the same visual weight, and no screen has an obvious first thing to look at. **Fix:** a character pass inside the token system (no new hues): a record-kind icon tile in the page header, a key-facts strip under the title (dates, PI, counts), accent-subtle for current and selected states, larger figures for the Year-to-Date numbers, and a clearer table-header treatment. Mock three directions and choose one. **Resolved:** selected Hierarchy C is implemented.
6. [x] **A linked chip and a tag look nearly the same.** In the activities list the agreement chip (bordered) sits beside the activity-type tag (tinted, no border); both are grey boxes, one clickable and one not, so "a chip always means clickable" is not obvious on screen. **Fix:** make tags text-only (muted text with no box), or give linked chips a visible link cue (a trailing link-out icon or accent text at rest). Mock both. **Resolved:** the user chose the linked-chip treatment without the link icon: cobalt ink, white fill and control outline; tags retain muted fill.
7. **The whole row should be a click target.** Only the name is a link, and the "+N" and row menu are separate controls, so the click target for opening a record is small. **Fix:** a stretched link on each row's primary cell (`.app-row-link::after` over the row, with chips, "+N" and the menu raised above it), a pointer cursor and the existing hover tint. In the activities list the primary link is the date or the activity type.
8. [x] **A chip is the wrong form for a table's primary related record.** In the activities list the agreement chip is truncated to "Indiana FOCUS Implem…", so the Copy 2, 3 and 4 agreements cannot be told apart. **Fix:** where the related record is the column's main content (activities → agreement), render a link that wraps to two lines; keep chips for secondary columns. **Resolved with the user’s chip preference:** primary agreement chips wrap their full name in activity and recent-activity tables.
9. [x] **A green action is left over.** `organizations/show.blade.php:116` ("+ New" next to Linked agreements) is `btn-outline-success`, which contradicts the rule that the accent marks actions. **Fix:** `btn-outline-primary` or `btn-outline-secondary`, matching other section actions. **Resolved:** the section action uses the accent outline.

Sortable-column discoverability was also restored: inactive columns always show both outline carets, while the active column shows its filled direction. Verified sorting through htmx and the resulting URL/ARIA state.

### NIT

- Breadcrumb links are accent blue while every other link is ink with a grey underline. Pick one link treatment for breadcrumbs.
- The phone card layout covers only the agreements and activities lists; the other index tables still scroll sideways.
- The Recent panel, an unpinned sidebar peek and the drawer were not re-checked after the restyle.

## Roadmap (updated 2026-10-22, after the post-restyle review)

**Done.** Shell and navigation, headers and breadcrumbs, the field and table accessibility pass, and the enterprise restyle (tokens through Bootstrap's variables, docked sidebar, light compact bar, quiet chips with "+N" menus, one-line tables with row menus and phone cards, flat ledgers, button sizes, CSS consolidation). Two regressions from the restyle are fixed (the collapsed search hint; the activity date is a link).

**Decided.** Lists and record pages grow up to 1680px; forms stay a centered column (68rem). Entity hues are gone; kind is shown by label and icon. The user selected soft elevation (option C): white card titles and working surfaces, a cool grey canvas, internal hairlines and one shared soft card shadow.

**Implementation status and remaining work.** Items marked "mock" need an approved mock before code.

1. **Layering — complete.** Finding 1 resolved with approved soft elevation (C); the system is recorded in DESIGN.md. The transparent 1px table-card border exposed a white rim around grey table headers, reading as gloss. Listing table cards now have zero border width so the header fill reaches the edge.
2. **Ledger rows and accordions — complete.** Findings 2 and 3 are implemented through the shared row and panel components, including staff/contact variants: wrapping title links, compact metadata, aligned roles/actions, full header buttons, collapsed name previews and non-interactive empty groups. Summary disclosures now start closed at every size; wider Relationships disclosures can start open. The approved row study remains in `.artifacts/ledger-accordion-mock.html`; selected Scrolling B supersedes its Show all proposal.
3. **Chip clarity and primary labels — complete; broader row targets remain open.** Findings 6, 8 and 9 are resolved: linked chips use cobalt ink on white with an outline and no link icon; primary agreement names wrap; the leftover green action uses the accent. Every sortable column now has visible arrows at rest. Finding 7 (stretched row links) remains a separate backlog item; activities already open through their linked date.
4. **Character and scrolling — B and C implemented.** Scrolling B now uses a styled native scrollbar, a visible record range and a bottom-edge cue on overflowing relationship panels. Short lists have no extra scroll caption or keyboard stop. Hierarchy C adds a record-kind icon to show-page headers, moves agreement dates and principal investigators into a key-facts strip, and strengthens existing agreement/organization YTD figures. Facts use already-loaded records and existing totals; forms retain their existing header behavior. `.artifacts/selected-direction-mock.html` records the combined visual direction. Verified record pages at 1440, 1920 and 390px, keyboard scrolling to the end, range updates and non-overflowing lists.
   **Desktop listing follow-up — complete.** All index tables now fit the viewport, scroll inside the card and keep column headings and the pagination footer visible. All listings, including Activity Families and Supervisees, share a Show 10/25/50/100 selector (default 25), a result range and page navigation. Size changes reset to page 1 and preserve filtering/sorting through htmx; empty and single-page results retain the selector. Phones and windows under 600px high keep document scrolling. Record previews retain their existing View Activities navigation.
   **Summary and record hierarchy follow-up — complete.** Summary ledgers now honor an explicit closed default at every count, including one record; wider Relationships panels retain their open default. Preview names form one clipped comma-separated line with an ellipsis, with no +N suffix; the heading retains the total count. Header facts and prominent existing Summary totals now cover organizations, projects, programs, states, teams, users/profiles, logging fields and activities. Organization YTD totals lead, redundant identity/status rows are removed, and user scope-source labels, team status, required-field flags and contact actions remain available. Agreement candidates and attachments also use closed Summary disclosures. The views use already-loaded records and existing totals; controller queries are unchanged.
   **Useful facts and table edge cues — complete.** Removed created/updated/added/joined record facts rather than filling every header strip. Organization PO and logging-field type are compact labels; absent identifiers and repeated organization relationship counts are omitted. Useful agreement/activity dates and user contact/supervisor details remain. All listing tables share directional scroll shadows that clear at the corresponding boundaries and update after resize or htmx replacement. The cues sit below column headings and above pagination, with pointer input passing through.

5. **Remaining accessibility:** `confirm()` dialogs (18 native confirms with names escaped into inline JS) become one shared confirmation component, a reduced-motion override for the drawer, and a re-check of the Recent panel, the sidebar peek and the drawer.
6. **Phone cards for the other index tables** (projects, programs, states, teams, organizations, users, admin lists): add `app-cards` and `data-label`. Include Home's existing horizontal overflow at 390px: its row action groups extend beyond the viewport. Confirmed unchanged by the soft-elevation update.
7. **Page patterns:** record pages with tabs (Overview, Deliverables, Activity, People, Files); long forms with a sticky sections list in the empty gutter, or create-then-enrich; deliverables chosen by type first; separate screens for building certificate rules and checking them; a dashboard that shows what needs attention.
8. **[NIT]** Breadcrumb link style, deliverable progress bars and charts (still stock Bootstrap colors), the page-header API refactor (27 props), the remaining inline `style=""` sizes.

**Deferred or later.** Pinned or favorite destinations on Home, a dark theme, Recent connection diagrams. Open checks: screen reader testing, Safari and Firefox (the phone card CSS uses `:has()`), real touch hardware, and how much staff use phones.

## What's working, so keep it

- **One form shell, section cards, and a sticky header that carries the form actions**, documented in `form-conventions.md`. That level of consistency is rare in internal tools.
- **Central color mapping** through `EntityBadge`, so a palette change is one file.
- **The form status model** (idle, dirty, saving, saved, error) with `aria-live` and a `beforeunload` guard, now in the page header, is better than most commercial apps.
- **Server-rendered htmx tables with `hx-push-url`**, so filtered and sorted views are shareable and back-button safe.
- **`aria-current` on navigation and dropdown items**, `aria-label` on icon-only row actions, and labeled breadcrumb `nav`.
- **Status pills always carry text** ("Active" or "Inactive"), not just color.

## Run notes

- **Sticky-header and focus follow-up:** one opaque sticky `thead` replaces individually sticky cells; separate borders keep the divider attached, and desktop overscroll bounce is disabled. The stationary frame draws keyboard focus below the header and above scroll shadows, with a zero header offset in non-sticky layouts. Playwright assertions passed across all 13 listings at 1440, 1920 and 390px plus short windows (52 cases), hard wheel scrolling at both boundaries, keyboard End, sort-link focus, htmx sorting/page-size replacement and desktop/phone resizing; no console errors. Production build and `composer check` passed (227 tests, 834 assertions). Independent Impeccable A (`table_header_design_review`, scoped 19/20) and B (`table_header_evidence_review`, detector once on the shared component, zero findings) found no blockers, should-fix items or nits. Target `resources-views-components-table-scroll-blade-php`; no ignore file. Both child browser attempts hit the existing profile lock and used source/screenshot evidence; parent live checks passed. No overlay, live server or temporary review files were created. Ponytail self-review removed the old scroller outline and per-cell sticky rule rather than adding another header or synchronizing duplicate tables. Questions skipped: the reported defect and intended behavior were clear.
- **Useful-facts and scroll-cue follow-up:** removed bookkeeping facts and redundant header counts rather than substituting filler. The shared scroll frame replaces duplicated table wrappers in all 13 listing templates. Browser assertions passed for 42 table/viewport combinations plus seven record headers; additional checks covered all four scroll boundaries, htmx replacement, resize to non-overflowing content and last-row menu interaction. No JavaScript exceptions or console errors in the batched pass. `composer check`, production build and all 227 tests / 834 assertions passed, including a full run without Vite assets. No new tests were added for the styling/copy-only markup; the new scroll-state behavior was checked with browser assertions against actual geometry.
- **Scroll-cue review:** independent Impeccable A (`scroll_cue_design_review`, 23/28) and B (`scroll_cue_evidence_review`, detector once on five markup files, zero findings), target `resources-views-components-table-scroll-blade-php`; no ignore file. No blockers or should-fix findings. A’s documentation NIT was addressed by recording the Useful Facts Rule. Child browsers hit the known profile lock and used screenshot/source fallback; parent runtime checks passed. No overlay, live server or temporary review files were created. Ponytail self-review retained the small shared frame and observer rather than per-table scripts; cuts include duplicated wrapper attributes, filler facts and empty identifier placeholders. Questions skipped: the user requested implementation of a clear refinement.
- **Summary defaults and cross-entity hierarchy:** 33 batched route/viewport cases at 1440, 1920 and 390px passed, with no expanded Summary ledgers, page overflow, escaping preview text or JavaScript exceptions. Confirmation covered the supplied organization example, keyboard expansion, scrolling a 16-state list to its end, reset to closed on reload, closed agreement candidates and a logging-field page with no empty Recent activity card. Long names clip with CSS ellipsis; preview text is hidden from assistive technology and the disclosure retains its title/count. `composer check`, production build and all 227 tests / 834 assertions passed; the final full suite also passed without Vite assets.
- **Summary review:** independent Impeccable A (`summary_hierarchy_design_review`, 29/36) and B (`summary_hierarchy_evidence_review`, detector run once on eight markup files, zero findings), target slug `nents-relationship-scroll-panel-blade-php-85ac9b54`; no ignore file. No blockers or should-fix findings. Applied A’s NIT by removing the activity’s repeated Logged by row. Child browsers hit the known profile lock and used screenshots/source; parent live checks passed. No overlay, live server or temporary review files were created. Ponytail review removed duplicate identity/status/date rows, repeated scope-list markup and the short-list override; existing permissions, required flags, team statuses and download actions remain intact. Questions skipped: the user supplied the behavior and requested implementation.
- **Desktop listing and pagination follow-up:** 42 route/viewport combinations (14 routes at 1440, 1920 and 390px) passed: no desktop document scroll, footers within the viewport and no horizontal document overflow. Verified page-size changes from page 2 reset to page 1; search, filter clearing and cascading activity filters preserve size. Keyboard End scrolls the table while the headings and footer stay in place; the final row's action menu remains visible and usable. Short-window fallback checked at 1440×500. No browser console errors in the batched pass. Screenshots remain in `.artifacts/`.
- **Pagination checks and review:** `composer check` passed (audit, Pint, PHPStan, 224 tests / 827 assertions); the production build passed, and all 224 tests also passed with the Vite build moved aside. Existing certificate tests now assert the selected 25-row default. Ponytail review found no blocker; fixed per-controller sizes and the duplicate single-page footer branch were replaced by the shared policy and footer. Impeccable used independent A (`table_viewport_design_review`, 30/36) and B (`table_viewport_evidence_review`, detector run once on three markup files, zero findings); no actionable findings. Target slug: `iews-components-htmx-pagination-blade-php-23df234c`; no ignore file. Child browsers hit the known profile lock and used the parent screenshot/source; parent live checks passed. No overlay, live server or temporary review files were created. Questions skipped: implementation was explicitly requested without a draft.
- **Ledger implementation confirmation (2026-10-22).** Shared rows and disclosures checked on seven record routes plus activities at 1440, 1920 and 390px: all returned 200, with no page overflow or JavaScript exceptions. Sort cues checked on all 13 index routes; inactive arrows stay visible, and htmx sorting changes URL and `aria-sort` in both directions. Keyboard End reaches the ledger's final rows, updates the range to "end of list" and removes the bottom cue. Linked chips render cobalt ink on white with the control outline and no icon.
- **Impeccable critique:** independent assessments A (`ledger_final_design_review`) and B (`ledger_final_evidence_review`), target slug `ponents-relationship-ledger-row-blade-php-139aeb62`; no ignore file. A scored the scoped components 30/36 with no actionable findings. B scanned the four shared row/panel/staff/contact markup files once: zero detector findings. Both child browsers hit the Playwright profile lock and used parent screenshots/source; the parent completed live browser checks. No overlay was injected, no live server or temporary review files were created. Questions skipped: the user requested implementation of the selected options.
- **Ponytail review:** no remaining blocker found in the connected component callers. Removed the duplicate metadata/rendering branch, per-contact nested boxes and an unused header-badge option; staff/contact rows now reuse the shared row. Existing permission checks and contact actions remain intact.
- The detector ran on `resources/views/components`, `resources/views/layouts`, and `resources/css` and returned 18 advisory findings: 11 in `repeater-rows-script`, 2 `side-tab` in `app.css` (intentional, documented), and 3 font-size and 2 color findings in other components. No browser overlay was injected.
- The only console error during browsing was a Vite dev-server `504 Outdated Optimize Dep` for `chart.js` on `/agreements/4`. That's an environment issue, not a design finding.
- Not tested: real screen reader output (findings come from DOM and ARIA inspection), touch gestures on a physical device, and pages outside agreements (activities, admin). Shared components behave the same there, but page-specific partials weren't audited.
