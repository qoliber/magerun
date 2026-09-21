<?php
/**
 * Created by Qoliber
 *
 * @author      Lukasz Owczarczuk <lowczarczuk@qoliber.com>
 */

namespace Qoliber\Magerun\Themes;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use N98\Magento\Command\AbstractMagentoCommand;
use N98\Util\Console\Helper\Table\Renderer\RendererFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DevThemeActiveCommand extends AbstractMagentoCommand
{
    /** @var string Config path MageOS' admin theme switcher stores the active admin theme under */
    private const XML_PATH_ADMIN_ACTIVE_THEME = 'admin/system_admin_design/active_theme';

    /** @var string Stock admin theme - always deployed so the admin can be switched back to it */
    private const ADMIN_FALLBACK_THEME = 'Magento/backend';

    /**
     * Configure Command
     *
     * @return void
     */
    protected function configure(): void
    {
      $this
          ->setName('qoliber:magerun:theme:active')
          ->setDescription('Get list of used themes')
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
     * Execute Command
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);

        if ($this->initMagento()) {
            $objectManager = ObjectManager::getInstance(); // Instance of object manager
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();

            $themeTableName = $resource->getTableName('theme'); //gives table name with prefix
            $configTableName = $resource->getTableName('core_config_data');
            $area = $input->getOption('area');
            $whereArea = '';
            if (!empty($area) && in_array($area, [AreaCodes::ADMINHTML, AreaCodes::FRONTEND])) {
                $whereArea = ' and `area`=\''.$area.'\'';
            }
            $sql = sprintf('SELECT DISTINCT theme_path FROM `%s` LEFT JOIN `%s` ON `value` = `theme_id` WHERE `path`=\'design/theme/theme_id\'%s',
                $themeTableName, $configTableName, $whereArea);
            $res = $connection->fetchCol($sql);

            if (is_null($area) || $area === AreaCodes::FRONTEND) {
                $hyvaSql = sprintf('SELECT DISTINCT value FROM `%s` where path = \'hyva_theme_fallback/general/theme_full_path\'', $configTableName);
                $hyvaRes = $connection->fetchCol($hyvaSql);

                if (!empty($hyvaRes)) {
                    foreach ($hyvaRes as $hs) {
                        $themeParts = explode('/', $hs);
                        $res[] = sprintf('%s/%s', $themeParts[1],  $themeParts[2]);
                    }
                }

                if (!count($res)) {
                    $res[] = 'Magento/luma';
                }
            }

            if (is_null($area) || $area === AreaCodes::ADMINHTML) {
                $res = array_merge(
                    $res,
                    $this->getAdminThemes($objectManager, $connection, $themeTableName, $configTableName)
                );
            }

            $res = array_values(array_unique(array_filter($res)));

            if (!$input->getOption('format')) {
                $out = array();

                foreach ($res as $t) {
                    $out[] = '--theme ' . $t;
                }

                $output->writeln(implode(' ', $out));
            }

            if ($input->getOption('format') == 'json') {
                $output->writeln(
                    json_encode($res, JSON_PRETTY_PRINT)
                );
            }
            return Command::SUCCESS;
        } else {
            return Command::FAILURE;
        }
    }

    /**
     * Get Admin Themes To Deploy
     *
     * The active admin theme is never stored under `design/theme/theme_id`, so the
     * theme query in execute() can never find it. Magento takes its admin theme from
     * di.xml, and MageOS switches it through `admin/system_admin_design/active_theme`
     * - which is why MageOS/m137-admin-theme was silently left out of
     * setup:static-content:deploy and shipped uncompiled.
     *
     * Two sources are used, because neither alone is enough:
     *  - ScopeConfig, which resolves the config.xml default. MageOS ships the theme
     *    as a default only, so on a stock install there is no DB row to find.
     *  - core_config_data, read directly, which catches a theme switched in the admin.
     *    The deploy runs before the cache is flushed, so a stale config cache must
     *    never decide what gets compiled.
     *
     * `Magento/backend` always stays in the list - it is the parent theme and the
     * admin can be switched back to it at any time, so its static content must exist.
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @param string $themeTableName
     * @param string $configTableName
     *
     * @return string[]
     */
    private function getAdminThemes(
        $objectManager,
        $connection,
        string $themeTableName,
        string $configTableName
    ): array {
        $themes = [self::ADMIN_FALLBACK_THEME];

        $candidates = [
            $objectManager->get(ScopeConfigInterface::class)->getValue(
                self::XML_PATH_ADMIN_ACTIVE_THEME,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT
            ),
            $connection->fetchOne(
                sprintf(
                    'SELECT `value` FROM `%s` WHERE `path` = ? AND `scope` = ?'
                    . ' ORDER BY `config_id` DESC',
                    $configTableName
                ),
                [self::XML_PATH_ADMIN_ACTIVE_THEME, ScopeConfigInterface::SCOPE_TYPE_DEFAULT]
            ),
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $candidate = trim($candidate);

            if (in_array($candidate, $themes, true)) {
                continue;
            }

            // Only deploy a theme that is really registered for the admin area.
            // setup:upgrade has already synced the theme table by the time this runs,
            // so a stale config value is a typo - not a reason to fail the deploy.
            $isRegistered = (bool)$connection->fetchOne(
                sprintf(
                    'SELECT COUNT(*) FROM `%s` WHERE `theme_path` = ? AND `area` = ?',
                    $themeTableName
                ),
                [$candidate, AreaCodes::ADMINHTML]
            );

            if ($isRegistered) {
                $themes[] = $candidate;
            }
        }

        return $themes;
    }
}
