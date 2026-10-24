<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Orangecat\MagoAiImage\Api\GenerationManagementInterface;
use Orangecat\MagoAiImage\Api\ImageGeneratorInterface;
use Orangecat\MagoAiImage\Model\Image\PreviewStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Generates previews for a product from the command line, optionally attaching the first one.
 */
class GenerateCommand extends Command
{
    private const OPTION_SKU = 'sku';
    private const OPTION_PROMPT = 'prompt';
    private const OPTION_PROVIDER = 'provider';
    private const OPTION_MEDIA_ENTRY = 'media-entry';
    private const OPTION_COUNT = 'count';
    private const OPTION_ASPECT_RATIO = 'aspect-ratio';
    private const OPTION_ATTACH = 'attach';
    private const OPTION_ROLES = 'roles';
    private const OPTION_LABEL = 'label';

    /**
     * @param State $appState
     * @param ImageGeneratorInterface $imageGenerator
     * @param GenerationManagementInterface $generationManagement
     * @param PreviewStorage $previewStorage
     */
    public function __construct(
        private readonly State $appState,
        private readonly ImageGeneratorInterface $imageGenerator,
        private readonly GenerationManagementInterface $generationManagement,
        private readonly PreviewStorage $previewStorage
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('mago:ai-image:generate')
            ->setDescription('Generate AI preview images for a product')
            ->addOption(self::OPTION_SKU, null, InputOption::VALUE_REQUIRED, 'Product SKU')
            ->addOption(self::OPTION_PROMPT, null, InputOption::VALUE_REQUIRED, 'Image prompt')
            ->addOption(self::OPTION_PROVIDER, null, InputOption::VALUE_REQUIRED, 'Provider code (default: configured)')
            ->addOption(self::OPTION_MEDIA_ENTRY, null, InputOption::VALUE_REQUIRED, 'Gallery entry ID as reference')
            ->addOption(self::OPTION_COUNT, null, InputOption::VALUE_REQUIRED, 'Number of images', '1')
            ->addOption(self::OPTION_ASPECT_RATIO, null, InputOption::VALUE_REQUIRED, 'Aspect ratio, e.g. 1:1')
            ->addOption(self::OPTION_ATTACH, null, InputOption::VALUE_NONE, 'Attach the first preview to the gallery')
            ->addOption(self::OPTION_ROLES, null, InputOption::VALUE_REQUIRED, 'Roles when attaching (comma separated)')
            ->addOption(self::OPTION_LABEL, null, InputOption::VALUE_REQUIRED, 'Label when attaching');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sku = trim((string) $input->getOption(self::OPTION_SKU));
        $prompt = trim((string) $input->getOption(self::OPTION_PROMPT));
        if ($sku === '' || $prompt === '') {
            $output->writeln('<error>--sku and --prompt are required.</error>');

            return Command::INVALID;
        }

        $options = array_filter([
            ImageGeneratorInterface::OPTION_PROVIDER => $input->getOption(self::OPTION_PROVIDER),
            ImageGeneratorInterface::OPTION_MEDIA_ENTRY_ID => $input->getOption(self::OPTION_MEDIA_ENTRY),
            ImageGeneratorInterface::OPTION_COUNT => $input->getOption(self::OPTION_COUNT),
            ImageGeneratorInterface::OPTION_ASPECT_RATIO => $input->getOption(self::OPTION_ASPECT_RATIO),
        ], static fn ($value): bool => $value !== null && $value !== '');

        try {
            return $this->appState->emulateAreaCode(
                Area::AREA_ADMINHTML,
                fn (): int => $this->generate($sku, $prompt, $options, $input, $output)
            );
        } catch (LocalizedException $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));

            return Command::FAILURE;
        }
    }

    /**
     * Generate, print and optionally attach.
     *
     * @param string $sku
     * @param string $prompt
     * @param mixed[] $options
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws LocalizedException
     */
    private function generate(
        string $sku,
        string $prompt,
        array $options,
        InputInterface $input,
        OutputInterface $output
    ): int {
        $generations = $this->imageGenerator->generateForProduct($sku, $prompt, $options);
        foreach ($generations as $generation) {
            $output->writeln(sprintf(
                'Preview #%d (%s/%s): %s',
                (int) $generation->getGenerationId(),
                $generation->getProvider(),
                $generation->getModel(),
                $this->previewStorage->getUrl($generation->getFile())
            ));
        }

        if ($input->getOption(self::OPTION_ATTACH) && $generations !== []) {
            $roles = array_filter(
                array_map('trim', explode(',', (string) $input->getOption(self::OPTION_ROLES)))
            );
            $label = $input->getOption(self::OPTION_LABEL);
            $attached = $this->generationManagement->attach(
                (int) $generations[0]->getGenerationId(),
                $roles,
                $label === null ? null : (string) $label
            );
            $output->writeln(sprintf(
                'Attached preview #%d as gallery entry %d.',
                (int) $attached->getGenerationId(),
                (int) $attached->getMediaGalleryEntryId()
            ));
        }

        return Command::SUCCESS;
    }
}
