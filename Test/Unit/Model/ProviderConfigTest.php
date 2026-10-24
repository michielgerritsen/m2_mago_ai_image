<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use MageOS\AiBase\Api\AiServiceSelectorInterface;
use MageOS\AiBase\Api\Data\AiServiceInterface;
use Orangecat\MagoAiImage\Api\ImageProviderInterface;
use Orangecat\MagoAiImage\Api\ProviderConfigInterface;
use Orangecat\MagoAiImage\Exception\ProviderUnavailableException;
use Orangecat\MagoAiImage\Model\ProviderConfig;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ProviderConfigTest extends TestCase
{
    /**
     * @var array<string, string>
     */
    private array $values = [];

    /**
     * @var array<string, AiServiceInterface>
     */
    private array $services = [];

    /**
     * @var ProviderConfig
     */
    private ProviderConfig $providerConfig;

    protected function setUp(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(fn (string $path) => $this->values[$path] ?? null);

        $encryptor = $this->createStub(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturnCallback(
            static fn (string $value): string => str_starts_with($value, 'enc:') ? substr($value, 4) : ''
        );

        $selector = $this->createStub(AiServiceSelectorInterface::class);
        $selector->method('getById')->willReturnCallback(fn (string $id) => $this->services[$id] ?? null);

        $this->providerConfig = new ProviderConfig($scopeConfig, $encryptor, $selector);
    }

    public function testOwnKeyIsDecrypted(): void
    {
        $this->values['mago_ai_image/gemini/api_key'] = 'enc:secret-key';

        $provider = $this->provider(['google']);
        $this->assertSame(
            ProviderConfigInterface::CREDENTIAL_SOURCE_OWN,
            $this->providerConfig->getCredentialSource($provider)
        );
        $this->assertSame('secret-key', $this->providerConfig->getApiKey($provider));
        $this->assertTrue($this->providerConfig->hasApiKey($provider));
    }

    public function testMissingKeyThrows(): void
    {
        $provider = $this->provider(['google']);

        $this->assertFalse($this->providerConfig->hasApiKey($provider));
        $this->expectException(ProviderUnavailableException::class);
        $this->providerConfig->getApiKey($provider);
    }

    public function testAiBaseRowSuppliesKey(): void
    {
        $this->values['mago_ai_image/gemini/credential_source'] = 'ai_base';
        $this->values['mago_ai_image/gemini/ai_service'] = 'row1';
        $this->services['row1'] = $this->service('google', ['api_key' => 'row-key', 'model' => 'gemini-pro']);

        $this->assertSame('row-key', $this->providerConfig->getApiKey($this->provider(['google'])));
    }

    public function testAiBaseRowOfOtherBackendIsRefused(): void
    {
        $this->values['mago_ai_image/gemini/credential_source'] = 'ai_base';
        $this->values['mago_ai_image/gemini/ai_service'] = 'row1';
        $this->services['row1'] = $this->service('openai_compatible', ['api_key' => 'kimi-key']);

        $this->expectException(ProviderUnavailableException::class);
        $this->providerConfig->getApiKey($this->provider(['google']));
    }

    public function testDeletedAiBaseRowIsReported(): void
    {
        $this->values['mago_ai_image/gemini/credential_source'] = 'ai_base';
        $this->values['mago_ai_image/gemini/ai_service'] = 'gone';

        $this->expectException(ProviderUnavailableException::class);
        $this->providerConfig->getApiKey($this->provider(['google']));
    }

    public function testAiBaseSourceIgnoredForProviderWithoutAiBaseSupport(): void
    {
        $this->values['mago_ai_image/gemini/credential_source'] = 'ai_base';
        $this->values['mago_ai_image/gemini/api_key'] = 'enc:own';

        $provider = $this->provider([]);
        $this->assertSame(
            ProviderConfigInterface::CREDENTIAL_SOURCE_OWN,
            $this->providerConfig->getCredentialSource($provider)
        );
        $this->assertSame('own', $this->providerConfig->getApiKey($provider));
    }

    public function testGenericReaderNeverReturnsTheKey(): void
    {
        $this->values['mago_ai_image/gemini/api_key'] = 'enc:secret-key';
        $this->values['mago_ai_image/gemini/base_url'] = 'https://example.test';

        $provider = $this->provider([]);
        $this->assertNull($this->providerConfig->getValue($provider, 'api_key'));
        $this->assertSame('https://example.test', $this->providerConfig->getValue($provider, 'base_url'));
    }

    public function testModelFallsBackToDefault(): void
    {
        $provider = $this->provider([]);
        $this->assertSame('default-model', $this->providerConfig->getModel($provider, 'default-model'));

        $this->values['mago_ai_image/gemini/model'] = ' custom-model ';
        $this->assertSame('custom-model', $this->providerConfig->getModel($provider, 'default-model'));
    }

    /**
     * @param string[] $aiBaseCodes
     */
    private function provider(array $aiBaseCodes): ImageProviderInterface&Stub
    {
        $provider = $this->createStub(ImageProviderInterface::class);
        $provider->method('getCode')->willReturn('gemini');
        $provider->method('getLabel')->willReturn('Gemini');
        $provider->method('getAiBaseServiceCodes')->willReturn($aiBaseCodes);

        return $provider;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function service(string $code, array $configuration): AiServiceInterface
    {
        $service = $this->createStub(AiServiceInterface::class);
        $service->method('getCode')->willReturn($code);
        $service->method('getConfiguration')->willReturn($configuration);

        return $service;
    }
}
