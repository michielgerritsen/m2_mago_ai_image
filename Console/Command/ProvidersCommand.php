<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Console\Command;

use Orangecat\MagoAiImage\Api\ProviderConfigInterface;
use Orangecat\MagoAiImage\Api\ProviderPoolInterface;
use Orangecat\MagoAiImage\Model\Config;
use Orangecat\MagoAiImage\Model\Provider\Dummy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lists installed image providers and whether they are usable. Never prints keys.
 */
class ProvidersCommand extends Command
{
    /**
     * @param ProviderPoolInterface $providerPool
     * @param ProviderConfigInterface $providerConfig
     * @param Config $config
     */
    public function __construct(
        private readonly ProviderPoolInterface $providerPool,
        private readonly ProviderConfigInterface $providerConfig,
        private readonly Config $config
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('mago:ai-image:providers')
            ->setDescription('List AI image providers and their status');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $default = $this->config->getProviderCode();
        $table = new Table($output);
        $table->setHeaders(['Code', 'Label', 'Default', 'Available', 'Credentials', 'API key']);
        foreach ($this->providerPool->getAll() as $code => $provider) {
            $needsKey = !$provider instanceof Dummy;
            $table->addRow([
                $code,
                $provider->getLabel(),
                $code === $default ? 'yes' : '',
                $provider->isAvailable() ? 'yes' : 'no',
                $needsKey ? $this->providerConfig->getCredentialSource($provider) : '-',
                $needsKey ? ($this->providerConfig->hasApiKey($provider) ? 'set' : 'missing') : '-',
            ]);
        }
        $table->render();
        $output->writeln(sprintf('Module enabled: %s', $this->config->isEnabled() ? 'yes' : 'no'));

        return Command::SUCCESS;
    }
}
