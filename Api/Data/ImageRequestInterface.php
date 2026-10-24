<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Api\Data;

/**
 * A provider-neutral image generation request.
 *
 * @api
 */
interface ImageRequestInterface
{
    /**
     * Text prompt describing the image to produce.
     *
     * @return string
     */
    public function getPrompt(): string;

    /**
     * Reference images the result must be based on (may be empty).
     *
     * @return ImageBinaryInterface[]
     */
    public function getReferenceImages(): array;

    /**
     * Requested aspect ratio, e.g. "1:1", or null for the provider default.
     *
     * @return string|null
     */
    public function getAspectRatio(): ?string;

    /**
     * Number of images requested.
     *
     * @return int
     */
    public function getCount(): int;

    /**
     * Optional seed for providers that support reproducible output.
     *
     * @return int|null
     */
    public function getSeed(): ?int;

    /**
     * Store whose configuration (credentials, model) applies.
     *
     * @return int|null
     */
    public function getStoreId(): ?int;
}
