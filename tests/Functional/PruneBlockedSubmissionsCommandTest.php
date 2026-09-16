<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Tests\Functional;

use Eekes\Sulu\SpamProtectionBundle\Application\Command\PruneBlockedSubmissionsCommand;
use Eekes\Sulu\SpamProtectionBundle\Domain\Entity\BlockedSubmission;
use Eekes\Sulu\SpamProtectionBundle\Domain\ValueObject\SubmissionSource;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PruneBlockedSubmissionsCommand::class)]
final class PruneBlockedSubmissionsCommandTest extends FunctionalTestCase
{
    public function testItDeletesWhatIsOlderThanTheConfiguredRetention(): void
    {
        $this->blocked('-200 days');
        $this->blocked('-10 days');

        $tester = $this->prune([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('1 blocked submissions', $tester->getDisplay());
        self::assertCount(1, $this->blockedSubmissions());
    }

    public function testACallCanOverrideTheRetention(): void
    {
        $this->blocked('-10 days');
        $this->blocked('-2 days');

        $this->prune(['--days' => '5']);

        self::assertCount(1, $this->blockedSubmissions());
    }

    public function testADryRunOnlyReportsWhatWouldGo(): void
    {
        $this->blocked('-200 days');

        $tester = $this->prune(['--dry-run' => true]);

        self::assertStringContainsString('would be deleted', $tester->getDisplay());
        self::assertCount(1, $this->blockedSubmissions());
    }

    public function testNothingIsDeletedWhenTheRetentionIsSwitchedOff(): void
    {
        $this->blocked('-200 days');

        $tester = $this->prune(['--days' => '0']);

        self::assertStringContainsString('No retention is configured', $tester->getDisplay());
        self::assertCount(1, $this->blockedSubmissions());
    }

    /**
     * @param array<string, bool|string> $input
     */
    private function prune(array $input): CommandTester
    {
        $application = new Application($this->testKernel);
        $tester = new CommandTester($application->find('eekes:spam-protection:prune'));
        $tester->execute($input);

        return $tester;
    }

    private function blocked(string $age): void
    {
        $blockedSubmission = new BlockedSubmission();
        $blockedSubmission->setSource(SubmissionSource::suluForm());
        $blockedSubmission->reason = 'score-threshold-not-met';
        $blockedSubmission->createdAt = new \DateTimeImmutable($age);

        $this->blockedSubmissionRepository()->save($blockedSubmission);
    }
}
