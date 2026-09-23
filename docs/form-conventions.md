# Form conventions

Create/edit forms share one shell and the same components. Do not invent a per-page layout.

## Shell
- `col-lg-10` centered. `x-form-errors` above the `<form>`. `x-page-header` inside the form. `x-section-card` for every section. Sticky `x-save-bar` only — no in-card submit/cancel.
- Section by user job: identity → coverage/scope → people/orgs → rules/options → related records.

## Fields
- `x-form-field` for labeled controls. Short noun labels (`Information`, `Logging Fields`, `Details`). Required uses `required-label` (no extra `*`, no "(Optional)" unless surprising, e.g. edit password).
- Labels use `.form-label` at `font-weight: 600`.
- Help is one `form-text` line under the control, only when the label is not enough. No implementation essays ("inferred and not saved") on every picker — the scope picker already explains that.
- Booleans: `x-form-switch` (hidden `0` + checkbox `1`) inside `x-form-options` (left-bar subsection titled Options). Controllers use `$request->boolean('name')`, never `has('name')`.
- Active belongs in the identity section Options, never in `x-page-header`, never a Status `<select>`.
- Choices with consequences: `x-form-radio-card`.
- Related string lists (options, candidates): `x-inline-string-list`. Grouped fields use `x-form-subsection`.

## Do not
- Put Active, filters, or form controls in the page header.
- Mix `card` + `card-header` with `x-section-card` on the same form.
- Change which fields save; regroup and restyle only.
