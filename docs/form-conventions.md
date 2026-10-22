# Form conventions

Create/edit forms share one shell and the same components. Do not invent a per-page layout.

## Shell
- `x-form-shell` (a centered column, 68rem max) wraps the form. `x-form-errors` above the `<form>`. `x-page-header` inside the form. `x-section-card` for every section. Save, Cancel, and status live in the sticky page header (`save-label`, `form-id`, `last-saved-at` props) — no in-card submit/cancel and no bottom bar. Once the header scrolls away a light compact bar (same white surface and buttons) takes over.
- Section by user job: identity → coverage/scope → people/orgs → rules/options → related records.

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
- Related string lists (options, candidates): `x-inline-string-list`. Grouped fields use `x-form-subsection`.

## Do not
- Add `<style>` blocks or off-palette hex values to a form; put styles in `resources/css/app.css` and use the tokens.
- Put Active, filters, or form controls in the page header.
- Mix `card` + `card-header` with `x-section-card` on the same form.
- Change which fields save; regroup and restyle only.
