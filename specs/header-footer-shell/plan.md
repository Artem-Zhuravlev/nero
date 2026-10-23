# PLAN — SPEC-031 Header, Footer and Mobile Shell

## Approach

Extend the SPEC-006 header override with includes for the announcement, strip and mobile bar; add a footer override (previously
CSS-only, now needed for structure). `ocs_shell` supplies data through a `view` event on `common/header` and `common/footer`
(SPEC-007) so templates only print variables.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Data | View event + settings | No controller edits in core |
| Mobile bar | CSS + small JS, in theme | Not a module: it is layout |

## Affected files / directories

- `custom/theme/ocs/template/common/{header,footer}.twig`, `custom/extensions/ocs_shell/`.

## Risks

- Footer override breaks the `{{ powered }}` core variable -> preserved, covered by a test.
