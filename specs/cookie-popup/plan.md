# PLAN — SPEC-083 Cookie Popup

## Approach

Расширение-модуль `ocs_cookie` по образцу `ocs_topbar` (глобальные настройки, форма с вкладками
языков, событие в шаблонные данные). Чистый класс `Ocs\Cookie` собирает данные для языка
(тексты, безопасный URL, версия, срок) — тестируется без БД. Событие
`catalog/view/common/footer/before` кладёт `ocs_cookie` в данные; `footer.twig` выводит скрытый
блок `.ocs-cookie`. Показ/скрытие, запись cookie `ocs_consent`, `window.ocsConsent` и событие
`ocs:consent` — небольшой блок в `theme.js` (читает/пишет cookie тем же способом, что закладки).

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Где живёт | Новое расширение `ocs_cookie` | Просили именно модуль; независим от `ocs_shell`/`ocs_tags` |
| Хранение выбора | Cookie `ocs_consent` на клиенте | Имя из SPEC-017; без БД и без запросов; совместимо с кэшем страниц |
| Рендер | Всегда в HTML, скрыт; показ по JS | Full-page cache не ломается, нет CLS |
| Категории | Только `all` / `necessary` сейчас | Гейтинг тегов — `ocs_tags` (SPEC-017); тут только хук |
| Слот | Фиксированный, не слот макета | Попап поверх страницы |

## Affected files

- `custom/extensions/ocs_cookie/{extension.json,db/001_install.php}`
- `custom/extensions/ocs_cookie/upload/admin/{controller,language,view}/extension/module/ocs_cookie.*`
- `custom/extensions/ocs_cookie/upload/catalog/{controller/extension/ocs_cookie/events.php,language}`
- `custom/extensions/ocs_cookie/upload/system/library/ocs/cookie.php`
- `custom/theme/ocs/template/common/footer.twig`, `custom/theme/ocs/stylesheet/stylesheet.css`, `custom/theme/ocs/javascript/theme.js`
- Тесты `scripts/test/php/CookieTest.php`, Node-тест пакета; `specs/README.md`; `custom/VERSION`; `npm run pack:theme`.

## Risks

- Пересечение с будущим `ocs_tags` → имя cookie и событие уже согласованы со SPEC-017.
- Перекрытие tab bar/липких элементов на мобильном → отступ снизу через токен высоты tab bar, скриншот-проверка.
- Правка текстов не видна сразу при full-page cache → сброс кэша/TTL (SPEC-019).
