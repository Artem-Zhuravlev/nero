# PLAN — SPEC-123 Настройки Nero

## Approach

Шаблон `ocs.twig` переписывается: `ul.nav-tabs` + `div.tab-content`, каждая вкладка — существующие поля, обёрнутые в карточки; `name=` не меняются, контроллер сохранения остаётся. Мини-скрипт запоминает вкладку (`localStorage`) и открывает вкладку с `.text-danger`. Стили — небольшой `<style>` в шаблоне (только админка).

Пункт меню: событие `admin/view/common/column_left/before` в расширении `ocs` (как `ocs_blog`), вставляет `menu-nero` сразу после `menu-dashboard`. Для установленных магазинов событие добавляется идемпотентной миграцией.

Переименование: правка языковых значений (не ключей и не кодов) в `admin/language/{uk-ua,ru-ru,en-gb}` расширений `ocs*`, плюс захардкоженные строки в `ocs.twig`. Скан запрещённых слов — Node-тест по `custom/`.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Вкладки | Bootstrap 3 nav-tabs | Уже в админке, без зависимостей |
| Пункт меню | Событие `column_left/before` | Паттерн проекта; OCMOD не нужен |
| Коды `ocs` | Не переименовываем | Ломает установленные магазины и настройки |
| Проверка слов | Тест с чёрным списком | Не даёт регрессировать |

## Affected files

- `custom/extensions/ocs/upload/admin/view/template/extension/theme/ocs.twig`, `admin/controller/extension/theme/ocs.php`, `admin/language/*/extension/theme/ocs.php`
- событие меню (контроллер в `ocs`) + миграция `0015_nero_menu_event.php`
- `admin/language/*/extension/**` расширений `ocs_*`, `ocs/.../setup.*`
- `ocs_super_search` (синонимы), `scripts/test/`, `specs/README.md`, `custom/VERSION`, `npm run pack:theme`

## Risks

- Много языковых файлов: правим скриптом + просмотр diff.
- Событие меню должно работать при частично установленной теме.
