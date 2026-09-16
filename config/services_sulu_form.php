<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eekes\Sulu\SpamProtectionBundle\Application\EventListener\SuluFormSpamListener;
use Eekes\Sulu\SpamProtectionBundle\Application\Service\BlockedSubmissionLogger;
use Eekes\Sulu\SpamProtectionBundle\Application\Service\SpamCheckerInterface;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\FormField\RecaptchaFieldType;

/*
 * Loaded only when sulu/form-bundle is registered: the field editors add to a form they built in
 * the administration interface, and the listener that answers a blocked submission.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(RecaptchaFieldType::class)
        ->arg('$spamChecker', service(SpamCheckerInterface::class))
        ->arg('$blockedSubmissionLogger', service(BlockedSubmissionLogger::class))
        ->arg('$requestStack', service('request_stack'))
        ->arg('$translator', service('translator'))
        ->arg('$xmlPath', param('eekes_sulu_spam_protection.field_xml_path'))
        ->arg('$title', param('eekes_sulu_spam_protection.field_title'))
        ->tag('sulu_form.dynamic.type', ['alias' => RecaptchaFieldType::ALIAS]);

    $services->set(SuluFormSpamListener::class)
        // Sulu registers its form builder without an autowiring alias.
        ->arg('$formBuilder', service('sulu_form.builder'))
        ->arg('$successRedirect', param('eekes_sulu_spam_protection.success_redirect'))
        // Anything above the 0 of sulu_form.request_listener, and below the router.
        ->tag('kernel.event_listener', ['event' => 'kernel.request', 'priority' => 1]);
};
