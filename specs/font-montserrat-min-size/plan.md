# PLAN — SPEC-102

1. Скачать Montserrat (OFL) woff2 для latin + cyrillic, веса 400–800, в `custom/theme/ocs/fonts/` (нужно ваше разрешение на скачивание: источник — Google Fonts / fontsource, ~100–200 КБ суммарно). Обновить `fonts/README.txt`.
2. `stylesheet.css`: заменить `@font-face` Gilroy на Montserrat, `--font` (в двух местах), fallback-метрики для Montserrat; убрать `font-feature-settings: "cv11","ss03"` (Gilroy-специфично).
3. Обновить `--fs-*`; заменить `0.625rem`/`0.6875rem` и прочие значения < 14px: интерактивные -> 14px, бейджи-счётчики -> 12px (исключение).
4. `header.twig`: preload `montserrat-regular/bold`. `DESIGN.md`: тип и исключения.
5. Обновить `TEST-VIS-001`/`TEST-REF-001`, добавить TEST-FNT-001/002.
6. Проверка в браузере 375/1280, правки переполнений (кнопки, меню, карточки) из-за более широкого шрифта.
7. `npm run verify`, `npm run pack:theme`, строка SPEC-102 в `specs/README.md`, коммит `[SPEC-102]`.
