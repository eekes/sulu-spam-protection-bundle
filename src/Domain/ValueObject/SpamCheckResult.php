<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Domain\ValueObject;

/**
 * The outcome of a single spam check. Keeps the score instead of only a yes or no, so every
 * caller can decide for itself what to do with a rejected submission.
 */
final readonly class SpamCheckResult
{
    /**
     * @param string[] $errorCodes
     */
    private function __construct(
        public bool $trusted,
        public ?float $score = null,
        public array $errorCodes = [],
        public ?string $unavailableReason = null,
    ) {
    }

    public static function trusted(?float $score = null): self
    {
        return new self(true, $score);
    }

    /**
     * @param string[] $errorCodes
     */
    public static function rejected(?float $score, array $errorCodes): self
    {
        return new self(false, $score, $errorCodes);
    }

    /**
     * The check could not be performed. The visitor is trusted so an outage at the provider never
     * blocks a genuine submission, but the caller should record that it happened.
     */
    public static function unavailable(string $reason): self
    {
        return new self(true, null, [], $reason);
    }

    /**
     * True when the submission went through without ever being checked.
     */
    public function isUnavailable(): bool
    {
        return $this->unavailableReason !== null;
    }

    /**
     * The token was already used or is older than two minutes. That is not spam but a visitor
     * submitting twice, so it should be answered with "try again" instead.
     */
    public function isTokenStale(): bool
    {
        return \in_array('timeout-or-duplicate', $this->errorCodes, true);
    }

    /**
     * A human readable summary of why the check failed, for the log of blocked submissions.
     */
    public function describe(): string
    {
        return implode(', ', $this->errorCodes) ?: 'unknown-error';
    }
}
