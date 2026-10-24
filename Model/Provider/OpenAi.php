<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model\Provider;

use Orangecat\MagoAiImage\Api\Data\ImageBinaryInterface;
use Orangecat\MagoAiImage\Api\Data\ImageBinaryInterfaceFactory;
use Orangecat\MagoAiImage\Api\Data\ImageRequestInterface;
use Orangecat\MagoAiImage\Api\Data\ImageResultInterface;
use Orangecat\MagoAiImage\Api\Data\ImageResultInterfaceFactory;
use Orangecat\MagoAiImage\Api\Data\ProviderCapabilitiesInterface;
use Orangecat\MagoAiImage\Api\Data\ProviderCapabilitiesInterfaceFactory;
use Orangecat\MagoAiImage\Api\ImageProviderInterface;
use Orangecat\MagoAiImage\Api\ProviderConfigInterface;
use Orangecat\MagoAiImage\Exception\ContentRejectedException;
use Orangecat\MagoAiImage\Exception\ProviderException;
use Orangecat\MagoAiImage\Exception\ProviderUnavailableException;
use Orangecat\MagoAiImage\Model\Config;
use Orangecat\MagoAiImage\Model\Platform\PlatformFactory;
use Orangecat\MagoAiImage\Model\Platform\ResponseReader;
use Symfony\AI\Platform\Bridge\OpenAi\Image as ImageModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformExceptionInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\MultiPartResult;

/**
 * OpenAI GPT Image models through `images/edits`, with the product image as reference, with the
 * Symfony AI OpenAI bridge.
 *
 * One call returns all requested images (`n`).
 */
class OpenAi implements ImageProviderInterface
{
    public const CODE = 'openai';
    public const DEFAULT_MODEL = 'gpt-image-2.5-sunburst';
    public const FIELD_QUALITY = 'quality';

    /**
     * Output size per aspect ratio. GPT Image 2 and later accept any WIDTHxHEIGHT with both sides a
     * multiple of 16 and a ratio up to 3:1; these stay near 1.5 megapixels.
     */
    private const SIZES = [
        '1:1' => '1024x1024',
        '3:2' => '1536x1024',
        '2:3' => '1024x1536',
        '4:3' => '1536x1152',
        '3:4' => '1152x1536',
        '5:4' => '1280x1024',
        '4:5' => '1024x1280',
        '16:9' => '1536x864',
        '9:16' => '864x1536',
        '21:9' => '1680x720',
    ];

    /**
     * gpt-image-1 models only know three sizes.
     */
    private const LEGACY_RATIOS = ['1:1', '3:2', '2:3'];
    private const LEGACY_MODEL_PREFIX = 'gpt-image-1';

    private const QUALITIES = ['low', 'medium', 'high'];

    /**
     * The bridge sends a single `image` field.
     */
    private const MAX_REFERENCE_IMAGES = 1;
    private const MAX_IMAGES_PER_REQUEST = 10;
    private const MAX_ERROR_LENGTH = 300;
    private const LABEL = 'OpenAI';

    /**
     * @param PlatformFactory $platformFactory
     * @param ResponseReader $responseReader
     * @param ProviderConfigInterface $providerConfig
     * @param Config $config
     * @param ProviderCapabilitiesInterfaceFactory $capabilitiesFactory
     * @param ImageBinaryInterfaceFactory $imageBinaryFactory
     * @param ImageResultInterfaceFactory $imageResultFactory
     */
    public function __construct(
        private readonly PlatformFactory $platformFactory,
        private readonly ResponseReader $responseReader,
        private readonly ProviderConfigInterface $providerConfig,
        private readonly Config $config,
        private readonly ProviderCapabilitiesInterfaceFactory $capabilitiesFactory,
        private readonly ImageBinaryInterfaceFactory $imageBinaryFactory,
        private readonly ImageResultInterfaceFactory $imageResultFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return self::CODE;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return (string) __('OpenAI (GPT Image)');
    }

