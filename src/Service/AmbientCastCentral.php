<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity;
use App\Environment;
use App\Version;
use Exception;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

final class AmbientCastCentral
{
    private const BASE_URL = 'https://ambient.kwafgroup.com/central';

    public function __construct(
        private readonly Environment $environment,
        private readonly Version $version,
        private readonly Client $httpClient,
        private readonly LoggerInterface $logger,
        private readonly Entity\Repository\SettingsRepository $settingsRepo
    ) {
    }

    /**
     * Ping the AmbientCast Central server for updates and return them if there are any.
     *
     * @return mixed[]|null
     */
    public function checkForUpdates(): ?array
    {
        $request_body = [
            'id' => $this->getUniqueIdentifier(),
            'is_docker' => $this->environment->isDocker(),
            'environment' => $this->environment->getAppEnvironmentEnum()->value,
            'release_channel' => $this->version->getReleaseChannelEnum()->value,
        ];

        $commit_hash = $this->version->getCommitHash();
        if ($commit_hash) {
            $request_body['version'] = $commit_hash;
        } else {
            $request_body['release'] = Version::FALLBACK_VERSION;
        }

        $this->logger->debug(
            'Update request body',
            [
                'body' => $request_body,
            ]
        );

        try {
            $response = $this->httpClient->request(
                'POST',
                self::BASE_URL . '/api/update',
                ['json' => $request_body]
            );

            $update_data_raw = $response->getBody()->getContents();

            $update_data = json_decode($update_data_raw, true, 512, JSON_THROW_ON_ERROR);
            return $update_data['updates'] ?? null;
        } catch (Exception $e) {
            $this->logger->error('Error checking for updates: ' . $e->getMessage());
        }

        return null;
    }

    public function getUniqueIdentifier(): string
    {
        return $this->settingsRepo->readSettings()->getAppUniqueIdentifier();
    }

    /**
     * Ping the AmbientCast Central server to retrieve this installation's likely public-facing IP.
     *
     * @param bool $cached
     */
    public function getIp(bool $cached = true): ?string
    {
        $settings = $this->settingsRepo->readSettings();
        $ip = ($cached)
            ? $settings->getExternalIp()
            : null;

        if (empty($ip)) {
            try {
                $response = $this->httpClient->request(
                    'GET',
                    self::BASE_URL . '/ip'
                );

                $body_raw = $response->getBody()->getContents();
                $body = json_decode($body_raw, true, 512, JSON_THROW_ON_ERROR);

                $ip = $body['ip'] ?? null;
            } catch (Exception $e) {
                $this->logger->error('Could not fetch remote IP: ' . $e->getMessage());
                $ip = null;
            }

            if (!empty($ip) && $cached) {
                $settings->setExternalIp($ip);
                $this->settingsRepo->writeSettings($settings);
            }
        }

        return $ip;
    }
}
