<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Generation resource model.
 */
class Generation extends AbstractDb
{
    public const TABLE = 'mago_ai_image_generation';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE, 'generation_id');
    }
}
