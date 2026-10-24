<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model;

use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterfaceFactory;
use Magento\Catalog\Api\ProductAttributeMediaGalleryManagementInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\Data\ImageContentInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Orangecat\MagoAiImage\Api\Data\GenerationInterface;
use Orangecat\MagoAiImage\Api\GenerationManagementInterface;
use Orangecat\MagoAiImage\Api\GenerationRepositoryInterface;
use Orangecat\MagoAiImage\Model\Image\ImageInspector;
use Orangecat\MagoAiImage\Model\Image\PreviewStorage;

/**
 * Attaches previews to the product gallery, or discards them.
 */
class GenerationManagement implements GenerationManagementInterface
{
    private const MAX_LABEL_LENGTH = 255;

    /**
     * @param GenerationRepositoryInterface $generationRepository
     * @param ProductRepositoryInterface $productRepository
     * @param ProductAttributeMediaGalleryManagementInterface $galleryManagement
     * @param ProductAttributeMediaGalleryEntryInterfaceFactory $entryFactory
     * @param ImageContentInterfaceFactory $imageContentFactory
     * @param PreviewStorage $previewStorage
     * @param ImageInspector $imageInspector
     */
    public function __construct(
        private readonly GenerationRepositoryInterface $generationRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductAttributeMediaGalleryManagementInterface $galleryManagement,
        private readonly ProductAttributeMediaGalleryEntryInterfaceFactory $entryFactory,
        private readonly ImageContentInterfaceFactory $imageContentFactory,
        private readonly PreviewStorage $previewStorage,
        private readonly ImageInspector $imageInspector
    ) {
    }

    /**
     * @inheritDoc
     */
    public function attach(int $generationId, array $roles = [], ?string $label = null): GenerationInterface
    {
        $generation = $this->getPreview($generationId);

        $roles = array_values(array_unique(array_map('strval', $roles)));
        $invalid = array_diff($roles, self::ALLOWED_ROLES);
        if ($invalid !== []) {
            throw new LocalizedException(
                __(
                    'Unknown image role(s): %1. Allowed: %2.',
                    implode(', ', $invalid),
                    implode(', ', self::ALLOWED_ROLES)
                )
            );
        }
        $label = $label === null ? '' : trim($label);
        if (mb_strlen($label) > self::MAX_LABEL_LENGTH) {
            throw new LocalizedException(__('The label is too long (maximum %1 characters).', self::MAX_LABEL_LENGTH));
        }

        try {
            $product = $this->productRepository->getById($generation->getProductId());
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('The product of this generated image no longer exists.'));
        }

        $image = $this->imageInspector->inspect($this->previewStorage->read($generation->getFile()));
        $extension = $this->imageInspector->getExtension($image->getMimeType());

        $content = $this->imageContentFactory->create();
        $content->setBase64EncodedData(base64_encode($image->getContent()));
        $content->setType($image->getMimeType());
        $content->setName(sprintf('%s-ai-%d.%s', $this->slug((string) $product->getSku()), $generationId, $extension));

        $entry = $this->entryFactory->create();
        $entry->setMediaType('image');
        $entry->setLabel($label);
        $entry->setTypes($roles);
        $entry->setDisabled(false);
        $entry->setContent($content);

        $entryId = (int) $this->galleryManagement->create((string) $product->getSku(), $entry);

        // The preview file stays until the retention period ends (Cron\CleanupPreviews), so the
        // Mago chat history keeps showing it.
        $generation->setStatus(GenerationInterface::STATUS_ATTACHED)
            ->setMediaGalleryEntryId($entryId > 0 ? $entryId : null);

        return $this->generationRepository->save($generation);
    }

    /**
     * @inheritDoc
     */
    public function discard(int $generationId): GenerationInterface
    {
        $generation = $this->getPreview($generationId);
        $this->previewStorage->delete($generation->getFile());
        $generation->setStatus(GenerationInterface::STATUS_DISCARDED);

        return $this->generationRepository->save($generation);
    }

    /**
     * Load a generation that is still a preview.
     *
     * @param int $generationId
     * @return GenerationInterface
     * @throws LocalizedException
     */
    private function getPreview(int $generationId): GenerationInterface
    {
        $generation = $this->generationRepository->getById($generationId);
        if ($generation->getStatus() !== GenerationInterface::STATUS_PREVIEW) {
            throw new LocalizedException(
                __('The generated image %1 is already %2.', $generationId, $generation->getStatus())
            );
        }

        return $generation;
    }

    /**
     * File-name friendly SKU.
     *
     * @param string $sku
     * @return string
     */
    private function slug(string $sku): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($sku)), '-');

        return $slug === '' ? 'product' : substr($slug, 0, 60);
    }
}
