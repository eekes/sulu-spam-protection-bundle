<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Application\Service;

use Eekes\Sulu\SpamProtectionBundle\Domain\Entity\BlockedSubmission;
use Eekes\Sulu\SpamProtectionBundle\Domain\Repository\BlockedSubmissionRepository;
use Eekes\Sulu\SpamProtectionBundle\Domain\ValueObject\SpamCheckResult;
use Eekes\Sulu\SpamProtectionBundle\Domain\ValueObject\SubmissionSource;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Records a submission that the spam check stopped, together with the request context needed to
 * tell a bot apart from a visitor who was flagged by mistake.
 *
 * What is kept is what a site owner has to be able to defend, so both the answers and the IP
 * address can be dialled back through the configuration.
 */
final readonly class BlockedSubmissionLogger
{
    /**
     * A bot can post a megabyte of text. The log only has to stay readable enough to follow up on
     * a false positive, so long answers are cut off rather than stored whole.
     */
    private const int MAX_VALUE_LENGTH = 10000;

    /**
     * How much of an anonymised address is kept: the network, not the host. Three bytes leaves an
     * IPv4 address as a /24, six bytes leaves an IPv6 address as a /48.
     */
    private const int KEPT_IPV4_BYTES = 3;
    private const int KEPT_IPV6_BYTES = 6;

    public function __construct(
        private BlockedSubmissionRepository $blockedSubmissionRepository,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
        private bool $storePayload = true,
        private bool $anonymizeIp = false,
    ) {
    }

    /**
     * @param array<string, mixed> $payload what the visitor filled in, without technical fields
     */
    public function logBlocked(
        SubmissionSource $source,
        SpamCheckResult $result,
        array $payload = [],
        ?int $sourceId = null,
        ?string $sourceName = null,
        ?string $locale = null,
    ): void {
        $request = $this->requestStack->getCurrentRequest();

        $blockedSubmission = new BlockedSubmission();
        $blockedSubmission->setSource($source);
        $blockedSubmission->sourceId = $sourceId;
        $blockedSubmission->sourceName = $sourceName;
        $blockedSubmission->payload = $this->storePayload ? $this->truncate($payload) : [];
        $blockedSubmission->score = $result->score;
        $blockedSubmission->reason = $result->describe();
        $blockedSubmission->locale = $locale ?? $request?->getLocale();
        // A live component posts to /_components/..., so the page the visitor was on only shows up
        // in the referer.
        $blockedSubmission->url = $request?->headers->get('Referer') ?? $request?->getUri();
        $blockedSubmission->ipAddress = $this->anonymize($request?->getClientIp());
        $blockedSubmission->userAgent = $request?->headers->get('User-Agent');

        try {
            $this->blockedSubmissionRepository->save($blockedSubmission);
        } catch (\Throwable $exception) {
            // Logging spam may never break the response of the form itself. Deliberately wider
            // than a database failure: an entity manager that an earlier flush already closed
            // throws an ORM exception, which is not a DBAL one.
            $this->logger->error('Could not store blocked submission.', ['exception' => $exception]);
        }
    }

    /**
     * The spam check could not be performed, so the submission was accepted without being checked.
     * That is an operational problem rather than spam, so it only goes to the log file.
     */
    public function logUnchecked(SubmissionSource $source, SpamCheckResult $result): void
    {
        $this->logger->warning('Submission accepted without a spam check.', [
            'source' => $source->value,
            'reason' => $result->unavailableReason,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function truncate(array $payload): array
    {
        foreach ($payload as $key => $value) {
            $payload[$key] = $this->truncateValue($value);
        }

        return $payload;
    }

    /**
     * A form field can hold a list rather than a single answer - a group of checkboxes posts an
     * array - so the cap has to reach every value, not only the ones at the top.
     */
    private function truncateValue(mixed $value): mixed
    {
        if (\is_array($value)) {
            return array_map($this->truncateValue(...), $value);
        }

        if (\is_string($value) && mb_strlen($value) > self::MAX_VALUE_LENGTH) {
            // Characters, not bytes: cutting in the middle of a multi-byte character leaves a
            // string that json_encode refuses, which would lose the whole entry.
            return mb_substr($value, 0, self::MAX_VALUE_LENGTH).'…';
        }

        return $value;
    }

    /**
     * Masks the host part of the address, so a network can still be recognised across submissions
     * while the visitor behind it cannot be identified.
     */
    private function anonymize(?string $ipAddress): ?string
    {
        if ($ipAddress === null || !$this->anonymizeIp) {
            return $ipAddress;
        }

        $packed = @inet_pton($ipAddress);

        if ($packed === false) {
            // An address that cannot be read cannot be masked either, and storing it raw would be
            // exactly what was switched off.
            return null;
        }

        $kept = \strlen($packed) === 4 ? self::KEPT_IPV4_BYTES : self::KEPT_IPV6_BYTES;
        $masked = inet_ntop(substr($packed, 0, $kept).str_repeat("\0", \strlen($packed) - $kept));

        return $masked === false ? null : $masked;
    }
}
