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
    fontSize: "calc(1.375rem + 1.5vw)"
    fontWeight: 600
    lineHeight: 1.2
  headline:
    fontSize: "calc(1.325rem + 0.9vw)"
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
- **Deep Action Blue** (`primary-hover`, `primary-active`): progressively darker Bootstrap button interaction states. The custom navigation action instead brightens on hover and focus.
- **Pale Blue / Blue Ink** (`primary-subtle`, `primary-emphasis`): project relationship badges and KFS metadata.

### Secondary
- **Status Green** (`success`): active status pills and success feedback; its subtle background and emphasis text identify agreements.
- **Attention Amber** (`warning`): warnings and unsaved changes; its subtle background and emphasis text identify programs and PI metadata.
- **Information Cyan** (`info`): activity-type badges in activity tables; its subtle background and emphasis text identify states.
- **Error Red** (`danger`): validation errors, required markers, and destructive actions.

### Neutral
- **Cool Slate** (`page-bg`): the page canvas, distinct from white content surfaces.
- **Inset Gray** (`surface-inset`): current dropdown destinations.
- **White Surface** (`surface`): cards, fields, drawers, relationship rows, and the save bar.
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
- **Display:** dashboard heading, semibold (600), responsive size capped at (2.5rem) from (1200px), line height (1.2).
- **Headline:** ordinary page headings, bold (700), responsive size capped at (2rem) from (1200px), line height (1.2).
- **Title:** section-card headings (1.25rem), medium (500), line height (1.2).
- **Body:** ordinary controls and content (1rem), regular (400), line height (1.5).
- **Label:** field labels (1rem), semibold (600); required markers are appended in Error Red.
- **Small:** help, compact actions, descriptions, and relationship metadata (0.875rem). Nested small text can inherit an em-based scale from Bootstrap.
- **Subsection:** muted uppercase labels (0.8rem), semibold (600), tracking (0.04em), line height (1.3).
- **Entity type:** uppercase pill labels (0.7rem), bold (700), tracking (0.05em), line height (1).

**The Label Hierarchy Rule.** Keep ordinary labels in sentence case; reserve tracked uppercase for subsection titles and entity-type labels.

## Layout

The app uses Bootstrap's responsive, twelve-column grid. The main container has horizontal padding (0.75rem per side) and vertical page padding (1.5rem). Container maximum widths are (540px), (720px), (960px), (1140px), and (1320px) at the respective (576px), (768px), (992px), (1200px), and (1400px) breakpoints.

Create/edit forms use the shared centered ten-column shell at large widths, expanding to the available width below (992px). Section cards have body padding (1rem) and bottom spacing (1.5rem). Field groups generally use bottom spacing (1rem). Subsections have a left rule (4px), inset (1rem), and separation (1.25rem). Reuse the existing grid and gap utilities rather than inventing per-page spacing scales.

Page headers wrap their title, metadata, and action areas with a gap (1rem). Tables remain horizontally scrollable in their responsive wrapper. The top navigation collapses below (992px); its search and activity action become a full-width tools row in the collapsed menu. Radio-card rows use two equal columns with a gap (0.5rem).

Drawers attach to the right edge at a maximum width (52rem), filling narrower viewports. Their bodies scroll independently. Forms reserve bottom space (70px) for the fixed save bar, whose minimum height is (54px).

## Elevation & Depth

Depth is layered and functional. White cards separate from the Cool Slate canvas with thin borders and subtle shadows. Relationship rows use borders rather than individual shadows. Drawers add a stronger lateral shadow and a translucent charcoal backdrop; the fixed save bar casts a small upward shadow.

### Shadow Vocabulary
- **Section/card:** `0 0.125rem 0.25rem rgba(0, 0, 0, 0.075)`.
- **Entity detail card:** `0 0.2rem 0.75rem rgba(0, 0, 0, 0.07)`, paired with a translucent border.
- **Save bar:** `0 -2px 12px rgba(0,0,0,.08)`.
- **Drawer:** `-0.5rem 0 1.5rem rgba(0, 0, 0, 0.12)`.

**The Working Surface Rule.** Use existing card, drawer, and save-bar treatments for their respective layers; keep internal relationship rows visually subordinate.

## Shapes

