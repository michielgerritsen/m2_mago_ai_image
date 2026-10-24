<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Api\Data;

/**
 * A stored generation: one generated image, as a preview or attached to a product.
 *
 * @api
 */
interface GenerationInterface
{
    public const GENERATION_ID = 'generation_id';
    public const PRODUCT_ID = 'product_id';
    public const SOURCE_FILE = 'source_file';
    public const PROVIDER = 'provider';
    public const MODEL = 'model';
    public const PROMPT = 'prompt';
    public const REVISED_PROMPT = 'revised_prompt';
    public const FILE = 'file';
    public const STATUS = 'status';
    public const MEDIA_GALLERY_ENTRY_ID = 'media_gallery_entry_id';
    public const ADMIN_USER_ID = 'admin_user_id';
    public const USAGE = 'usage';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const STATUS_PREVIEW = 'preview';
    public const STATUS_ATTACHED = 'attached';
    public const STATUS_DISCARDED = 'discarded';

    /**
     * Get generation ID.
     *
     * @return int|null
     */
    public function getGenerationId(): ?int;

    /**
     * Get product ID.
     *
     * @return int
     */
    public function getProductId(): int;

    /**
     * Set product ID.
     *
     * @param int $productId
     * @return $this
     */
    public function setProductId(int $productId): self;

    /**
     * Gallery file used as reference (relative to catalog/product), if any.
     *
     * @return string|null
     */
    public function getSourceFile(): ?string;

    /**
     * Set source file.
     *
     * @param string|null $sourceFile
     * @return $this
     */
    public function setSourceFile(?string $sourceFile): self;

    /**
     * Get provider code.
     *
     * @return string
     */
    public function getProvider(): string;

    /**
     * Set provider code.
     *
     * @param string $provider
     * @return $this
     */
    public function setProvider(string $provider): self;

    /**
     * Get model.
     *
     * @return string
     */
    public function getModel(): string;

    /**
     * Set model.
     *
     * @param string $model
     * @return $this
     */
    public function setModel(string $model): self;

    /**
     * Get prompt.
     *
     * @return string
     */
    public function getPrompt(): string;

    /**
     * Set prompt.
     *
     * @param string $prompt
     * @return $this
     */
    public function setPrompt(string $prompt): self;

    /**
     * Get revised prompt.
     *
     * @return string|null
     */
    public function getRevisedPrompt(): ?string;

    /**
     * Set revised prompt.
     *
     * @param string|null $revisedPrompt
     * @return $this
     */
    public function setRevisedPrompt(?string $revisedPrompt): self;

    /**
     * Preview file, relative to pub/media; empty once the file was removed.
     *
     * @return string
     */
    public function getFile(): string;

    /**
     * Set preview file.
     *
     * @param string $file
     * @return $this
     */
    public function setFile(string $file): self;

    /**
     * Get status (preview, attached, discarded).
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Set status.
     *
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self;

    /**
     * Media gallery value ID once attached.
     *
     * @return int|null
     */
    public function getMediaGalleryEntryId(): ?int;

    /**
     * Set media gallery value ID.
     *
     * @param int|null $entryId
     * @return $this
     */
    public function setMediaGalleryEntryId(?int $entryId): self;

    /**
     * Admin user who requested the generation.
     *
     * @return int|null
     */
    public function getAdminUserId(): ?int;

    /**
     * Set admin user ID.
     *
     * @param int|null $adminUserId
     * @return $this
     */
    public function setAdminUserId(?int $adminUserId): self;

    /**
     * Provider usage metadata.
     *
     * @return array<string, mixed>
     */
    public function getUsage(): array;

    /**
     * Set provider usage metadata.
     *
     * @param mixed[] $usage
     * @return $this
     */
    public function setUsage(array $usage): self;

    /**
     * Get creation time.
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Get update time.
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;
}
