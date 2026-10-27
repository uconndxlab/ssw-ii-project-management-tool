---
name: TA Tracker
description: The Working Ledger — a calm, hairline-ruled enterprise interface for agreements, activities and the records around them.
colors:
  canvas: "#f0f2f5"
  surface: "#ffffff"
  fill: "#f0f2f5"
  table-header: "#e1e6ed"
  hover: "#eef0f4"
  line: "#d4dbe3"
  line-strong: "#cfd5dd"
  control-outline: "#8c959f"
  ink: "#151a23"
  body: "#232a36"
  muted: "#5d6675"
  accent: "#2250c4"
  accent-hover: "#1b43a8"
  accent-active: "#163787"
  accent-subtle: "#eaf0fd"
  accent-line: "#c7d5f7"
  accent-ink: "#1a3e9a"
  success: "#157347"
  success-subtle: "#e6f4ec"
  success-ink: "#0f5a36"
  warning: "#b7791f"
  warning-subtle: "#fdf1d6"
  warning-ink: "#7a4a06"
  danger: "#c62d3a"
  danger-hover: "#a82430"
  danger-subtle: "#fbe8ea"
  danger-ink: "#8e1f2a"
  neutral-subtle: "#eceef2"
  neutral-ink: "#434b59"
typography:
  title:
    fontFamily: 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif'
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: "-0.01em"
  headline:
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.2
  card-title:
    fontSize: "1rem"
    fontWeight: 600
    lineHeight: 1.2
  body:
    fontFamily: 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif'
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontSize: "0.875rem"
    fontWeight: 500
    lineHeight: 1.5
  small:
    fontSize: "0.8125rem"
    fontWeight: 400
    lineHeight: 1.5
  table-head:
    fontSize: "0.78125rem"
    fontWeight: 600
    lineHeight: 1.5
  chip:
    fontSize: "0.8125rem"
    fontWeight: 500
    lineHeight: 1.2
  subsection:
    fontSize: "0.75rem"
    fontWeight: 600
    lineHeight: 1.3
    letterSpacing: "0.04em"
  metric:
    fontSize: "1.625rem"
    fontWeight: 600
    lineHeight: 1.2
rounded:
  sm: "0.25rem"
  md: "0.375rem"
  lg: "0.5rem"
spacing:
  xs: "0.25rem"
  sm: "0.5rem"
  compact: "0.75rem"
  md: "1rem"
  section: "1.5rem"
components:
  button-primary:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.surface}"
    rounded: "{rounded.md}"
    padding: "0.40625rem 0.875rem"
    height: "36px"
  button-primary-hover:
    backgroundColor: "{colors.accent-hover}"
    textColor: "{colors.surface}"
  button-primary-active:
    backgroundColor: "{colors.accent-active}"
    textColor: "{colors.surface}"
  button-outline:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "0.40625rem 0.875rem"
    height: "36px"
  button-outline-hover:
    backgroundColor: "{colors.fill}"
    textColor: "{colors.ink}"
  button-small:
    rounded: "{rounded.md}"
    padding: "0.328rem 0.625rem"
    height: "32px"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.body}"
    rounded: "{rounded.md}"
    padding: "0.40625rem 0.75rem"
    height: "36px"
  section-card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.body}"
    rounded: "{rounded.lg}"
    padding: "{spacing.md}"
  chip-linked:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.accent-ink}"
    rounded: "{rounded.sm}"
    padding: "0 0.5rem"
    height: "24px"
  chip-linked-hover:
    backgroundColor: "{colors.accent-subtle}"
    textColor: "{colors.accent-ink}"
  status-chip-success:
    backgroundColor: "{colors.success-subtle}"
    textColor: "{colors.success-ink}"
    rounded: "{rounded.sm}"
    padding: "0 0.5rem"
    height: "22px"
  status-chip-neutral:
    backgroundColor: "{colors.neutral-subtle}"
    textColor: "{colors.neutral-ink}"
    rounded: "{rounded.sm}"
    padding: "0 0.5rem"
    height: "22px"
  navigation:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.body}"
    padding: "0.75rem"
  navigation-current:
    backgroundColor: "{colors.accent-subtle}"
    textColor: "{colors.accent-ink}"
  header-bar:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
    padding: "0.375rem 0.5rem 0.375rem 1rem"
