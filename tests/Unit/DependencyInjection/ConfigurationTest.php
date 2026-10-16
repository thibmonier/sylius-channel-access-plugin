<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusChannelAccessPlugin\Unit\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Tmonier\SyliusChannelAccessPlugin\DependencyInjection\Configuration;

final class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = $this->process([]);

        self::assertSame('sylius_shop_login', $config['login_route']);
        self::assertSame([], $config['exempt_routes']);
        self::assertSame([], $config['exempt_paths']);
    }

    public function testExemptionsAreKept(): void
    {
        $config = $this->process(['exempt_paths' => ['/punchout/*', '/feeds/*'], 'exempt_routes' => ['app_mcp_*']]);

        self::assertSame(['/punchout/*', '/feeds/*'], $config['exempt_paths']);
        self::assertSame(['app_mcp_*'], $config['exempt_routes']);
    }

    /** @return iterable<string, array{string}> */
    public static function tooBroad(): iterable
    {
        yield 'star' => ['*'];
        yield 'double star' => ['**'];
        yield 'slash star' => ['/*'];
        yield 'slash double star' => ['/**'];
    }

    #[DataProvider('tooBroad')]
    public function testAPatternThatWouldOpenTheWholeChannelIsRefused(string $pattern): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process(['exempt_paths' => [$pattern]]);
    }

    public function testAPatternThatWouldOpenEveryRouteIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process(['exempt_routes' => ['*']]);
    }

    public function testAnEmptyLoginRouteIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process(['login_route' => '']);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{login_route: string, exempt_routes: list<string>, exempt_paths: list<string>}
     */
    private function process(array $config): array
    {
        /** @var array{login_route: string, exempt_routes: list<string>, exempt_paths: list<string>} $processed */
        $processed = (new Processor())->processConfiguration(new Configuration(), [$config]);

        return $processed;
    }
}
