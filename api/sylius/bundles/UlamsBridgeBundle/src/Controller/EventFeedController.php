<?php

declare(strict_types=1);

namespace Ulams\Bridge\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Ulams\Bridge\Repository\OutboxEventRepository;

/**
 * The pull side of the outbox: `GET /api/v2/admin/ulams/events?channel=<code>&after=<cursor>&limit=200`, behind the
 * admin API firewall (the LMS's admin API user). It does not depend on delivery, so a blocked webhook URL or a dead
 * worker does not hide an event from reconciliation.
 */
#[AsController]
final class EventFeedController
{
    public function __construct(private readonly OutboxEventRepository $outbox)
    {
    }

    #[Route('/api/v2/admin/ulams/events', name: 'ulams_bridge_events', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $channel = (string) $request->query->get('channel', '');
        if ($channel === '') {
            return new JsonResponse(['detail' => 'The channel query parameter is required.'], 400);
        }
        $after = max(0, (int) $request->query->get('after', 0));
        $limit = min(500, max(1, (int) $request->query->get('limit', 200)));

        $events = $this->outbox->feed($channel, $after, $limit);
        $payloads = [];
        $cursor = (string) $after;
        foreach ($events as $event) {
            $payloads[] = $event->getPayload();
            $cursor = (string) $event->getSeq();
        }
        $backlog = $this->outbox->backlog($channel);

        return new JsonResponse([
            'events' => $payloads,
            'nextCursor' => $cursor,
            'undelivered' => $backlog['pending'],
            'oldestUndelivered' => $backlog['oldest'],
        ]);
    }
}
