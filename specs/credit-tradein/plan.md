# PLAN — SPEC-036 Instalments and Trade-in

## Approach

Two small modules rendered in product-page slots (SPEC-035). Calculator is pure JS over server-provided plan data plus a server
check function used by tests; trade-in is a form handled by the shared request library of SPEC-025.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Calculator | Display-only, settings-driven | Bank APIs vary per client |
| Form handling | Reuse `orderrequest` library | Same validation, mail and rate limit |

## Affected files / directories

- `custom/extensions/ocs_credit/`, `custom/extensions/ocs_tradein/`, migrations.

## Risks

- Misleading financial display -> explicit disclaimer text mandatory (REQ-CRD-002).
