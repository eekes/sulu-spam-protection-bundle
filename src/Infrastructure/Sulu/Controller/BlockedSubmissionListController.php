<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller;

use Eekes\Sulu\SpamProtectionBundle\Domain\Entity\BlockedSubmission;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BlockedSubmissionListController extends AbstractBlockedSubmissionController
{
    /**
     * Registered with Sulu as the list route of the resource by the container extension.
     */
    public const string ROUTE = 'eekes_sulu_spam_protection.get_blocked_submissions';

    public function __construct(
        private readonly RestHelperInterface $restHelper,
        private readonly FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private readonly DoctrineListBuilderFactoryInterface $listBuilderFactory,
    ) {
    }

    #[Route(
        path: '/blocked-submissions',
        name: self::ROUTE,
        methods: ['GET'],
    )]
    public function __invoke(): Response
    {
        $listBuilder = $this->listBuilderFactory->create(BlockedSubmission::class);
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(BlockedSubmission::SULU_LIST_KEY);

        if ($fieldDescriptors === null) {
            throw new \LogicException(\sprintf('The list "%s" is not registered. Check that the bundle could add its list directory to sulu_admin.', BlockedSubmission::SULU_LIST_KEY));
        }

        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);

        $list = new PaginatedRepresentation(
            $listBuilder->execute(),
            BlockedSubmission::SULU_RESOURCE_KEY,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            (int) $listBuilder->count(),
        );

        return $this->json($list->toArray());
    }
}
