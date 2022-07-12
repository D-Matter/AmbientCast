<?php

declare(strict_types=1);

namespace App\Controller\Stations;

use App\Entity;
use App\Http\Response;
use App\Http\ServerRequest;
use App\Service\AmbientCastCentral;
use Psr\Http\Message\ResponseInterface;

final class StreamersAction
{
    public function __construct(
        private readonly AmbientCastCentral $acCentral,
        private readonly Entity\Repository\SettingsRepository $settingsRepo
    ) {
    }

    public function __invoke(
        ServerRequest $request,
        Response $response,
        string $station_id
    ): ResponseInterface {
        $station = $request->getStation();

        $settings = $this->settingsRepo->readSettings();
        $backendConfig = $station->getBackendConfig();

        $router = $request->getRouter();

        return $request->getView()->renderVuePage(
            response: $response,
            component: 'Vue_StationsStreamers',
            id: 'station-streamers',
            title: __('Streamer/DJ Accounts'),
            props: [
                'listUrl' => (string)$router->fromHere('api:stations:streamers'),
                'newArtUrl' => (string)$router->fromHere('api:stations:streamers:new-art'),
                'scheduleUrl' => (string)$router->fromHere('api:stations:streamers:schedule'),
                'stationTimeZone' => $station->getTimezone(),
                'connectionInfo' => [
                    'serverUrl' => $settings->getBaseUrl(),
                    'streamPort' => $backendConfig->getDjPort(),
                    'ip' => $this->acCentral->getIp(),
                    'djMountPoint' => $backendConfig->getDjMountPoint(),
                ],
            ]
        );
    }
}
