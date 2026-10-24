<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model;

use Orangecat\MagoAiImage\Api\ProviderPoolInterface;
use Orangecat\MagoAiImage\Exception\ProviderUnavailableException;
use Orangecat\MagoAiImage\Model\Config;

/**
 * Whether the image tools can run: generation enabled and the default provider configured.
 */
class Availability
{
    /**
     * @param Config $config
     * @param ProviderPoolInterface $providerPool
     */
    public function __construct(
        private readonly Config $config,
        private readonly ProviderPoolInterface $providerPool
    ) {
    }

    /**
     * Whether a generation can be requested.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->getUnavailableReason() === null;
    }

    /**
     * Why generation is not possible, or null when it is.
     *
     * @return string|null
     */
    public function getUnavailableReason(): ?string
    {
        if (!$this->config->isEnabled()) {
            return (string) __('AI image generation is disabled.');
        }
        try {
            $this->providerPool->getDefault();
        } catch (ProviderUnavailableException $e) {
            return $e->getMessage();
        }

        return null;
    }
}
