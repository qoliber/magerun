<?php
/**
 * Created by Qoliber
 *
 * @author      Lukasz Owczarczuk <lowczarczuk@qoliber.com>
 */

namespace Qoliber\Magerun\Themes;

use Magento\Framework\Indexer\IndexerInterface;
use Magento\Indexer\Model\Indexer\CollectionFactory;
use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ProductionModeCommand extends AbstractMagentoCommand
{
    /** @var \Symfony\Component\Console\Output\OutputInterface|null  */
    private ?OutputInterface $output = null;

    /** @var \Magento\Indexer\Model\Indexer\CollectionFactory|null  */
    private ?CollectionFactory $indexerCollectionFactory = null;

    /** @var array|null  */
    private ?array $nonScheduledIndexers = null;

    /**
     *  Configure Command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('qoliber:magerun:mode:production')
            ->setDescription('Set production mode but compile only used themes and locales');
    }

    /**
     * Execute Command
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->detectMagento($output);
        if (!$this->initMagento()) {
            return Command::FAILURE;
        }

        $this->output = $output;

        $steps = [
            fn() => $this->maintenanceModeSet(true),
            fn() => $this->indexerModeSet(true),
            fn() => $this->setupUpgrade(),
            fn() => $this->indexerModeSet(false),
            fn() => $this->processNonScheduledIndexers(),
            fn() => $this->setProductionMode(),
            fn() => $this->flushCache(),
            fn() => $this->diCompile(),
            fn() => $this->flushCache(),
            fn() => $this->deployStaticContent(AreaCodes::FRONTEND),
            fn() => $this->deployStaticContent(AreaCodes::ADMINHTML),
            fn() => $this->maintenanceModeSet(false)
        ];

        foreach ($steps as $step) {
            if ($step() === Command::FAILURE) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Processes non-scheduled indexers if available
     */
    private function processNonScheduledIndexers(): int
    {
        $indexers = $this->getNonScheduledIndexers();
        return !empty($indexers) ? $this->indexerModeSet(true, $indexers) : Command::SUCCESS;
    }

    private function maintenanceModeSet(
        bool $enabled
    ): int {
        $input = new ArrayInput([
            'command' => $enabled ? 'maintenance:enable'
                : 'maintenance:disable',
        ]);

        return $this->getApplication()->doRun($input, $this->output);
    }

    /**
     * Set Indexer Mode
     *
     * @param bool $realtime
     * @param array $indexes
     *
     * @return int
     *
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Throwable
     */
    private function indexerModeSet(
        bool $realtime,
        array $indexes = []
    ): int {
        $input = new ArrayInput([
            'command' => 'indexer:set-mode',
            'mode' => $realtime ? 'realtime' : 'schedule',
            'index' => $indexes,
        ]);

        return $this->getApplication()->doRun($input, $this->output);
    }
    private function getNonScheduledIndexers(): array {
        if (null !== $this->nonScheduledIndexers) {
            return $this->nonScheduledIndexers;
        }

        $this->nonScheduledIndexers = [];

        /** @var IndexerInterface $indexer */
        foreach ($this->getAllIndexers() as $indexer) {
            if ($indexer->isScheduled()) {
                continue;
            }

            $this->nonScheduledIndexers[] = $indexer->getId();
        }

        return $this->nonScheduledIndexers;
    }

    private function getAllIndexers()
    {
        $indexers = $this->getCollectionFactory()->create()->getItems();

        return array_combine(
            array_map(
                function ($item) {
                    /** @var IndexerInterface $item */
                    return $item->getId();
                },
                $indexers
            ),
            $indexers
        );
    }

    private function getCollectionFactory()
    {
        if (null === $this->indexerCollectionFactory) {
            $this->indexerCollectionFactory = $this->getObjectManager()->get(CollectionFactory::class);
        }

        return $this->indexerCollectionFactory;
    }

    private function setProductionMode(): int
    {
        $input = new ArrayInput([
            'command' => 'deploy:mode:set',
            'mode'    => 'production',
            '--skip-compilation',
        ]);

        return $this->getApplication()->doRun($input, $this->output);
    }

    private function flushCache(): int
    {
        $input = new ArrayInput([
            'command' => 'cache:flush',
        ]);

        return $this->getApplication()->doRun($input, $this->output);
    }

    private function diCompile(): int
    {
        $input = new ArrayInput([
            'command' => 'setup:di:compile',
        ]);

        return $this->getApplication()->doRun($input, $this->output);
    }
    private function setupUpgrade(): int
    {
        $input = new ArrayInput([
            'command' => 'setup:upgrade',
        ]);

        return $this->getApplication()->doRun($input, $this->output);
    }

    private function deployStaticContent(
        string $areaCode
    ): int {
        $nproc = (int)trim(shell_exec('nproc'));

        $activeThemes = $this->getActiveThemes($areaCode);
        $activeLocale = $this->getActiveLocale($areaCode);

        if (empty($activeThemes) || empty($activeLocale)) {
            return Command::FAILURE;
        }

        $input = new ArrayInput([
            'command' => 'setup:static-content:deploy',
            '-a'      => $areaCode,
            '-j'      => $nproc,
            '-t'      => $activeThemes,
            '-l'      => $activeLocale,
            '-f',
            '-s'      => 'quick',
        ]);

        return $this->getApplication()->doRun($input, $this->output);
    }

    /**
     * Get Active Themes For Area
     *
     * @param string $areaCode
     *
     * @return int|string[]
     *
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Throwable
     */
    private function getActiveThemes(string $areaCode): int|array
    {
        if ($areaCode !== AreaCodes::FRONTEND
            && $areaCode !== AreaCodes::ADMINHTML) {
            return Command::FAILURE;
        }
        $input = new ArrayInput([
            'command' => 'qoliber:magerun:theme:active',
            '--area'  => $areaCode,
        ]);

        $output = new BufferedOutput();
        $this->getApplication()->doRun($input, $output);
        $themes = rtrim($output->fetch(), "\n");
        $themes = str_replace('--theme', '', $themes);
        $themes = explode(' ', $themes);

        return array_filter($themes);
    }

    /**
     * Get Active Locales
     *
     * @param string $areaCode
     *
     * @return int|string[]
     *
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Throwable
     */
    private function getActiveLocale(string $areaCode): int|array
    {
        if ($areaCode !== AreaCodes::FRONTEND
            && $areaCode !== AreaCodes::ADMINHTML) {
            return Command::FAILURE;
        }

        $input = new ArrayInput([
            'command' => 'qoliber:magerun:locale:active',
            '--area'  => $areaCode,
        ]);

        $output = new BufferedOutput();
        $this->getApplication()->doRun($input, $output);
        $locales = rtrim($output->fetch(), "\n");
        $locales = explode(' ', $locales);

        return array_filter($locales);
    }
}
