# PLAN — SPEC-124 Характеристики, наличие, код, без bold

## Approach

Правки только в теме. (1) Новая разметка и CSS блока `.ocs-specs` вместо `table-bordered`. (2) Два SVG-символа в спрайт, `.ocs-stock::before`-иконки не используем — выводим `<svg>` в шаблонах рядом с текстом. (3) Код переносим в `.caption`. (4) Вес: ввести токены `--fw-text: 400`, `--fw-btn: 500`, `--fw-strong: 700`; заменить значения в правилах по списку: всё не из REQ-TYPO-002 → токен текста; цены/заголовки → `--fw-strong`.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Скрытие кода/наличия в списках | Существующие `show_sku`/`show_stock` | Уже работают во всех списках; второй переключатель только для списков — лишняя настройка |
| Иконки | SVG-символы в спрайте | Стиль остальных иконок темы; без шрифтовых иконок |
| Веса | Токены в `:root` | Единая точка правки, не 130 литералов |

## Affected files / directories

- `custom/theme/ocs/template/product/product.twig`, `template/ocs/card.twig`, спрайт иконок (найти в `common/header.twig`)
- `custom/theme/ocs/stylesheet/stylesheet.css`
- `scripts/test/*` (новый тест), `specs/README.md`, `dist/ocs-theme-*.ocmod.zip`

## Risks

- Массовая замена весов затронет много экранов -> правка по группам, просмотр ключевых страниц в браузере.
- В рабочем дереве есть незакоммиченные правки SPEC pdp-qty-stepper в тех же файлах (`product.twig`, `stylesheet.css`) -> не смешивать в одном коммите.
