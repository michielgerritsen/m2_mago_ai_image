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

        return $this->imageBinaryFactory->create([
            'content' => $content,
            'mimeType' => $info['mime'],
            'width' => (int) $info[0],
            'height' => (int) $info[1],
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
