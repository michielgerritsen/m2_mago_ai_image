<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model\ResourceModel\Generation;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Orangecat\MagoAiImage\Model\Generation;
use Orangecat\MagoAiImage\Model\ResourceModel\Generation as GenerationResource;

/**
 * Generation collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'generation_id';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(Generation::class, GenerationResource::class);
    }
}
