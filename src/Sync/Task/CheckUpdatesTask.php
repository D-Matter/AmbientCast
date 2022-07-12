<?php

declare(strict_types=1);

namespace App\Sync\Task;

use App\Doctrine\ReloadableEntityManagerInterface;
use App\Entity;
use App\Environment;
use App\Service\AmbientCastCentral;
use GuzzleHttp\Exception\TransferException;
use Psr\Log\LoggerInterface;

final class CheckUpdatesTask extends AbstractTask
{
    private const UPDATE_THRESHOLD = 3780;

    public function __construct(
        private readonly Entity\Repository\SettingsRepository $settingsRepo,
        private readonly AmbientCastCentral $ambientcastCentral,
        ReloadableEntityManagerInterface $em,
        LoggerInterface $logger
    ) {
        parent::__construct($em, $logger);
    }

    public static function getSchedulePattern(): string
    {
        return '3-59/5 * * * *';
    }

    public function run(bool $force = false): void
    {
        $settings = $this->settingsRepo->readSettings();

        if (!$force) {
            $update_last_run = $settings->getUpdateLastRun();

            if ($update_last_run > (time() - self::UPDATE_THRESHOLD)) {
                $this->logger->debug('Not checking for updates; checked too recently.');
                return;
            }
        }

        if (Environment::getInstance()->isTesting()) {
            $this->logger->info('Update checks are currently disabled for this AmbientCast instance.');
            return;
        }

        try {
            $updates = $this->ambientcastCentral->checkForUpdates();

            if (!empty($updates)) {
                $settings->setUpdateResults($updates);

                $this->logger->info('Successfully checked for updates.', ['results' => $updates]);
            } else {
                $this->logger->error('Error parsing update data response from AmbientCast central.');
            }
        } catch (TransferException $e) {
            $this->logger->error(sprintf('Error from AmbientCast Central (%d): %s', $e->getCode(), $e->getMessage()));
            return;
        }

        $settings->updateUpdateLastRun();
        $this->settingsRepo->writeSettings($settings);
    }
}
