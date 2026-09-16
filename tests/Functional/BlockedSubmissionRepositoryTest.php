<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Tests\Functional;

use Eekes\Sulu\SpamProtectionBundle\Domain\Entity\BlockedSubmission;
use Eekes\Sulu\SpamProtectionBundle\Domain\Repository\BlockedSubmissionRepository;
use Eekes\Sulu\SpamProtectionBundle\Domain\ValueObject\SubmissionSource;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BlockedSubmissionRepository::class)]
final class BlockedSubmissionRepositoryTest extends FunctionalTestCase
{
    public function testASelectionIsDeletedAndTheRestIsLeftAlone(): void
    {
        $doomed = $this->blocked();
        $kept = $this->blocked();

        $deleted = $this->blockedSubmissionRepository()->deleteByIds([$doomed]);

        self::assertSame(1, $deleted);
        self::assertSame([$kept], array_map(static fn (BlockedSubmission $b): int => $b->id, $this->blockedSubmissions()));
    }

    /**
     * An id that is no longer there is not an error: two administrators can empty the same list.
     */
    public function testAnIdThatNoLongerExistsIsSkipped(): void
    {
        $kept = $this->blocked();

        $deleted = $this->blockedSubmissionRepository()->deleteByIds([$kept + 999]);

        self::assertSame(0, $deleted);
        self::assertCount(1, $this->blockedSubmissions());
    }

    public function testDeletingNothingTouchesNothing(): void
    {
        $this->blocked();

        self::assertSame(0, $this->blockedSubmissionRepository()->deleteByIds([]));
        self::assertCount(1, $this->blockedSubmissions());
    }

    private function blocked(): int
    {
        $blockedSubmission = new BlockedSubmission();
        $blockedSubmission->setSource(SubmissionSource::suluForm());
        $blockedSubmission->reason = 'score-threshold-not-met';

        $this->blockedSubmissionRepository()->save($blockedSubmission);

        return $blockedSubmission->id;
    }
}
