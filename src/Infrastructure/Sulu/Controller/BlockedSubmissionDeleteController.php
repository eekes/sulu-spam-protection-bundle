<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller;

use Eekes\Sulu\SpamProtectionBundle\Domain\Entity\BlockedSubmission;
use Eekes\Sulu\SpamProtectionBundle\Domain\Repository\BlockedSubmissionRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BlockedSubmissionDeleteController extends AbstractBlockedSubmissionController
{
    public const string ROUTE = 'eekes_sulu_spam_protection.delete_blocked_submission';

    public function __construct(
        private readonly BlockedSubmissionRepository $blockedSubmissionRepository,
    ) {
    }

    #[Route(
        path: '/blocked-submissions/{id}',
        name: self::ROUTE,
        requirements: ['id' => '\d+'],
        methods: ['DELETE'],
    )]
    public function __invoke(BlockedSubmission $blockedSubmission): Response
    {
        $this->blockedSubmissionRepository->remove($blockedSubmission);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
