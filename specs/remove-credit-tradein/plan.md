# PLAN — SPEC-087 Remove Instalments and Trade-in

## Approach

Mirror SPEC-052: one migration unregisters everything and drops the lead table, then the extension folders, template hooks,
JS/CSS blocks, demo seeds and tests are deleted. Bump `custom/VERSION` only if the repo does so for removals (check SPEC-052 commit).

## Architecture decisions

| Decision | Choice | Why / alternatives rejected |
| -------- | ------ | --------------------------- |
| Scope | Delete both extensions whole | Third-party services replace them; hiding via status=0 would leave dead code. |
| Data | Drop `ocs_tradein_lead` | No consumers remain; stated in spec. Alternative (keep table) rejected: orphan data without an admin screen. |
| Demo FAQ texts | Keep | Content, not feature code. |

## Affected files / directories

- `custom/extensions/ocs_credit/`, `custom/extensions/ocs_tradein/` (delete); `custom/db/migrations/0012_remove_credit_tradein.php` (new)
- `custom/theme/ocs/template/product/product.twig`, `product/category.twig`, `javascript/theme.js` (credit block ~2884-2985, quick-order note ~813), `stylesheet/stylesheet.css`, `DESIGN.md`
- `scripts/lib/demo-custom-fields.mjs`, `scripts/lib/php/Setup/DemoCatalogue.php`, `custom/catalog/demo/catalogue.json`
- `scripts/test/theme.test.mjs`, `scripts/test/gallery.test.mjs`, `scripts/test/php/CreditTradeinTest.php` (delete), `scripts/test/php/DemoCatalogueTest.php`
- `specs/README.md`, `specs/credit-tradein/spec.md` (superseded), `dist/` archive via `npm run pack:theme`

## Risks

- Leftover references to `ocs_credit_block` / `ocs_tradein_banner` template vars -> grep + verify.
- Dropping leads is irreversible -> flagged for approval.
