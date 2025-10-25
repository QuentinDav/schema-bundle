<?php

namespace Qd\SchemaBundle\Controller;

use Qd\SchemaBundle\Service\SnapshotService;
use Symfony\Component\HttpFoundation\JsonResponse;

final class SnapshotApiController
{
    public function __construct(private SnapshotService $snapshotService)
    {
    }

    public function create(): JsonResponse
    {
        $res = $this->snapshotService->snapshotAll();
        return new JsonResponse($res);
    }
}