    /**
     * @inheritDoc
     */
    public function getCapabilities(): ProviderCapabilitiesInterface
    {
        return $this->capabilitiesFactory->create([
            'supportsReference' => true,
            'maxReferenceImages' => self::MAX_REFERENCE_IMAGES,
            'supportedAspectRatios' => $this->getAspectRatios(
                $this->providerConfig->getModel($this, self::DEFAULT_MODEL)
            ),
            'maxImagesPerRequest' => self::MAX_IMAGES_PER_REQUEST,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getAiBaseServiceCodes(): array
    {
        return ['openai'];
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(?int $storeId = null): bool
    {
        return $this->providerConfig->hasApiKey($this, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function generate(ImageRequestInterface $request): ImageResultInterface
    {
        $storeId = $request->getStoreId();
        $references = $request->getReferenceImages();
        if ($references === []) {
            throw new ProviderException(__('OpenAI image editing needs a reference image.'));
        }
        $model = $this->getModel($storeId);
        $timeout = $this->config->getTimeout($storeId);
        $options = $this->buildOptions($request, $model->getName(), $storeId);
        $options['image'] = new Image($references[0]->getContent(), $references[0]->getMimeType());

        try {
            $platform = $this->platformFactory->createOpenAi(
                $this->providerConfig->getApiKey($this, $storeId),
                $timeout
            );
            $result = $platform->invoke($model, $request->getPrompt(), $options);
        } catch (InvalidArgumentException $e) {
            // The bridge validates the key format ("sk-...") before any request.
            throw new ProviderUnavailableException(__('OpenAI rejected the API key: %1', $e->getMessage()), $e);
        } catch (PlatformExceptionInterface $e) {
            throw new ProviderException(__('OpenAI error: %1', $e->getMessage()), $e);
        }

        $response = $this->responseReader->read($result, self::LABEL, $timeout);
        [$images, $revisedPrompt] = $this->parseResponse($result, $response['status'], $response['body']);
        $usage = $response['body']['usage'] ?? [];

        return $this->imageResultFactory->create([
            'images' => $images,
            'model' => $model->getName(),
            'revisedPrompt' => $revisedPrompt,
            'usage' => [
                'images' => count($images),
                'calls' => 1,
                'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
                'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
                'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
            ],
        ]);
    }

    /**
     * Aspect ratios the given model supports.
     *
     * @param string $model
     * @return string[]
     */
    private function getAspectRatios(string $model): array
    {
        return str_starts_with($model, self::LEGACY_MODEL_PREFIX)
            ? self::LEGACY_RATIOS
            : array_keys(self::SIZES);
    }

    /**
     * Configured model, validated so it cannot inject into the request.
     *
     * Built directly instead of read from the bridge catalog, so models newer than the bridge
     * release can be configured.
     *
     * @param int|null $storeId
     * @return ImageModel
     * @throws ProviderUnavailableException
     */
    private function getModel(?int $storeId): ImageModel
    {
        $model = $this->providerConfig->getModel($this, self::DEFAULT_MODEL, $storeId);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $model)) {
            throw new ProviderUnavailableException(__('The OpenAI model name "%1" is not valid.', $model));
        }

        return new ImageModel($model, [Capability::INPUT_TEXT, Capability::INPUT_IMAGE, Capability::OUTPUT_IMAGE]);
    }

    /**
     * Form fields besides the model, prompt and image.
     *
     * @param ImageRequestInterface $request
     * @param string $model
     * @param int|null $storeId
     * @return array<string, string|int>
     * @throws ProviderException
     */
    private function buildOptions(ImageRequestInterface $request, string $model, ?int $storeId): array
    {
        $options = ['n' => max(1, min(self::MAX_IMAGES_PER_REQUEST, $request->getCount()))];

        $aspectRatio = $request->getAspectRatio();
        if ($aspectRatio !== null) {
            if (!in_array($aspectRatio, $this->getAspectRatios($model), true)) {
                throw new ProviderException(
                    __('The OpenAI model %1 does not support the aspect ratio %2.', $model, $aspectRatio)
                );
            }
            $options['size'] = self::SIZES[$aspectRatio];
        }

        $quality = (string) $this->providerConfig->getValue($this, self::FIELD_QUALITY, $storeId);
        if (in_array($quality, self::QUALITIES, true)) {
            $options['quality'] = $quality;
        }

        return $options;
    }

    /**
     * Extract the images and revised prompt, or turn a failure into an exception.
     *
     * @param DeferredResult $result
     * @param int $status
     * @param mixed[] $body
     * @return array{0: ImageBinaryInterface[], 1: string|null}
     * @throws ProviderException
     */
    private function parseResponse(DeferredResult $result, int $status, array $body): array
    {
        if ($status < 200 || $status >= 300) {
            $this->throwHttpError($status, $body);
        }
        if (($body['data'] ?? []) === []) {
            throw new ProviderException(__('OpenAI returned no image.'));
        }

        try {
            $converted = $result->getResult();
        } catch (PlatformExceptionInterface $e) {
            throw new ProviderException(__('OpenAI error: %1', $e->getMessage()), $e);
        }

        $images = [];
        foreach ($converted instanceof MultiPartResult ? $converted->getContent() : [$converted] as $part) {
            if ($part instanceof BinaryResult && $part->getContent() !== '') {
                // The real type is detected from the bytes by the core; png is the API default.
                $images[] = $this->imageBinaryFactory->create([
                    'content' => $part->getContent(),
                    'mimeType' => $part->getMimeType() ?? 'image/png',
                ]);
            }
        }
        if ($images === []) {
            throw new ProviderException(__('OpenAI returned no image.'));
        }

        // The bridge drops revised_prompt, so it is read from the body.
        $revised = [];
        foreach ($body['data'] as $item) {
            if (!empty($item['revised_prompt']) && is_string($item['revised_prompt'])) {
                $revised[] = trim($item['revised_prompt']);
            }
        }

        return [$images, $revised === [] ? null : implode("\n", array_unique($revised))];
    }

    /**
     * Map an HTTP error to an exception with the provider's message.
     *
     * @param int $status
     * @param mixed[] $body
     * @return never
     * @throws ProviderException
     */
    private function throwHttpError(int $status, array $body): never
    {
        $error = is_array($body['error'] ?? null) ? $body['error'] : [];
        $message = mb_substr((string) ($error['message'] ?? ''), 0, self::MAX_ERROR_LENGTH);
        $code = (string) ($error['code'] ?? '');
        $detail = $message !== '' ? $message : sprintf('HTTP %d', $status);

        if ($code === 'moderation_blocked' || ($error['type'] ?? '') === 'image_generation_user_error') {
            throw new ContentRejectedException(
                __('OpenAI refused the request: %1 Try a different prompt or image.', $detail)
            );
        }
        if ($status === 401) {
            throw new ProviderUnavailableException(__('OpenAI rejected the API key: %1', $detail));
        }
        if ($status === 403) {
            throw new ProviderUnavailableException(
                __('OpenAI denied access (the organization may need to be verified for GPT Image): %1', $detail)
            );
        }
        if ($status === 404 || $code === 'model_not_found') {
            throw new ProviderUnavailableException(__('OpenAI does not know the configured model: %1', $detail));
        }
        if ($status === 429) {
            throw new ProviderException(__('OpenAI quota or rate limit reached: %1', $detail));
        }

        throw new ProviderException(__('OpenAI error (HTTP %1): %2', $status, $detail));
    }
}
