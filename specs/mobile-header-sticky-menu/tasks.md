# TASKS — SPEC-090 Мобильная шапка в одну строку и кастомное меню

| Task         | Description                                                                             | Fulfills                             | Check             | Status  |
| ------------ | --------------------------------------------------------------------------------------- | ------------------------------------ | ----------------- | ------- |
| TASK-MHS-001 | grep потребителей `data-toggle="collapse"`, `.collapse.in`, `*.bs.collapse` в `custom/` | REQ-MHS-006, 020, 021, 023                   | TEST-MHS-002      | done    |
| TASK-MHS-002 | Шаблоны: убрать `data-toggle/target`, добавить `data-ocs-menu`, aria; кнопка-иконка     | REQ-MHS-002, 006, 010                | TEST-MHS-002      | done    |
| TASK-MHS-003 | CSS: сетка в одну строку, логотип, поиск, sticky, `scroll-padding-top`                  | REQ-MHS-001, 002, 003, 004, 005, 022 | TEST-MHS-001      | done    |
| TASK-MHS-004 | JS: открыватель панели (состояния, Popover, transitionend, фокус, Esc, ресайз)          | REQ-MHS-006, 007, 008, 010, 012      | TEST-MHS-002      | done    |
| TASK-MHS-005 | CSS: анимация панели/подложки, подпункты на `grid-template-rows`, reduced-motion        | REQ-MHS-007, 008, 009, 011           | TEST-MHS-003      | done    |
| TASK-MHS-006 | Тесты темы (шапка, шаблоны, CSS-анимация)                                               | REQ-MHS-001..011, 021, 023                  | TEST-MHS-001..003 | done    |
| TASK-MHS-007 | Ручная проверка 375/320px, `npm run verify`, `npm run pack:theme`, строка в README      | all                                  | TEST-MHS-004      | done    |
