# PLAN — SPEC-082 Top Bar

## Approach

Отдельное расширение-модуль `ocs_topbar` по образцу `ocs_shell` (глобальные настройки, форма с
вкладками языков). Чистый класс `Ocs\Topbar` собирает данные (текст, ссылки, кнопка, ID, стиль,
окно показа) — тестируется на SQLite/без БД. Событие на `catalog/view/common/header/before`
кладёт `ocs_topbar` в данные; `header.twig` выводит блок и пропускает старый анонс, если
`ocs_topbar` непуст. Стили — секция в `stylesheet.css` рядом с `.ocs-announce`, переиспользуются
токены темы (`--accent`, `--on-accent`, `--fs-sm`, `--sp-*`), закрытие — общий JS анонса в `theme.js`.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Где живёт | Новое расширение `ocs_topbar` | Просили именно модуль; не раздувает `ocs_shell` |
| Слот | Всегда над шапкой, не слот макета | Позиции макетов OC начинаются ниже хедера |
| Старый анонс | Остаётся как fallback | Обратная совместимость, нет миграции данных |
| Разметка | Тот же `.ocs-announce` + модификатор `--btn` | Один набор стилей и JS закрытия |
| Цвета | Пресеты + HEX с автоподбором цвета текста по яркости | Гарантия контраста без ручной настройки |
| Настройки | `setting`, один набор | Строка одна на сайт |

## Affected files

- `custom/extensions/ocs_topbar/{extension.json,db/001_install.php}`
- `custom/extensions/ocs_topbar/upload/admin/{controller,language,view}/extension/module/ocs_topbar.*`
- `custom/extensions/ocs_topbar/upload/catalog/{controller/extension/ocs_topbar/events.php,language}`
- `custom/extensions/ocs_topbar/upload/system/library/ocs/topbar.php`
- `custom/theme/ocs/template/common/header.twig`, `custom/theme/ocs/stylesheet/stylesheet.css`, `custom/theme/ocs/javascript/theme.js` (если нужен общий хук закрытия)
- Тесты `scripts/test/php/TopbarTest.php`, Node-тест структуры пакета; `specs/README.md`; `custom/VERSION`; `npm run pack:theme`.

## Risks

- Дублирование с анонсом `ocs_shell` → правило приоритета REQ-TBR-007 + тест.
- Контраст на «свой HEX» → автоподбор цвета текста по яркости.
- Full-page cache (SPEC-019): правка текста видна после сброса кэша/TTL.