---

# Design System: TA Tracker

## Overview

**Creative North Star: "The Working Ledger"**

A calm, orderly interface for working with agreements, activities, organizations and the records that connect them. Enterprise structure with minimal surfaces: a cool neutral ramp, one cobalt accent, internal hairline dividers, a modest 4/6/8 radius scale and 14px system type. White working surfaces separate from the canvas through soft elevation; card titles stay white and buttons stay flat, without gloss or gradients.

The look was chosen for the Operate mode PRODUCT.md describes: scanability and consistency beat expression. Color is spent in two places only. The accent marks actions, the current place and focus; status (success, warning, danger, neutral) is the only other place color carries meaning, and it always comes with text.

**Key Characteristics:**
- Cool grey canvas, white working surfaces and white card titles; soft card elevation with stronger shadows for overlays.
- One cobalt accent for actions, selection and focus; semantic color only for status.
- Linked record chips use cobalt ink on white with a visible outline and no link icon; non-clickable categories use muted filled tags.
- Dense but calm tables: one-line cells, 48–56px rows, a visible "+N" instead of a wall of chips.
- 14px system type, tabular numerals in tables, 36px controls (32px small, 40px on touch).

This file replaces the earlier scan of the stock-Bootstrap implementation. Sources are `resources/css/app.css` (tokens and primitives), `resources/views/layouts/app.blade.php`, the shared Blade components, `app/Support/EntityBadge.php`, and `docs/form-conventions.md`. Bootstrap CSS is pinned to 5.3.2 in the layout and Bootstrap Icons to 1.11.3; the tokens are pushed through Bootstrap's variables, so stock components follow.

## Colors

A cool, low-chroma neutral ramp with a single cobalt accent. Frontmatter values are normative; Bootstrap's `--bs-*` variables and the `--app-*` properties in `app.css` carry them.

### Primary
- **Cobalt** (`accent`): primary buttons, the current sidebar section's icon, focus rings, selected radio-card borders and the active page in pagination. White text on it is 6.97:1.
- **Deep Cobalt** (`accent-hover`, `accent-active`): hover and pressed states of accent buttons and links.
- **Cobalt Mist / Cobalt Ink** (`accent-subtle`, `accent-line`, `accent-ink`): the current sidebar section, hovered linked chips, the "+N" button and selected options.

### Secondary
- **Status Green** (`success`, `success-subtle`, `success-ink`): Active and completed. A darker green so white text on it clears 4.5:1.
- **Attention Amber** (`warning`, `warning-subtle`, `warning-ink`): warnings, needs-review and unsaved changes.
- **Error Red** (`danger`, `danger-hover`, `danger-subtle`, `danger-ink`): validation errors, required markers, cancelled and destructive actions.

### Neutral
- **Canvas** (`canvas`): the cool grey page background beneath elevated white working surfaces.
- **Surface** (`surface`): cards, fields, the sidebar, drawers and menus.
- **Fill** (`fill`): disclosure headers, selected picker tokens, inset wells and disabled fields.
- **Table Header** (`table-header`): a darker cool grey band, distinct from the canvas; labels use the stronger neutral emphasis text.
- **Hover** (`hover`): row and menu-item hover.
- **Hairline** (`line`, `line-strong`): internal dividers and sidebar edges (`line`); picker-token and well edges (`line-strong`). Linked chips use the stronger control outline. Ordinary cards have transparent outer borders; semantic borders such as danger and selected rows remain visible.
- **Control Outline** (`control-outline`): field, select, checkbox and radio outlines, 3:1 on white.
- **Ink / Body / Muted** (`ink`, `body`, `muted`): headings and links, running text, and secondary text (5.8:1 on white, 5.2:1 on the canvas).
- **Neutral Chip** (`neutral-subtle`, `neutral-ink`): inactive status and non-clickable tags.

