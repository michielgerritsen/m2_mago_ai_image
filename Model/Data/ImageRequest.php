<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model\Data;

use Orangecat\MagoAiImage\Api\Data\ImageBinaryInterface;
use Orangecat\MagoAiImage\Api\Data\ImageRequestInterface;

/**
 * Immutable image generation request.
 */
class ImageRequest implements ImageRequestInterface
{
    /**
     * @param string $prompt
     * @param ImageBinaryInterface[] $referenceImages
     * @param string|null $aspectRatio
     * @param int $count
     * @param int|null $seed
     * @param int|null $storeId
     */
    public function __construct(
        private readonly string $prompt,
        private readonly array $referenceImages = [],
        private readonly ?string $aspectRatio = null,
        private readonly int $count = 1,
        private readonly ?int $seed = null,
        private readonly ?int $storeId = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getPrompt(): string
    {
        return $this->prompt;
    }

    /**
     * @inheritDoc
     */
    public function getReferenceImages(): array
    {
        return $this->referenceImages;
    }

    /**
     * @inheritDoc
     */
    public function getAspectRatio(): ?string
    {
        return $this->aspectRatio;
    }

    /**
     * @inheritDoc
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @inheritDoc
     */
    public function getSeed(): ?int
    {
        return $this->seed;
    }

    /**
     * @inheritDoc
     */
    public function getStoreId(): ?int
    {
        return $this->storeId;
    }
}
