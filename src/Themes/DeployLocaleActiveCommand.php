<?php
/**
 * Created by Qoliber
 *
 * @author      Lukasz Owczarczuk <lowczarczuk@qoliber.com>
 */

namespace Qoliber\Magerun\Themes;

use Magento\Framework\Validator\Locale;
use Magento\Store\Model\Config\StoreView;
use Magento\User\Model\ResourceModel\User\Collection as UserCollection;
use N98\Magento\Command\AbstractMagentoCommand;
use N98\Util\Console\Helper\Table\Renderer\RendererFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeployLocaleActiveCommand extends AbstractMagentoCommand
{
    /** @var \Magento\User\Model\ResourceModel\User\Collection  */
    private UserCollection $userCollection;

    /** @var \Magento\Store\Model\Config\StoreView  */
    private StoreView $storeView;

    /** @var \Magento\Framework\Validator\Locale  */
    private Locale $locale;

    /**
     * @param \Magento\User\Model\ResourceModel\User\Collection $userCollection
     * @param \Magento\Store\Model\Config\StoreView $storeView
     * @param \Magento\Framework\Validator\Locale $locale
     * @return void
     */
    public function inject(
        UserCollection $userCollection,
        StoreView $storeView,
        Locale $locale
    ) {
       	$this->userCollection = $userCollection;
        $this->storeView = $storeView;
        $this->locale = $locale;
    }

    /**
     * Get admin user locales
     *
     * @return array
     */
    private function getAdminUserInterfaceLocales(): array
    {
        $locales = [];

        foreach ($this->userCollection as $user) {
            $locales[] = $user->getInterfaceLocale();
        }

	    return $locales;
    }

    /**
     * Get used store and admin user locales
     *
     * @return array
     * @throws \InvalidArgumentException if unknown locale is provided by the store configuration
     */
    private function getUsedLocales(?string $area = null): array
    {
        $storeLocales = [];
        if ($area === null || $area === AreaCodes::FRONTEND) {
            $storeLocales = $this->storeView->retrieveLocales();
        }

        $adminLocales = [];
        if ($area === null || $area === AreaCodes::ADMINHTML) {
            $adminLocales = $this->getAdminUserInterfaceLocales();
        }

     	$usedLocales = array_merge(
            $storeLocales,
            $adminLocales
        );

        return array_map(
            function ($locale) {
                if (!$this->locale->isValid($locale)) {
                    throw new \InvalidArgumentException(
                        $locale .
                        ' argument has invalid value, run info:language:list for list of available locales'
                    );
                }

                return $locale;
            },
            array_unique($usedLocales)
        );
    }

    /**
     *  Configure Command
     *
     * @return void
     */
    protected function configure(): void
    {
      $this
          ->setName('qoliber:magerun:locale:active')
          ->setDescription('Get list of active locales')
          ->addOption(
              'format',
              null,
              InputOption::VALUE_OPTIONAL,
              'Output Format. One of [' . implode(',', RendererFactory::getFormats()) . ']'
          )
          ->addOption(
              'area',
              null,
              InputOption::VALUE_OPTIONAL,
              'Area codes. One of [' . implode(',', [AreaCodes::ADMINHTML, AreaCodes::FRONTEND])
        . ']'
          )
      ;
    }

   /**
    * @param \Symfony\Component\Console\Input\InputInterface $input
    * @param \Symfony\Component\Console\Output\OutputInterface $output
    *
    * @return int|void
    */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);

        if ($this->initMagento()) {
            $localeForAllStores = $this->getUsedLocales($input->getOption('area'));

            if (!$input->getOption('format')) {
                $output->writeln(implode(' ', $localeForAllStores));
            }

            if ($input->getOption('format') == 'json') {
                $output->writeln(
                    json_encode($localeForAllStores, JSON_PRETTY_PRINT)
                );
            }

            return Command::SUCCESS;
        } else {
            return Command::FAILURE;
        }
    }
}
