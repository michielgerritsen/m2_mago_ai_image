<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Orangecat\MagoAiImage\Api\Data\GenerationInterface;
use Orangecat\MagoAiImage\Api\Data\GenerationSearchResultsInterface;
use Orangecat\MagoAiImage\Api\Data\GenerationSearchResultsInterfaceFactory;
use Orangecat\MagoAiImage\Api\GenerationRepositoryInterface;
use Orangecat\MagoAiImage\Model\ResourceModel\Generation as GenerationResource;
use Orangecat\MagoAiImage\Model\ResourceModel\Generation\CollectionFactory;

/**
 * Loads, saves and searches generations through the resource model and search criteria.
 */
class GenerationRepository implements GenerationRepositoryInterface
{
    /**
     * @param GenerationResource $resource
     * @param GenerationFactory $generationFactory
     * @param CollectionFactory $collectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param GenerationSearchResultsInterfaceFactory $searchResultsFactory
     */
    public function __construct(
        private readonly GenerationResource $resource,
        private readonly GenerationFactory $generationFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly GenerationSearchResultsInterfaceFactory $searchResultsFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(GenerationInterface $generation): GenerationInterface
    {
        try {
            /** @var Generation $generation */
            $this->resource->save($generation);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save the generated image: %1', $e->getMessage()), $e);
        }

        return $generation;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $generationId): GenerationInterface
    {
        $generation = $this->generationFactory->create();
        $this->resource->load($generation, $generationId);
        if ($generation->getGenerationId() === null) {
            throw new NoSuchEntityException(__('The generated image with ID "%1" does not exist.', $generationId));
        }

        return $generation;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): GenerationSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        /** @var GenerationInterface[] $items */
        $items = $collection->getItems();
        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(GenerationInterface $generation): bool
    {
        try {
            /** @var Generation $generation */
            $this->resource->delete($generation);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete the generated image: %1', $e->getMessage()), $e);
        }

        return true;
    }
}
