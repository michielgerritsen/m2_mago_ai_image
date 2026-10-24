<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model;

use Magento\Framework\Model\AbstractModel;
use Orangecat\MagoAiImage\Api\Data\GenerationInterface;
use Orangecat\MagoAiImage\Model\ResourceModel\Generation as GenerationResource;

/**
 * Generation model.
 */
class Generation extends AbstractModel implements GenerationInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'mago_ai_image_generation';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(GenerationResource::class);
    }

    /**
     * @inheritDoc
     */
    public function getGenerationId(): ?int
    {
        $id = $this->getData(self::GENERATION_ID);

        return $id === null ? null : (int) $id;
    }

    /**
     * @inheritDoc
     */
    public function getProductId(): int
    {
        return (int) $this->getData(self::PRODUCT_ID);
    }

    /**
     * @inheritDoc
     */
    public function setProductId(int $productId): GenerationInterface
    {
        return $this->setData(self::PRODUCT_ID, $productId);
    }

    /**
     * @inheritDoc
     */
    public function getSourceFile(): ?string
    {
        $value = $this->getData(self::SOURCE_FILE);

        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritDoc
     */
    public function setSourceFile(?string $sourceFile): GenerationInterface
    {
        return $this->setData(self::SOURCE_FILE, $sourceFile);
    }

    /**
     * @inheritDoc
     */
    public function getProvider(): string
    {
        return (string) $this->getData(self::PROVIDER);
    }

    /**
     * @inheritDoc
     */
    public function setProvider(string $provider): GenerationInterface
    {
        return $this->setData(self::PROVIDER, $provider);
    }

    /**
     * @inheritDoc
     */
    public function getModel(): string
    {
        return (string) $this->getData(self::MODEL);
    }

    /**
     * @inheritDoc
     */
    public function setModel(string $model): GenerationInterface
    {
        return $this->setData(self::MODEL, $model);
    }

    /**
     * @inheritDoc
     */
    public function getPrompt(): string
    {
        return (string) $this->getData(self::PROMPT);
    }

    /**
     * @inheritDoc
     */
    public function setPrompt(string $prompt): GenerationInterface
    {
        return $this->setData(self::PROMPT, $prompt);
    }

    /**
     * @inheritDoc
     */
    public function getRevisedPrompt(): ?string
    {
        $value = $this->getData(self::REVISED_PROMPT);

        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritDoc
     */
    public function setRevisedPrompt(?string $revisedPrompt): GenerationInterface
    {
        return $this->setData(self::REVISED_PROMPT, $revisedPrompt);
    }

    /**
     * @inheritDoc
     */
    public function getFile(): string
    {
        return (string) $this->getData(self::FILE);
    }

    /**
     * @inheritDoc
     */
    public function setFile(string $file): GenerationInterface
    {
        return $this->setData(self::FILE, $file);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): string
    {
        return (string) $this->getData(self::STATUS);
    }

    /**
     * @inheritDoc
     */
    public function setStatus(string $status): GenerationInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritDoc
     */
    public function getMediaGalleryEntryId(): ?int
    {
        $value = $this->getData(self::MEDIA_GALLERY_ENTRY_ID);

        return $value === null ? null : (int) $value;
    }

    /**
     * @inheritDoc
     */
    public function setMediaGalleryEntryId(?int $entryId): GenerationInterface
    {
        return $this->setData(self::MEDIA_GALLERY_ENTRY_ID, $entryId);
    }

    /**
     * @inheritDoc
     */
    public function getAdminUserId(): ?int
    {
        $value = $this->getData(self::ADMIN_USER_ID);

        return $value === null ? null : (int) $value;
    }

    /**
     * @inheritDoc
     */
    public function setAdminUserId(?int $adminUserId): GenerationInterface
    {
        return $this->setData(self::ADMIN_USER_ID, $adminUserId);
    }

    /**
     * @inheritDoc
     */
    public function getUsage(): array
    {
        $value = $this->getData(self::USAGE);
        if (is_array($value)) {
            return $value;
        }
        $decoded = is_string($value) && $value !== '' ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @inheritDoc
     */
    public function setUsage(array $usage): GenerationInterface
    {
        return $this->setData(self::USAGE, $usage === [] ? null : (string) json_encode($usage));
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        $value = $this->getData(self::CREATED_AT);

        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): ?string
    {
        $value = $this->getData(self::UPDATED_AT);

        return $value === null ? null : (string) $value;
    }
}
