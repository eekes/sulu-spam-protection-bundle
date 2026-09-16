<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Tests\Unit;

use Eekes\Sulu\SpamProtectionBundle\Domain\ValueObject\SpamCheckResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpamCheckResult::class)]
final class SpamCheckResultTest extends TestCase
{
    public function testATrustedResultCarriesTheScoreItWasGiven(): void
    {
        $result = SpamCheckResult::trusted(0.9);

        self::assertTrue($result->trusted);
        self::assertSame(0.9, $result->score);
        self::assertFalse($result->isUnavailable());
    }

    public function testARejectedResultKeepsTheScoreSoItCanBeLogged(): void
    {
        $result = SpamCheckResult::rejected(0.1, ['score-threshold-not-met']);

        self::assertFalse($result->trusted);
        self::assertSame(0.1, $result->score);
        self::assertSame('score-threshold-not-met', $result->describe());
    }

    /**
     * An outage may never cost the customer a lead, so the visitor is trusted - but the caller has
     * to be able to see that nothing was actually checked.
     */
    public function testAnUnavailableProviderTrustsTheVisitorAndSaysSo(): void
    {
        $result = SpamCheckResult::unavailable('Connection refused');

        self::assertTrue($result->trusted);
        self::assertTrue($result->isUnavailable());
        self::assertSame('Connection refused', $result->unavailableReason);
    }

    public function testAReusedTokenIsReportedAsStaleInsteadOfSpam(): void
    {
        self::assertTrue(SpamCheckResult::rejected(null, ['timeout-or-duplicate'])->isTokenStale());
        self::assertFalse(SpamCheckResult::rejected(0.1, ['score-threshold-not-met'])->isTokenStale());
    }

    public function testAFailureWithoutErrorCodesStillDescribesItself(): void
    {
        self::assertSame('unknown-error', SpamCheckResult::rejected(null, [])->describe());
    }
}
