<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Api\Data;

/**
 * What a provider returned for one request.
 *
 * @api
 */
interface ImageResultInterface
{
    /**
     * Generated images.
     *
     * @return ImageBinaryInterface[]
     */
    public function getImages(): array;

    /**
     * Model that produced the images.
     *
     * @return string
     */
    public function getModel(): string;

    /**
     * Prompt as rewritten by the provider, when it reports one.
     *
     * @return string|null
     */
    public function getRevisedPrompt(): ?string;

    /**
     * Provider usage metadata (image count, tokens, cost), free form. Never contains credentials.
     *
     * @return array<string, mixed>
     */
    public function getUsage(): array;
}
