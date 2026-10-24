<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Output quality of the GPT Image models. Empty lets the model choose (auto).
 */
class OpenAiQuality implements OptionSourceInterface
{
    /**
     * @inheritDoc
     *
     * @return array<int, array{value: string, label: string|\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('Automatic')],
            ['value' => 'low', 'label' => __('Low')],
            ['value' => 'medium', 'label' => __('Medium')],
            ['value' => 'high', 'label' => __('High')],
        ];
    }
}
