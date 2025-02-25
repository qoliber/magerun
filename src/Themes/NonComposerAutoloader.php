<?php
/**
 * Created by Qoliber
 *
 * @author      Lukasz Owczarczuk <lowczarczuk@qoliber.com>
 */

namespace Qoliber\Magerun\Themes;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NonComposerAutoloader extends AbstractMagentoCommand
{
    /**
     * Configure command
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('qoliber:magerun:non-composer-autoloader')
            ->setDescription('Removes `glob` from app/etc/NonComposerComponentRegistration.php - use only in production mode');
    }

    /**
     * Execute command
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);
        if (!$this->initMagento()) {
            return Command::FAILURE;
        }

        $baseDir = $this->getApplication()->getMagentoRootFolder() . '/';

        $globPatterns = require $baseDir . 'app/etc/registration_globlist.php';
        $registrationFiles = [];

        foreach ($globPatterns as $globPattern) {
            $files = \glob($baseDir . $globPattern, GLOB_NOSORT);

            if ($files === false) {
                continue;
            }
            $registrationFiles = array_merge($registrationFiles, $files);
        }

        $registrationFilesString = var_export($registrationFiles, true);
        $phpCode = <<<PHP
<?php

\$registrationFiles = $registrationFilesString;

foreach (\$registrationFiles as \$registrationFile) {
    require_once \$registrationFile;
}
PHP;

        file_put_contents($baseDir
            . 'app/etc/NonComposerComponentRegistration.php', $phpCode);

        return Command::SUCCESS;
    }
}
