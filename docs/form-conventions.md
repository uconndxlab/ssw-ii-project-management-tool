# Form conventions

Create/edit forms share one shell and the same components. Do not invent a per-page layout.

## Shell
- `x-form-shell` (a centered column, 68rem max) wraps the form. `x-form-errors` above the `<form>`. `x-page-header` inside the form. `x-section-card` for every section. Save, Cancel, and status live in the sticky page header (`save-label`, `form-id`, `last-saved-at` props) — no in-card submit/cancel and no bottom bar. Once the header scrolls away a light compact bar (same white surface and buttons) takes over.
- Section by user job: identity → coverage/scope → people/orgs → rules/options → related records.
- Header metadata is optional. Omit entity-type badges; create/edit titles and breadcrumbs already establish the entity. Do not leave an empty metadata row for spacing.
- Dirty forms guard ordinary same-tab links and Cancel with the shared leave warning: exit icon, record name, Keep editing first, and Discard changes. Keep editing preserves input and restores focus; approved navigation bypasses the native warning once. Modified/new-tab links, downloads, same-document anchors and local controls keep their usual behavior. Save still validates and submits normally. Retain `beforeunload` for refresh, tab close and browser navigation; it cannot be restyled.

## Fields
- `x-form-field` for labeled controls. Short noun labels (`Information`, `Logging Fields`, `Details`). Required uses `required-label` (no extra `*`, no "(Optional)" unless surprising, e.g. edit password).
- Labels use `.form-label` at `font-weight: 500` in ink; controls are 36px high (32px small) with the 3:1 outline and a cobalt focus ring. Token pickers share that height and show selected records as neutral chips (`app-chip`) with a remove button.
- Pass `for` when the control has an id. Help and error text get ids and `form-fields.js` links them to the control (`aria-describedby`, `aria-invalid`, `aria-required`), and names a control whose label has no `for`. A set of radios sharing one question uses `<x-form-field group ...>` (a fieldset with a legend); a radio list without a field label uses `role="radiogroup"` and an `aria-label`.
- Token pickers are comboboxes: pass `labelled-by` (or `aria-label`) when the picker is not inside an `x-form-field`. Index filters need an `aria-label` on every control. Labels without a `for` are linked to the one control beside them by `form-fields.js`, including rows added by scripts.
- Token picker changes made by a person mark the form as having unsaved changes (the picker fires `token-picker:change` with `detail.user`).
- Drawers (`x-form-drawer`) are opened and closed by toggling `.is-open`; `form-drawer.js` handles focus, the page lock, the close buttons and Escape.
- Help is one `form-text` line under the control, only when the label is not enough. No implementation essays ("inferred and not saved") on every picker — the scope picker already explains that.
- Booleans: `x-form-switch` (hidden `0` + checkbox `1`) inside `x-form-options` (left-bar subsection titled Options). Controllers use `$request->boolean('name')`, never `has('name')`.
- Active belongs in the identity section Options, never in `x-page-header`, never a Status `<select>`.
- Choices with consequences: `x-form-radio-card`.
- Destructive submits use `data-confirm` with escaped title, record, message and action attributes; the shared confirmation resumes the original form after approval. Form-only cleanup uses `await window.appConfirm(...)` and changes the unsaved form only. Do not use `window.confirm()` or interpolate names into inline JavaScript.
- Related string lists (plain options): `x-inline-string-list`. Entity + detail rows (e.g. agreement certification candidates) use `x-repeater-rows` with a compact summary and focused display/edit row. Candidate assignments stay directly editable within that row, without another collapse step. Single-person search reuses `x-token-picker` with `single=true`; ordinary program and duty pickers remain multi-select. A plain string list cannot carry an identity reference or assignment fields. Grouped fields use `x-form-subsection`.

## Do not
- Add `<style>` blocks or off-palette hex values to a form; put styles in `resources/css/app.css` and use the tokens.
- Put Active, filters, or form controls in the page header.
- Mix `card` + `card-header` with `x-section-card` on the same form.
- Change which fields save; regroup and restyle only.

## Certificate catalog authoring

- Use direct job-based section buttons for Basics / Initial requirements / Renewal / Review rules.
  Keep one certificate-level save. Section navigation, Done editing and contextual role edits never save.
- A compact requirement row opens one inline editor. Reveal Activity work / Tool submissions /
  Coach confirmation fields by kind; one visible editor at a time. Full review expands every condition.
- Keep grouped members together beneath their All / Any one / A set number rule. Use up/down buttons
  for keyboard ordering. Ungrouping preserves members; removing a requirement is a separate action.
- Manage certificate-specific roles beside the selector and resolve new roles in the same transaction.
  Preserve global roles and Any role; this is not a general role-administration surface.
- Explain linked renewal versus an independent copy. Confirm clearing/replacement and incompatible
  type/tool changes. Keep incompatible scope selections visible for correction. Preserve stable IDs,
  stage/group references and nested values through validation; open the section/editor with the error.
- This certificate-specific editor is a proven local pattern, not a new shared form framework. Evaluate
  a difficult deliverable before extracting reusable behavior; enterprise-style reconciliation stays separate.

## Activity certification capture

- Opening another candidate editor scrolls the selected candidate below the phone and compact form
  headers after the previous editor collapses, while retaining keyboard focus on the editor toggle.
- Enrollment progress links retain the source agreement for breadcrumbs. Only visible roster-linked
  agreements can appear in that navigation context; agreement context never limits certificate credit.

## Certification lifecycle actions

- Endorse, withdraw, return to coach, award, revoke and change coach are inline `<details>` panels on
  the enrollment progress page, like coach confirmations. Each posts only its own action, never the
  page. Unavailable actions are explained in the Next step text instead of shown as disabled buttons.
- Required reasons (return, revoke) use `x-form-field` with the error name only for the submitted
  panel, so one failed panel does not mark the others invalid. A hidden `lifecycle_form` reopens it.
- Lifecycle forms post the cycle and the endorsement they were opened against; a stale or duplicate
  submission returns an error flash that says nothing was saved.
