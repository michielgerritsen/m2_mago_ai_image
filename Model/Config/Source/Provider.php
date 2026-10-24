<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Orangecat\MagoAiImage\Api\ProviderPoolInterface;

/**
 * Registered image providers.
 */
class Provider implements OptionSourceInterface
{
    /**
     * @param ProviderPoolInterface $providerPool
     */
    public function __construct(
        private readonly ProviderPoolInterface $providerPool
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
        foreach ($this->providerPool->getAll() as $code => $provider) {
            $options[] = ['value' => $code, 'label' => $provider->getLabel()];
        }

        return $options;
    }
}
