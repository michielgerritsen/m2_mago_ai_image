<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Test\Unit\Model;

use InvalidArgumentException;
use Magento\Framework\Exception\NoSuchEntityException;
use Orangecat\MagoAiImage\Api\ImageProviderInterface;
use Orangecat\MagoAiImage\Exception\ProviderUnavailableException;
use Orangecat\MagoAiImage\Model\Config;
use Orangecat\MagoAiImage\Model\ProviderPool;
use PHPUnit\Framework\TestCase;

class ProviderPoolTest extends TestCase
{
    public function testRejectsProviderRegisteredUnderAnotherKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ProviderPool($this->createStub(Config::class), ['other' => $this->provider('gemini', true)]);
    }

    public function testGetUnknownProviderThrows(): void
    {
        $pool = new ProviderPool($this->createStub(Config::class), []);

        $this->assertFalse($pool->has('gemini'));
        $this->expectException(NoSuchEntityException::class);
        $pool->get('gemini');
    }

    public function testDefaultRequiresSelection(): void
    {
        $pool = new ProviderPool($this->config(''), ['gemini' => $this->provider('gemini', true)]);

        $this->expectException(ProviderUnavailableException::class);
        $pool->getDefault();
    }

    public function testDefaultRequiresInstalledProvider(): void
    {
        $pool = new ProviderPool($this->config('openai'), ['gemini' => $this->provider('gemini', true)]);

        $this->expectException(ProviderUnavailableException::class);
        $pool->getDefault();
    }

    public function testDefaultRequiresAvailableProvider(): void
    {
        $pool = new ProviderPool($this->config('gemini'), ['gemini' => $this->provider('gemini', false)]);

        $this->expectException(ProviderUnavailableException::class);
        $pool->getDefault();
    }

    public function testDefaultReturnsConfiguredProvider(): void
    {
        $gemini = $this->provider('gemini', true);
        $pool = new ProviderPool(
            $this->config('gemini'),
            ['gemini' => $gemini, 'dummy' => $this->provider('dummy', true)]
        );

        $this->assertSame($gemini, $pool->getDefault());
        $this->assertSame(['gemini', 'dummy'], array_keys($pool->getAll()));
    }

    private function provider(string $code, bool $available): ImageProviderInterface
    {
        $provider = $this->createStub(ImageProviderInterface::class);
        $provider->method('getCode')->willReturn($code);
        $provider->method('getLabel')->willReturn(ucfirst($code));
        $provider->method('isAvailable')->willReturn($available);

        return $provider;
    }

    private function config(string $providerCode): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('getProviderCode')->willReturn($providerCode);

        return $config;
    }
}
