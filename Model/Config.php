<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * General module configuration reader (store scope).
 */
class Config
{
    private const XML_PATH_ENABLED = 'mago_ai_image/general/enabled';
    private const XML_PATH_PROVIDER = 'mago_ai_image/general/provider';
    private const XML_PATH_TIMEOUT = 'mago_ai_image/general/timeout';
    private const XML_PATH_MAX_IMAGES = 'mago_ai_image/general/max_images';
    private const XML_PATH_MAX_REFERENCE_MB = 'mago_ai_image/general/max_reference_mb';
    private const XML_PATH_PREVIEW_RETENTION_DAYS = 'mago_ai_image/general/preview_retention_days';

    private const DEFAULT_TIMEOUT = 90;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Whether image generation is enabled.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Code of the default provider.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getProviderCode(?int $storeId = null): string
    {
        return trim((string) $this->getValue(self::XML_PATH_PROVIDER, $storeId));
    }

    /**
     * Provider request timeout in seconds.
     *
     * @param int|null $storeId
     * @return int
     */
    public function getTimeout(?int $storeId = null): int
    {
        $timeout = (int) $this->getValue(self::XML_PATH_TIMEOUT, $storeId);

        return $timeout > 0 ? $timeout : self::DEFAULT_TIMEOUT;
    }

    /**
     * Maximum images per generation request.
     *
     * @param int|null $storeId
     * @return int
     */
    public function getMaxImages(?int $storeId = null): int
    {
        return max(1, (int) $this->getValue(self::XML_PATH_MAX_IMAGES, $storeId));
    }

    /**
     * Maximum size of the reference image in bytes.
     *
     * @param int|null $storeId
     * @return int
     */
    public function getMaxReferenceBytes(?int $storeId = null): int
    {
        return max(1, (int) $this->getValue(self::XML_PATH_MAX_REFERENCE_MB, $storeId)) * 1024 * 1024;
    }

    /**
     * Days an unattached preview is kept.
     *
     * @return int
     */
    public function getPreviewRetentionDays(): int
    {
        return max(1, (int) $this->getValue(self::XML_PATH_PREVIEW_RETENTION_DAYS, null));
    }

    /**
     * Read a raw value.
     *
     * @param string $path
     * @param int|null $storeId
     * @return mixed
     */
    private function getValue(string $path, ?int $storeId): mixed
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
