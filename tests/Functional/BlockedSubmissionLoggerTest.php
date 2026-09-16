<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Tests\Functional;

use Eekes\Sulu\SpamProtectionBundle\Application\Service\BlockedSubmissionLogger;
use Eekes\Sulu\SpamProtectionBundle\Domain\ValueObject\SpamCheckResult;
use Eekes\Sulu\SpamProtectionBundle\Domain\ValueObject\SubmissionSource;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(BlockedSubmissionLogger::class)]
final class BlockedSubmissionLoggerTest extends FunctionalTestCase
{
    public function testABlockedSubmissionIsStoredWithEverythingNeededToJudgeIt(): void
    {
        $this->onPage('https://example.com/contact', ipAddress: '203.0.113.9', userAgent: 'CurlBot/1.0');

        $this->logger()->logBlocked(
            SubmissionSource::suluForm(),
            SpamCheckResult::rejected(0.1, ['score-threshold-not-met']),
            ['email' => 'spam@example.com'],
            sourceId: 7,
            sourceName: 'Contactformulier',
            locale: 'nl',
        );

        $blocked = $this->blockedSubmissions();
        self::assertCount(1, $blocked);
        self::assertSame(SubmissionSource::SULU_FORM, $blocked[0]->source);
        self::assertSame(7, $blocked[0]->sourceId);
        self::assertSame('Contactformulier', $blocked[0]->sourceName);
        self::assertSame(['email' => 'spam@example.com'], $blocked[0]->payload);
        self::assertSame(0.1, $blocked[0]->score);
        self::assertSame('score-threshold-not-met', $blocked[0]->reason);
        self::assertSame('nl', $blocked[0]->locale);
        self::assertSame('203.0.113.9', $blocked[0]->ipAddress);
        self::assertSame('CurlBot/1.0', $blocked[0]->userAgent);
    }

    /**
     * A live component posts to /_components/..., so only the referer says which page the visitor
     * was actually on.
     */
    public function testThePageIsTakenFromTheRefererWhenThereIsOne(): void
    {
        $this->onPage('https://example.com/_components/UI:NewsletterSubscribe', referer: 'https://example.com/contact');

        $this->logger()->logBlocked(SubmissionSource::of('newsletter'), SpamCheckResult::rejected(0.1, ['bad']));

        self::assertSame('https://example.com/contact', $this->blockedSubmissions()[0]->url);
    }

    public function testTheLocaleOfTheRequestIsUsedWhenTheCallerKnowsNoBetter(): void
    {
        $this->onPage('https://example.com/contact', locale: 'fr');

        $this->logger()->logBlocked(SubmissionSource::suluForm(), SpamCheckResult::rejected(null, ['bad']));

        self::assertSame('fr', $this->blockedSubmissions()[0]->locale);
    }

    /**
     * A bot can post a megabyte of text; the log only has to stay readable enough to follow up on
     * a false positive.
     */
    public function testAnEnormousAnswerIsCutOffInsteadOfStoredWhole(): void
    {
        $this->onPage('https://example.com/contact');

        $this->logger()->logBlocked(
            SubmissionSource::suluForm(),
            SpamCheckResult::rejected(0.1, ['bad']),
            ['message' => str_repeat('a', 25000)],
        );

        $stored = $this->blockedSubmissions()[0]->payload['message'];
        self::assertIsString($stored);
        self::assertLessThan(25000, \strlen($stored));
        self::assertStringEndsWith('…', $stored);
    }

    /**
     * A group of checkboxes posts a list, so a bot that hides its megabyte one level down would
     * walk straight past a cap that only looks at the top.
     */
    public function testAnEnormousAnswerInsideAListIsCutOffToo(): void
    {
        $this->onPage('https://example.com/contact');

        $this->logger()->logBlocked(
            SubmissionSource::suluForm(),
            SpamCheckResult::rejected(0.1, ['bad']),
            ['interests' => ['fine', str_repeat('a', 25000)]],
        );

        $stored = $this->blockedSubmissions()[0]->payload['interests'];
        self::assertIsArray($stored);
        self::assertSame('fine', $stored[0]);
        self::assertIsString($stored[1]);
        self::assertLessThan(25000, \strlen($stored[1]));
        self::assertStringEndsWith('…', $stored[1]);
    }

    /**
     * Cutting on bytes can split a multi-byte character, and json_encode refuses what comes out -
     * which used to cost the whole entry rather than the tail of one answer.
     */
    public function testAnEnormousAnswerFullOfAccentsIsStillStored(): void
    {
        $this->onPage('https://example.com/contact');

        $this->logger()->logBlocked(
            SubmissionSource::suluForm(),
            SpamCheckResult::rejected(0.1, ['bad']),
            ['message' => str_repeat('é', 25000)],
        );

        $blocked = $this->blockedSubmissions();
        self::assertCount(1, $blocked, 'The blocked submission was dropped instead of stored.');

        $stored = $blocked[0]->payload['message'];
        self::assertIsString($stored);
        self::assertStringEndsWith('…', $stored);
    }

