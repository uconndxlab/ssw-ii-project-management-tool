---
name: TA Tracker
description: The Working Ledger — a practical, orderly interface for project and activity records.
colors:
  primary: "#0d6efd"
  primary-hover: "#0b5ed7"
  primary-active: "#0a58ca"
  primary-subtle: "#cfe2ff"
  primary-emphasis: "#052c65"
  page-bg: "#dde1e6"
  surface-inset: "#e8eaed"
  surface: "#fff"
  light: "#f8f9fa"
  ink: "#212529"
  muted: "rgba(33, 37, 41, 0.75)"
  border: "#dee2e6"
  secondary: "#6c757d"
  secondary-subtle: "#e2e3e5"
  secondary-emphasis: "#2b2f32"
  success: "#198754"
  success-subtle: "#d1e7dd"
  success-emphasis: "#0a3622"
  warning: "#ffc107"
  warning-subtle: "#fff3cd"
  warning-emphasis: "#664d03"
  danger: "#dc3545"
  info: "#0dcaf0"
  info-subtle: "#cff4fc"
  info-emphasis: "#055160"
  organization-bg: "#eef1f6"
  organization-text: "#4a5568"
  organization-border: "#d8dee9"
typography:
  display:
    fontSize: "2rem"
    fontWeight: 600
    lineHeight: 1.2
  headline:
    fontSize: "1.75rem"
    fontWeight: 700
    lineHeight: 1.2
  title:
    fontSize: "1.25rem"
    fontWeight: 500
    lineHeight: 1.2
  body:
    fontFamily: 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"'
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontSize: "1rem"
    fontWeight: 600
    lineHeight: 1.5
  small:
    fontSize: "0.875rem"
    lineHeight: 1.5
  subsection:
    fontSize: "0.8rem"
    fontWeight: 600
    lineHeight: 1.3
    letterSpacing: "0.04em"
  entity-type:
    fontSize: "0.7rem"
    fontWeight: 700
    lineHeight: 1
    letterSpacing: "0.05em"
rounded:
  sm: "0.25rem"
  md: "0.375rem"
  pill: "50rem"
spacing:
  xs: "0.25rem"
  sm: "0.5rem"
  compact: "0.75rem"
  md: "1rem"
  section: "1.25rem"
  lg: "1.5rem"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.surface}"
    rounded: "{rounded.md}"
    padding: "0.375rem 0.75rem"
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
    textColor: "{colors.surface}"
  button-primary-active:
    backgroundColor: "{colors.primary-active}"
    textColor: "{colors.surface}"
  button-outline-primary:
    backgroundColor: "transparent"
    textColor: "{colors.primary}"
    rounded: "{rounded.md}"
    padding: "0.375rem 0.75rem"
  button-outline-secondary:
    backgroundColor: "transparent"
    textColor: "{colors.secondary}"
    rounded: "{rounded.md}"
    padding: "0.375rem 0.75rem"
  button-small:
    rounded: "{rounded.sm}"
    padding: "0.25rem 0.5rem"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "0.375rem 0.75rem"
    typography: "{typography.body}"
  section-card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "{spacing.md}"
  organization-chip:
    backgroundColor: "{colors.organization-bg}"
    textColor: "{colors.organization-text}"
    rounded: "{rounded.md}"
    padding: "0.35em 0.65em"
  navigation:
    backgroundColor: "{colors.ink}"
    padding: "0.5rem 0"
---

# Design System: TA Tracker

## Overview

**Creative North Star: "The Working Ledger"**

A practical, orderly, restrained interface for working with agreements, activities, organizations, and related records. Familiar controls and a stable visual hierarchy keep attention on the work. White working surfaces sit over Cool Slate; Action Blue identifies primary actions and links.

Components are practical and restrained. Depth is layered: subtle shadows separate working surfaces, while borders, headings, and compact badges organize the information inside them. The system uses a single system sans-serif family, clear labels, and responsive Bootstrap grids.

