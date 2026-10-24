<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Api;

use Magento\Framework\Exception\LocalizedException;
use Orangecat\MagoAiImage\Api\Data\GenerationInterface;

/**
 * Generates preview images for a product from one of its gallery images and a prompt.
 *
 * @api
 */
interface ImageGeneratorInterface
{
    public const OPTION_PROVIDER = 'provider';
    public const OPTION_MEDIA_ENTRY_ID = 'media_entry_id';
    public const OPTION_ASPECT_RATIO = 'aspect_ratio';
    public const OPTION_COUNT = 'count';
    public const OPTION_SEED = 'seed';
    public const OPTION_STORE_ID = 'store_id';

    /**
     * Generate previews. Nothing is attached to the product.
     *
     * @param string $sku
     * @param string $prompt
     * @param mixed[] $options See OPTION_* constants
     * @param int|null $adminUserId
     * @return GenerationInterface[]
     * @throws LocalizedException
     */
    public function generateForProduct(
        string $sku,
        string $prompt,
        array $options = [],
        ?int $adminUserId = null
    ): array;
}
