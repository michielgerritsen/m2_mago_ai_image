<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use MageOS\AiBase\Api\AiServiceSelectorInterface;
use Orangecat\MagoAiImage\Api\ImageProviderInterface;
use Orangecat\MagoAiImage\Api\ProviderConfigInterface;
use Orangecat\MagoAiImage\Exception\ProviderUnavailableException;

/**
 * Reads `mago_ai_image/<code>/*` and resolves the provider's API key.
 *
 * The own key is saved through Magento's Encrypted backend model, so it is decrypted here. A
 * MageOS_AiBase row is handed over already decrypted by AiServiceSelectorInterface.
 */
class ProviderConfig implements ProviderConfigInterface
{
    private const XML_PATH_PATTERN = 'mago_ai_image/%s/%s';
    private const AI_BASE_API_KEY = 'api_key';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     * @param AiServiceSelectorInterface $aiServiceSelector
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly AiServiceSelectorInterface $aiServiceSelector
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCredentialSource(ImageProviderInterface $provider, ?int $storeId = null): string
    {
        $source = $this->read($provider, self::FIELD_CREDENTIAL_SOURCE, $storeId);

        return $source === self::CREDENTIAL_SOURCE_AI_BASE && $provider->getAiBaseServiceCodes() !== []
            ? self::CREDENTIAL_SOURCE_AI_BASE
            : self::CREDENTIAL_SOURCE_OWN;
    }

    /**
     * @inheritDoc
     */
    public function hasApiKey(ImageProviderInterface $provider, ?int $storeId = null): bool
    {
        try {
            return $this->getApiKey($provider, $storeId) !== '';
        } catch (ProviderUnavailableException) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function getApiKey(ImageProviderInterface $provider, ?int $storeId = null): string
    {
        $apiKey = $this->getCredentialSource($provider, $storeId) === self::CREDENTIAL_SOURCE_AI_BASE
            ? $this->getAiBaseApiKey($provider, $storeId)
            : $this->getOwnApiKey($provider, $storeId);

        if ($apiKey === '') {
            throw new ProviderUnavailableException(
                __('No API key is configured for the image provider "%1".', $provider->getLabel())
            );
        }

        return $apiKey;
    }

    /**
     * @inheritDoc
     */
    public function getModel(ImageProviderInterface $provider, string $default = '', ?int $storeId = null): string
    {
        $model = $this->read($provider, self::FIELD_MODEL, $storeId);

        return $model !== '' ? $model : $default;
    }

    /**
     * @inheritDoc
     */
    public function getValue(ImageProviderInterface $provider, string $field, ?int $storeId = null): ?string
    {
        if ($field === self::FIELD_API_KEY) {
            // The key never leaves this class undecrypted or through the generic reader.
            return null;
        }
        $value = $this->read($provider, $field, $storeId);

        return $value === '' ? null : $value;
    }

    /**
     * Decrypt the provider's own key.
     *
     * @param ImageProviderInterface $provider
     * @param int|null $storeId
     * @return string
     */
    private function getOwnApiKey(ImageProviderInterface $provider, ?int $storeId): string
    {
        $encrypted = $this->read($provider, self::FIELD_API_KEY, $storeId);

        return $encrypted === '' ? '' : trim((string) $this->encryptor->decrypt($encrypted));
    }

    /**
     * Take the key of the selected MageOS_AiBase row, if it is of an accepted backend.
     *
     * @param ImageProviderInterface $provider
     * @param int|null $storeId
     * @return string
     * @throws ProviderUnavailableException
     */
    private function getAiBaseApiKey(ImageProviderInterface $provider, ?int $storeId): string
    {
        $serviceId = $this->read($provider, self::FIELD_AI_SERVICE, $storeId);
        $service = $serviceId === '' ? null : $this->aiServiceSelector->getById($serviceId);
        if ($service === null) {
            throw new ProviderUnavailableException(
                __(
                    'The AI service selected for the image provider "%1" no longer exists or is disabled.',
                    $provider->getLabel()
                )
            );
        }
        if (!in_array($service->getCode(), $provider->getAiBaseServiceCodes(), true)) {
            throw new ProviderUnavailableException(
                __(
                    'The AI service selected for the image provider "%1" is not a supported backend.',
                    $provider->getLabel()
                )
            );
        }
        $apiKey = $service->getConfiguration()[self::AI_BASE_API_KEY] ?? '';

        return is_string($apiKey) ? trim($apiKey) : '';
    }

    /**
     * Read a provider field.
     *
     * @param ImageProviderInterface $provider
     * @param string $field
     * @param int|null $storeId
     * @return string
     */
    private function read(ImageProviderInterface $provider, string $field, ?int $storeId): string
    {
        $value = $this->scopeConfig->getValue(
            sprintf(self::XML_PATH_PATTERN, $provider->getCode(), $field),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
