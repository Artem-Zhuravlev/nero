# PLAN — SPEC-096

1. В четырёх шаблонах обернуть блок `.ocs-more` условием `{% if ocs_listing and pagination %}` (пагинация core пуста при одной странице).
2. CSS: перенести правило скрытия в слой `overrides` и усилить: `.ocs-more button[hidden] { display: none !important; }`; старое правило в `legacy` удалить.
3. Добавить TEST-LML-001 (шаблоны содержат условие по `pagination`; CSS-правило с `!important`), `npm run verify`, `npm run pack:theme`, строка в `specs/README.md`.
