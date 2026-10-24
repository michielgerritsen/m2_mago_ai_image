<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MageOS\AiBase\Api\AiServiceSelectorInterface;

/**
 * MageOS_AiBase rows of the given backends. Provider modules configure `codes` through a virtualType.
 */
class AiBaseService implements OptionSourceInterface
{
    /**
     * @param AiServiceSelectorInterface $aiServiceSelector
     * @param string[] $codes
     */
    public function __construct(
        private readonly AiServiceSelectorInterface $aiServiceSelector,
        private readonly array $codes = []
    ) {
    }

    /**
     * @inheritDoc
     *
     * @return array<int, array{value: string, label: string|\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => __('-- Please Select --')]];
        foreach ($this->codes as $code) {
            foreach ($this->aiServiceSelector->getByCode($code) as $service) {
                // The row's own model is a chat model, irrelevant here: only its credentials are used.
                $options[] = [
                    'value' => $service->getId(),
                    'label' => $service->getLabel() ?? sprintf('%s (%s)', $service->getCode(), $service->getId()),
                ];
            }
        }

        return $options;
    }
}
