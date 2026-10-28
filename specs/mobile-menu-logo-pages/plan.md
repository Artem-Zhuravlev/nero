# PLAN — SPEC-107

1. `initMobileNav`: при `<768px` переносить `#logo` в `.ocs-mnav-head` (первый ребёнок панели), список страниц — в `<details>`; на десктопе возвращать.
2. CSS: убрать верхний отступ панели, стили `.ocs-mnav-head` и `.ocs-mnav-pages`.
3. Тест, `npm run verify`, `npm run pack:theme`, строка в `specs/README.md`.
