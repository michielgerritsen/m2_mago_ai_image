<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Cron;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Orangecat\MagoAiImage\Api\Data\GenerationInterface;
use Orangecat\MagoAiImage\Api\GenerationManagementInterface;
use Orangecat\MagoAiImage\Api\GenerationRepositoryInterface;
use Orangecat\MagoAiImage\Model\Config;
use Orangecat\MagoAiImage\Model\Image\PreviewStorage;
use Orangecat\MagoAiImage\Model\ResourceModel\Generation\CollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Removes preview files older than the retention period.
 *
 * Previews nobody attached are discarded. Attached ones only lose the preview file (the product
 * gallery has its own copy); until then the Mago chat history keeps showing them.
 */
class CleanupPreviews
{
    private const BATCH_SIZE = 200;

    /**
     * @param Config $config
     * @param CollectionFactory $collectionFactory
     * @param GenerationManagementInterface $generationManagement
     * @param GenerationRepositoryInterface $generationRepository
     * @param PreviewStorage $previewStorage
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly CollectionFactory $collectionFactory,
        private readonly GenerationManagementInterface $generationManagement,
        private readonly GenerationRepositoryInterface $generationRepository,
        private readonly PreviewStorage $previewStorage,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Run the cleanup.
     *
     * @return int Number of preview files removed
     */
    public function execute(): int
    {
        $threshold = $this->dateTime->gmtDate(
            'Y-m-d H:i:s',
            $this->dateTime->gmtTimestamp() - $this->config->getPreviewRetentionDays() * 86400
        );

        return $this->discardUnattached($threshold) + $this->clearAttached($threshold);
    }

    /**
     * Discard previews nobody attached.
     *
     * @param string $threshold
     * @return int
     */
    private function discardUnattached(string $threshold): int
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(GenerationInterface::STATUS, GenerationInterface::STATUS_PREVIEW)
            ->addFieldToFilter(GenerationInterface::CREATED_AT, ['lt' => $threshold])
            ->setPageSize(self::BATCH_SIZE);

        $discarded = 0;
        foreach ($collection as $generation) {
            try {
                $this->generationManagement->discard((int) $generation->getGenerationId());
                $discarded++;
            } catch (LocalizedException $e) {
                $this->logger->warning('Orangecat_MagoAiImage: could not discard preview', [
                    'generation_id' => $generation->getGenerationId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $discarded;
    }

    /**
     * Remove the preview file of attached generations; an empty file marks it as removed.
     *
     * @param string $threshold
     * @return int
     */
    private function clearAttached(string $threshold): int
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(GenerationInterface::STATUS, GenerationInterface::STATUS_ATTACHED)
            ->addFieldToFilter(GenerationInterface::FILE, ['neq' => ''])
            ->addFieldToFilter(GenerationInterface::CREATED_AT, ['lt' => $threshold])
            ->setPageSize(self::BATCH_SIZE);

        $cleared = 0;
        foreach ($collection as $generation) {
            /** @var GenerationInterface $generation */
            try {
                $this->previewStorage->delete($generation->getFile());
                $this->generationRepository->save($generation->setFile(''));
                $cleared++;
            } catch (LocalizedException $e) {
                $this->logger->warning('Orangecat_MagoAiImage: could not remove attached preview', [
                    'generation_id' => $generation->getGenerationId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $cleared;
    }
}
