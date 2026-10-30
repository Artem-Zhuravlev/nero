# TASKS — SPEC-125 Своя иконка инфо-блока

| Task         | Description                                                        | Fulfills                          | Check        | Status  |
| ------------ | ------------------------------------------------------------------ | --------------------------------- | ------------ | ------- |
| TASK-INF-101 | `InfoBlocks::sanitize/view`: поле `image`, проверка пути           | REQ-INF-102, 103, 104, 111        | TEST-INF-101 | done |
| TASK-INF-102 | Админка: выбор из менеджера изображений, миниатюра, ошибка         | REQ-INF-101, 103                  | TEST-INF-102 | done |
| TASK-INF-103 | Витрина: `<img>` вместо SVG, fallback при отсутствии файла, CSS    | REQ-INF-102, 110                  | TEST-INF-103 | done |
| TASK-INF-104 | Тесты, строка в `specs/README.md`, `npm run verify`, `pack:theme`  | REQ-INF-101..111                  | TEST-INF-104 | done |
