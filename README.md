# Sylius Channel Access Plugin

[![Build](https://github.com/thibmonier/sylius-channel-access-plugin/actions/workflows/build.yaml/badge.svg)](https://github.com/thibmonier/sylius-channel-access-plugin/actions/workflows/build.yaml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Choose, channel by channel, whether visitors who are not signed in see the catalogue, the
prices and the buy button: open, prices hidden, or closed behind a login.**

A B2B shop often needs a login wall (nothing for anonymous visitors) or a catalogue without
prices until the customer signs in. Hiding a price in a template is not enough: the same price is
in the shop API, and the add-to-cart works whatever the page shows. This plugin enforces the mode
on the server.

## Features

| Mode | Anonymous visitor | Signed-in customer |
|---|---|---|
| `open` (default) | Everything, as Sylius | Everything |
| `prices_hidden` | Catalogue pages; **no price, no promotion, no buy button** (replaced by a "Log in to see your prices" link), no mini cart; cart, checkout and order routes redirect to the login; the shop API returns no price and refuses carts | Everything |
| `closed` | **Only the home page, the login, the registration, the password reset and the routes you exempt**; everything else redirects to the login (401 JSON on `/api/v2/shop/*`); no price, no product and no taxon menu even on the home page and the login | Everything |

Also: a field on the channel form of the admin (invalid value refused, previous mode kept);
responses of a restricted channel are `private, no-store` (no shared cache mixes visitors); a
last-line guard on the order modifier refuses any add to cart of an anonymous visitor of a
channel that hides prices (shop pages, live components, shop API, re-order); an unknown stored
value reads as `closed`; translations in English, French, German, Spanish, Italian, Dutch and
Polish; MySQL, MariaDB and PostgreSQL (Schema API migration).

## Requirements

| | Version |
|---|---|
| PHP | 8.3+ |
| Sylius | 2.3 (the plugin relies on the `condition` option of the twig hooks, absent from Sylius 2.2) |
| Symfony | 6.4, 7.4, 8.0 |

## Installation

1. Require the package:

    ```bash
    composer require tmonier/sylius-channel-access-plugin
    ```

2. Register the bundle in `config/bundles.php`:

    ```php
    Tmonier\SyliusChannelAccessPlugin\TmonierSyliusChannelAccessPlugin::class => ['all' => true],
    ```

3. Add the trait to the channel entity of your application (the plugin changes no Sylius entity
   on its own):

    ```php
    use Doctrine\ORM\Mapping as ORM;
    use Sylius\Component\Core\Model\Channel as BaseChannel;
    use Tmonier\SyliusChannelAccessPlugin\Entity\Channel\AccessModeAwareInterface;
    use Tmonier\SyliusChannelAccessPlugin\Entity\Channel\AccessModeTrait;

    #[ORM\Entity]
    #[ORM\Table(name: 'sylius_channel')]
    class Channel extends BaseChannel implements AccessModeAwareInterface
    {
        use AccessModeTrait;
    }
    ```

   and declare it (`config/packages/_sylius.yaml`):

    ```yaml
    sylius_channel:
        resources:
            channel:
                classes:
                    model: App\Entity\Channel\Channel
    ```

   A channel class without the trait is treated as **closed** (and an error is logged): the
   plugin refuses rather than silently opening everything.

4. Import the hooks (prices, buy button and the admin field) in `config/packages/_sylius.yaml`:

    ```yaml
    imports:
        - { resource: "@TmonierSyliusChannelAccessPlugin/config/config.yaml" }
    ```

5. Run the migration (one column `sylius_channel.access_mode`, default `open`; existing channels
   stay open):

    ```bash
    bin/console doctrine:migrations:migrate
    ```

6. Choose the mode in the admin: *Channels > edit > Access mode*.

## Configuration

Optional (`config/packages/tmonier_sylius_channel_access.yaml`):

```yaml
tmonier_sylius_channel_access:
    login_route: sylius_shop_login      # where a closed channel sends anonymous visitors ("_locale" parameter)
    exempt_routes: ['app_mcp_*']        # shell-style patterns on the route name
    exempt_paths: ['/punchout/*', '/feeds/*']   # shell-style patterns on the path
    restricted_routes: ['app_quote_*']          # refused without access to the prices (see below)
    restricted_paths: ['%sylius.security.api_shop_route%/product-variants/*/tier-prices']
```

An anonymous visitor of a **closed** channel reaches only:

- the built-in allow-list: the home page (`sylius_shop_homepage`), locale and currency switchers,
  login, logout, registration, e-mail verification, password reset; the administration
  (`/admin`, and `/api/v2/admin/*` which has its own firewall); the shop API sign-in, registration,
  password reset and verification endpoints; the profiler and error pages;
- the routes and paths **you** list. Typical entries: PunchOut (`/punchout/*` or the route
  names), an MCP endpoint, product feeds, a health check. They are usually authenticated by
  something else (token, basic auth): the plugin only stops refusing them.

Patterns use `*` for any characters, `/` included (`/feeds/*` matches `/feeds/a/b.xml`, not
`/feedsx`). A pattern that would match every request (`*`, `/*`...) is refused when the container
is built. A path containing `..`, `//` or a NUL byte is never matched by a path pattern (the
request is refused as any non-listed path). **Everything not listed is refused**, including
unknown paths (no 404 page that would show the menu of the catalogue).

### Restricting more routes (`restricted_routes`, `restricted_paths`)

On a **prices hidden** channel an anonymous visitor is refused the cart, the checkout, the orders and
the shop API orders. Other plugins add routes that show prices or build a priced document; list them
with the same patterns as the exemptions (route names with `*`, paths with `*`, `%parameter%`
placeholders resolved by the container). They are merged with the built-in ones and refused like the
cart: redirection to the login for pages, 401 JSON under the shop API. Examples: the quote request form
(`tmonier_quote_request_shop_new`, `tmonier_quote_request_shop_basket_add`), the quick order
(`tmonier_quick_order_shop_add`), the AI assistants consent (`tmonier_b2b_mcp_shop_*`), the volume prices
API (`.../product-variants/*/tier-prices`). On a **closed** channel everything not exempt is already
refused, so these options only matter for prices hidden channels. A value that is empty or not a string
is a configuration error naming the option. A route or path that is both in `exempt_*` and in
`restricted_*` (the built-in ones included) fails the container compilation with a message naming it;
when patterns overlap without being equal, the exemption wins.

The redirect always goes to `login_route`; no query parameter or header of the request chooses
the target (no open redirect). The locale of the login URL is the request one when the channel
offers it, else the default locale of the channel.

## Contract for other plugins

Frozen for 0.x (`Tmonier\SyliusChannelAccessPlugin\Access`):

```php
enum AccessMode: string { case Open = 'open'; case PricesHidden = 'prices_hidden'; case Closed = 'closed'; }

interface AccessModeResolverInterface
{
    public function modeOf(ChannelInterface $channel): AccessMode;
    public function currentMode(): AccessMode;      // channel of the current HTTP request; Open without request, Closed without channel
    public function isVisitorSignedIn(): bool;      // the token holds a Sylius user (customer or administrator)
    public function canSeeCatalog(): bool;          // false: anonymous visitor of a closed channel
    public function canSeePrices(): bool;           // false: anonymous visitor of a channel that is not open
}
```

The service is `tmonier_channel_access.resolver` (alias on the interface). Twig:
`tmonier_channel_access_mode()`, `tmonier_channel_can_see_prices()`,
`tmonier_channel_can_see_catalog()`. Security attributes for `is_granted()` and hook conditions:
`TMONIER_CHANNEL_ACCESS_SEE_PRICES`, `TMONIER_CHANNEL_ACCESS_SEE_CATALOG`. A plugin that shows prices elsewhere (a quick order form, a
price list) asks the resolver, or puts the same condition on its hookable.

Extension points (used by `tmonier/sylius-registration-approval-plugin`): decorate the service
`tmonier_channel_access.resolver` to change who counts as signed in (every protection follows), and
call `VisitorAccessListener::refusalFor(Request): ?Response` (service
`tmonier_channel_access.listener.visitor_access`) to know whether an anonymous visitor would be
refused for a request, and with which response.

## How it works and limits

- The catalogue (home page product lists, taxon menu and hamburger of every page, error pages included) is hidden from anonymous visitors of a **closed** channel by the same mechanism, with the attribute `TMONIER_CHANNEL_ACCESS_SEE_CATALOG`.
- Prices of the shop pages are hidden by **conditions on the Sylius hookables** (product page:
  `prices`, `catalog_promotions`, `add_to_cart`; product card: `prices`; header and off-canvas:
  `cart`). A theme or a plugin that renders prices elsewhere has to use the same condition.
- The shop API variant normalizer is wrapped: `price`, `originalPrice`,
  `lowestPriceBeforeDiscount` and `appliedPromotions` are removed for anonymous visitors of a
  channel that is not open. Other price sources (custom resources) are not covered.
- Sorting the listing by price still works for an anonymous visitor on a prices hidden channel
  (the order of the products, not the amounts, is visible).
- Static files (`/media`, `/build`) are served by the web server and are not protected.
- A restricted channel answers `Cache-Control: private, no-store` and `Vary: Cookie, Authorization`.
  An exempt route that sets its response `public` on purpose keeps its headers.
- On a closed channel an unknown URL redirects to the login even for a signed-in customer: the router rejects the path before the firewall has run, so the visitor is still unknown.
- Console commands and message handlers have no HTTP request: they are never restricted.
- The administration and the admin API are never restricted by this plugin.

## Testing

See `CLAUDE.md` for the commands. The functional tests drive the real shop and API and check the
rendered behaviour (redirects, absence of prices in pages and JSON, refused add to cart, exempt
routes, forged inputs).

## License

MIT, see [LICENSE](LICENSE).
