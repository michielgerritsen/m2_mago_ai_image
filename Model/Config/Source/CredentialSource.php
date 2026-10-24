<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Orangecat\MagoAiImage\Api\ProviderConfigInterface;

/**
 * Where a provider takes its API key from.
 */
class CredentialSource implements OptionSourceInterface
{
    /**
     * @inheritDoc
     *
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => ProviderConfigInterface::CREDENTIAL_SOURCE_OWN, 'label' => __('API key entered here')],
            [
                'value' => ProviderConfigInterface::CREDENTIAL_SOURCE_AI_BASE,
                'label' => __('Service from Mage-OS AI Configuration'),
            ],
        ];
    }
}
