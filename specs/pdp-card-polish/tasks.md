# TASKS — SPEC-124 Характеристики, наличие, код, без bold

| Task         | Description                                             | Fulfills                  | Check        | Status  |
| ------------ | ------------------------------------------------------- | ------------------------- | ------------ | ------- |
| TASK-PDP-001 | Таблица характеристик `.ocs-specs` (twig + css)         | REQ-PDP-001               | TEST-PDP-001 | done |
| TASK-STK-001 | Иконки check / x-thin в спрайте и в карточке, PDP       | REQ-STK-001, REQ-STK-002  | TEST-STK-001 | done |
| TASK-CODE-001| Код товара в `.caption`, static, чистка absolute-правил | REQ-CODE-001, REQ-CODE-002| TEST-CODE-001| done |
| TASK-TYPO-001| Токены весов, замена по CSS                             | REQ-TYPO-001..003         | TEST-TYPO-001| done |
| TASK-NFR-001 | `npm run verify`, `npm run pack:theme`, README          | REQ-NFR-001..003          | TEST-NFR-001 | done |

TEST-PDP-001 / STK-001 / CODE-001 — verifies REQ-… : шаблонные проверки в `scripts/test/pdp-card-polish.test.mjs`; TEST-TYPO-001 — скан CSS на `font-weight` ≥ 600 вне белого списка селекторов.