**Key Characteristics:**
- Cool neutral canvas, white working surfaces, and blue actions.
- Compact metadata and tables alongside clearly grouped forms.
- Consistent entity colors and labeled status indicators.
- Small corner radii, thin borders, and subtle structural shadows.

This is a scan of the existing implementation, not a redesign. Sources are `resources/css/app.css`, `resources/views/layouts/app.blade.php`, shared Blade components, `app/Support/EntityBadge.php`, and `docs/form-conventions.md`. Bootstrap CSS is pinned to 5.3.2 in the layout; Bootstrap Icons is pinned to 1.11.3. Live home and activity-create styles were sampled during documentation. No PRODUCT.md exists; the descriptive language above was confirmed for this documentation pass.

## Colors

The palette combines Action Blue with cool neutrals and functional status and entity colors. Frontmatter values are normative; app-specific values come from the root custom properties, and framework values come from the pinned Bootstrap stylesheet.

### Primary
- **Action Blue** (`primary`): primary buttons, links, selected radio-card borders, and the navigation activity action.
- **Deep Action Blue** (`primary-hover`, `primary-active`): progressively darker Bootstrap button interaction states. Log Activity uses the same Bootstrap hover and keyboard-focus treatment.
- **Pale Blue / Blue Ink** (`primary-subtle`, `primary-emphasis`): project relationship badges and KFS metadata.

### Secondary
- **Status Green** (`success`): active status pills and success feedback; its subtle background and emphasis text identify agreements.
- **Attention Amber** (`warning`): warnings and unsaved changes; its subtle background and emphasis text identify programs and PI metadata.
- **Information Cyan** (`info`): activity-type badges in activity tables; its subtle background and emphasis text identify states.
- **Error Red** (`danger`): validation errors, required markers, and destructive actions.

### Neutral
- **Cool Slate** (`page-bg`): the page canvas, distinct from white content surfaces.
- **Inset Gray** (`surface-inset`): current dropdown destinations.
- **White Surface** (`surface`): cards, fields, drawers, relationship rows, and the action buttons' raised layer.
- **Light Gray** (`light`): quiet metadata, selected radio cards, and supporting regions.
- **Charcoal Ink** (`ink`): body text and the dark navigation background.
- **Muted Ink** (`muted`): secondary text, help, and subsection labels.
- **Divider Gray** (`border`): field outlines, section dividers, and ordinary badge borders.
- **Secondary Gray** (`secondary`): inactive status and secondary controls. Its subtle/emphasis pair identifies teams, activities, and several administrative record types.
- **Organization Mist / Slate / Edge** (`organization-bg`, `organization-text`, `organization-border`): the custom organization relationship palette.

**The Entity Color Rule.** Reuse `EntityBadge` mappings across related records, type labels, and counts. Entity color conveys record kind; labeled status pills convey active or inactive state.

## Typography

**Display and Body Font:** the Bootstrap system sans-serif stack captured in `typography.body`, inherited throughout. No separate display, web, or monospace family is established.

**Character:** familiar and utilitarian. Weight, size, spacing, and muted metadata establish hierarchy without decorative typography.

### Hierarchy
- **Display:** dashboard heading, semibold (600), fixed size (2rem), line height (1.2).
- **Headline:** ordinary page headings, bold (700), fixed size (1.75rem), line height (1.2).
- **Title:** form-section and entity-detail card headings (1.25rem), medium (500), line height (1.2).
- **Body:** ordinary controls and content (1rem), regular (400), line height (1.5).
- **Label:** field labels (1rem), semibold (600); required markers are appended in Error Red.
- **Small:** help, compact actions, descriptions, and relationship metadata (0.875rem). Nested small text can inherit an em-based scale from Bootstrap.
- **Subsection:** muted uppercase labels (0.8rem), semibold (600), tracking (0.04em), line height (1.3).
- **Entity type:** uppercase pill labels (0.7rem), bold (700), tracking (0.05em), line height (1).

