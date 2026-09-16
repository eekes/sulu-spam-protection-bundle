<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller;

use Eekes\Sulu\SpamProtectionBundle\Domain\Entity\BlockedSubmission;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Read-only on purpose: a blocked submission is a log entry, so there is no POST or PUT to change
 * one after the fact.
 */
final class BlockedSubmissionDetailController extends AbstractBlockedSubmissionController
{
    /**
     * Registered with Sulu as the detail route of the resource by the container extension.
     */
    public const string ROUTE = 'eekes_sulu_spam_protection.get_blocked_submission';

    #[Route(
        path: '/blocked-submissions/{id}',
        name: self::ROUTE,
        requirements: ['id' => '\d+'],
        methods: ['GET'],
    )]
    public function __invoke(BlockedSubmission $blockedSubmission): Response
    {
        return $this->json($this->getDataForEntity($blockedSubmission));
    }
}
