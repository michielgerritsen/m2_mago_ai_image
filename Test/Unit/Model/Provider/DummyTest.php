<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Test\Unit\Model\Provider;

use Magento\Framework\App\State;
use Orangecat\MagoAiImage\Api\Data\ImageBinaryInterfaceFactory;
use Orangecat\MagoAiImage\Api\Data\ImageResultInterfaceFactory;
use Orangecat\MagoAiImage\Api\Data\ProviderCapabilitiesInterfaceFactory;
use Orangecat\MagoAiImage\Exception\ProviderException;
use Orangecat\MagoAiImage\Model\Data\ImageBinary;
use Orangecat\MagoAiImage\Model\Data\ImageRequest;
use Orangecat\MagoAiImage\Model\Data\ImageResult;
use Orangecat\MagoAiImage\Model\Data\ProviderCapabilities;
use Orangecat\MagoAiImage\Model\Provider\Dummy;
use Orangecat\MagoAiImage\Test\Unit\Model\Image\ImageInspectorTest;
use PHPUnit\Framework\TestCase;

class DummyTest extends TestCase
{
    public function testGeneratesPngsWithRequestedAspectRatio(): void
    {
        $dummy = $this->dummy(State::MODE_DEVELOPER);
        $reference = new ImageBinary(ImageInspectorTest::png(40, 40), 'image/png', 40, 40);

        $result = $dummy->generate(new ImageRequest('backpack in the city', [$reference], '3:4', 2));

        $this->assertCount(2, $result->getImages());
        $info = getimagesizefromstring($result->getImages()[0]->getContent());
        $this->assertNotFalse($info);
        $this->assertSame('image/png', $info['mime']);
        $this->assertSame([384, 512], [$info[0], $info[1]]);
        $this->assertSame('dummy-gd', $result->getModel());
    }

    public function testUnavailableOutsideDeveloperMode(): void
    {
        $dummy = $this->dummy(State::MODE_PRODUCTION);

        $this->assertFalse($dummy->isAvailable());
        $this->expectException(ProviderException::class);
        $dummy->generate(new ImageRequest('prompt'));
    }

    private function dummy(string $mode): Dummy
    {
        $state = $this->createStub(State::class);
        $state->method('getMode')->willReturn($mode);

        $capabilities = $this->createStub(ProviderCapabilitiesInterfaceFactory::class);
        $capabilities->method('create')->willReturnCallback(
            static fn (array $data): ProviderCapabilities => new ProviderCapabilities(...$data)
        );
        $binaries = $this->createStub(ImageBinaryInterfaceFactory::class);
        $binaries->method('create')->willReturnCallback(
            static fn (array $data): ImageBinary => new ImageBinary(...$data)
        );
        $results = $this->createStub(ImageResultInterfaceFactory::class);
        $results->method('create')->willReturnCallback(
            static fn (array $data): ImageResult => new ImageResult(...$data)
        );

        return new Dummy($state, $capabilities, $binaries, $results);
    }
}
