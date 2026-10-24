<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Service\Tool;

use Magento\Framework\Exception\LocalizedException;
use Orangecat\MagoAiImage\Api\ProviderPoolInterface;
use Orangecat\MagoAiImage\Model\Config;
use Orangecat\MagoAiImage\Model\Image\ReferenceImageLoader;
use Orangecat\MagoAiImage\Model\Availability;
use Orangecat\MagoAiImage\Model\ProductImageReader;
use MagoAssistant\Mago\Api\Tool\AvailabilityAwareToolInterface;
use MagoAssistant\Mago\Api\Tool\PresentableToolInterface;
use MagoAssistant\Mago\Service\Privacy\PiiClass;

/**
 * Lists a product's gallery images and pending AI previews, and what the image provider supports.
 */
class ListImages implements AvailabilityAwareToolInterface, PresentableToolInterface
{
    public const NAME = 'mago_product_image_list';

    /**
     * @param ProductImageReader $productImageReader
     * @param ReferenceImageLoader $referenceImageLoader
     * @param ProviderPoolInterface $providerPool
     * @param Config $config
     * @param Availability $availability
     */
    public function __construct(
        private readonly ProductImageReader $productImageReader,
        private readonly ReferenceImageLoader $referenceImageLoader,
        private readonly ProviderPoolInterface $providerPool,
        private readonly Config $config,
        private readonly Availability $availability
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
        return 'List the gallery images of a product (media_entry_id, roles, label, url), its AI previews '
            . 'not yet added to the gallery, and the aspect ratios and image count the AI image provider '
            . 'supports. Use it to pick the reference image before mago_product_image_generate.';
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
                'sku' => ['type' => 'string', 'description' => 'Product SKU'],
            ],
            'required' => ['sku'],
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
        return 'Magento_Catalog::products';
    }

    /**
     * @inheritDoc
     */
    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * Whether this call only reads.
     *
     * @param mixed[] $input
     * @return bool
     */
    public function isReadOnlyAction(array $input): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getFieldClassification(string $action = ''): array
    {
        return [
            'sku' => [PiiClass::PUBLIC],
            'product_id' => [PiiClass::PUBLIC],
            'product_name' => [PiiClass::PUBLIC],
            'default_reference_id' => [PiiClass::PUBLIC],
            'media_entry_id' => [PiiClass::PUBLIC],
            'label' => [PiiClass::PUBLIC],
            'roles' => [PiiClass::PUBLIC],
            'position' => [PiiClass::PUBLIC],
            'disabled' => [PiiClass::PUBLIC],
            'file_available' => [PiiClass::PUBLIC],
            'url' => [PiiClass::PUBLIC],
            'generation_id' => [PiiClass::PUBLIC],
            'status' => [PiiClass::PUBLIC],
            'prompt' => [PiiClass::PUBLIC],
            'created_at' => [PiiClass::PUBLIC],
            'preview_url' => [PiiClass::PUBLIC],
            'provider' => [PiiClass::PUBLIC],
            'aspect_ratios' => [PiiClass::PUBLIC],
            'max_images' => [PiiClass::PUBLIC],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getInstructions(): string
    {
        return 'To show an image, write it as Markdown, ![label](url), with the url exactly as returned. '
            . 'An image with file_available false cannot be used as reference. Without media_entry_id, '
            . 'generation uses default_reference_id.';
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
        return (string) __('Product images');
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
        return (string) __('Reading the product images...');
    }

    /**
     * Run the tool.
     *
     * @param mixed[] $params
     * @return mixed[]
     */
    public function execute(array $params): array
    {
        $sku = trim((string) ($params['sku'] ?? ''));
        if ($sku === '') {
            return ['error' => (string) __('The SKU is required.')];
        }

        try {
            $product = $this->productImageReader->getProduct($sku);
            $default = $this->referenceImageLoader->selectEntry($product);
            $provider = $this->providerPool->getDefault();
        } catch (LocalizedException $e) {
            return ['error' => $e->getMessage()];
        }
        $capabilities = $provider->getCapabilities();

        return [
            'sku' => (string) $product->getSku(),
            'product_id' => (int) $product->getId(),
            'product_name' => (string) $product->getName(),
            'default_reference_id' => $default === null ? null : (int) $default->getId(),
            'images' => $this->productImageReader->getImages($product),
            'previews' => $this->productImageReader->getPreviews((int) $product->getId()),
            'generation' => [
                'provider' => $provider->getLabel(),
                'aspect_ratios' => $capabilities->getSupportedAspectRatios(),
                'max_images' => min($this->config->getMaxImages(), max(1, $capabilities->getMaxImagesPerRequest())),
            ],
        ];
    }
}
