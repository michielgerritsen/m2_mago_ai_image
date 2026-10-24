<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model\Image;

use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Orangecat\MagoAiImage\Api\Data\ImageBinaryInterface;

/**
 * Picks and reads the gallery image used as reference.
 */
class ReferenceImageLoader
{
    /**
     * @param Filesystem $filesystem
     * @param MediaConfig $mediaConfig
     * @param ImageInspector $imageInspector
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly MediaConfig $mediaConfig,
        private readonly ImageInspector $imageInspector
    ) {
    }

    /**
     * Gallery entry to use: the given one, else the base image, else the first enabled image.
     *
     * @param ProductInterface $product
     * @param int|null $mediaEntryId
     * @return ProductAttributeMediaGalleryEntryInterface|null Null when the product has no images
     * @throws LocalizedException When the requested entry does not belong to the product
     */
    public function selectEntry(
        ProductInterface $product,
        ?int $mediaEntryId = null
    ): ?ProductAttributeMediaGalleryEntryInterface {
        $images = array_values(array_filter(
            $product->getMediaGalleryEntries() ?? [],
            static fn (ProductAttributeMediaGalleryEntryInterface $entry): bool =>
                $entry->getMediaType() === 'image' && (string) $entry->getFile() !== ''
        ));

        if ($mediaEntryId !== null) {
            foreach ($images as $entry) {
                if ((int) $entry->getId() === $mediaEntryId) {
                    return $entry;
                }
            }
            throw new LocalizedException(
                __('The image %1 does not belong to the product "%2".', $mediaEntryId, $product->getSku())
            );
        }

        foreach ($images as $entry) {
            if (in_array('image', (array) $entry->getTypes(), true)) {
                return $entry;
            }
        }
        foreach ($images as $entry) {
            if (!$entry->isDisabled()) {
                return $entry;
            }
        }

        return $images[0] ?? null;
    }

    /**
     * Read a gallery file and validate it.
     *
     * @param string $file Gallery file as stored (e.g. /m/b/mb01-blue-0.jpg)
     * @param int $maxBytes
     * @return ImageBinaryInterface
     * @throws LocalizedException
     */
    public function read(string $file, int $maxBytes): ImageBinaryInterface
    {
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $path = $this->mediaConfig->getMediaPath($file);
        try {
            if (!$directory->isFile($path)) {
                throw new LocalizedException(__('The product image file "%1" is missing.', $file));
            }
            $size = (int) ($directory->stat($path)['size'] ?? 0);
            if ($size > $maxBytes) {
                throw new LocalizedException(
                    __(
                        'The product image is too large (%1 MB, maximum %2 MB).',
                        round($size / 1048576, 1),
                        round($maxBytes / 1048576, 1)
                    )
                );
            }
            $content = $directory->readFile($path);
        } catch (FileSystemException $e) {
            throw new LocalizedException(__('The product image file "%1" could not be read.', $file), $e);
        }

        return $this->imageInspector->inspect($content);
    }
}
