# Changelog

## Unreleased

### Added

- Access mode per channel: `open` (default), `prices_hidden`, `closed`. One additive column
  `sylius_channel.access_mode` (trait `AccessModeTrait`, migration `Version20261016200000`).
- Closed channel: anonymous visitors are redirected to the login (401 JSON on `/api/v2/shop/*`)
  outside a built-in allow-list and the configured `exempt_routes` / `exempt_paths`.
- Prices hidden channel: prices, promotions, buy button and mini cart replaced by a login link
  (Twig hook conditions); the shop API returns no price; cart, checkout and order routes need a
  login; every add to cart of an anonymous visitor is refused (order modifier decorator).
- Admin field on the channel form, invalid value refused.
- Public contract `AccessMode` + `AccessModeResolverInterface`, Twig functions, security attribute.
- Translations in the seven languages.

### Security

- A closed channel no longer leaks the catalogue on the pages it leaves open: the home page product
  lists and the taxon menu / hamburger (header of every page) are hidden from anonymous visitors
  (`TMONIER_CHANNEL_ACCESS_SEE_CATALOG`).
- An HTTP request without a resolvable channel is treated as `closed` (was `open`).

### Compatibility

- Requires Sylius 2.3 and PHP 8.3 (the hook `condition` option does not exist in Sylius 2.2).
- Voters are compatible with Symfony 8 (optional `?Vote $vote` argument).
