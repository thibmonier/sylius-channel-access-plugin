# CLAUDE.md

Sylius 2 plugin: access mode per channel (open, prices hidden, closed). One column on the channel
(trait), a request listener, hook conditions, an order modifier decorator, an API normalizer and
an admin field. Research and decisions: `SPEC.md`. Public docs and contract: `README.md`.

## Commands

PHP needs more than 128 MB to compile the test application container: prefix console and
PHPStan calls with `php -d memory_limit=2G` when running locally.

```bash
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite non-unit   # integration + functional, need the test DB + Sylius fixtures
vendor/bin/phpstan analyse                # level max, src + tests, no ignore
vendor/bin/ecs check
vendor/bin/rector process --dry-run
vendor/bin/console lint:container
```

Local env overrides (gitignored): `tests/TestApplication/.env.local` and `.env.test.local`
(DATABASE_URL). Database: `tmonier_sylius_channel_access_test`, migrated (`doctrine:migrations:migrate`)
and loaded with the default fixture suite (`sylius:fixtures:load`). The shop assets must be built
(`vendor/sylius/test-application/public/build`) for the pages to render. After adding a
`translations/` file, run `cache:clear -e test`.

## Architecture

- `Access/`: `AccessMode` enum (stored values are the contract), `AccessModeResolverInterface`
  (5 methods, consumed by other plugins), `AccessModeResolver` (no HTTP request or no channel =
  open; signed in = token user is a Sylius `UserInterface`), `PatternRequestMatcher` (route and
  path patterns, never matches a path containing `..`, `//` or a NUL).
- `Entity/Channel/`: `AccessModeAwareInterface` + `AccessModeTrait` (column `access_mode`, an
  unknown stored value reads as `closed`). The test application entity is
  `tests/TestApplication/src/Entity/Channel/Channel`.
- `EventListener/VisitorAccessListener` (kernel.request, priority 6, after the firewall): closed
  channel = anonymous refused outside the exempt allow-list; prices hidden = anonymous refused on
  the restricted routes (cart, checkout, order, API orders). Pages: 302 to the login; `/api/v2/shop/*`:
  401 JSON. `ClosedChannelExceptionListener` (kernel.exception, priority 64) gives the same refusal
  instead of a 404/403 page. `PrivateCacheListener`: `private, no-store` on every non-open response.
- `config/twig_hooks/shop.yaml`: conditions `is_granted('TMONIER_CHANNEL_ACCESS_SEE_PRICES')` on the
  Sylius hookables (product page prices / promotions / add to cart, product card prices, mini cart)
  + a replacement hookable. `Security/SeePricesVoter` answers the attribute.
- `Order/PriceGuardOrderModifier` decorates `sylius.modifier.order` (every add to cart path).
- `Api/PriceHidingNormalizer` (priority 65, before the Sylius variant normalizer at 64) strips
  `price`, `originalPrice`, `lowestPriceBeforeDiscount`, `appliedPromotions`.
- `Form/Extension/ChannelTypeExtension` + `templates/admin/channel/access_mode.html.twig` (hooks in
  `config/twig_hooks/admin.yaml`).
- Tests: `tests/ChannelAccessTestTrait` changes the mode inside a rolled-back transaction;
  `tests/TestApplication/config/routes.yaml` has a feed, an exempt route and a private route.
