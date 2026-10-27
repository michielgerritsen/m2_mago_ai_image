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

    /**
     * Which of these preview paths a generation row still points to.
     *
     * @param string[] $files
     * @return string[]
     */
    public function getReferencedFiles(array $files): array
    {
        if ($files === []) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->distinct()
            ->from($this->getMainTable(), ['file'])
            ->where('file IN (?)', $files);

        return array_map('strval', $connection->fetchCol($select));
    }
}
