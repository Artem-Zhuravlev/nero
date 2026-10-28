# PLAN — SPEC-084 Blog: Authors, Dates, Multilingual Settings, Comments, Likes

## Approach

Всё в новом расширении `custom/extensions/ocs_blog/` + переопределения шаблонов темы + OCMOD для двух форм админки. `core/` не трогаем.

## Architecture decisions

1. **Сателлитные таблицы** (`ocs_blog_article`, `ocs_blog_comment`, `ocs_blog_author*`, `ocs_blog_vote`) вместо ALTER core-таблиц — миграции расширения (`db/001…`), по образцу `ocs_product_extras/db/003_review_extra.php`.
2. **Автор и дата в форме статьи** — OCMOD `ocs_blog_article_form.ocmod.xml` вставляет `{{ ocs_blog_article_extra }}` (по образцу `ocs_review_extra.ocmod.xml`); HTML рендерит admin view-event `admin/view/blog/article_form/before`; сохранение — event `admin/model/blog/article/{add,edit}Article/after` пишет `author_id` в сателлит; `date_available` уже есть в core-форме — OCMOD только оформляет блок.
3. **Мультиязычные настройки блога** — OCMOD на `blog/setting.twig` заменяет 5 одноязычных полей на `{{ ocs_blog_lang_fields }}` (вкладки языков); event `admin/controller/blog/setting/before` (POST) собирает JSON `configblog_description`, удаляя одиночные ключи из POST не нужно (оставляем как fallback). На витрине event `catalog/controller/blog/latest/before` и `blog/category/before` (+ `blog/article` для хлебных крошек) подменяют `config` значения `configblog_name/html_h1/meta_*` языковыми — шаблоны и контроллеры core продолжают читать `config->get('configblog_*')`.
4. **Комментарии** — переопределённые `blog/article.twig` / `blog/review.twig`: список карточками, попап формы со звёздами (JS и CSS попапа SPEC-049 переиспользуются классами); отправка в core `blog/article/write` (контракт не меняется), «достоинства/недостатки» не добавляем.
5. **Голоса** — контроллер `extension/ocs_blog/vote`: один UPSERT в `ocs_blog_vote` + пересчёт счётчика в сателлите в той же транзакции; ключ `customer:<id>` либо `sha1(ip|ua|salt)`; rate-limit через `ocs_blog_vote` по времени.
6. **Единый вид** — partial `blog/_card.twig` (теме) + общий блок CSS `.ocs-blog-card`/`.ocs-blog-grid` в `stylesheet.css`; модули `blog_*.twig` и страницы `latest/category` включают partial. «Товары в статьях» — существующий вывод связанных товаров на странице статьи переводим на карточку товара темы (`product/thumb`).
7. **schema.org** — расширяем `system/library/ocs/structureddata.php` (SPEC-080): `author`, `datePublished`, `dateModified` берутся из сателлита, который кладётся в view-данные событием.

## Affected files

- new: `custom/extensions/ocs_blog/{extension.json,db/*,upload/admin/{controller,model,language,view}/…,upload/catalog/{controller,model,language}/…}`
- new: `custom/ocmod/ocs_blog_article_form.ocmod.xml`, `custom/ocmod/ocs_blog_setting.ocmod.xml`
- edit: `custom/theme/ocs/template/blog/{article,review,category,latest,menu}.twig`, `…/extension/module/blog_{latest,featured,category}.twig`, new `blog/_card.twig`
- edit: `custom/theme/ocs/stylesheet/stylesheet.css`, theme JS (попап/голоса)
- edit: `custom/extensions/ocs/upload/system/library/ocs/structureddata.php`
- edit: `custom/catalog/demo/catalogue.json` (автор, комментарии, реакции)
- new: `scripts/test/` and `tests/php` tests; `specs/README.md` row

## Risks

- OCMOD-поиск строк в `blog/article_form.twig` / `setting.twig` ломается при другой версии core → `@core-version 3.0.3.7` и тест-якорь.
- Подмена `config` в событии может конфликтовать с чтением из `blog/setting` в админке → применяется только на `catalog/*`.
- Голоса без регистрации можно накрутить сменой IP — принимаем (rate-limit, UNIQUE), для строгости — Open question 3.
