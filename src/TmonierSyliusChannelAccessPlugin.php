<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin;

use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tmonier\SyliusChannelAccessPlugin\DependencyInjection\Compiler\RestrictionOverlapPass;

final class TmonierSyliusChannelAccessPlugin extends Bundle
{
    use SyliusPluginTrait;

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // After the other passes that may add entries to the lists, before the parameters are resolved.
        $container->addCompilerPass(new RestrictionOverlapPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -100);
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
