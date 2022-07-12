<?php

declare(strict_types=1);

namespace App\Controller\Api\Stations;

use App\Controller\Api\Traits\CanSortResults;
use App\Doctrine\ReloadableEntityManagerInterface;
use App\Entity;
use App\Http\Response;
use App\Http\ServerRequest;
use App\OpenApi;
use App\Radio\AutoDJ\Scheduler;
use App\Service\Flow\UploadedFile;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @extends AbstractScheduledEntityController<Entity\StationStreamer> */
#[
    OA\Get(
        path: '/station/{station_id}/streamers',
        operationId: 'getStreamers',
        description: 'List all current Streamer/DJ accounts for the specified station.',
        security: OpenApi::API_KEY_SECURITY,
        tags: ['Stations: Streamers/DJs'],
        parameters: [
            new OA\Parameter(ref: OpenApi::REF_STATION_ID_REQUIRED),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/StationStreamer')
                )
            ),
            new OA\Response(ref: OpenApi::REF_RESPONSE_ACCESS_DENIED, response: 403),
            new OA\Response(ref: OpenApi::REF_RESPONSE_NOT_FOUND, response: 404),
            new OA\Response(ref: OpenApi::REF_RESPONSE_GENERIC_ERROR, response: 500),
        ]
    ),
    OA\Post(
        path: '/station/{station_id}/streamers',
        operationId: 'addStreamer',
        description: 'Create a new Streamer/DJ account.',
        security: OpenApi::API_KEY_SECURITY,
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: '#/components/schemas/StationStreamer')
        ),
        tags: ['Stations: Streamers/DJs'],
        parameters: [
            new OA\Parameter(ref: OpenApi::REF_STATION_ID_REQUIRED),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(ref: '#/components/schemas/StationStreamer')
            ),
            new OA\Response(ref: OpenApi::REF_RESPONSE_ACCESS_DENIED, response: 403),
            new OA\Response(ref: OpenApi::REF_RESPONSE_NOT_FOUND, response: 404),
            new OA\Response(ref: OpenApi::REF_RESPONSE_GENERIC_ERROR, response: 500),
        ]
    ),
    OA\Get(
        path: '/station/{station_id}/streamer/{id}',
        operationId: 'getStreamer',
        description: 'Retrieve details for a single Streamer/DJ account.',
        security: OpenApi::API_KEY_SECURITY,
        tags: ['Stations: Streamers/DJs'],
        parameters: [
            new OA\Parameter(ref: OpenApi::REF_STATION_ID_REQUIRED),
            new OA\Parameter(
                name: 'id',
                description: 'Streamer ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', format: 'int64')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(ref: '#/components/schemas/StationStreamer')
            ),
            new OA\Response(ref: OpenApi::REF_RESPONSE_ACCESS_DENIED, response: 403),
            new OA\Response(ref: OpenApi::REF_RESPONSE_NOT_FOUND, response: 404),
            new OA\Response(ref: OpenApi::REF_RESPONSE_GENERIC_ERROR, response: 500),
        ]
    ),
    OA\Put(
        path: '/station/{station_id}/streamer/{id}',
        operationId: 'editStreamer',
        description: 'Update details of a single Streamer/DJ account.',
        security: OpenApi::API_KEY_SECURITY,
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: '#/components/schemas/StationStreamer')
        ),
        tags: ['Stations: Streamers/DJs'],
        parameters: [
            new OA\Parameter(ref: OpenApi::REF_STATION_ID_REQUIRED),
            new OA\Parameter(
                name: 'id',
                description: 'Streamer ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', format: 'int64')
            ),
        ],
        responses: [
            new OA\Response(ref: OpenApi::REF_RESPONSE_SUCCESS, response: 200),
            new OA\Response(ref: OpenApi::REF_RESPONSE_ACCESS_DENIED, response: 403),
            new OA\Response(ref: OpenApi::REF_RESPONSE_NOT_FOUND, response: 404),
            new OA\Response(ref: OpenApi::REF_RESPONSE_GENERIC_ERROR, response: 500),
        ]
    ),
    OA\Delete(
        path: '/station/{station_id}/streamer/{id}',
        operationId: 'deleteStreamer',
        description: 'Delete a single Streamer/DJ account.',
        security: OpenApi::API_KEY_SECURITY,
        tags: ['Stations: Streamers/DJs'],
        parameters: [
            new OA\Parameter(ref: OpenApi::REF_STATION_ID_REQUIRED),
            new OA\Parameter(
                name: 'id',
                description: 'StationStreamer ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', format: 'int64')
            ),
        ],
        responses: [
            new OA\Response(ref: OpenApi::REF_RESPONSE_SUCCESS, response: 200),
            new OA\Response(ref: OpenApi::REF_RESPONSE_ACCESS_DENIED, response: 403),
            new OA\Response(ref: OpenApi::REF_RESPONSE_NOT_FOUND, response: 404),
            new OA\Response(ref: OpenApi::REF_RESPONSE_GENERIC_ERROR, response: 500),
        ]
    )
]
final class StreamersController extends AbstractScheduledEntityController
{
    use CanSortResults;

