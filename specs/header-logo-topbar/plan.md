# PLAN — SPEC-088 Logo in the Top Bar

## Approach

Перенести блок `#logo` в `#top > .container` первым элементом, кнопку контактов собрать из
`ocs_shell.phones|first`, блок `.ocs-contact` переставить внутрь `.ocs-util-end` перед
`{{ language }}`. Отступ между «Информацией» и «контакты + язык» — `margin-inline-start` на
контактах / `gap` в `.ocs-util-end`. На мобильном (<768px) `#top` скрыт (SPEC-059), поэтому
логотип там нужен отдельно: CSS-сетка `.ocs-head-grid` теряет область `logo`; для телефона
выводится второй экземпляр логотипа в `.ocs-head`, скрытый на ≥768px (`aria-hidden`,
`hidden` по media), а в `#top` на <768px — наоборот.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Логотип | Один include-фрагмент `logo` дважды: в `#top` (≥768px) и в `.ocs-head` (<768px) | Верхняя панель скрыта на телефоне; перемещение через JS даёт CLS |
| `id="logo"` | Только у десктопного экземпляра; мобильный — класс | Уникальность id, стили/скрипты, привязанные к `#logo`, проверить grep |
| Контакты | Первый телефон вместо слова, fallback на слово | Запрос заказчика |
| Отступ | `gap` в `.ocs-util-end` + `margin-inline-start` на контактах | Без «магических» пикселей, токены `--sp-*` |

## Affected files

- `custom/theme/ocs/template/common/header.twig`
- `custom/theme/ocs/stylesheet/stylesheet.css` (секции `#top.ocs-util`, `.ocs-head-grid`, SPEC-059)
- `scripts/test/theme.test.mjs`; `specs/README.md`; `custom/VERSION`; `npm run pack:theme`; проверка скриншотами desktop/tablet/mobile.