**The Label Hierarchy Rule.** Keep ordinary labels in sentence case; reserve tracked uppercase for subsection titles and entity-type labels.

## Layout

The app uses Bootstrap's responsive, twelve-column grid. Signed-in pages use a shell: on desktop a left sidebar beside the main content with no top bar; below (992px) a sticky phone top bar (3.5rem) over the main content. The body is a flex column with a minimum height of `100dvh` (and a `100vh` fallback); main content fills the remaining width and does not assume a navbar height. Beside the sidebar the main container has horizontal padding (1.5rem per side); vertical page padding is (1.5rem). Container maximum widths are (540px), (720px), (960px), (1140px), and (1320px) at the respective (576px), (768px), (992px), (1200px), and (1400px) breakpoints.

Create/edit forms use the shared centered ten-column shell at large widths, expanding to the available width below (992px). Section cards have body padding (1rem) and bottom spacing (1.5rem). Field groups generally use bottom spacing (1rem). Subsections have a left rule (4px), inset (1rem), and separation (1.25rem). Reuse the existing grid and gap utilities rather than inventing per-page spacing scales.

Page headers wrap their title/metadata block and action area with a gap (1rem). Actions align with the top of the title, including when long record names wrap; the title block can shrink and break long unspaced names. Tables remain horizontally scrollable in their responsive wrapper. Below (992px) the sidebar becomes a slide-in menu opened from the phone top bar. Radio-card rows use two equal columns with a gap (0.5rem).

Drawers attach to the right edge at a maximum width (52rem), filling narrower viewports. Their bodies scroll independently.

## Elevation & Depth

Depth is layered and functional. White cards separate from the Cool Slate canvas with thin borders and subtle shadows. Relationship rows use borders rather than individual shadows. Drawers add a stronger lateral shadow and a translucent charcoal backdrop; the compact header bar casts a deeper downward shadow.

### Shadow Vocabulary
- **Section/card:** `0 0.125rem 0.25rem rgba(0, 0, 0, 0.075)`.
- **Entity detail card:** `0 0.2rem 0.75rem rgba(0, 0, 0, 0.07)`, paired with a translucent border.
- **Buttons:** solid buttons cast `0 1px 3px` in their own color at 45% opacity; outline buttons use a white fill with `0 1px 2px rgba(33,37,41,.08)`. No gloss or gradients. Grouped, link, close, and disabled buttons stay flat.
- **Compact header bar:** `0 0.75rem 2rem rgba(33,37,41,.28)`.
- **Drawer:** `-0.5rem 0 1.5rem rgba(0, 0, 0, 0.12)`.

**The Working Surface Rule.** Use existing card, drawer, and header-bar treatments for their respective layers; keep internal relationship rows visually subordinate.

## Shapes

Controls and cards are gently rounded rectangles. Standard cards, fields, and buttons use the medium radius; small buttons and navigation tools use the small radius. Entity-type labels, count badges, and status badges use pills. Ordinary relationship chips retain rectangular rounded corners unless their caller requests a pill.

Borders are generally (1px). Exceptions carry structure: subsection rules (4px) and the current dropdown item's blue left accent (3px). Iconography comes from Bootstrap Icons; it supports actions and labels.

## Components

### Buttons

Practical actions with familiar filled and outline variants. Primary actions use Action Blue and white text; editing uses an outline-blue action in the shared page header. Cancel and supporting actions commonly use outline gray. Standard buttons use the medium radius and padding (0.375rem 0.75rem); compact buttons use the small radius and padding (0.25rem 0.5rem), with text (0.875rem).

Buttons are medium weight and sit on the soft lift described in the Shadow Vocabulary, so they stand above the slate canvas by default. Bootstrap buttons darken or fill on hover, show a focus ring for keyboard focus, and reduce opacity when disabled. Their state transitions last (0.15s), with Bootstrap reduced-motion handling. Log Activity reuses `btn btn-primary btn-sm` and retains an outlined current-page state; do not add a brightness filter that reduces text contrast.

