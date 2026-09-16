<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Domain\ValueObject;

/**
 * Which form a blocked submission came from, so the log stays readable without knowing the
 * calling context.
 *
 * Deliberately not an enum: every project has its own forms, and a closed set would mean editing
 * this bundle to log a newsletter or a quote request. Projects that already keep their sources in
 * an enum pass their cases through fromEnum().
 */
final readonly class SubmissionSource implements \Stringable
{
    /**
     * Any form editors build in the Sulu admin, identified further by form id and title.
     */
    public const string SULU_FORM = 'sulu_form';

    /**
     * The column it is stored in; a longer value would be silently cut off by the database.
     */
    public const int MAX_LENGTH = 32;

    private function __construct(
        public string $value,
    ) {
    }

    public static function of(string $value): self
    {
        if ($value === '') {
            throw new \InvalidArgumentException('A submission source cannot be empty.');
        }

        if (\strlen($value) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(\sprintf('The submission source "%s" is longer than the %d characters that can be stored.', $value, self::MAX_LENGTH));
        }

        return new self($value);
    }

    /**
     * For projects that keep their own sources in a string backed enum.
     */
    public static function fromEnum(\BackedEnum $case): self
    {
        return self::of((string) $case->value);
    }

    public static function suluForm(): self
    {
        return self::of(self::SULU_FORM);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
