<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Api\Data;

/**
 * Raw image bytes travelling between the generator and a provider.
 *
 * @api
 */
interface ImageBinaryInterface
{
    /**
     * Binary image content.
     *
     * @return string
     */
    public function getContent(): string;

    /**
     * MIME type, e.g. image/png.
     *
     * @return string
     */
    public function getMimeType(): string;

    /**
     * Width in pixels, when known.
     *
     * @return int|null
     */
    public function getWidth(): ?int;

    /**
     * Height in pixels, when known.
     *
     * @return int|null
     */
    public function getHeight(): ?int;
}
