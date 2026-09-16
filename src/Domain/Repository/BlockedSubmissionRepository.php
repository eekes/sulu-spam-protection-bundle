<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Domain\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Eekes\Sulu\SpamProtectionBundle\Domain\Entity\BlockedSubmission;

/**
 * @extends ServiceEntityRepository<BlockedSubmission>
 */
class BlockedSubmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlockedSubmission::class);
    }

    public function save(BlockedSubmission $blockedSubmission, bool $flush = true): void
    {
        $this->getEntityManager()->persist($blockedSubmission);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(BlockedSubmission $blockedSubmission, bool $flush = true): void
    {
        $this->getEntityManager()->remove($blockedSubmission);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Deletes everything logged before the given moment and returns how many rows went.
     *
     * A DQL delete on purpose: the rows are a log nothing else refers to, so loading them into
     * memory first only makes pruning a large table slower.
     */
    public function deleteOlderThan(\DateTimeImmutable $moment): int
    {
        return (int) $this->createQueryBuilder('blockedSubmission')
            ->delete()
            ->where('blockedSubmission.createdAt < :moment')
            ->setParameter('moment', $moment)
            ->getQuery()
            ->execute();
    }

    /**
     * Deletes a selection and returns how many rows went. A DQL delete for the same reason as
     * deleteOlderThan(): nothing refers to these rows, so loading them first buys nothing.
     *
     * @param int[] $ids
     */
    public function deleteByIds(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return (int) $this->createQueryBuilder('blockedSubmission')
            ->delete()
            ->where('blockedSubmission.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }

    public function countOlderThan(\DateTimeImmutable $moment): int
    {
        return (int) $this->createQueryBuilder('blockedSubmission')
            ->select('COUNT(blockedSubmission.id)')
            ->where('blockedSubmission.createdAt < :moment')
            ->setParameter('moment', $moment)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
