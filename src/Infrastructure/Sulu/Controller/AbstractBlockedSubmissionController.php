<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller;

use Eekes\Sulu\SpamProtectionBundle\Domain\Entity\BlockedSubmission;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Centralises the security context, so every endpoint of the resource is guarded by the same
 * permission as the views in BlockedSubmissionAdmin.
 */
abstract class AbstractBlockedSubmissionController extends AbstractController implements SecuredControllerInterface
{
    public function getSecurityContext(): string
    {
        return BlockedSubmission::SULU_SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): ?string
    {
        return $request->query->getString('locale') ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDataForEntity(BlockedSubmission $blockedSubmission): array
    {
        return [
            'id' => $blockedSubmission->id,
            'source' => $blockedSubmission->source,
            'sourceId' => $blockedSubmission->sourceId,
            'sourceName' => $blockedSubmission->sourceName,
            // A form in the administration interface shows text, not a nested structure, so the
            // answers are rendered as readable JSON.
            'payload' => json_encode(
                $blockedSubmission->payload,
                \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
            ) ?: '',
            'score' => $blockedSubmission->score,
            'reason' => $blockedSubmission->reason,
            'locale' => $blockedSubmission->locale,
            'url' => $blockedSubmission->url,
            'ipAddress' => $blockedSubmission->ipAddress,
            'userAgent' => $blockedSubmission->userAgent,
            'createdAt' => $blockedSubmission->createdAt->format('c'),
        ];
    }
}