**The One Accent Rule.** Cobalt means "act here" or "you are here". Never use it to decorate, and never use a second hue for record kinds; kind is shown by label and icon.

**The White-Surface Rule.** Controls sit on white surfaces only. The control outline passes 3:1 on white but not on the canvas, so a bare field on the page background is a bug.

## Typography

**Display and Body Font:** the Bootstrap system sans-serif stack, inherited throughout. No hosted or monospace family.

**Character:** familiar and quiet. Weight, size and muted color make the hierarchy; there is no display face.

### Hierarchy
- **Title:** page headings (1.5rem, 600, -0.01em tracking). Dashboards use 1.75rem.
- **Headline / Card title:** `h2` in 1.125rem, card and section titles in 1rem, both 600.
- **Body:** controls and content (0.875rem, 400, 1.5).
- **Label:** field labels (0.875rem, 500); required markers are appended in Error Red.
- **Small:** help, meta and descriptions (0.8125rem). Absolute, not em-based, so nested small text does not shrink twice.
- **Table head:** 0.78125rem, 600, neutral emphasis ink, sentence case.
- **Chip:** 0.75rem, 500.
- **Subsection:** muted uppercase labels (0.75rem, 600, 0.04em tracking), reserved for form subsections, record-page sub-headings and entity-type tags.

**The Label Hierarchy Rule.** Keep ordinary labels in sentence case; reserve tracked uppercase for subsection titles and the entity-type tag. Tables use `font-variant-numeric: tabular-nums` so columns of dates and counts line up.

## Layout

Signed-in pages use a shell: on desktop a docked left sidebar beside the main content with no top bar; below 992px a sticky phone top bar (3.5rem) over the main content. The body is a flex column with a minimum height of `100dvh`. Page padding is 1.5rem on all sides; the spacing scale is 4, 8, 12, 16, 24 and 32px.

Desktop listing pages fit the viewport. Their title, filters and pagination remain visible while the table scrolls inside its card, with sticky column headings and both scroll axes available. This applies from 992px wide and 600px high; phones and shorter windows retain normal document scrolling so controls remain reachable. Record pages and forms retain their normal page flow.

Lists and record pages use the room beside the sidebar up to a maximum container width of 1680px. Create and edit forms use the shared centered form column (maximum 68rem). Section cards have 1rem body padding and 1.5rem spacing between them; field groups use 1rem.

Page headers wrap their title and action area with a 1rem gap; actions align with the top of the title, and long record names wrap. Below 768px, all index tables use `app-cards`: one card per row with name and menu on top, status, then labeled lines. Agreements and Activities collapse filters behind a "Filters" button that shows how many are active. Embedded data and report tables keep a horizontally scrollable wrapper. Home activity actions stack below their content on phones so long names do not push controls outside the page. Below 992px the sidebar becomes a slide-in menu opened from the phone top bar. Radio-card rows use two equal columns.

Drawers attach to the right edge at a maximum width of 52rem, filling narrower viewports. Their bodies scroll independently.

## Elevation & Depth

Soft elevation, approved as option C in the layering study. White cards and white title strips sit above the cool grey canvas, separated by a small shadow rather than a visible outer outline. Hairlines divide content inside cards. Overlays use a stronger shadow so their depth remains distinct.

### Shadow Vocabulary
- **Cards, sections, table containers:** `0 1px 3px rgba(21,26,35,.10), 0 5px 12px rgba(21,26,35,.05)` through `--app-card-shadow`. This is the default working surface treatment.
- **Buttons:** `0 1px 2px rgba(21,26,35,.08)`, a soft flat lift. No gloss or gradients. Grouped, link, close and disabled buttons stay flat.
- **Compact header bar:** `0 4px 16px rgba(21,26,35,.10)`.
- **Menus, popovers, the Recent panel and a peeking sidebar:** `0 8px 24px rgba(21,26,35,.12), 0 2px 6px rgba(21,26,35,.06)`.
- **Drawer:** `-0.5rem 0 1.5rem rgba(21,26,35,.12)` over a 35% ink backdrop.
- **Focus ring:** `0 0 0 3px rgba(34,80,196,.28)`.

