<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Test\Unit\Model\Image;

use Magento\Framework\Exception\LocalizedException;
use Orangecat\MagoAiImage\Api\Data\ImageBinaryInterfaceFactory;
use Orangecat\MagoAiImage\Model\Data\ImageBinary;
use Orangecat\MagoAiImage\Model\Image\ImageInspector;
use PHPUnit\Framework\TestCase;

class ImageInspectorTest extends TestCase
{
    /**
     * @var ImageInspector
     */
    private ImageInspector $inspector;

    protected function setUp(): void
    {
        $factory = $this->createStub(ImageBinaryInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(
            static fn (array $data): ImageBinary => new ImageBinary(...$data)
        );
        $this->inspector = new ImageInspector($factory);
    }

    public function testDetectsPngFromBytesIgnoringDeclaredType(): void
    {
        $image = $this->inspector->inspect(self::png(3, 2));

        $this->assertSame('image/png', $image->getMimeType());
        $this->assertSame(3, $image->getWidth());
        $this->assertSame(2, $image->getHeight());
        $this->assertSame('png', $this->inspector->getExtension($image->getMimeType()));
    }

    public function testRejectsNonImage(): void
    {
        $this->expectException(LocalizedException::class);
        $this->inspector->inspect('<?php echo "not an image";');
    }

    public function testRejectsUnsupportedFormat(): void
    {
        $gd = imagecreatetruecolor(2, 2);
        ob_start();
        imagegif($gd);
        $gif = (string) ob_get_clean();

        $this->expectException(LocalizedException::class);
        $this->inspector->inspect($gif);
    }

    public static function png(int $width, int $height): string
    {
        $gd = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($gd);

        return (string) ob_get_clean();
    }
}
