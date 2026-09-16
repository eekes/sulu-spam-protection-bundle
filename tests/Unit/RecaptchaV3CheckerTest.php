<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Tests\Unit;

use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Recaptcha\RecaptchaV3Checker;
use Eekes\Sulu\SpamProtectionBundle\Tests\Double\FakeRecaptchaRequestMethod;
use Karser\Recaptcha3Bundle\ReCaptcha\ReCaptcha;
use Karser\Recaptcha3Bundle\ReCaptcha\RequestMethod;
use Karser\Recaptcha3Bundle\ReCaptcha\RequestParameters;
use Karser\Recaptcha3Bundle\Services\IpResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(RecaptchaV3Checker::class)]
final class RecaptchaV3CheckerTest extends TestCase
{
    private const string ACTION = 'a_form';
    private const float THRESHOLD = 0.5;

    private FakeRecaptchaRequestMethod $google;

    protected function setUp(): void
    {
        $this->google = new FakeRecaptchaRequestMethod();
    }

    public function testItTrustsEverythingWhenRecaptchaIsDisabled(): void
    {
        $result = $this->createChecker(enabled: false)->check('a-token', self::ACTION);

        self::assertTrue($result->trusted);
        self::assertSame([], $this->google->submittedTokens());
    }

    public function testItRejectsAnEmptyTokenWithoutCallingGoogle(): void
    {
        $result = $this->createChecker()->check('', self::ACTION);

        self::assertFalse($result->trusted);
        self::assertSame('missing-input-response', $result->describe());
        self::assertSame([], $this->google->submittedTokens());
    }

    public function testItTrustsATokenWithAScoreAboveTheThreshold(): void
    {
        $this->google->queueScore(0.9, self::ACTION);

        $result = $this->createChecker()->check('a-token', self::ACTION);

        self::assertTrue($result->trusted);
        self::assertSame(0.9, $result->score);
        self::assertSame(['a-token'], $this->google->submittedTokens());
    }

    public function testItRejectsATokenWithAScoreBelowTheThresholdAndKeepsTheScore(): void
    {
        $this->google->queueScore(0.1, self::ACTION);

        $result = $this->createChecker()->check('a-token', self::ACTION);

        self::assertFalse($result->trusted);
        self::assertSame(0.1, $result->score);
        self::assertStringContainsString('score-threshold-not-met', $result->describe());
    }

    public function testItRejectsATokenThatWasGeneratedForAnotherAction(): void
    {
        $this->google->queueScore(0.9, 'some_other_form');

        $result = $this->createChecker()->check('a-token', self::ACTION);

        self::assertFalse($result->trusted);
        self::assertStringContainsString('action-mismatch', $result->describe());
    }

    public function testAReusedTokenIsReportedAsStaleInsteadOfSpam(): void
    {
        $this->google->queueErrors(['timeout-or-duplicate']);

        $result = $this->createChecker()->check('a-token', self::ACTION);

        self::assertFalse($result->trusted);
        self::assertTrue($result->isTokenStale());
    }

    public function testItTrustsTheVisitorButReportsWhenGoogleCannotBeReached(): void
    {
        $broken = new class implements RequestMethod {
            public function submit(RequestParameters $params): string
            {
                throw new \RuntimeException('Connection refused');
            }
        };

        $result = $this->createChecker(requestMethod: $broken)->check('a-token', self::ACTION);

        self::assertTrue($result->trusted);
        self::assertSame('Connection refused', $result->unavailableReason);
    }

    public function testASuccessfulCheckIsNotReportedAsUnavailable(): void
    {
        $this->google->queueScore(0.9, self::ACTION);

        self::assertFalse($this->createChecker()->check('a-token', self::ACTION)->isUnavailable());
    }

    private function createChecker(bool $enabled = true, ?RequestMethod $requestMethod = null): RecaptchaV3Checker
    {
        $recaptcha = (new ReCaptcha('test-secret', $requestMethod ?? $this->google))
            ->setScoreThreshold(self::THRESHOLD);

        return new RecaptchaV3Checker(
            static fn (): ReCaptcha => $recaptcha,
            new IpResolver(new RequestStack()),
            $enabled,
        );
    }
}
