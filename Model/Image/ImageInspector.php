<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model\Image;

use Magento\Framework\Exception\LocalizedException;
use Orangecat\MagoAiImage\Api\Data\ImageBinaryInterface;
use Orangecat\MagoAiImage\Api\Data\ImageBinaryInterfaceFactory;

/**
 * Detects the real type of image bytes and rejects anything that is not a supported raster image.
 */
class ImageInspector
{
    public const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Largest side accepted, in pixels. Providers return up to 4K; anything far larger could exhaust
     * memory when Magento resizes it for the catalog.
     */
    public const MAX_DIMENSION = 8192;

    /**
     * @param ImageBinaryInterfaceFactory $imageBinaryFactory
     */
    public function __construct(
        private readonly ImageBinaryInterfaceFactory $imageBinaryFactory
    ) {
    }

    /**
     * Inspect bytes and wrap them with their detected MIME type and size.
     *
     * The MIME type a provider declares is ignored: only the bytes count.
     *
     * @param string $content
     * @return ImageBinaryInterface
     * @throws LocalizedException
     */
    public function inspect(string $content): ImageBinaryInterface
    {
        // Sniff the type first: getimagesizefromstring() raises a notice on bytes that are no image.
        $detected = $content === '' ? '' : (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
        $info = isset(self::EXTENSIONS[$detected]) ? getimagesizefromstring($content) : false;
        if ($info === false || !isset(self::EXTENSIONS[$info['mime']])) {
            throw new LocalizedException(__('The image is not a JPEG, PNG or WebP file.'));
        }
        $width = (int) $info[0];
        $height = (int) $info[1];
        if ($width < 1 || $height < 1 || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw new LocalizedException(
                __(
                    'The image is %1x%2 pixels; each side must be between 1 and %3 pixels.',
                    $width,
                    $height,
                    self::MAX_DIMENSION
                )
            );
        }

        return $this->imageBinaryFactory->create([
            'content' => $content,
            'mimeType' => $info['mime'],
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * File extension for a supported MIME type.
     *
     * @param string $mimeType
     * @return string
     * @throws LocalizedException
     */
    public function getExtension(string $mimeType): string
    {
        if (!isset(self::EXTENSIONS[$mimeType])) {
            throw new LocalizedException(__('Unsupported image type "%1".', $mimeType));
        }

        return self::EXTENSIONS[$mimeType];
    }
}
