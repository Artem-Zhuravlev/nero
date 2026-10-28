# PLAN — SPEC-085 Доставка «Нова пошта»

## Approach

Один shipping-extension `ocs_novaposhta` по контракту SPEC-004. Слои:

1. **Данные.** Миграция расширения создаёт 5 таблиц (`ocs_np_*`), регистрирует расширение, права, события и дефолтные настройки (статус 0).
2. **Синхронизация.** `system/library/ocs/novaposhta/`: `Client` (API v2.0, POST JSON, таймауты 2/10 с, 2 повтора на 5xx/сетевые ошибки), `Sync` (страница → разбор → хеши → пачечный upsert → курсор), `Repository` (SQL, подсказки, поиск). `Sync` не знает ни про OpenCart-реестр, ни про curl — принимает клиента и `DB`-подобный интерфейс, поэтому тестируется SQLite-тестами и симулятором API без сети (как существующие PHP-тесты проекта).
   Входы: CLI (`cli.php`, поднимает ядро OpenCart по `config.php` для доступа к БД) и HTTP `cron` с токеном. Оба вызывают один `Sync`.
3. **Витрина.** Контроллер `extension/ocs_novaposhta` (JSON: `cities`, `warehouses`, `select`, `cron`), модель `getQuote` в `extension/shipping/ocs_novaposhta`. События: (а) `catalog/controller/checkout/shipping_method/save/before` или проверка внутри `getQuote`/валидации — не допускать способ без точки; (б) `catalog/model/checkout/order/addOrder/before` — подмена `shipping_city`/`shipping_address_1`/`shipping_zone` и запись `ocs_np_order` после создания заказа (`addOrder/after`).
4. **Компонент UI.** `novaposhta.js` — автономный (vanilla), без jQuery-зависимости в логике (jQuery допускается только для обратной совместимости событий Simple). Два «адаптера» (`default`, `simple`) описываются селекторами; переинициализация через `MutationObserver` на контейнере чекаута; значения в поля пишутся нативным setter + диспатч `input`/`change`, чтобы Simple/чекаут увидели изменение. Список — popover под полем, `position: absolute`, виртуальный/постраничный рендер.
5. **Админка.** Страница настроек: ключ, цены, селекторы/пресет, токен cron, блок «Справочники» (статус, «обновить порцию», «сбросить курсор»), блок «Cron» с готовыми строками для crontab.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Запросы подсказок | Только локальная БД | Прямой вызов API на ввод: лимиты, латентность, падение чекаута при сбое НП |
| Загрузка справочников | 3 раздельные сущности, страницы ≤ 500, пачечный upsert, курсор в БД | Один «TRUNCATE + вставить всё» блокирует чтение и теряет данные при сбое |
| Исчезнувшие записи | `is_active = 0` после полного прогона | Физическое удаление ломает ссылки из старых заказов |
| Детект изменений | `hash` полезных полей | Сокращает запись в БД: большинство строк суточного прогона не меняются |
| Вход для cron | CLI внутри расширения + HTTP с токеном | `scripts/` не попадает в пакет темы; на shared-хостинге CLI может быть недоступен |
| Выбор точки | Сессия + событие на `addOrder` | Не требует правки core; точка попадает в стандартные поля заказа, поэтому видна во всех интеграциях/письмах |
| Совместимость с Simple | Селекторы + делегирование + нативные события | Правка файлов Simple невозможна/нежелательна (чужой модуль, обновляется) |
| Поиск по точкам | `LIKE` по нормализованному полю + префиксные индексы по городу | FULLTEXT на ~40k строк избыточен; фильтр всегда по `city_ref` (малая выборка) |
| Блокировка способа без точки | Серверная валидация (событие) + мягкая деградация без JS | Только клиентская проверка обходится; жёсткая блокировка без JS ломает оформление |

## Affected files / directories

- `custom/extensions/ocs_novaposhta/extension.json`
- `custom/extensions/ocs_novaposhta/db/001_install.php` (таблицы, права, события, настройки)
- `custom/extensions/ocs_novaposhta/upload/system/library/ocs/novaposhta/{client,sync,repository,cli}.php`
- `custom/extensions/ocs_novaposhta/upload/admin/{controller,model,language,view}/extension/shipping/ocs_novaposhta*`
- `custom/extensions/ocs_novaposhta/upload/catalog/controller/extension/ocs_novaposhta/{api,events}.php`
- `custom/extensions/ocs_novaposhta/upload/catalog/model/extension/shipping/ocs_novaposhta.php`
- `custom/extensions/ocs_novaposhta/upload/catalog/language/{uk-ua,ru-ru,en-gb}/…`
- `custom/extensions/ocs_novaposhta/upload/catalog/view/javascript/ocs/novaposhta.js`, CSS
- Подключение JS на страницы чекаута: событие `catalog/view/checkout/*/before` или `header` (только `checkout/*`, `simplecheckout`)
- Тесты: `scripts/test/` (JS-логика, симулятор API), PHP-тесты (`Sync`, `Repository`, валидация токена)
- `scripts/pack-theme.mjs` (если расширения перечислены явно), `docs/` — раздел про cron НП
- `specs/README.md` (строка SPEC-085)

## Risks

- **Нет реального доступа к API/ключу в тестах** -> симулятор ответов НП (фикстуры реальных форматов), живая проверка вручную владельцем с боевым ключом перед `verified`.
- **Размер/время `getWarehouses`** (десятки тысяч записей) -> страницы, паузы, курсор, лимит времени HTTP-входа, пометка в статусе «в процессе».
- **Разметка Simple неизвестна** -> без файлов модуля пресет «simple» — гипотеза; селекторы настраиваются, проверка на живом Simple обязательна.
- **События OpenCart 3 не умеют отменять действие**, `before` может только подменить аргументы/вернуть вывод -> валидацию способа без точки делаем в `getQuote`/модели способа и в overlay-проверке на `checkout/shipping_method/save`; детали уточним в T-007, при необходимости вернёмся к спеке (STOP → UPDATE SPEC).
- **Нагрузка на БД ночью** -> маленькие транзакции, паузы, хеши; отдельное время запуска для каждой сущности.
- **MySQL/MariaDB-код миграций и upsert ни разу не гонялся на реальном сервере в проекте** -> SQLite-тесты + ручная проверка на dev-БД, явно указать в отчёте.
