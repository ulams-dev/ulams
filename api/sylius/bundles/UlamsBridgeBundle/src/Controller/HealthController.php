<?php

declare(strict_types=1);

namespace Ulams\Bridge\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/** Liveness for the container healthcheck: the process answers and the database does. Public, tells nothing else. */
#[AsController]
final class HealthController
{
    public function __construct(private readonly Connection $connection)
    {
    }

    #[Route('/ulams/health', name: 'ulams_bridge_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            $this->connection->executeQuery('SELECT 1')->fetchOne();
        } catch (\Throwable) {
            return new JsonResponse(['status' => 'error'], 503);
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
