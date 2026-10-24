<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model\Data;

use Orangecat\MagoAiImage\Api\Data\ImageBinaryInterface;

/**
 * Immutable image bytes.
 */
class ImageBinary implements ImageBinaryInterface
{
    /**
     * @param string $content
     * @param string $mimeType
     * @param int|null $width
     * @param int|null $height
     */
    public function __construct(
        private readonly string $content,
        private readonly string $mimeType,
        private readonly ?int $width = null,
        private readonly ?int $height = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @inheritDoc
     */
    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * @inheritDoc
     */
    public function getWidth(): ?int
    {
        return $this->width;
    }

    /**
     * @inheritDoc
     */
    public function getHeight(): ?int
    {
        return $this->height;
    }
}
