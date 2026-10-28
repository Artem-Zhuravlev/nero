# PLAN — SPEC-086 Таймер акции в карточке товара

## Approach

В `applyDisplay` (расширение `ocs`) для `product/product` выбираем действующую скидку текущей группы
клиентов с `date_end != 0000-00-00` (логика как в `ldProduct`, вынести в общий приватный метод) и
кладём в `$data` секунды до `date_end 00:00:00`. Шаблон выводит блок под ценой; небольшой скрипт
темы считает отсчёт от серверного остатка и скрывает блок на нуле.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Источник времени | серверный остаток секунд | не зависит от часов/пояса посетителя |
| Где считать | событие `ocs` | без правки core, как SPEC-048/080 |
| JS | ~20 строк в скрипте темы | библиотека не нужна |

## Affected files / directories

- `custom/extensions/ocs/upload/catalog/controller/extension/ocs/events.php`
- `custom/theme/ocs/template/product/product.twig`, `custom/theme/ocs/stylesheet/stylesheet.css`, скрипт темы
- языковые файлы `custom/i18n` / расширения `ocs` (uk-ua, ru-ru, en-gb)
- `custom/VERSION`, `dist/` (pack:theme)

## Risks

- Кэш страницы с зашитым остатком секунд -> проверить, что страницы товара не кэшируются целиком (SPEC-019).
