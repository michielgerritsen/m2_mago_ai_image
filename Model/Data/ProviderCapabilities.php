<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model\Data;

use Orangecat\MagoAiImage\Api\Data\ProviderCapabilitiesInterface;

/**
 * Immutable provider capabilities.
 */
class ProviderCapabilities implements ProviderCapabilitiesInterface
{
    /**
     * @param bool $supportsReference
     * @param int $maxReferenceImages
     * @param string[] $supportedAspectRatios
     * @param int $maxImagesPerRequest
     */
    public function __construct(
        private readonly bool $supportsReference = true,
        private readonly int $maxReferenceImages = 1,
        private readonly array $supportedAspectRatios = [],
        private readonly int $maxImagesPerRequest = 1
    ) {
    }

    /**
     * @inheritDoc
     */
    public function supportsReference(): bool
    {
        return $this->supportsReference;
    }

    /**
     * @inheritDoc
     */
    public function getMaxReferenceImages(): int
    {
        return $this->maxReferenceImages;
    }

    /**
     * @inheritDoc
     */
    public function getSupportedAspectRatios(): array
    {
        return $this->supportedAspectRatios;
    }

    /**
     * @inheritDoc
     */
    public function getMaxImagesPerRequest(): int
    {
        return $this->maxImagesPerRequest;
    }
}
