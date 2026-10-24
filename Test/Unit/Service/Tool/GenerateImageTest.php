<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Test\Unit\Service\Tool;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\LocalizedException;
use Orangecat\MagoAiImage\Api\Data\GenerationInterface;
use Orangecat\MagoAiImage\Api\ImageGeneratorInterface;
use Orangecat\MagoAiImage\Api\ImageProviderInterface;
use Orangecat\MagoAiImage\Api\ProviderPoolInterface;
use Orangecat\MagoAiImage\Exception\ProviderUnavailableException;
use Orangecat\MagoAiImage\Model\Config;
use Orangecat\MagoAiImage\Model\Data\ProviderCapabilities;
use Orangecat\MagoAiImage\Model\Availability;
use Orangecat\MagoAiImage\Model\ProductAccess;
use Orangecat\MagoAiImage\Model\ProductImageReader;
use Orangecat\MagoAiImage\Service\Tool\GenerateImage;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class GenerateImageTest extends TestCase
{
    /**
     * @var ImageGeneratorInterface|MockObject
     */
    private $imageGenerator;

    /**
     * @var ProductImageReader|MockObject
     */
    private $productImageReader;

    /**
     * @var ProviderPoolInterface|MockObject
     */
    private $providerPool;

    /**
     * @var Availability|MockObject
     */
    private $availability;

    /**
     * @var UserContextInterface|MockObject
     */
    private $userContext;

    /**
     * @var GenerateImage
     */
    private GenerateImage $tool;

    /**
     * Whether the admin may manage products.
     *
     * @var bool
     */
    private bool $canManageProducts = true;

    protected function setUp(): void
    {
        $this->imageGenerator = $this->createMock(ImageGeneratorInterface::class);
        $this->productImageReader = $this->createMock(ProductImageReader::class);
        $this->providerPool = $this->createMock(ProviderPoolInterface::class);
        $config = $this->createMock(Config::class);
        $config->method('getMaxImages')->willReturn(4);
        $this->availability = $this->createMock(Availability::class);
        $this->userContext = $this->createMock(UserContextInterface::class);

        $this->tool = new GenerateImage(
            $this->imageGenerator,
            $this->productImageReader,
            $this->providerPool,
            $config,
            $this->availability,
            $this->userContext,
            new ProductAccess($this->authorization())
        );
    }

    public function testRefusesAdminWhoCannotManageProducts(): void
    {
        $this->canManageProducts = false;
        $this->imageGenerator->expects($this->never())->method('generateForProduct');

        $this->assertSame(
            ['error' => 'You are not allowed to manage products.'],
            $this->tool->findRefusal(['sku' => 'WJ12', 'prompt' => 'p'])
        );
        $this->assertSame(
            ['error' => 'You are not allowed to manage products.'],
            $this->tool->execute(['sku' => 'WJ12', 'prompt' => 'p'])
        );
    }

    public function testExecutePassesOptionsAndAdminUser(): void
    {
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_ADMIN);
        $this->userContext->method('getUserId')->willReturn(5);

        $generation = $this->createMock(GenerationInterface::class);
        $generation->method('getProductId')->willReturn(1396);
        $generation->method('getProvider')->willReturn('gemini');

        $this->imageGenerator->expects($this->once())
            ->method('generateForProduct')
            ->with(
                'WJ12',
                'A person in the city',
                [
                    ImageGeneratorInterface::OPTION_MEDIA_ENTRY_ID => 2386,
                    ImageGeneratorInterface::OPTION_ASPECT_RATIO => '3:4',
                    ImageGeneratorInterface::OPTION_COUNT => 2,
                ],
                5
            )
            ->willReturn([$generation]);
        $this->productImageReader->method('describeGeneration')
            ->willReturn(['generation_id' => 9, 'preview_url' => 'https://example.test/media/p.png']);

        $result = $this->tool->execute([
            'sku' => ' WJ12 ',
            'prompt' => 'A person in the city',
            'media_entry_id' => '2386',
            'aspect_ratio' => '3:4',
            'count' => 2,
        ]);

        $this->assertSame('WJ12', $result['sku']);
        $this->assertSame(1396, $result['product_id']);
        $this->assertSame('gemini', $result['provider']);
        $this->assertSame(
            [['generation_id' => 9, 'preview_url' => 'https://example.test/media/p.png']],
            $result['previews']
        );
    }

    public function testExecuteReturnsErrorInsteadOfThrowing(): void
    {
        $this->imageGenerator->method('generateForProduct')
            ->willThrowException(new LocalizedException(__('Quota exceeded.')));

        $this->assertSame(
            ['error' => 'Quota exceeded.'],
            $this->tool->execute(['sku' => 'WJ12', 'prompt' => 'x'])
        );
    }

    public function testFindRefusalWhenUnavailable(): void
    {
        $this->availability->method('getUnavailableReason')->willReturn('AI image generation is disabled.');

        $this->assertSame(
            ['error' => 'AI image generation is disabled.'],
            $this->tool->findRefusal(['sku' => 'WJ12', 'prompt' => 'x'])
        );
    }

    public function testFindRefusalOnEmptyPrompt(): void
    {
        $this->assertSame(['error' => 'The prompt is empty.'], $this->tool->findRefusal(['sku' => 'WJ12']));
    }

    public function testFindRefusalOnUnknownProduct(): void
    {
        $this->productImageReader->method('getProduct')
            ->willThrowException(new LocalizedException(__('The product "%1" does not exist.', 'NOPE')));

        $this->assertSame(
            ['error' => 'The product "NOPE" does not exist.'],
            $this->tool->findRefusal(['sku' => 'NOPE', 'prompt' => 'x'])
        );
    }

    public function testFindRefusalAcceptsValidCall(): void
    {
        $this->productImageReader->method('getProduct')->willReturn($this->createMock(ProductInterface::class));

        $this->assertNull($this->tool->findRefusal(['sku' => 'WJ12', 'prompt' => 'x']));
    }

    public function testSchemaUsesProviderCapabilities(): void
    {
        $provider = $this->createMock(ImageProviderInterface::class);
        $provider->method('getCapabilities')->willReturn(new ProviderCapabilities(true, 14, ['1:1', '3:4'], 8));
        $this->providerPool->method('getDefault')->willReturn($provider);

        $schema = $this->tool->getParameterSchema();

        $this->assertSame(['1:1', '3:4'], $schema['properties']['aspect_ratio']['enum']);
        $this->assertSame(4, $schema['properties']['count']['maximum']);
        $this->assertSame(['sku', 'prompt'], $schema['required']);
    }

    public function testSchemaWithoutProvider(): void
    {
        $this->providerPool->method('getDefault')
            ->willThrowException(new ProviderUnavailableException(__('No image provider is selected.')));

        $schema = $this->tool->getParameterSchema();

        $this->assertArrayNotHasKey('enum', $schema['properties']['aspect_ratio']);
        $this->assertSame(1, $schema['properties']['count']['maximum']);
    }

    /**
     * Authorization that follows $canManageProducts.
     *
     * @return AuthorizationInterface
     */
    private function authorization(): AuthorizationInterface
    {
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturnCallback(
            fn (string $resource): bool => $resource !== ProductAccess::ACL_PRODUCTS || $this->canManageProducts
        );

        return $authorization;
    }
}
