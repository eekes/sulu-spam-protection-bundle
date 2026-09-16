<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Tests\Functional;

use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller\BlockedSubmissionBatchDeleteController;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller\BlockedSubmissionDeleteController;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller\BlockedSubmissionDetailController;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller\BlockedSubmissionListController;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * The routing file a project imports points at a directory of controllers by a relative path, and
 * the route names it produces are what the container extension hands to Sulu. Nothing else in the
 * suite touches either, so a rename or a moved folder would otherwise only show up as an empty
 * list in a real administration interface.
 */
#[CoversNothing]
final class AdminRoutingTest extends FunctionalTestCase
{
    public function testTheListRouteIsRegisteredWhereSuluIsToldItIs(): void
    {
        $route = $this->route(BlockedSubmissionListController::ROUTE);

        self::assertSame('/admin/api/blocked-submissions', $route->getPath());
        self::assertSame(['GET'], $route->getMethods());
    }

    public function testTheDetailRouteIsRegisteredWhereSuluIsToldItIs(): void
    {
        $route = $this->route(BlockedSubmissionDetailController::ROUTE);

        self::assertSame('/admin/api/blocked-submissions/{id}', $route->getPath());
        self::assertSame(['GET'], $route->getMethods());
    }

    public function testASubmissionCanBeDeletedFromTheSameUrl(): void
    {
        $route = $this->route(BlockedSubmissionDeleteController::ROUTE);

        self::assertSame('/admin/api/blocked-submissions/{id}', $route->getPath());
        self::assertSame(['DELETE'], $route->getMethods());
    }

    /**
     * The list view offers a selection, so the administration interface deletes it in one call:
     * a DELETE on the collection with the ids as a query parameter.
     */
    public function testAWholeSelectionCanBeDeletedInOneCall(): void
    {
        $route = $this->route(BlockedSubmissionBatchDeleteController::ROUTE);

        self::assertSame('/admin/api/blocked-submissions', $route->getPath());
        self::assertSame(['DELETE'], $route->getMethods());
    }

    /**
     * A blocked submission is a log entry, so there is deliberately nothing to create or update.
     */
    public function testThereIsNoRouteThatWritesToTheLog(): void
    {
        foreach ($this->router()->getRouteCollection() as $route) {
            self::assertSame([], array_intersect(['POST', 'PUT', 'PATCH'], $route->getMethods()));
        }
    }

    private function route(string $name): Route
    {
        $route = $this->router()->getRouteCollection()->get($name);

        self::assertInstanceOf(Route::class, $route, \sprintf('The route "%s" was not registered.', $name));

        return $route;
    }

    private function router(): RouterInterface
    {
        $router = self::getContainer()->get('eekes_test.router');
        \assert($router instanceof RouterInterface);

        return $router;
    }
}
