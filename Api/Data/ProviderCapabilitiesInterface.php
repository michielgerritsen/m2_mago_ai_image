<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Api\Data;

/**
 * What an image provider can do, so callers can validate a request before paying for it.
 *
 * @api
 */
interface ProviderCapabilitiesInterface
{
    /**
     * Whether the provider accepts reference images (image-to-image editing).
     *
     * @return bool
     */
    public function supportsReference(): bool;

    /**
     * Maximum number of reference images per request.
     *
     * @return int
     */
    public function getMaxReferenceImages(): int;

    /**
     * Aspect ratios the provider accepts, e.g. ['1:1', '3:4']. Empty means any.
     *
     * @return string[]
     */
    public function getSupportedAspectRatios(): array;

    /**
     * Maximum number of images returned by one request.
     *
     * @return int
     */
    public function getMaxImagesPerRequest(): int;
}