**The Elevation Rule.** Working surfaces use the shared soft card shadow. Overlays use the stronger popover or drawer shadow; do not stack extra shadows on nested ledger rows.

## Shapes

Three radii: 4px (`sm`) for chips, tags and menu items; 6px (`md`) for buttons, fields and the sidebar's controls; 8px (`lg`) for cards, menus, drawers' panels and the compact bar. The docked sidebar is square against the viewport edge. Borders are 1px; the one exception is the 2px rule beside a form subsection. Iconography is Bootstrap Icons and supports labels, never replaces them.

## Components

### Buttons

Familiar filled and outline variants at two sizes: 36px (default) and 32px (small, for row and section actions), growing to 40px on touch (`pointer: coarse`). The accent button is the single primary action; outline buttons use ink text and the control outline; destructive actions are red outline that fills on hover. Log Activity is always the accent button, in the sidebar, the phone top bar and an agreement's activity table. A disabled button says why next to it ("This agreement is inactive."). Buttons are 500 weight with the soft lift above and a 0.15s state transition under Bootstrap's reduced-motion handling.

### Page Headers and Breadcrumbs

Reuse `x-page-header` with its `badges`, `meta`, `controls` and `more` slots. Page headings use `.app-page-title`; the record's Active/Inactive status chip belongs in the header metadata and is not repeated in Summary. Record and form headers omit entity-type badges: the breadcrumb and record icon already establish the entity. Metadata is optional and its row disappears when empty.

Show-page headers add a 40px cobalt-mist record-kind icon beside the title. User/profile headers use the people icon while retaining the meaningful access-profile label. Create/edit headers retain their title and action composition without a type badge. The optional `facts` slot carries useful working information: agreement dates and principal investigators, user contact/supervisor details, and activity date, logger and family. Activity facts appear once in the labeled strip, without a duplicate subtitle; Internal only, Cancelled and Not yet complete remain in metadata. Person links retain their policy checks. A populated organization PO number and logging-field type use compact metadata labels; missing identifiers are omitted.

**The Useful Facts Rule.** Do not fill a header strip for symmetry. Created, updated, added and joined dates are bookkeeping metadata and stay out of the record display. Relationship counts already visible in the wider panel headings do not need repeating above the page. Projects, programs, states and teams can have no facts strip at all; keep useful business dates on agreements and activities.

Summary is a quick-facts column. Existing YTD figures lead on agreements and organizations; other entities lead with their existing totals or completion/duration figures. Use 26px semibold tabular numerals and 13px labels; only actual year-to-date figures carry a cobalt-ink period label. Related-record disclosures in Summary start closed at every item count. Wider relationship and reporting sections can start open. This extends selected Hierarchy C across record pages without new metrics or entity hues.

Header actions are captured once and repeated in the compact bar. In the full header the form status sits under the buttons; in the compact bar it sits beside them. Icon-only buttons and Save (with its shortcut) get tooltips. A `more` slot adds an overflow menu. In the compact bar only Save keeps its label; on phones Save is an icon too.

Form headers are sticky. Once the full header scrolls out of view a compact bar slides in at the top of the form column without moving the page: back link to the parent crumb, title, status and actions. The bar is the same white surface and buttons as the full header, condensed, with a hairline and the bar shadow; it no longer inverts to dark. Form headers take `save-label`, `form-id` and `last-saved-at`.

Breadcrumbs show route hierarchy, independent of navigation history: resource index → record on show pages, resource index → Create on create pages, resource index → record → Edit where a show route exists. The current crumb exposes `aria-current="page"`. `RouteBreadcrumbs` supplies the hierarchy and the form Cancel target (the record or list page the user came from, else the parent crumb).

### Cards / Containers

White, 8px, with the shared soft shadow and a transparent outer border. Use `x-section-card` for form sections: a 1rem title (600), optional muted subtitle and 1rem body padding. Form section titles remain unbanded. Entity detail and Home card titles use the same white surface as the body, with a hairline below the title. Search result card titles follow this treatment too. Recent activity and the main card render in the requested `activityFirst` order. Index table cards clip overflow around their table and footer.

