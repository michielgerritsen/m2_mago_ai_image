<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Test\Unit\Service\Tool;

use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductAttributeMediaGalleryManagementInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Orangecat\MagoAiImage\Api\Data\GenerationInterface;
use Orangecat\MagoAiImage\Api\GenerationManagementInterface;
use Orangecat\MagoAiImage\Api\GenerationRepositoryInterface;
use Orangecat\MagoAiImage\Model\Availability;
use Orangecat\MagoAiImage\Model\ProductAccess;
use Orangecat\MagoAiImage\Service\Tool\AttachImage;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class AttachImageTest extends TestCase
{
    /**
     * @var GenerationManagementInterface|MockObject
     */
    private $generationManagement;

    /**
     * @var GenerationRepositoryInterface|MockObject
     */
    private $generationRepository;

    /**
     * @var ProductRepositoryInterface|MockObject
     */
    private $productRepository;

    /**
     * @var ProductAttributeMediaGalleryManagementInterface|MockObject
     */
    private $galleryManagement;

    /**
     * @var AttachImage
     */
    private AttachImage $tool;

    /**
     * Whether the admin may manage products.
     *
     * @var bool
     */
    private bool $canManageProducts = true;

    protected function setUp(): void
    {
        $this->generationManagement = $this->createMock(GenerationManagementInterface::class);
        $this->generationRepository = $this->createMock(GenerationRepositoryInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->galleryManagement = $this->createMock(ProductAttributeMediaGalleryManagementInterface::class);
        $mediaConfig = $this->createMock(MediaConfig::class);
        $mediaConfig->method('getMediaUrl')
            ->willReturnCallback(
                static fn (string $file): string => 'https://example.test/media/catalog/product' . $file
            );
        $backendUrl = $this->createMock(BackendUrl::class);
        $backendUrl->method('getUrl')->willReturn('https://example.test/admin/catalog/product/edit/id/1396/');

        $this->tool = new AttachImage(
            $this->generationManagement,
            $this->generationRepository,
            $this->productRepository,
            $this->galleryManagement,
            $mediaConfig,
            $backendUrl,
            $this->createMock(Availability::class),
            new ProductAccess($this->authorization())
        );
    }

    public function testRefusesAdminWhoCannotManageProducts(): void
    {
        $this->canManageProducts = false;
        $this->generationManagement->expects($this->never())->method('attach');

        $this->assertSame(
            ['error' => 'You are not allowed to manage products.'],
            $this->tool->findRefusal(['generation_id' => 8])
        );
        $this->assertSame(
            ['error' => 'You are not allowed to manage products.'],
            $this->tool->execute(['generation_id' => 8])
        );
    }

    public function testFindRefusalOnUnknownGeneration(): void
    {
        $this->generationRepository->method('getById')
            ->willThrowException(new NoSuchEntityException(__('missing')));

        $this->assertSame(
            ['error' => 'The generated image 42 does not exist.'],
            $this->tool->findRefusal(['generation_id' => 42])
        );
    }

    public function testFindRefusalOnAttachedGeneration(): void
    {
        $this->generationRepository->method('getById')
            ->willReturn($this->generation(GenerationInterface::STATUS_ATTACHED));

        $this->assertSame(
            ['error' => 'The generated image 7 is already attached.'],
            $this->tool->findRefusal(['generation_id' => 7])
        );
    }

    public function testFindRefusalOnUnknownRole(): void
    {
        $this->generationRepository->method('getById')
            ->willReturn($this->generation(GenerationInterface::STATUS_PREVIEW));

        $this->assertSame(
            ['error' => 'Unknown image role(s): hero.'],
            $this->tool->findRefusal(['generation_id' => 7, 'roles' => ['image', 'hero']])
        );
    }

    public function testFindRefusalAcceptsPreview(): void
    {
        $this->generationRepository->method('getById')
            ->willReturn($this->generation(GenerationInterface::STATUS_PREVIEW));

        $this->assertNull($this->tool->findRefusal(['generation_id' => 7, 'roles' => ['image']]));
    }

    public function testExecuteAttachesAndDescribesTheGalleryEntry(): void
    {
        $attached = $this->generation(GenerationInterface::STATUS_ATTACHED);
        $attached->method('getMediaGalleryEntryId')->willReturn(3418);
        $this->generationManagement->expects($this->once())
            ->method('attach')
            ->with(7, ['image', 'small_image'], 'City walk')
            ->willReturn($attached);

        $product = $this->createMock(ProductInterface::class);
        $product->method('getSku')->willReturn('WJ12');
        $product->method('getId')->willReturn(1396);
        $this->productRepository->method('getById')->with(1396)->willReturn($product);

        $entry = $this->createMock(ProductAttributeMediaGalleryEntryInterface::class);
        $entry->method('getFile')->willReturn('/w/j/wj12-ai-7.png');
        $this->galleryManagement->method('get')->with('WJ12', 3418)->willReturn($entry);

        $result = $this->tool->execute([
            'generation_id' => 7,
            'roles' => ['image', 'small_image'],
            'label' => 'City walk',
        ]);

        $this->assertSame([
            'generation_id' => 7,
            'status' => GenerationInterface::STATUS_ATTACHED,
            'sku' => 'WJ12',
            'media_entry_id' => 3418,
            'roles' => ['image', 'small_image'],
            'label' => 'City walk',
            'url' => 'https://example.test/media/catalog/product/w/j/wj12-ai-7.png',
            'admin_url' => 'https://example.test/admin/catalog/product/edit/id/1396/',
        ], $result);
    }

    public function testExecuteReturnsErrorInsteadOfThrowing(): void
    {
        $this->generationManagement->method('attach')
            ->willThrowException(new LocalizedException(__('The generated image %1 is already %2.', 7, 'attached')));

        $this->assertSame(
            ['error' => 'The generated image 7 is already attached.'],
            $this->tool->execute(['generation_id' => 7])
        );
    }

    /**
     * @param string $status
     * @return GenerationInterface|MockObject
     */
    private function generation(string $status)
    {
        $generation = $this->createMock(GenerationInterface::class);
        $generation->method('getGenerationId')->willReturn(7);
        $generation->method('getProductId')->willReturn(1396);
        $generation->method('getStatus')->willReturn($status);

        return $generation;
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
