<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Output resolutions of the Gemini image models. Empty keeps the model default.
 */
class GeminiImageSize implements OptionSourceInterface
{
    /**
     * @inheritDoc
     *
     * @return array<int, array{value: string, label: string|\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('Model default')],
            ['value' => '1K', 'label' => '1K'],
            ['value' => '2K', 'label' => '2K'],
            ['value' => '4K', 'label' => '4K'],
        ];
    }
}
