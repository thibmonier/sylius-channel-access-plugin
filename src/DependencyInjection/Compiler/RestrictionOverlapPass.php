<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\DependencyInjection\Compiler;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * An entry that is both exempted and restricted has no clear meaning, and a wide exemption silently opening a
 * restricted route is the mistake to catch. Checked on the resolved values ("%parameter%" placeholders replaced) of
 * the final lists (built-in and configured entries, whichever pass added them): an entry that covers another one, in
 * either direction, fails the compilation and both entries are named.
 *
 * When patterns overlap only partly (which a comparison of patterns cannot see) the restriction wins at runtime.
 */
final class RestrictionOverlapPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('tmonier_channel_access.exempt_routes')) {
            return;
        }

        foreach (['routes', 'paths'] as $kind) {
            $exempt = $this->resolved($container, 'tmonier_channel_access.exempt_' . $kind);
            $restricted = $this->resolved($container, 'tmonier_channel_access.restricted_' . $kind);

            foreach ($exempt as $exemption) {
                foreach ($restricted as $restriction) {
                    if ($this->covers($exemption, $restriction) || $this->covers($restriction, $exemption)) {
                        throw new InvalidConfigurationException(sprintf('The exempt %s "%s" and the restricted %s "%s" of "tmonier_sylius_channel_access" overlap: an entry cannot be exempted and restricted at once (the restriction wins at runtime). Remove or narrow one of them.', rtrim($kind, 's'), $exemption, rtrim($kind, 's'), $restriction));
                    }
                }
            }
        }
    }

    private function covers(string $pattern, string $other): bool
    {
        return $pattern === $other || fnmatch($pattern, $other);
    }

    /** @return list<string> */
    private function resolved(ContainerBuilder $container, string $parameter): array
    {
        $values = $container->getParameterBag()->resolveValue($container->getParameter($parameter));

        return \is_array($values) ? array_values(array_filter($values, \is_string(...))) : [];
    }
}
