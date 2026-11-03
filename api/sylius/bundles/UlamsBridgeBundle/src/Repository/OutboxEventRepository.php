<?php

declare(strict_types=1);

namespace Ulams\Bridge\Repository;

use Ulams\Bridge\Entity\OutboxEvent;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<OutboxEvent> */
class OutboxEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OutboxEvent::class);
    }

    /** @return list<OutboxEvent> events of one channel after a cursor (`seq`), oldest first */
    public function feed(string $channelCode, int $after, int $limit): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.channelCode = :channel')->andWhere('e.seq > :after')
            ->setParameter('channel', $channelCode)->setParameter('after', $after)
            ->orderBy('e.seq', 'ASC')->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /** @return list<OutboxEvent> */
    public function due(int $limit): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.deliveredAt IS NULL')->andWhere('e.nextAttemptAt IS NOT NULL')->andWhere('e.nextAttemptAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('e.seq', 'ASC')->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function nextSequence(string $orderToken): int
    {
        return 1 + (int) $this->createQueryBuilder('e')->select('MAX(e.sequence)')
            ->andWhere('e.orderToken = :t')->setParameter('t', $orderToken)->getQuery()->getSingleScalarResult();
    }

    /** Events not yet delivered, and the age in seconds of the oldest one (for the LMS health card). */
    public function backlog(?string $channelCode = null): array
    {
        $qb = $this->createQueryBuilder('e')->select('COUNT(e.seq) AS pending', 'MIN(e.createdAt) AS oldest')->andWhere('e.deliveredAt IS NULL');
        if ($channelCode !== null) {
            $qb->andWhere('e.channelCode = :c')->setParameter('c', $channelCode);
        }
        $row = $qb->getQuery()->getSingleResult();

        return ['pending' => (int) $row['pending'], 'oldest' => $row['oldest']];
    }
}
