<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Service\Tool;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\LocalizedException;
use Orangecat\MagoAiImage\Api\Data\ProviderCapabilitiesInterface;
use Orangecat\MagoAiImage\Api\ImageGeneratorInterface;
use Orangecat\MagoAiImage\Api\ProviderPoolInterface;
use Orangecat\MagoAiImage\Model\Config;
use Orangecat\MagoAiImage\Model\Availability;
use Orangecat\MagoAiImage\Model\ProductAccess;
use Orangecat\MagoAiImage\Model\ProductImageReader;
use MagoAssistant\Mago\Api\Tool\AvailabilityAwareToolInterface;
use MagoAssistant\Mago\Api\Tool\PresentableToolInterface;
use MagoAssistant\Mago\Api\Tool\UpfrontGuidanceToolInterface;
use MagoAssistant\Mago\Api\Tool\ValidatingToolInterface;
use MagoAssistant\Mago\Service\Privacy\PiiClass;

/**
 * Generates preview images of a product from one of its gallery images and a prompt.
 *
 * A write tool, so the admin confirms every call: each one is billed by the image provider.
 */
class GenerateImage implements
    AvailabilityAwareToolInterface,
    PresentableToolInterface,
    UpfrontGuidanceToolInterface,
    ValidatingToolInterface
{
    public const NAME = 'mago_product_image_generate';

    private const MAX_PROMPT_LENGTH = 4000;

    /**
     * @param ImageGeneratorInterface $imageGenerator
     * @param ProductImageReader $productImageReader
     * @param ProviderPoolInterface $providerPool
     * @param Config $config
     * @param Availability $availability
     * @param UserContextInterface $userContext
     * @param ProductAccess $productAccess
     */
    public function __construct(
        private readonly ImageGeneratorInterface $imageGenerator,
        private readonly ProductImageReader $productImageReader,
        private readonly ProviderPoolInterface $providerPool,
        private readonly Config $config,
        private readonly Availability $availability,
        private readonly UserContextInterface $userContext,
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
        return 'Generate new images of a product with AI from one of its gallery images and a prompt, '
            . 'e.g. the product worn by a person in a city street. Returns previews only: nothing is added '
            . 'to the product until mago_product_image_attach. Each image is billed by the provider.';
    }

    /**
     * JSON Schema of the tool parameters.
     *
     * @return mixed[]
     */
    public function getParameterSchema(): array
    {
        $aspectRatio = [
            'type' => 'string',
            'description' => 'Aspect ratio of the new image, e.g. 1:1, 3:4 or 16:9. Omit for the provider default.',
        ];
        $maxImages = 1;
        $capabilities = $this->getCapabilities();
        if ($capabilities !== null) {
            if ($capabilities->getSupportedAspectRatios() !== []) {
                $aspectRatio['enum'] = $capabilities->getSupportedAspectRatios();
            }
            $maxImages = min($this->config->getMaxImages(), max(1, $capabilities->getMaxImagesPerRequest()));
        }

        return [
            'type' => 'object',
            'properties' => [
                'sku' => ['type' => 'string', 'description' => 'Product SKU'],
                'prompt' => [
                    'type' => 'string',
                    'maxLength' => self::MAX_PROMPT_LENGTH,
                    'description' => 'Image prompt in English: the new scene, keeping the product identical',
                ],
                'media_entry_id' => [
                    'type' => 'integer',
                    'description' => 'Gallery image to use as reference (from mago_product_image_list). '
                        . 'Omit for the base image.',
                ],
                'aspect_ratio' => $aspectRatio,
                'count' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => $maxImages,
                    'description' => 'Number of images, default 1',
                ],
            ],
            'required' => ['sku', 'prompt'],
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
        return 'Orangecat_MagoAiImage::generate';
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
            'sku' => [PiiClass::PUBLIC],
            'product_id' => [PiiClass::PUBLIC],
            'provider' => [PiiClass::PUBLIC],
            'generation_id' => [PiiClass::PUBLIC],
            'status' => [PiiClass::PUBLIC],
            'prompt' => [PiiClass::PUBLIC],
            'created_at' => [PiiClass::PUBLIC],
            'preview_url' => [PiiClass::PUBLIC],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getUpfrontGuidance(): string
    {
        return 'Product images with AI (mago_product_image_generate): write the prompt in English, whatever '
            . 'language the user writes in. Describe the new scene (person, place, light, framing, style) and '
            . 'say the product from the reference image must stay identical: same shape, colours, materials, '
            . 'logos and details. Never ask for text, logos or brands that are not on the product. Generate one '
            . 'image unless the user asks for more. Call mago_product_image_list first when the user wants a '
            . 'reference image other than the base one or asks which images the product has.';
    }

    /**
     * @inheritDoc
     */
    public function getInstructions(): string
    {
        return 'Show every preview as a Markdown image, ![short description](preview_url), with preview_url '
            . 'exactly as returned. Then ask whether to add one to the product gallery and with which roles '
            . '(mago_product_image_attach with its generation_id). Previews are deleted automatically '
            . 'after a few days, also once added (the gallery keeps its own copy).';
    }

    /**
     * Refuse a call that cannot succeed before the admin is asked to confirm it.
     *
     * @param mixed[] $input
     * @return mixed[]|null
     */
    public function findRefusal(array $input): ?array
    {
        $reason = $this->productAccess->getDeniedReason() ?? $this->availability->getUnavailableReason();
        if ($reason !== null) {
            return ['error' => $reason];
        }
        if (trim((string) ($input['prompt'] ?? '')) === '') {
            return ['error' => (string) __('The prompt is empty.')];
        }
        $sku = trim((string) ($input['sku'] ?? ''));
        if ($sku === '') {
            return ['error' => (string) __('The SKU is required.')];
        }
        try {
            $this->productImageReader->getProduct($sku);
        } catch (LocalizedException $e) {
            return ['error' => $e->getMessage()];
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
        return (string) __('Generate product image');
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
        return (string) __('Generating the image, this can take up to a minute...');
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
        $sku = trim((string) ($params['sku'] ?? ''));
        $options = [];
        if (isset($params['media_entry_id'])) {
            $options[ImageGeneratorInterface::OPTION_MEDIA_ENTRY_ID] = (int) $params['media_entry_id'];
        }
        if (isset($params['aspect_ratio']) && $params['aspect_ratio'] !== '') {
            $options[ImageGeneratorInterface::OPTION_ASPECT_RATIO] = (string) $params['aspect_ratio'];
        }
        if (isset($params['count'])) {
            $options[ImageGeneratorInterface::OPTION_COUNT] = (int) $params['count'];
        }
        $adminUserId = $this->userContext->getUserType() === UserContextInterface::USER_TYPE_ADMIN
            ? $this->userContext->getUserId()
            : null;

        try {
            $generations = $this->imageGenerator->generateForProduct(
                $sku,
                (string) ($params['prompt'] ?? ''),
                $options,
                $adminUserId === null ? null : (int) $adminUserId
            );
        } catch (LocalizedException $e) {
            return ['error' => $e->getMessage()];
        }

        $first = reset($generations);

        return [
            'sku' => $sku,
            'product_id' => $first === false ? null : $first->getProductId(),
            'provider' => $first === false ? null : $first->getProvider(),
            'previews' => array_map([$this->productImageReader, 'describeGeneration'], $generations),
        ];
    }

    /**
     * Capabilities of the default provider, or null while none is configured (the tool is hidden then).
     *
     * @return ProviderCapabilitiesInterface|null
     */
    private function getCapabilities(): ?ProviderCapabilitiesInterface
    {
        try {
            return $this->providerPool->getDefault()->getCapabilities();
        } catch (LocalizedException) {
            return null;
        }
    }
}
