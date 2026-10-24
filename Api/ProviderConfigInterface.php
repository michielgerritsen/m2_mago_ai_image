<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Api;

use Orangecat\MagoAiImage\Exception\ProviderUnavailableException;

/**
 * Per-provider configuration under `mago_ai_image/<code>/`, including credentials.
 *
 * Providers read their settings only through this interface: the API key is stored encrypted
 * (or taken from a MageOS_AiBase row) and is decrypted here, never by the provider itself.
 *
 * @api
 */
interface ProviderConfigInterface
{
    public const CREDENTIAL_SOURCE_OWN = 'own';
    public const CREDENTIAL_SOURCE_AI_BASE = 'ai_base';

    public const FIELD_CREDENTIAL_SOURCE = 'credential_source';
    public const FIELD_API_KEY = 'api_key';
    public const FIELD_AI_SERVICE = 'ai_service';
    public const FIELD_MODEL = 'model';

    /**
     * Where the provider's credentials come from (own / ai_base).
     *
     * @param ImageProviderInterface $provider
     * @param int|null $storeId
     * @return string
     */
    public function getCredentialSource(ImageProviderInterface $provider, ?int $storeId = null): string;

    /**
     * Whether an API key can be resolved, without throwing.
     *
     * @param ImageProviderInterface $provider
     * @param int|null $storeId
     * @return bool
     */
    public function hasApiKey(ImageProviderInterface $provider, ?int $storeId = null): bool;

    /**
     * The decrypted API key.
     *
     * @param ImageProviderInterface $provider
     * @param int|null $storeId
     * @return string
     * @throws ProviderUnavailableException When no usable key is configured
     */
    public function getApiKey(ImageProviderInterface $provider, ?int $storeId = null): string;

    /**
     * Configured model, or the given default when empty.
     *
     * @param ImageProviderInterface $provider
     * @param string $default
     * @param int|null $storeId
     * @return string
     */
    public function getModel(ImageProviderInterface $provider, string $default = '', ?int $storeId = null): string;

    /**
     * Any other non-secret field of the provider group.
     *
     * @param ImageProviderInterface $provider
     * @param string $field
     * @param int|null $storeId
     * @return string|null
     */
    public function getValue(ImageProviderInterface $provider, string $field, ?int $storeId = null): ?string;
}