    public function testItStillWorksOutsideOfARequest(): void
    {
        $this->logger()->logBlocked(SubmissionSource::suluForm(), SpamCheckResult::rejected(null, ['bad']));

        $blocked = $this->blockedSubmissions();
        self::assertCount(1, $blocked);
        self::assertNull($blocked[0]->ipAddress);
    }

    /**
     * An outage is an operational problem, not spam, so it only goes to the log file.
     */
    public function testASubmissionThatWasNeverCheckedIsReportedButNotStored(): void
    {
        $this->logger()->logUnchecked(
            SubmissionSource::suluForm(),
            SpamCheckResult::unavailable('Connection refused'),
        );

        self::assertSame([], $this->blockedSubmissions());
        self::assertTrue($this->spamProtectionLog()->hasWarningThatContains('Submission accepted without a spam check.'));
    }

    /**
     * Switched off for customers who may not keep what was sent.
     */
    public function testThePayloadCanBeLeftOutEntirely(): void
    {
        $this->onPage('https://example.com/contact');

        $this->logger(storePayload: false)->logBlocked(
            SubmissionSource::suluForm(),
            SpamCheckResult::rejected(0.1, ['score-threshold-not-met']),
            ['email' => 'spam@example.com'],
        );

        $blocked = $this->blockedSubmissions();
        self::assertSame([], $blocked[0]->payload);
        // The technical context is what makes the log worth keeping at all, so it stays.
        self::assertSame('score-threshold-not-met', $blocked[0]->reason);
    }

    /**
     * Enough to recognise a network across submissions, not enough to identify a visitor.
     */
    public function testAnIpv4AddressCanBeStoredWithoutItsHostPart(): void
    {
        $this->onPage('https://example.com/contact', ipAddress: '203.0.113.9');

        $this->logger(anonymizeIp: true)->logBlocked(
            SubmissionSource::suluForm(),
            SpamCheckResult::rejected(0.1, ['bad']),
        );

        self::assertSame('203.0.113.0', $this->blockedSubmissions()[0]->ipAddress);
    }

    public function testAnIpv6AddressKeepsOnlyItsNetwork(): void
    {
        $this->onPage('https://example.com/contact', ipAddress: '2001:db8:1234:5678::1');

        $this->logger(anonymizeIp: true)->logBlocked(
            SubmissionSource::suluForm(),
            SpamCheckResult::rejected(0.1, ['bad']),
        );

        self::assertSame('2001:db8:1234::', $this->blockedSubmissions()[0]->ipAddress);
    }

    public function testTheAddressIsStoredWholeWhenAnonymisingIsOff(): void
    {
        $this->onPage('https://example.com/contact', ipAddress: '203.0.113.9');

        $this->logger()->logBlocked(SubmissionSource::suluForm(), SpamCheckResult::rejected(0.1, ['bad']));

        self::assertSame('203.0.113.9', $this->blockedSubmissions()[0]->ipAddress);
    }

    /**
     * The configured logger, or one built with other switches to cover what the configuration does.
     */
    private function logger(bool $storePayload = true, bool $anonymizeIp = false): BlockedSubmissionLogger
    {
        if ($storePayload && !$anonymizeIp) {
            $logger = self::getContainer()->get('eekes_test.blocked_submission_logger');
            \assert($logger instanceof BlockedSubmissionLogger);

            return $logger;
        }

        $requestStack = self::getContainer()->get('eekes_test.request_stack');
        \assert($requestStack instanceof RequestStack);

        return new BlockedSubmissionLogger(
            $this->blockedSubmissionRepository(),
            $requestStack,
            new NullLogger(),
            $storePayload,
            $anonymizeIp,
        );
    }

    private function onPage(
        string $uri,
        ?string $referer = null,
        string $ipAddress = '198.51.100.4',
        string $userAgent = 'Mozilla/5.0',
        string $locale = 'nl',
    ): void {
        $headers = ['REMOTE_ADDR' => $ipAddress, 'HTTP_USER_AGENT' => $userAgent];

        if ($referer !== null) {
            $headers['HTTP_REFERER'] = $referer;
        }

        $request = Request::create($uri, 'POST', server: $headers);
        $request->setLocale($locale);

        $requestStack = self::getContainer()->get('eekes_test.request_stack');
        \assert($requestStack instanceof RequestStack);
        $requestStack->push($request);
    }
}
