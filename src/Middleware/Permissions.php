<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Enums\PermissionInterface;
use App\Exception\PermissionDeniedException;
use App\Http\ServerRequest;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Get the current user entity object and assign it into the request if it exists.
 */
final class Permissions
{
    public function __construct(
        private readonly string|PermissionInterface $action,
        private readonly bool $use_station = false
    ) {
    }

    public function __invoke(ServerRequest $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->use_station) {
            $station_id = $request->getStation()->getId();
        } else {
            $station_id = null;
        }

        try {
            $user = $request->getUser();
        } catch (Exception) {
            throw new PermissionDeniedException();
        }

        $acl = $request->getAcl();
        if (!$acl->userAllowed($user, $this->action, $station_id)) {
            throw new PermissionDeniedException();
        }

        return $handler->handle($request);
    }
}
