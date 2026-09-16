<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Tests\Unit;

use Eekes\Sulu\SpamProtectionBundle\Application\EventListener\SuluFormSpamListener;
use Eekes\Sulu\SpamProtectionBundle\Application\Service\SpamCheckerInterface;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Admin\BlockedSubmissionAdmin;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller\BlockedSubmissionListController;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\FormField\RecaptchaFieldType;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Symfony\DependencyInjection\EekesSuluSpamProtectionExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Which services the extension registers, depending on the bundles a project has.
 *
 * The container is built the way Symfony builds it for an extension: load() is handed a throwaway
 * container that carries the parameters but none of the other extensions. Registering the Sulu
 * services off hasExtension() therefore silently registered nothing at all, which no test in the
 * light kernel could notice - it has no Sulu bundles either way.
 */
#[CoversClass(EekesSuluSpamProtectionExtension::class)]
final class EekesSuluSpamProtectionExtensionTest extends TestCase
{
    public function testAProjectWithoutSuluGetsOnlyTheCheckerAndTheLog(): void
    {
        $container = $this->load([]);

        self::assertTrue($container->hasAlias(SpamCheckerInterface::class));
        self::assertFalse($container->hasDefinition(RecaptchaFieldType::class));
        self::assertFalse($container->hasDefinition(BlockedSubmissionAdmin::class));
    }

    public function testTheFormFieldIsRegisteredForAProjectWithTheFormBundle(): void
    {
        $container = $this->load(['SuluFormBundle' => 'Sulu\Bundle\FormBundle\SuluFormBundle']);

        self::assertTrue($container->hasDefinition(RecaptchaFieldType::class));
        self::assertTrue($container->hasDefinition(SuluFormSpamListener::class));
        self::assertFalse($container->hasDefinition(BlockedSubmissionAdmin::class));
    }

    public function testTheAdminListIsRegisteredForAProjectWithTheAdminBundle(): void
    {
        $container = $this->load(['SuluAdminBundle' => 'Sulu\Bundle\AdminBundle\SuluAdminBundle']);

        self::assertTrue($container->hasDefinition(BlockedSubmissionAdmin::class));
        self::assertTrue($container->hasDefinition(BlockedSubmissionListController::class));
        self::assertFalse($container->hasDefinition(RecaptchaFieldType::class));
    }

    /**
     * Autoconfiguration answers the #[Route] attribute on these controllers with the
     * routing.controller tag, and an application importing "resource: routing.controllers" then
     * publishes every route a second time without the /admin/api prefix a project imports them
     * under - outside the firewall that guards the administration interface.
     */
    public function testTheControllersAreNotPublishedAsRoutesOfTheirOwn(): void
    {
        $container = $this->load(['SuluAdminBundle' => 'Sulu\Bundle\AdminBundle\SuluAdminBundle']);

        $controller = $container->getDefinition(BlockedSubmissionListController::class);

        self::assertFalse($controller->isAutoconfigured());
        self::assertSame([], $controller->getTag('routing.controller'));
        // Without this one the controller cannot be resolved from the container at all.
        self::assertNotSame([], $controller->getTag('controller.service_arguments'));
    }

    public function testTheAdminListCanBeSwitchedOffWhileTheRestKeepsWorking(): void
    {
        $container = $this->load(
            ['SuluAdminBundle' => 'Sulu\Bundle\AdminBundle\SuluAdminBundle'],
            [['admin' => ['enabled' => false]]],
        );

        self::assertFalse($container->hasDefinition(BlockedSubmissionAdmin::class));
        self::assertTrue($container->hasAlias(SpamCheckerInterface::class));
    }

    /**
     * @param array<string, string> $bundles the kernel.bundles parameter of the project
     * @param array<array<mixed>>   $configs
     */
    private function load(array $bundles, array $configs = [[]]): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);
        $container->setParameter('karser_recaptcha3.enabled', true);

        (new EekesSuluSpamProtectionExtension())->load($configs, $container);

        return $container;
    }
}
