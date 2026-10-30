# TASKS — SPEC-127 Карточки отзывов и «полезно / не полезно»

| Task         | Description                                   | Fulfills                      | Check        | Status  |
| ------------ | --------------------------------------------- | ----------------------------- | ------------ | ------- |
| TASK-REV-201 | Вёрстка карточки отзыва, иконки в спрайт      | REQ-REV-201, REQ-REV-212      | TEST-REV-201 | pending |
| TASK-REV-202 | Миграция таблицы `ocs_review_vote`            | REQ-REV-210                   | TEST-REV-202 | pending |
| TASK-REV-203 | Библиотека `Ocs\ReviewVote` + эндпоинт        | REQ-REV-203, REQ-REV-204      | TEST-REV-203 | pending |
| TASK-REV-204 | Счётчики в списке отзывов (один запрос)       | REQ-REV-205                   | TEST-REV-204 | pending |
| TASK-REV-205 | JS кнопок, языки uk/ru/en, пересборка архива  | REQ-REV-202, REQ-REV-211      | TEST-REV-205 | pending |

TEST-REV-201 — verifies REQ-REV-201: в `review.twig` нет `<table`. TEST-REV-202 — verifies REQ-REV-210: миграция идемпотентна (SQLite). TEST-REV-203 — verifies REQ-REV-203/204: повторный голос не дублируется, лимит срабатывает. TEST-REV-204 — verifies REQ-REV-205. TEST-REV-205 — verifies REQ-REV-202/211: ключи языков есть во всех трёх языках.
