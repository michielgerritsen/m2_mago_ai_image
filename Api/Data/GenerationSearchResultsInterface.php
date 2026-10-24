<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Search results of generations.
 *
 * @api
 */
interface GenerationSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get generations.
     *
     * @return GenerationInterface[]
     */
    public function getItems();

    /**
     * Set generations.
     *
     * @param GenerationInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
