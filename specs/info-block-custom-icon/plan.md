# PLAN — SPEC-125 Своя иконка инфо-блока

## Approach

Добавить в строку блока необязательное поле `image`. Админка: тот же паттерн выбора изображения, что в `ocs_home_slider_form.twig` (`a-image`, `#thumb-image-N`, `$('#...').find('a')`-менеджер), миниатюру строим через `model_tool_image->resize` в контроллере (плейсхолдер для пустого). `InfoBlocks::sanitize` проверяет путь (чистка `..`, префикс `catalog/`, белый список расширений), `view` отдаёт `image_url` (проверка существования файла через `is_file(DIR_IMAGE...)` — в events, чистый класс без реестра остаётся: путь проверяется в событии/передаётся callback). Twig: `{% if b.image %}<img class="ocs-i-img" ...>{% else %}<svg>{% endif %}`.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Хранение | Поле `image` в существующей JSON-настройке | Без миграции и новых ключей |
| Вывод | `<img>` с прямым URL | Работает для SVG/PNG, без resize-кэша; перекраска в цвет темы невозможна (принято) |
| Приоритет | Картинка перекрывает иконку из списка | Выпадающий список остаётся запасным |

## Affected files

- `custom/extensions/ocs/upload/system/library/ocs/infoblocks.php`
- `custom/extensions/ocs/upload/admin/{controller,view/template,language/*}/extension/theme/ocs.*`
- `custom/extensions/ocs/upload/catalog/controller/extension/ocs/events.php` (передача проверки файла)
- `custom/theme/ocs/template/product/product.twig`, `stylesheet/stylesheet.css`
- `scripts/test/*` (тест InfoBlocks), `specs/README.md`, `dist/ocs-theme-*.ocmod.zip` (`npm run pack:theme`)

## Risks

- Динамически добавляемая строка (`__N__` шаблон) должна получить уникальные id для менеджера изображений -> id по индексу строки в JS добавления.
