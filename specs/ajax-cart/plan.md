# PLAN — SPEC-026 AJAX Cart

## Approach

Own catalog controller `extension/module/ocs_ajax_cart` with JSON actions calling `$this->cart`. JS in the extension
(namespaced under `OCS.cart`) uses `OCS.modal` side variant. Core `cart.add` JS is wrapped, not replaced, so third-party
code calling `cart.add` still works.

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Server API | Own JSON actions | Core `checkout/cart` returns HTML fragments, hard to reuse |
| Hooking | Wrap `window.cart` | Keeps compatibility with modules calling it |

## Affected files / directories

- `custom/extensions/ocs_ajax_cart/`, theme header counter markup.

## Risks

- Third-party modules that override `cart.add` -> load order documented, tested with Simple.
