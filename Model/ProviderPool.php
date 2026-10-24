<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model;

use InvalidArgumentException;
use Magento\Framework\Exception\NoSuchEntityException;
use Orangecat\MagoAiImage\Api\ImageProviderInterface;
use Orangecat\MagoAiImage\Api\ProviderPoolInterface;
use Orangecat\MagoAiImage\Exception\ProviderUnavailableException;

/**
 * Providers registered through di.xml.
 */
class ProviderPool implements ProviderPoolInterface
{
    /**
     * @var array<string, ImageProviderInterface>
     */
    private array $providers = [];

    /**
     * @param Config $config
     * @param ImageProviderInterface[] $providers
     */
    public function __construct(
        private readonly Config $config,
        array $providers = []
    ) {
        foreach ($providers as $key => $provider) {
            if (!$provider instanceof ImageProviderInterface) {
                throw new InvalidArgumentException(
                    sprintf('Image provider "%s" must implement %s.', $key, ImageProviderInterface::class)
                );
            }
            if ($provider->getCode() !== $key) {
                throw new InvalidArgumentException(
                    sprintf('Image provider "%s" is registered under the key "%s".', $provider->getCode(), $key)
                );
            }
            $this->providers[$key] = $provider;
        }
    }

    /**
     * @inheritDoc
     */
    public function getAll(): array
    {
        return $this->providers;
    }

    /**
     * @inheritDoc
     */
    public function has(string $code): bool
    {
        return isset($this->providers[$code]);
    }

    /**
     * @inheritDoc
     */
    public function get(string $code): ImageProviderInterface
    {
        if (!isset($this->providers[$code])) {
            throw new NoSuchEntityException(__('The image provider "%1" is not installed.', $code));
        }

        return $this->providers[$code];
    }

    /**
     * @inheritDoc
     */
    public function getDefault(?int $storeId = null): ImageProviderInterface
    {
        $code = $this->config->getProviderCode($storeId);
        if ($code === '') {
            throw new ProviderUnavailableException(__('No image provider is selected in the configuration.'));
        }
        if (!isset($this->providers[$code])) {
            throw new ProviderUnavailableException(__('The image provider "%1" is not installed.', $code));
        }
        $provider = $this->providers[$code];
        if (!$provider->isAvailable($storeId)) {
            throw new ProviderUnavailableException(
                __('The image provider "%1" is not configured.', $provider->getLabel())
            );
        }

        return $provider;
    }
}
