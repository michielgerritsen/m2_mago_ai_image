<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Orangecat\MagoAiImage\Api\Data\GenerationInterface;
use Orangecat\MagoAiImage\Api\Data\GenerationSearchResultsInterface;

/**
 * Persistence of generations.
 *
 * @api
 */
interface GenerationRepositoryInterface
{
    /**
     * Save a generation.
     *
     * @param GenerationInterface $generation
     * @return GenerationInterface
     * @throws CouldNotSaveException
     */
    public function save(GenerationInterface $generation): GenerationInterface;

    /**
     * Load by ID.
     *
     * @param int $generationId
     * @return GenerationInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $generationId): GenerationInterface;

    /**
     * Search generations.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return GenerationSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): GenerationSearchResultsInterface;

    /**
     * Delete a generation record (the file is not touched).
     *
     * @param GenerationInterface $generation
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(GenerationInterface $generation): bool;
}
