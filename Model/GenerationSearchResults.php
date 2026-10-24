<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model;

use Magento\Framework\Api\SearchResults;
use Orangecat\MagoAiImage\Api\Data\GenerationSearchResultsInterface;

/**
 * Search criteria result holding generations.
 */
class GenerationSearchResults extends SearchResults implements GenerationSearchResultsInterface
{
}
