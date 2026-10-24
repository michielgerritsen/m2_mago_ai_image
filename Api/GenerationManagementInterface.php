<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Api;

use Magento\Framework\Exception\LocalizedException;
use Orangecat\MagoAiImage\Api\Data\GenerationInterface;

/**
 * Moves a preview into the product gallery, or discards it.
 *
 * @api
 */
interface GenerationManagementInterface
{
    public const ALLOWED_ROLES = ['image', 'small_image', 'thumbnail', 'swatch_image'];

    /**
     * Add a preview to its product's media gallery.
     *
     * @param int $generationId
     * @param string[] $roles Subset of ALLOWED_ROLES
     * @param string|null $label
     * @return GenerationInterface
     * @throws LocalizedException
     */
    public function attach(int $generationId, array $roles = [], ?string $label = null): GenerationInterface;

    /**
     * Delete a preview file and mark it discarded.
     *
     * @param int $generationId
     * @return GenerationInterface
     * @throws LocalizedException
     */
    public function discard(int $generationId): GenerationInterface;
}