### Page Headers and Breadcrumbs

Reuse `x-page-header` with its existing props and `badges`, `meta`, `controls`, and `more` slots. Default page headings use `.app-page-title` (1.75rem); dashboard headings use `.app-dashboard-title` (2rem). Explicit title and control classes remain supported. Keep the record's Active/Inactive badge in the header metadata for immediate scanning; do not repeat it in Summary. Agreement type labels remain unchanged.

Header actions are captured once and repeated in the compact bar. In the full header the form status sits under the buttons; in the compact bar it sits beside them. Icon-only buttons and Save (with its shortcut) get tooltips; a button that already shows its text gets none. A `more` slot adds an overflow menu (three dots) to the right of the main actions; it is left out when empty. The main action keeps its label in the full header and in the compact bar only Save keeps its label (Cancel and Edit become icons whose names stay available); on phones Save is an icon too.

Form headers are sticky: pass `sticky` to override (`true` for other contexts, `false` to turn off). Once the full header scrolls out of view a compact bar slides in at the top of the form column without moving the page: back link to the parent crumb, title, status, and actions. The compact bar is dark on purpose (an experiment): it reads as chrome, not as another white card. The full header itself never shifts. Form headers take `save-label`, `form-id`, and `last-saved-at`.

Breadcrumbs show route hierarchy, independent of navigation history: resource index → record on show pages, resource index → Create on create pages, and resource index → record → Edit where a show route exists. Resources without show pages link directly from their index to Edit; profile editing links to Profile. The current crumb exposes `aria-current="page"`. `RouteBreadcrumbs` supplies this hierarchy and also the form Cancel target: the page the user came from when it is a record or list page on this site (so index filters and a record's Edit button both return them there), otherwise the parent crumb. The proposed page-header prop/inference API refactor is deferred.

### Cards / Containers

White, thin-bordered working surfaces. Use `x-section-card` for form sections: medium corners, subtle card shadow, a medium-weight title, optional muted subtitle, and body padding (1rem). Entity detail cards use the distinct shadow above and the same sentence-case, medium-weight (1.25rem) `h2` title style as form sections. Their light header strip remains; counts and actions wrap alongside the heading. Recent activity and the main card render in the requested `activityFirst` order, including in heading and keyboard navigation. Index table cards clip overflow around their responsive table and footer.

### Inputs / Fields

White fields with a thin Divider Gray outline, medium corners, body-size text, and padding (0.375rem 0.75rem). Focus uses Bootstrap's pale blue border and blue ring (0.25rem). Invalid fields use Error Red and associated feedback; disabled fields use the framework's secondary background.

Reuse `x-form-field` for semibold labels, required markers, one-line help, and inline errors. Token pickers wrap selected relationship badges and a search field in a control with minimum height (42px) and compact vertical padding (0.25rem). Disabled token pickers preserve opacity and show a not-allowed cursor. Use switches inside Options subsections and radio cards for choices with consequences. Selected radio cards use a blue border and Light Gray background.

### Chips / Badges

Compact, bold labels that distinguish record kind, metadata, counts, and status. Related-record chips use subtle entity backgrounds, dark emphasis text, and a border; linked chips are underlined. Organization chips use the custom mist/slate/edge palette. Type labels and counts use pills; active and inactive status pills use solid green and gray with explicit text. Preserve existing contextual exceptions such as cyan activity-type badges in activity tables.

### Navigation

One floating sidebar holds everything global; there is no desktop top bar. Below (992px) a slim dark top bar carries only the menu button, the brand, and Log Activity.

The sidebar is a floating white card (radius 0.75rem, inset 0.75rem from the viewport edge) lifted off the slate canvas with the entity-card border and shadow, so the layers read canvas, then chrome and cards. It has three zones. **Actions** at the top: the brand mark and name with a pin button, a search field (a “/” hint; pressing “/” focuses it) and a solid Action Blue Log Activity button, so actions look different from destinations. **Destinations** in the middle: links come from `AppNav::railGroups()`, which reuses the role rules in `primaryLinks()` and `adminSections()`: Work, then Directory (members) or the admin setup groups (admins). Every group is always listed; none collapses. Each link is a Bootstrap Icon plus a label. The section that owns the current route gets a Primary Subtle fill, semibold Primary Emphasis text, and `aria-current` (`page` on the index, `true` on child pages), whatever the entry point. **Personal** at the bottom: Recent, then an account row (initials avatar, name, access profile) whose menu opens upward with Profile and Log out. An open Recent button turns solid Action Blue, distinct from the tinted current section.

The sidebar is pinned open (15rem, docked, the page makes room) or unpinned (4.25rem icons, the page does not move). Unpinned, it peeks open over the page on pointer hover (120ms in, 250ms out), keyboard focus, the search shortcut, or while the Recent panel or account menu is showing; Escape dismisses a peek. The pin choice is remembered per user in `localStorage` and new users start pinned at 1200px and wider. Touch devices and narrow screens do not hover: below (992px) the same markup is a Bootstrap offcanvas menu with the search field, the groups and the account row; links are at least (44px) high.

Recent is a floating card (same radius, with a larger shadow) beside the sidebar, sized to its content and scrolling past the viewport, listing up to 30 records opened in the last seven days (newest first under a “Last opened” label, a record-type icon per row, and a “Current” tag). It has a labeled search with a leading icon, a Clear action, empty, no-match, and load-error states, closes on Escape or an outside click, and returns focus to its button. Entries are stored per user in `recent_records` and rechecked with the `view` policy each time the panel opens.

A “Skip to content” link is the first keyboard stop and becomes visible on focus; it targets the focusable main landmark. The sidebar navigation has an accessible landmark name, the pin is a toggle button with `aria-pressed`, collapsed links keep their labels as accessible names, and flash-message dismiss buttons are named “Close”. Bootstrap, Bootstrap Icons, and htmx retain their pinned CDN versions with SRI hashes and anonymous CORS attributes.

The app uses a light content theme with a dark navigation bar. A separate dark theme is intentionally deferred.

### Relationship Ledger Rows and Tables

Rows group an underlined linked title, muted metadata, optional entity badges, and trailing actions inside a bordered white rounded surface with padding (0.5rem 1rem). Optional details sit below a divider. Index tables use light headers, vertically centered cells, row hover feedback, and compact right-aligned action groups. Reuse the shared sort, badge-list, and pagination components.

### Form Actions and Drawers

Form actions live in the page header, not in a bottom bar: a status line (idle, unsaved, saving, saved, error through text and icon, announced politely once), Cancel, and the primary Save/Create button. Save does not use implicit submit: it calls `requestSubmit()`, so Enter inside token pickers keeps its own behavior. Ctrl/⌘ + S saves, and a `beforeunload` guard protects unsaved edits. Cancel returns to the previous record or list page, else the parent crumb.

Drawers place related forms in a white panel over a translucent backdrop, with bordered header/footer, body padding (1.25rem), and horizontal padding (1.25rem) around the header/footer. The panel slides and the backdrop fades over (0.2s ease). The existing custom drawer CSS does not establish a reduced-motion override; this document does not imply one exists.

## Do's and Don'ts

### Do:
- **Do** reuse the shared page header, form shell, section card, and field components.
- **Do** use the existing entity palette consistently for record kinds and labeled status pills for state.
- **Do** keep help and metadata subordinate with small text and muted color.
- **Do** retain responsive table wrappers, wrapping metadata, and drawer body scrolling.
- **Do** preserve visible focus treatments and the shared save-status announcement.

### Don't:
- **Don't** put Active switches, filters, or form controls in the page header.
- **Don't** mix header-style cards with section-card form sections on the same form.
- **Don't** add in-card submit/cancel controls to forms; the header owns them.
- **Don't** introduce a new per-page form shell or remap entity colors independently.
- **Don't** replace ordinary sentence-case labels with tracked uppercase metadata styling.
