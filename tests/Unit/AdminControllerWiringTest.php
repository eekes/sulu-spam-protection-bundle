<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Tests\Unit;

use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller\BlockedSubmissionBatchDeleteController;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller\BlockedSubmissionDeleteController;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller\BlockedSubmissionDetailController;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller\BlockedSubmissionListController;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Symfony\DependencyInjection\EekesSuluSpamProtectionExtension;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * The admin controllers extend Symfony's AbstractController, which is useless until its
 * #[Required] setContainer() has been called with the locator of the services it subscribes to.
 *
 * Autoconfiguration is what normally arranges that, and these controllers deliberately do without
 * it - see config/services_sulu_admin.php for why. Everything else about them kept working when
 * the two replacements for it went missing: the services were built, the routes were registered
 * and the container linted, and only a real request failed, with
 * "has no container set, did you forget to define it as a service subscriber?".
 *
 * So the assertion is on the compiled definition rather than on the tags that produce it: what
 * matters is that the call is there, whichever way it got there.
 */
#[CoversNothing]
final class AdminControllerWiringTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function controllers(): iterable
    {
        yield 'list' => [BlockedSubmissionListController::class];
        yield 'detail' => [BlockedSubmissionDetailController::class];
        yield 'delete' => [BlockedSubmissionDeleteController::class];
        yield 'batch delete' => [BlockedSubmissionBatchDeleteController::class];
    }

    /**
     * @param class-string $controller
     */
    #[DataProvider('controllers')]
    public function testEveryControllerIsHandedTheContainerItNeedsToRun(string $controller): void
    {
        $container = $this->compile();

        $arguments = null;

        // Definition::getMethodCalls() is untyped, so the shape is checked rather than assumed.
        foreach ($container->getDefinition($controller)->getMethodCalls() as $call) {
            self::assertIsArray($call);

            if (($call[0] ?? null) !== 'setContainer') {
                continue;
            }

            self::assertNull($arguments, \sprintf('%s is handed a container more than once.', $controller));
            self::assertIsArray($call[1] ?? null);

            $arguments = $call[1];
        }

        self::assertNotNull(
            $arguments,
            \sprintf('%s is never handed a container, so every request to it fails in Symfony\'s ControllerResolver.', $controller),
        );

        // A locator of what AbstractController subscribes to, not the container as a whole. The
        // compiler inlines it into the call, so the definition sits there rather than a reference.
        $locator = $arguments[0] ?? null;
        self::assertInstanceOf(Definition::class, $locator);
        self::assertSame(ServiceLocator::class, $locator->getClass());
    }

    /**
     * A compiled container rather than a freshly loaded one: the setContainer() call is added by
     * the compiler passes, which is exactly the step that was missing.
     */
    private function compile(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SuluAdminBundle' => 'Sulu\Bundle\AdminBundle\SuluAdminBundle']);
        $container->setParameter('karser_recaptcha3.enabled', true);

        // The collaborators the extension refers to. Their classes are irrelevant here: the test
        // is about how the controllers are built, not about what they are given.
        foreach ([
            'doctrine',
            'request_stack',
            'logger',
            'karser_recaptcha3.google.recaptcha',
            'karser_recaptcha3.ip_resolver',
            'sulu_admin.view_builder_factory',
            'sulu_security.security_checker',
            'sulu_core.doctrine_rest_helper',
            'sulu_core.list_builder.field_descriptor_factory',
            'sulu_core.doctrine_list_builder_factory',
        ] as $id) {
            $container->setDefinition($id, (new Definition(\stdClass::class))->setPublic(true));
        }

        (new EekesSuluSpamProtectionExtension())->load([[]], $container);

        foreach (self::controllers() as [$controller]) {
            $container->getDefinition($controller)->setPublic(true);
        }

        $container->compile();

        return $container;
    }
}
