# PLAN — SPEC-121 Выбор шрифта

## Approach

Каталог шрифтов в чистом классе `Ocs\Fonts` (`custom/extensions/ocs/upload/system/library/ocs/fonts.php`): ключ → название, семейство, веса, fallback-стек, метрики fallback, наличие файлов (по `is_file` в каталоге темы). `Fonts::resolve($key)` — белый список с откатом на `montserrat`. Событие/контроллер шапки кладёт в данные готовые `font_css` и `font_preload`; `header.twig` выводит `<style>` и preload выбранного шрифта. Статические `@font-face` Montserrat и `--font` убираются из `stylesheet.css` (иначе грузились бы всегда) — остаётся `font-family: var(--font)`, а `--font` по умолчанию в `:root` как fallback-стек без файлов.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Где `@font-face` | Montserrat остаётся статически в CSS (браузер не качает неиспользуемый face), остальные генерируются в `<style>` шапки по выбору | Не пересобирать CSS, грузится только выбранное |
| Формат файлов | woff2, `fonts/<key>-<latin\|cyrillic>-<вес>.woff2` (как Montserrat) | Единый шаблон имён, fontsource-дистрибуция |
| Источник OFL-файлов | fontsource (npm), subset latin + cyrillic | Тот же источник, что у Montserrat, лицензия OFL |
| Коммерческие шрифты | Каталог знает их, но выбор доступен только при наличии файлов | Нельзя вкладывать без лицензии; не блокирует остальную работу |
| Fallback-метрики | `size-adjust`/`ascent-override` в каталоге на шрифт | Минимизирует CLS |

## Affected files

- `custom/extensions/ocs/upload/system/library/ocs/fonts.php` (новый), событие в `catalog/controller/extension/ocs/events.php`
- `custom/extensions/ocs/upload/admin/{controller,view,language}/extension/theme/ocs.*` (поле, валидация, 3 языка)
- `custom/theme/ocs/template/common/header.twig`, `custom/theme/ocs/stylesheet/stylesheet.css` (убрать статические `@font-face`)
- `custom/theme/ocs/fonts/*.woff2`, `fonts/README.txt`, `custom/theme/ocs/DESIGN.md`
- `custom/db/migrations/NNNN_theme_font.php`, `scripts/test/` (PHP + Node), `scripts/test/theme.test.mjs` (правка SPEC-102 проверок), `specs/README.md`, `custom/VERSION`, `npm run pack:theme`

## Risks

- Лицензии Gilroy/Proxima Nova/Geometria: без файлов от владельца они остаются недоступными (REQ-FCH-004).
- Размер пакета темы: +5 OFL-шрифтов × 10 файлов ≈ 1–2 МБ; допустимо.
- Тесты SPEC-102 не меняются: статический Montserrat в CSS сохранён.
