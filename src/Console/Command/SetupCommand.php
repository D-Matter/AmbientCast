<?php

declare(strict_types=1);

namespace App\Console\Command;

use App\Entity;
use App\Environment;
use App\Service\AmbientCastCentral;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ambientcast:setup',
    description: 'Run all general AmbientCast setup steps.',
)]
final class SetupCommand extends CommandAbstract
{
    public function __construct(
        private readonly Environment $environment,
        private readonly Entity\Repository\SettingsRepository $settingsRepo,
        private readonly AmbientCastCentral $acCentral,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('update', null, InputOption::VALUE_NONE)
            ->addOption('load-fixtures', null, InputOption::VALUE_NONE)
            ->addOption('release', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $update = (bool)$input->getOption('update');
        $loadFixtures = (bool)$input->getOption('load-fixtures');

        $io->title(__('AmbientCast Setup'));
        $io->writeln(__('Welcome to AmbientCast. Please wait while some key dependencies of AmbientCast are set up...'));

        $this->runCommand($output, 'ambientcast:setup:initialize');

        if ($loadFixtures || (!$this->environment->isProduction() && !$update)) {
            $io->newLine();
            $io->section(__('Installing Data Fixtures'));

            $this->runCommand($output, 'ambientcast:setup:fixtures');
        }

        $io->newLine();
        $io->section(__('Refreshing All Stations'));

        $this->runCommand($output, 'ambientcast:station-queues:clear');

        $restartArgs = [];
        if ($this->environment->isDocker()) {
            $restartArgs['--no-supervisor-restart'] = true;
        }

        $this->runCommand(
            $output,
            'ambientcast:radio:restart',
            $restartArgs
        );

        // Update system setting logging when updates were last run.
        $settings = $this->settingsRepo->readSettings();
        $settings->updateUpdateLastRun();
        $this->settingsRepo->writeSettings($settings);

        if ($update) {
            $io->success(
                [
                    __('AmbientCast is now updated to the latest version!'),
                ]
            );
        } else {
            $public_ip = $this->acCentral->getIp(false);

            /** @noinspection HttpUrlsUsage */
            $io->success(
                [
                    __('AmbientCast installation complete!'),
                    sprintf(
                        __('Visit %s to complete setup.'),
                        'http://' . $public_ip
                    ),
                ]
            );
        }

        return 0;
    }
}
