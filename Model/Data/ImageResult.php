<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model\Data;

use Orangecat\MagoAiImage\Api\Data\ImageBinaryInterface;
use Orangecat\MagoAiImage\Api\Data\ImageResultInterface;

/**
 * Immutable provider result.
 */
class ImageResult implements ImageResultInterface
{
    /**
     * @param ImageBinaryInterface[] $images
     * @param string $model
     * @param string|null $revisedPrompt
     * @param mixed[] $usage
     */
    public function __construct(
        private readonly array $images,
        private readonly string $model,
        private readonly ?string $revisedPrompt = null,
        private readonly array $usage = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * @inheritDoc
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * @inheritDoc
     */
    public function getRevisedPrompt(): ?string
    {
        return $this->revisedPrompt;
    }

    /**
     * @inheritDoc
     */
    public function getUsage(): array
    {
        return $this->usage;
    }
}