    protected string $entityClass = Entity\StationStreamer::class;
    protected string $resourceRouteName = 'api:stations:streamer';

    public function __construct(
        Entity\Repository\StationScheduleRepository $scheduleRepo,
        Scheduler $scheduler,
        ReloadableEntityManagerInterface $em,
        Serializer $serializer,
        ValidatorInterface $validator,
        private readonly Entity\Repository\StationStreamerRepository $streamerRepo,
    ) {
        parent::__construct($scheduleRepo, $scheduler, $em, $serializer, $validator);
    }

    public function listAction(
        ServerRequest $request,
        Response $response,
        string $station_id
    ): ResponseInterface {
        $station = $request->getStation();

        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from(Entity\StationStreamer::class, 'e')
            ->where('e.station = :station')
            ->setParameter('station', $station);

        $qb = $this->sortQueryBuilder(
            $request,
            $qb,
            [
                'display_name' => 'e.display_name',
                'streamer_username' => 'e.streamer_username',
            ],
            'e.streamer_username'
        );

        $searchPhrase = trim($request->getParam('searchPhrase', ''));
        if (!empty($searchPhrase)) {
            $qb->andWhere('(e.streamer_username LIKE :name OR e.display_name LIKE :name)')
                ->setParameter('name', '%' . $searchPhrase . '%');
        }

        return $this->listPaginatedFromQuery($request, $response, $qb->getQuery());
    }

    public function createAction(
        ServerRequest $request,
        Response $response,
        string $station_id
    ): ResponseInterface {
        $station = $request->getStation();

        $parsedBody = (array)$request->getParsedBody();

        /** @var Entity\StationStreamer $record */
        $record = $this->editRecord(
            $parsedBody,
            new Entity\StationStreamer($station)
        );

        if (!empty($parsedBody['artwork_file'])) {
            $artwork = UploadedFile::fromArray($parsedBody['artwork_file'], $station->getRadioTempDir());
            $this->streamerRepo->writeArtwork(
                $record,
                $artwork->readAndDeleteUploadedFile()
            );

            $this->em->persist($record);
            $this->em->flush();
        }

        return $response->withJson($this->viewRecord($record, $request));
    }

    public function scheduleAction(
        ServerRequest $request,
        Response $response,
        string $station_id
    ): ResponseInterface {
        $station = $request->getStation();

        $scheduleItems = $this->em->createQuery(
            <<<'DQL'
                SELECT ssc, sst
                FROM App\Entity\StationSchedule ssc
                LEFT JOIN ssc.streamer sst
                WHERE sst.station = :station AND sst.is_active = 1
            DQL
        )->setParameter('station', $station)
            ->execute();

        return $this->renderEvents(
            $request,
            $response,
            $scheduleItems,
            function (
                Entity\StationSchedule $scheduleItem,
                CarbonInterface $start,
                CarbonInterface $end
            ) use (
                $request,
                $station
            ) {
                /** @var Entity\StationStreamer $streamer */
                $streamer = $scheduleItem->getStreamer();

                return [
                    'id' => $streamer->getId(),
                    'title' => $streamer->getDisplayName(),
                    'start' => $start->toIso8601String(),
                    'end' => $end->toIso8601String(),
                    'edit_url' => (string)$request->getRouter()->named(
                        'api:stations:streamer',
                        ['station_id' => $station->getId(), 'id' => $streamer->getId()]
                    ),
                ];
            }
        );
    }

    /**
     * @param Entity\StationStreamer $record
     * @param ServerRequest $request
     *
     * @return mixed[]
     */
    protected function viewRecord(object $record, ServerRequest $request): array
    {
        $return = parent::viewRecord($record, $request);

        $router = $request->getRouter();
        $isInternal = ('true' === $request->getParam('internal', 'false'));

        $return['has_custom_art'] = (0 !== $record->getArtUpdatedAt());
        $return['art'] = (string)$router->fromHere(
            route_name: 'api:stations:streamer:art',
            route_params: ['id' => $record->getIdRequired() . '|' . $record->getArtUpdatedAt()],
            absolute: !$isInternal
        );

        $return['links']['broadcasts'] = (string)$router->fromHere(
            route_name: 'api:stations:streamer:broadcasts',
            route_params: ['id' => $record->getId()],
            absolute: !$isInternal
        );
        $return['links']['art'] = (string)$router->fromHere(
            route_name: 'api:stations:streamer:art-internal',
            route_params: ['id' => $record->getId()],
            absolute: !$isInternal
        );

        return $return;
    }

    protected function deleteRecord(object $record): void
    {
        if (!($record instanceof Entity\StationStreamer)) {
            throw new InvalidArgumentException(sprintf('Record must be an instance of %s.', $this->entityClass));
        }

        $this->streamerRepo->delete($record);
    }
}
