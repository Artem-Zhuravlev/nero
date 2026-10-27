# PLAN — SPEC-054 Breadcrumbs: schema.org Markup, No Self-Link, "Home" as a Word

## Approach

Replace the 3-line `{% for breadcrumb in breadcrumbs %}` block in all storefront templates with one identical block
(scripted replace). Home text comes from a view-event that rewrites `fa-home` breadcrumb text to the language
string, so templates stay language-agnostic. `.breadcrumb` CSS gets a rule for the current-item `<span>`.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Markup | Microdata in the `<ul>` | Matches the existing HTML, no extra JSON; JSON-LD would need a controller event per page |
| Home word | Language string + event | Hard-coding "Главная" in 44 templates breaks uk-ua/en-gb |
| Last item | `<span aria-current="page">` | Valid per Google (last item may omit `item`) |

## Affected files / directories

- `custom/theme/ocs/template/**` (43 files), `custom/extensions/ocs_category_wall/.../ocs_category_wall_page.twig`
- `custom/extensions/ocs_shell/upload/catalog/controller/extension/ocs_shell/events.php` + language files
- `custom/theme/ocs/stylesheet/stylesheet.css`
- a node test in `scripts/test/` checking all templates use the same block

## Risks

- A template that renders breadcrumbs via JS (`$('.breadcrumb').after(...)`) -> selector stays valid.
- Event runs for non-`ocs` theme -> guard with the existing `enabled()` check.
