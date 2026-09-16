<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Tests\Unit;

use Eekes\Sulu\SpamProtectionBundle\Domain\ValueObject\SubmissionSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubmissionSource::class)]
final class SubmissionSourceTest extends TestCase
{
    public function testAProjectCanNameItsOwnSource(): void
    {
        self::assertSame('newsletter', SubmissionSource::of('newsletter')->value);
    }

    public function testTheSuluFormSourceIsTheOneTheBundleUsesItself(): void
    {
        self::assertSame('sulu_form', SubmissionSource::suluForm()->value);
    }

    public function testAProjectCanKeepItsSourcesInAnEnum(): void
    {
        $source = SubmissionSource::fromEnum(ExampleProjectSource::Newsletter);

        self::assertSame('newsletter', $source->value);
    }

    public function testTwoSourcesWithTheSameValueAreTheSameSource(): void
    {
        self::assertTrue(SubmissionSource::of('quote')->equals(SubmissionSource::of('quote')));
        self::assertFalse(SubmissionSource::of('quote')->equals(SubmissionSource::suluForm()));
    }

    public function testItReadsAsItsValueWhereverAStringIsExpected(): void
    {
        self::assertSame('Blocked: quote', 'Blocked: '.SubmissionSource::of('quote'));
    }

    public function testAnEmptySourceIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SubmissionSource::of('');
    }

    /**
     * Silently cutting it off would make the log lie about where a submission came from.
     */
    public function testASourceThatDoesNotFitInTheColumnIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('longer than the 32 characters');

        SubmissionSource::of(str_repeat('a', SubmissionSource::MAX_LENGTH + 1));
    }
}

enum ExampleProjectSource: string
{
    case Newsletter = 'newsletter';
}
