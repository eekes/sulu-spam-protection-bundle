<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller;

use Eekes\Sulu\SpamProtectionBundle\Domain\Repository\BlockedSubmissionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Deletes a selection at once. Emptying a spam log one row at a time is not work anyone should
 * have to do, and the list view offers the selection, so the endpoint behind it has to exist.
 *
 * The shape is the one the administration interface uses for every resource: a DELETE on the
 * collection with the ids as a comma separated query parameter.
 */
final class BlockedSubmissionBatchDeleteController extends AbstractBlockedSubmissionController
{
    public const string ROUTE = 'eekes_sulu_spam_protection.delete_blocked_submissions';

    public function __construct(
        private readonly BlockedSubmissionRepository $blockedSubmissionRepository,
    ) {
    }

    #[Route(
        path: '/blocked-submissions',
        name: self::ROUTE,
        methods: ['DELETE'],
    )]
    public function __invoke(Request $request): Response
    {
        $ids = array_values(array_filter(array_map(
            static fn (string $id): int => (int) trim($id),
            explode(',', $request->query->getString('ids')),
        )));

        if ($ids === []) {
            // Answering 204 here would report a delete that never happened.
            throw new BadRequestHttpException('The "ids" parameter is missing or holds no id.');
        }

        $this->blockedSubmissionRepository->deleteByIds($ids);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