### Inputs / Fields

White 36px fields (32px small) with the control outline, 6px radius and 0.875rem text. Focus swaps the border to cobalt and adds the 3px ring. Invalid fields use Error Red and associated feedback; disabled fields use the Fill color. Help and error text are linked to their field (`aria-describedby`, `aria-invalid`, `aria-required`), a set of radios is a fieldset with a legend, and token pickers are named comboboxes with arrow-key and Enter selection and a live "added" or "removed" announcement. Pickers share the 36px minimum height of the fields beside them.

Reuse `x-form-field` for labels, required markers, one-line help and inline errors. Use switches inside Options subsections and radio cards for choices with consequences. Selected radio cards use a cobalt border and the cobalt mist fill. Checked checkboxes, radios and switches are cobalt.

### Chips, Tags and Status

Four distinct things, each with one treatment:
- **Linked chip** (`a.app-chip`): a related record the user can open. White, 1px control outline, cobalt ink, at least 24px high, 4px radius, no link icon. Hover goes to cobalt mist with an underline. Selected picker tokens retain their neutral fill and remove button.
- **Plain text** (`.app-plain`): a related record the user cannot open. Never a chip, so a chip always means clickable. Access follows `isLinkable()` (the `view` policy), exactly as before.
- **Tag** (`.app-tag`): a non-clickable label (role, flag, record kind). Tinted, no border.
- **Status chip** (`.app-status`): tinted fill, dark ink, a dot and text. Tones: success, neutral, warning, danger. `x-status-badge` takes `active` and an optional `tone`. Meaning never rests on color alone.

Counts are plain numbers (`.app-count`). In tables a related-record cell shows the first record as a linked chip and a visible "+N" button (`x-table-badge-list`); the button opens a menu listing every record, each a deep link or plain text. Primary agreement chips in activity tables wrap their full name so similarly named agreements remain distinguishable. `EntityBadge` returns these class strings, so there are no per-kind hues.

### Navigation

A docked, full-height white sidebar with a hairline on its right edge; there is no desktop top bar. Below 992px a slim light top bar carries only the menu button, the brand and Log Activity.

The sidebar has three zones. **Actions** at the top: the brand mark (a cobalt square) and name with a pin button, a search field (a "/" hint; pressing "/" focuses it) and the accent Log Activity button. **Destinations** in the middle: links come from `AppNav::railGroups()`, which reuses the role rules in `primaryLinks()` and `adminSections()`: Work, then Reference Data, Agreement Setup, Activity Setup, Certification Setup and People for admins. Every group is always listed. Each link is a Bootstrap Icon plus a 14px label. The section that owns the current route gets the cobalt mist fill, semibold cobalt-ink text and `aria-current` (`page` on the index, `true` on child pages). **Personal** at the bottom: Recent, then an account row (initials avatar, name, access profile) whose menu opens upward with Profile and Log out. An open Recent button turns solid cobalt, distinct from the tinted current section.

The sidebar is pinned open (15rem, docked, the page makes room) or unpinned (4.25rem icons, the page does not move). Unpinned, it peeks open over the page on pointer hover (120ms in, 250ms out), keyboard focus, the search shortcut, or while the Recent panel or account menu is showing, with the popover shadow; Escape dismisses a peek. Unpinning with the mouse collapses at once and only peeks again after the pointer leaves and re-enters. The pin choice is remembered per user in `localStorage`, and new users start pinned at 1200px and wider. Below 992px the same markup is a Bootstrap offcanvas menu; links are at least 44px high.

Recent is a floating 8px panel beside the sidebar, sized to its content, listing up to 30 records opened in the last seven days with a labeled search, a Clear action, and empty, no-match and load-error states. It closes on Escape or an outside click and returns focus to its button. Entries are stored per user in `recent_records` and rechecked with the `view` policy each time the panel opens.

A "Skip to content" link is the first keyboard stop. The sidebar navigation has a landmark name, the pin is a toggle button with `aria-pressed`, collapsed links keep their labels as accessible names, and flash-message dismiss buttons are named "Close". Bootstrap, Bootstrap Icons and htmx retain their pinned CDN versions with SRI hashes.

