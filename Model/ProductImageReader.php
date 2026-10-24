<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model;

use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Orangecat\MagoAiImage\Api\Data\GenerationInterface;
use Orangecat\MagoAiImage\Api\GenerationRepositoryInterface;
use Orangecat\MagoAiImage\Model\Image\PreviewStorage;

/**
 * Product gallery and pending previews, shaped for the tool results.
 */
class ProductImageReader
{
    private const MAX_PREVIEWS = 10;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param MediaConfig $mediaConfig
     * @param Filesystem $filesystem
     * @param GenerationRepositoryInterface $generationRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param PreviewStorage $previewStorage
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly MediaConfig $mediaConfig,
        private readonly Filesystem $filesystem,
        private readonly GenerationRepositoryInterface $generationRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly PreviewStorage $previewStorage
    ) {
    }

    /**
     * Load a product by SKU.
     *
     * @param string $sku
     * @return ProductInterface
     * @throws LocalizedException
     */
    public function getProduct(string $sku): ProductInterface
    {
        try {
            return $this->productRepository->get($sku);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('The product "%1" does not exist.', $sku));
        }
    }

    /**
     * Gallery images of a product.
     *
     * @param ProductInterface $product
     * @return array<int, array<string, mixed>>
     */
    public function getImages(ProductInterface $product): array
    {
        $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $rows = [];
        foreach ($product->getMediaGalleryEntries() ?? [] as $entry) {
            /** @var ProductAttributeMediaGalleryEntryInterface $entry */
            $file = (string) $entry->getFile();
            if ($entry->getMediaType() !== 'image' || $file === '') {
                continue;
            }
            $rows[] = [
                'media_entry_id' => (int) $entry->getId(),
                'label' => (string) $entry->getLabel(),
                'roles' => array_values((array) $entry->getTypes()),
                'position' => (int) $entry->getPosition(),
                'disabled' => (bool) $entry->isDisabled(),
                'file_available' => $media->isFile($this->mediaConfig->getMediaPath($file)),
                'url' => $this->mediaConfig->getMediaUrl($file),
            ];
        }

        return $rows;
    }

    /**
     * Previews of a product not yet attached or discarded, newest first.
     *
     * @param int $productId
     * @return array<int, array<string, mixed>>
     */
    public function getPreviews(int $productId): array
    {
        $newestFirst = $this->sortOrderBuilder->setField(GenerationInterface::GENERATION_ID)
            ->setDescendingDirection()
            ->create();
        $criteria = $this->searchCriteriaBuilder
            ->addFilter(GenerationInterface::PRODUCT_ID, $productId)
            ->addFilter(GenerationInterface::STATUS, GenerationInterface::STATUS_PREVIEW)
            ->addSortOrder($newestFirst)
            ->setPageSize(self::MAX_PREVIEWS)
            ->setCurrentPage(1)
            ->create();

        $rows = [];
        foreach ($this->generationRepository->getList($criteria)->getItems() as $generation) {
            $rows[] = $this->describeGeneration($generation);
        }

        return $rows;
    }

    /**
     * One generation as returned to the model.
     *
     * @param GenerationInterface $generation
     * @return array<string, mixed>
     */
    public function describeGeneration(GenerationInterface $generation): array
    {
        $row = [
            'generation_id' => (int) $generation->getGenerationId(),
            'status' => $generation->getStatus(),
            'prompt' => $generation->getPrompt(),
        ];
        // A generation saved in this request has no created_at until it is reloaded.
        if ((string) $generation->getCreatedAt() !== '') {
            $row['created_at'] = (string) $generation->getCreatedAt();
        }
        if ($generation->getStatus() === GenerationInterface::STATUS_PREVIEW) {
            $row['preview_url'] = $this->previewStorage->getUrl($generation->getFile());
        }

        return $row;
    }
}
