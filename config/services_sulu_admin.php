<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eekes\Sulu\SpamProtectionBundle\Domain\Repository\BlockedSubmissionRepository;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Admin\BlockedSubmissionAdmin;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller\BlockedSubmissionBatchDeleteController;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller\BlockedSubmissionDeleteController;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller\BlockedSubmissionDetailController;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller\BlockedSubmissionListController;

/*
 * Loaded only when the Sulu admin bundle is registered: the list of blocked submissions and the
 * endpoints behind it.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(BlockedSubmissionAdmin::class)
        ->arg('$viewBuilderFactory', service('sulu_admin.view_builder_factory'))
        ->arg('$securityChecker', service('sulu_security.security_checker'))
        ->arg('$navigationPosition', param('eekes_sulu_spam_protection.navigation_position'))
        ->tag('sulu.admin');

    // The controllers are tagged by hand and never autoconfigured. Symfony answers a #[Route]
    // attribute on an autoconfigured service with the routing.controller tag as well, and an
    // application that imports "resource: routing.controllers" - the default of the Symfony
    // skeleton - then publishes these routes a second time, without the /admin/api prefix they are
    // imported under. That second set falls outside the firewall guarding the administration
    // interface, which would leave the log readable, and deletable, by anyone knowing the path.
    //
    // Skipping autoconfiguration means the two things AbstractController relies on have to be set
    // by hand: autowire(), so the #[Required] setContainer() of the base class is called at all,
    // and the container.service_subscriber tag, so the argument of that call resolves to the
    // service locator holding what the base class subscribes to. Without them the controller is
    // built without a container and every request dies in ControllerResolver.
    $services->set(BlockedSubmissionListController::class)
        ->autowire()
        ->tag('container.service_subscriber')
        ->arg('$restHelper', service('sulu_core.doctrine_rest_helper'))
        ->arg('$fieldDescriptorFactory', service('sulu_core.list_builder.field_descriptor_factory'))
        ->arg('$listBuilderFactory', service('sulu_core.doctrine_list_builder_factory'))
        ->tag('controller.service_arguments');

    $services->set(BlockedSubmissionDetailController::class)
        ->autowire()
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(BlockedSubmissionDeleteController::class)
        ->autowire()
        ->tag('container.service_subscriber')
        ->arg('$blockedSubmissionRepository', service(BlockedSubmissionRepository::class))
        ->tag('controller.service_arguments');

    $services->set(BlockedSubmissionBatchDeleteController::class)
        ->autowire()
        ->tag('container.service_subscriber')
        ->arg('$blockedSubmissionRepository', service(BlockedSubmissionRepository::class))
        ->tag('controller.service_arguments');
};