### Tables and Relationship Ledgers

Index tables use the darker Table Header fill in sentence case (0.78125rem, 600, neutral emphasis ink), 1px row dividers, a hover tint, and one-line cells with names clamped to two lines. Rows are 48–56px. Record names, people and other links share one style: ink, 500 weight, a grey underline at rest, cobalt on hover (`.app-link`). Sort links retain visible carets at rest. Row actions are a single three-dots menu (View, Edit, Duplicate, then Delete in red after a divider), not a strip of icon buttons. Destructive actions still confirm. Deliverable charts read accent, success, muted text and grid colors from the same CSS tokens; expected pace retains its dashed line so meaning does not rely on color alone.

When a row has a permitted record-view link, stretch that native anchor across the row with `.app-row-link`; this also applies to embedded recent activities. Related links, chips and menu controls sit above the target and retain their own actions. Keyboard focus outlines the row without adding a second tab stop. Native modified-click and open-in-new-tab behavior remain available. Keep any two-line text clamp inside `.app-row-link-label` so it does not clip the stretched target. Records without a view link, and administrative lists without a record-view page, do not gain a row target.

Table stacking is explicit: row targets use layer 1, secondary links and dropdown wrappers use 2, open dropdown wrappers use 5, and the opaque sticky header uses 6. Apply dropdown layers to every table, including lists without stretched links. Do not isolate the table body into its own stacking context: fixed dropdown menus must remain usable beyond a short table's edge.

A table being replaced by htmx is dimmed (`is-loading`) and marked `aria-busy`, and the polite live region (`#app-live`) announces the new result summary. Every sortable column shows outline up/down carets at rest; the active direction uses one filled caret and exposes `aria-sort`. Pagination arrows and the current page are named. An agreement's embedded activity table drops the Agreements column.

Every listing has a pagination footer, including empty and single-page results. It combines a Show selector (10, 25, 50 or 100 rows, default 25), the current result range and page navigation when needed. Page-size changes return to page 1 and preserve search, filters and sorting. The chosen size stays in the URL, survives htmx swaps and filter clearing, and is bounded on the server. Activity Families uses this same pagination rather than loading every record.

The shared table scroll frame adds a subtle dark shadow at each edge where rows or columns remain hidden. Top shadows sit below sticky headings; bottom shadows stop above pagination. Cues disappear at the corresponding scroll boundary or when all content fits, and recalculate after resize and htmx replacement. On desktop the opaque header sticks as one unit, with separate cell borders so its divider stays attached. Disable overscroll bounce inside the desktop table. The scroll-region focus border is drawn by the stationary frame below the header, above the shadow; individual sort links retain their own focus indicators. On phones and short windows, the frame includes the non-sticky header. Neither overlay intercepts pointer input. Use soft inset shadows from `--app-scroll-shadow-color`, without gradients or a permanent rim.

Relationship ledgers use one flat record row across name-only, description, person, organization, contact and staff-group variants. Wrapping title links lead; role tags and actions sit alongside; email, PO, KFS and source agreements follow as compact metadata. Narrow ledgers stack metadata; wide ledgers can use two columns. No nested row cards or bulleted metadata. Permission-restricted titles remain plain text; contact email and phone remain actionable.

The full disclosure header is a button with a hover fill, visible focus and a rotating chevron. All Summary disclosures start closed, including single-record lists; wider Relationships disclosures start open unless explicitly closed. A collapsed preview is one comma-separated line of record names, clipped with a CSS ellipsis at the available width. Do not append a "+N" suffix: the heading already carries the total count. Preview text is hidden from assistive technology so long lists do not inflate button names; expanded rows expose the full records. Empty groups show a muted count and explanation without a disclosure button. Group navigation and add/edit controls sit outside the button.