Controls and cards are gently rounded rectangles. Standard cards, fields, and buttons use the medium radius; small buttons and navigation tools use the small radius. Entity-type labels, count badges, and status badges use pills. Ordinary relationship chips retain rectangular rounded corners unless their caller requests a pill.

Borders are generally (1px). Exceptions carry structure: subsection rules (4px) and the current dropdown item's blue left accent (3px). Iconography comes from Bootstrap Icons; it supports actions and labels.

## Components

### Buttons

Practical actions with familiar filled and outline variants. Primary actions use Action Blue and white text; editing uses an outline-blue action in the shared page header. Cancel and supporting actions commonly use outline gray. Standard buttons use the medium radius and padding (0.375rem 0.75rem); compact buttons use the small radius and padding (0.25rem 0.5rem), with text (0.875rem).

Bootstrap buttons darken or fill on hover, show a focus ring for keyboard focus, and reduce opacity when disabled. Their state transitions last (0.15s), with Bootstrap reduced-motion handling. Preserve the custom navigation action's distinct hover brightening and outlined current state.

### Cards / Containers

White, thin-bordered working surfaces. Use `x-section-card` for form sections: medium corners, subtle card shadow, a medium-weight title, optional muted subtitle, and body padding (1rem). Entity detail cards use the distinct shadow above. Index table cards clip overflow around their responsive table and footer.

### Inputs / Fields

White fields with a thin Divider Gray outline, medium corners, body-size text, and padding (0.375rem 0.75rem). Focus uses Bootstrap's pale blue border and blue ring (0.25rem). Invalid fields use Error Red and associated feedback; disabled fields use the framework's secondary background.

Reuse `x-form-field` for semibold labels, required markers, one-line help, and inline errors. Token pickers wrap selected relationship badges and a search field in a control with minimum height (42px) and compact vertical padding (0.25rem). Disabled token pickers preserve opacity and show a not-allowed cursor. Use switches inside Options subsections and radio cards for choices with consequences. Selected radio cards use a blue border and Light Gray background.

### Chips / Badges

Compact, bold labels that distinguish record kind, metadata, counts, and status. Related-record chips use subtle entity backgrounds, dark emphasis text, and a border; linked chips are underlined. Organization chips use the custom mist/slate/edge palette. Type labels and counts use pills; active and inactive status pills use solid green and gray with explicit text. Preserve existing contextual exceptions such as cyan activity-type badges in activity tables.

### Navigation

A dark horizontal bar with muted white links and a white brand label. The current primary destination uses white semibold text and a thin inset underline. Current dropdown destinations use Inset Gray and a blue left rule. Search is a compact translucent field with a maximum width (15rem); Log Activity is a small blue action. Preserve the shared collapsed-menu behavior at the large breakpoint.

### Relationship Ledger Rows and Tables

Rows group an underlined linked title, muted metadata, optional entity badges, and trailing actions inside a bordered white rounded surface with padding (0.5rem 1rem). Optional details sit below a divider. Index tables use light headers, vertically centered cells, row hover feedback, and compact right-aligned action groups. Reuse the shared sort, badge-list, and pagination components.

### Save Bar and Drawers

The save bar keeps form actions at the viewport bottom, with status on the left and cancel/save on the right. It distinguishes idle, unsaved, saving, saved, and error states through text and button treatment. Keep its live status announcement and reserved page space.

Drawers place related forms in a white panel over a translucent backdrop, with bordered header/footer, body padding (1.25rem), and horizontal padding (1.25rem) around the header/footer. The panel slides and the backdrop fades over (0.2s ease). The existing custom drawer CSS does not establish a reduced-motion override; this document does not imply one exists.

## Do's and Don'ts

### Do:
- **Do** reuse the shared page header, form shell, section card, field, and save bar components.
- **Do** use the existing entity palette consistently for record kinds and labeled status pills for state.
- **Do** keep help and metadata subordinate with small text and muted color.
- **Do** retain responsive table wrappers, wrapping metadata, and drawer body scrolling.
- **Do** preserve visible focus treatments and the shared save-status announcement.

### Don't:
- **Don't** put Active switches, filters, or form controls in the page header.
- **Don't** mix header-style cards with section-card form sections on the same form.
- **Don't** add in-card submit/cancel controls to forms that use the sticky save bar.
- **Don't** introduce a new per-page form shell or remap entity colors independently.
- **Don't** replace ordinary sentence-case labels with tracked uppercase metadata styling.
