<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\Access;

use Symfony\Component\HttpFoundation\Request;

/**
 * Matches a request by route name or by path with shell-style patterns ("*" matches any
 * characters, "/" included). Nothing matches by default: the lists come from the configuration. A suspicious path
 * ("..", "//", a backslash, a NUL byte) matches only a matcher built to fail closed.
 */
final readonly class PatternRequestMatcher
{
    /**
     * @param list<string> $routes route name patterns, e.g. "sylius_shop_login" or "app_feed_*"
     * @param list<string> $paths path patterns, e.g. "/feeds/*"
     */
    public function __construct(
        private array $routes,
        private array $paths,
        private bool $suspiciousPathsMatch = false,
    ) {
    }

    public function matches(Request $request): bool
    {
        $route = $request->attributes->get('_route');
        if (\is_string($route) && $this->matchesAny($this->routes, $route)) {
            return true;
        }

        $path = rawurldecode($request->getPathInfo());
        // A path that climbs or doubles slashes is never exempted by its prefix; for a matcher that fails closed
        // (the restrictions) it is the opposite: it cannot be told apart from a restricted one, so it matches.
        if (str_contains($path, '..') || str_contains($path, '//') || str_contains($path, '\\') || str_contains($path, "\0")) {
            return $this->suspiciousPathsMatch;
        }

        return $this->matchesAny($this->paths, $path);
    }

    /** @param list<string> $patterns */
    private function matchesAny(array $patterns, string $subject): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $subject)) {
                return true;
            }
        }

        return false;
    }
}