Ledger header actions use the approved icon-button pattern: a 44×44px bordered eye or plus action immediately left of the disclosure chevron. View and Add remain native links outside the disclosure button, with explicit accessible names, hover/focus tooltips and an accent focus outline. Reserve preview space so text cannot run underneath actions. Empty groups can retain a permitted Add action without a chevron; groups without an action gain no placeholder. The shared header supports the existing View and Add slots without nesting controls.

Long lists use the selected Scrolling B: a styled 8px native scrollbar, a visible range caption (for example, "1–3 of 9 · more below"), and a dark bottom-edge cue while records remain below. The scroll region gets a keyboard stop, a name and an associated caption only when it overflows. The caption is not a live region, so it does not announce every scroll event. Short lists have no extra keyboard stop or visible caption. Nested panels count only their own rendered items; record counts are measured from the displayed rows, not inferred from server totals.

### Form Actions and Drawers

Confirmations use one shared HTML dialog, styled as a flat white 8px surface with the popover shadow and a 35% ink backdrop. A small semantic icon accompanies the sentence-case title; the full record name and a plain consequence follow. Delete/remove actions use danger, duplicate actions use the accent. Cancel receives initial focus; Escape and the close button cancel, and focus returns to the visible trigger. Confirm resumes the original form once, retaining CSRF and method fields. Certificate-form changes use the same asynchronous confirmation without saving the form. The body scrolls in short windows while the action footer remains reachable. A restrained 140ms entrance is disabled for reduced motion. Use `textContent` and escaped data attributes for names; never interpolate record names into inline JavaScript.

Form actions live in the page header, not in a bottom bar: a status line (idle, unsaved, saving, saved, error through text and icon, announced politely once), Cancel and the primary Save/Create button. Save calls `requestSubmit()`, so Enter inside token pickers keeps its own behavior. Ctrl/⌘ + S saves, and a `beforeunload` guard protects unsaved edits.

Ordinary same-tab links and Cancel on a dirty form first use the shared confirmation, with the exit icon, record name, Keep editing and Discard changes. Keep editing receives focus and preserves values; confirming resumes the original link without a second native prompt. New-tab/modified links, downloads, same-document anchors and local UI controls pass through. Saving retains its original validation and submission flow. Refresh, tab close and browser-driven navigation retain the browser-owned `beforeunload` safeguard; its appearance is not customizable. Restored pages reset the navigation bypass so retained unsaved values remain protected.

Drawers behave as modals: the rest of the page is inert while one is open, Escape closes it, and focus returns to the control that opened it. They are a white panel with 1.25rem padding, hairline header and footer, a 35% ink backdrop and the drawer shadow, sliding in over 0.2s. Reduced motion removes the panel slide while retaining the backdrop fade. The document reserves scrollbar space while overlays lock scrolling, preventing a sideways page shift.

### Alerts, Dropdowns and Pagination

Alerts and dropdowns follow the semantic subtle colors and the 8px menu radius. Pagination uses ink text, a hairline border and a cobalt current page. Empty table states keep a muted sentence and, where permitted, the accent create button.

## Do's and Don'ts

### Do:
- **Do** reuse the shared page header, form shell, section card and field components.
- **Do** use outlined linked chips in tables and wrapping title links in ledgers; restricted records remain plain text.
- **Do** use status chips (tint, dot and text) for state, and nothing else for color.
- **Do** keep help and metadata subordinate with small muted text.
- **Do** put row actions in the three-dots menu and keep Delete last, in red, behind a confirm.
- **Do** keep controls on white surfaces so the 3:1 outline holds.
- **Do** preserve visible focus rings and the shared save-status announcement.

### Don't:
- **Don't** add a second accent hue, per-record-kind colors, gradients or gloss.
- **Don't** add heavy borders or stronger overlay shadows to ordinary cards; use the shared soft elevation.
- **Don't** put Active switches, filters or form controls in the page header.
- **Don't** mix header-style cards with section-card form sections on the same form.
- **Don't** add in-card submit/cancel controls to forms; the header owns them.
- **Don't** introduce a per-page form shell, inline `<style>` blocks or off-palette hex values; add to `app.css` using the tokens.
- **Don't** replace ordinary sentence-case labels with tracked uppercase styling.
