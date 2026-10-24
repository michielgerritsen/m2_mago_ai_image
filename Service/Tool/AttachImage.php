<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Service\Tool;

use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Catalog\Api\ProductAttributeMediaGalleryManagementInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Orangecat\MagoAiImage\Api\Data\GenerationInterface;
use Orangecat\MagoAiImage\Api\GenerationManagementInterface;
use Orangecat\MagoAiImage\Api\GenerationRepositoryInterface;
use Orangecat\MagoAiImage\Model\Availability;
use Orangecat\MagoAiImage\Model\ProductAccess;
use MagoAssistant\Mago\Api\Tool\AvailabilityAwareToolInterface;
use MagoAssistant\Mago\Api\Tool\PresentableToolInterface;
use MagoAssistant\Mago\Api\Tool\ValidatingToolInterface;
use MagoAssistant\Mago\Service\Privacy\PiiClass;

/**
 * Adds an AI preview to its product's media gallery.
 */
class AttachImage implements AvailabilityAwareToolInterface, PresentableToolInterface, ValidatingToolInterface
{
    public const NAME = 'mago_product_image_attach';

    /**
     * @param GenerationManagementInterface $generationManagement
     * @param GenerationRepositoryInterface $generationRepository
     * @param ProductRepositoryInterface $productRepository
     * @param ProductAttributeMediaGalleryManagementInterface $galleryManagement
     * @param MediaConfig $mediaConfig
     * @param BackendUrl $backendUrl
     * @param Availability $availability
     * @param ProductAccess $productAccess
     */
    public function __construct(
        private readonly GenerationManagementInterface $generationManagement,
        private readonly GenerationRepositoryInterface $generationRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductAttributeMediaGalleryManagementInterface $galleryManagement,
        private readonly MediaConfig $mediaConfig,
        private readonly BackendUrl $backendUrl,
        private readonly Availability $availability,
        private readonly ProductAccess $productAccess
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Add an AI preview from mago_product_image_generate to its product\'s gallery, optionally '
            . 'as base, small, thumbnail or swatch image and with a label.';
    }

    /**
     * JSON Schema of the tool parameters.
     *
     * @return mixed[]
     */
    public function getParameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'generation_id' => ['type' => 'integer', 'description' => 'generation_id of the preview'],
                'roles' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => GenerationManagementInterface::ALLOWED_ROLES],
                    'description' => 'Image roles to assign; image is the base image. Empty for none.',
                ],
                'label' => ['type' => 'string', 'maxLength' => 255, 'description' => 'Image label (alt text)'],
            ],
            'required' => ['generation_id'],
        ];
    }

    /**
     * Magento ACL resource the call needs.
     *
     * @param mixed[] $input
     * @return string
     */
    public function getMagentoAcl(array $input = []): string
    {
        return 'Orangecat_MagoAiImage::attach';
    }

    /**
     * @inheritDoc
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Whether this call only reads.
     *
     * @param mixed[] $input
     * @return bool
     */
    public function isReadOnlyAction(array $input): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getFieldClassification(string $action = ''): array
    {
        return [
            'generation_id' => [PiiClass::PUBLIC],
            'status' => [PiiClass::PUBLIC],
            'sku' => [PiiClass::PUBLIC],
            'media_entry_id' => [PiiClass::PUBLIC],
            'roles' => [PiiClass::PUBLIC],
            'label' => [PiiClass::PUBLIC],
            'url' => [PiiClass::PUBLIC],
            'admin_url' => [PiiClass::PUBLIC],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getInstructions(): string
    {
        return 'Confirm the image was added and which roles it has, with a link to the product (admin_url). '
            . 'Storefront caches may need a refresh before the new image shows there.';
    }

    /**
     * Refuse a call that cannot succeed before the admin is asked to confirm it.
     *
     * @param mixed[] $input
     * @return mixed[]|null
     */
    public function findRefusal(array $input): ?array
    {
        $denied = $this->productAccess->getDeniedReason();
        if ($denied !== null) {
            return ['error' => $denied];
        }
        $generationId = (int) ($input['generation_id'] ?? 0);
        try {
            $generation = $this->generationRepository->getById($generationId);
        } catch (NoSuchEntityException) {
            return ['error' => (string) __('The generated image %1 does not exist.', $generationId)];
        }
        if ($generation->getStatus() !== GenerationInterface::STATUS_PREVIEW) {
            return [
                'error' => (string) __(
                    'The generated image %1 is already %2.',
                    $generationId,
                    $generation->getStatus()
                ),
            ];
        }
        $invalid = array_diff(
            array_map('strval', (array) ($input['roles'] ?? [])),
            GenerationManagementInterface::ALLOWED_ROLES
        );
        if ($invalid !== []) {
            return ['error' => (string) __('Unknown image role(s): %1.', implode(', ', $invalid))];
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return $this->availability->isAvailable();
    }

    /**
     * @inheritDoc
     */
    public function getDisplayName(): string
    {
        return (string) __('Add image to gallery');
    }

    /**
     * Status line while the call runs.
     *
     * @param string $action
     * @param mixed[] $input
     * @return string|null
     */
    public function getStatusMessage(string $action, array $input): ?string
    {
        return (string) __('Adding the image to the product gallery...');
    }

    /**
     * Run the tool.
     *
     * @param mixed[] $params
     * @return mixed[]
     */
    public function execute(array $params): array
    {
        $denied = $this->productAccess->getDeniedReason();
        if ($denied !== null) {
            return ['error' => $denied];
        }
        $roles = array_values(array_map('strval', (array) ($params['roles'] ?? [])));
        $label = isset($params['label']) ? (string) $params['label'] : null;

        try {
            $generation = $this->generationManagement->attach((int) ($params['generation_id'] ?? 0), $roles, $label);
            $product = $this->productRepository->getById($generation->getProductId());
        } catch (LocalizedException $e) {
            return ['error' => $e->getMessage()];
        }

        $sku = (string) $product->getSku();
        $entryId = $generation->getMediaGalleryEntryId();
        $url = null;
        if ($entryId !== null) {
            try {
                $file = (string) $this->galleryManagement->get($sku, $entryId)->getFile();
                $url = $this->mediaConfig->getMediaUrl($file);
            } catch (NoSuchEntityException) {
                $url = null;
            }
        }

        return [
            'generation_id' => (int) $generation->getGenerationId(),
            'status' => $generation->getStatus(),
            'sku' => $sku,
            'media_entry_id' => $entryId,
            'roles' => $roles,
            'label' => (string) $label,
            'url' => $url,
            'admin_url' => $this->backendUrl->getUrl('catalog/product/edit', ['id' => $product->getId()]),
        ];
    }
}
