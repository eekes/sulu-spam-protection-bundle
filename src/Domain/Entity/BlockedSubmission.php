<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Domain\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Eekes\Sulu\SpamProtectionBundle\Domain\Repository\BlockedSubmissionRepository;
use Eekes\Sulu\SpamProtectionBundle\Domain\ValueObject\SubmissionSource;

/**
 * Write-once log of submissions the spam check stopped, so it stays visible which requests are
 * being blocked and a genuine visitor who got caught can still be helped.
 *
 * The table name in the attribute is only the default: BlockedSubmissionTableListener applies the
 * name a project configured.
 */
#[ORM\Entity(repositoryClass: BlockedSubmissionRepository::class)]
#[ORM\Table(name: self::DEFAULT_TABLE_NAME)]
#[ORM\Index(name: 'idx_eekes_blocked_submission_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_eekes_blocked_submission_source', columns: ['source'])]
class BlockedSubmission
{
    public const string DEFAULT_TABLE_NAME = 'eekes_blocked_submission';

    final public const string SULU_RESOURCE_KEY = 'eekes_blocked_submissions';
    final public const string SULU_LIST_KEY = 'eekes_blocked_submissions';
    final public const string SULU_FORM_KEY = 'eekes_blocked_submission_details';
    final public const string SULU_SECURITY_CONTEXT = 'sulu.settings.eekes_blocked_submissions';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int $id;

    /**
     * The raw value of a SubmissionSource; see source() and setSource() for the value object.
     */
    #[ORM\Column(length: SubmissionSource::MAX_LENGTH)]
    public string $source;

    /**
     * The id of the form the submission belongs to, for sources that have one.
     */
    #[ORM\Column(nullable: true)]
    public ?int $sourceId = null;

    /**
     * The title of the form as the editor named it, for sources that have one.
     */
    #[ORM\Column(length: 255, nullable: true)]
    public ?string $sourceName = null;

    /**
     * Everything the visitor filled in, without the technical fields of the form.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    public array $payload = [];

    /**
     * The score of the spam check, only known when the provider returned one.
     */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    public ?float $score = null;

    /**
     * The error codes that made the check fail.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $reason = null;

    #[ORM\Column(length: 5, nullable: true)]
    public ?string $locale = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $url = null;

    #[ORM\Column(length: 45, nullable: true)]
    public ?string $ipAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $userAgent = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function source(): SubmissionSource
    {
        return SubmissionSource::of($this->source);
    }

    public function setSource(SubmissionSource $source): void
    {
        $this->source = $source->value;
    }
}
