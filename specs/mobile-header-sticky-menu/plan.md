# PLAN — SPEC-090 Мобильная шапка в одну строку и кастомное меню

## Approach

1. **Шапка.** В `@layer overrides` (после правил SPEC-059) для `max-width: 767px` задать сетку `"cat logo search"`, кнопку каталога сделать иконкой, логотип уменьшить, вернуть `position: sticky` у `header.ocs-head` (переопределить `static` из SPEC-038 для ≤40rem). Полноэкранная панель по-прежнему идёт через top layer (Popover API): шапка — size container и с `backdrop-filter`, `fixed` внутри неё ломается (SPEC-043).
2. **Открытие меню.** Убрать Bootstrap collapse на мобильном: кнопки получают `data-ocs-menu="toggle|close"`, `initMega()` сам ведёт состояние (`is-open` на `.navbar-collapse`, `ocs-menu-open` на `<html>`). Панель всегда в DOM; закрытая — `visibility: hidden`/`inert`. Десктопные правила Bootstrap `.collapse` не затрагиваются (медиа-запрос <768px).
3. **Анимация.** Открытие: `showPopover()` → следующий кадр `is-open` → переход `transform: translateX(-100%) → 0` + `opacity`, подложка с fade. Закрытие: снять `is-open`, по `transitionend` (с таймаут-страховкой) `hidePopover()`. Без Popover API — тот же класс на `position: fixed`.
4. **Без дёрганья.** Блокировка прокрутки через `overflow: hidden` на `<html>` + `scrollbar-gutter: stable`; `overscroll-behavior: contain` в панели; состояния `opening/open/closing/closed`, прерываемые.
5. **Подпункты.** `.ocs-mega-fly` на мобильном — `display: grid; grid-template-rows: 0fr → 1fr` при `.open`, внутренний контейнер `overflow: hidden`.
6. **A11y/ресайз.** `aria-expanded`, фокус-менеджмент, Esc; `matchMedia` сбрасывает состояние при переходе ≥768px; `prefers-reduced-motion`.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Открытие панели | собственный JS + CSS transition | Bootstrap collapse анимирует `height` fixed-панели и конфликтует с Popover API |
| Что анимируем | `transform` + `opacity` | композитор, без layout; высота — только у подпунктов через `grid-template-rows` |
| Top layer | сохранить Popover API | `fixed` внутри шапки с `backdrop-filter`/container ломается (SPEC-043) |
| Страховка закрытия | `transitionend` + таймаут | при reduced-motion/прерывании событие может не прийти |
| Sticky | `position: sticky` у существующего `header.ocs-head` | `fixed` потребовал бы компенсирующий отступ → CLS |

## Affected files / directories

- `custom/theme/ocs/template/common/header.twig`, `custom/theme/ocs/template/common/menu.twig`
- `custom/extensions/ocs_mega_menu/upload/catalog/view/theme/default/template/extension/module/ocs_mega_menu.twig`
- `custom/theme/ocs/javascript/theme.js` (`initMega`, `initMobileNav`)
- `custom/theme/ocs/stylesheet/stylesheet.css` (шапка SPEC-059/088, `.ocs-mega` mobile, `@media (max-width: 40rem)` из SPEC-038)
- `custom/VERSION`, `specs/README.md`, тесты в `scripts/test/`, `dist/ocs-theme-<VERSION>.ocmod.zip`

## Risks

- Другие скрипты ждут `shown.bs.collapse`/`hidden.bs.collapse` → grep по `custom/` перед правкой, при необходимости диспатчить совместимые события.
- Sticky + `container-type: inline-size` на шапке → проверить в браузере (родитель — `body`).
- Sticky-строка перекрывает якоря → `scroll-padding-top` на `html` для мобильного.
- `@starting-style`/Popover поддерживаются не везде → fallback на класс-переход без top layer.
