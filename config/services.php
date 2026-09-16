<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eekes\Sulu\SpamProtectionBundle\Application\Command\PruneBlockedSubmissionsCommand;
use Eekes\Sulu\SpamProtectionBundle\Application\Service\BlockedSubmissionLogger;
use Eekes\Sulu\SpamProtectionBundle\Application\Service\SpamCheckerInterface;
use Eekes\Sulu\SpamProtectionBundle\Domain\Repository\BlockedSubmissionRepository;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Doctrine\BlockedSubmissionTableListener;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Recaptcha\RecaptchaV3Checker;

/*
 * Core services. Everything that needs the Sulu admin or sulu/form-bundle lives in the two sibling
 * files, so the bundle also boots in a project (or test kernel) without them.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(BlockedSubmissionRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    $services->set(RecaptchaV3Checker::class)
        // The ReCaptcha service refuses to be built without a secret, so it is resolved lazily.
        ->arg('$recaptchaFactory', service_closure('karser_recaptcha3.google.recaptcha'))
        ->arg('$ipResolver', service('karser_recaptcha3.ip_resolver'))
        // One switch for the whole feature: karser decides whether Google is called at all.
        ->arg('$enabled', param('karser_recaptcha3.enabled'));

    $services->alias(SpamCheckerInterface::class, RecaptchaV3Checker::class);

    $services->set(BlockedSubmissionLogger::class)
        ->arg('$blockedSubmissionRepository', service(BlockedSubmissionRepository::class))
        ->arg('$requestStack', service('request_stack'))
        ->arg('$logger', service('logger'))
        ->arg('$storePayload', param('eekes_sulu_spam_protection.store_payload'))
        ->arg('$anonymizeIp', param('eekes_sulu_spam_protection.anonymize_ip'))
        ->tag('monolog.logger', ['channel' => 'spam_protection']);

    $services->set(BlockedSubmissionTableListener::class)
        ->arg('$tableName', param('eekes_sulu_spam_protection.table_name'))
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

    $services->set(PruneBlockedSubmissionsCommand::class)
        ->arg('$blockedSubmissionRepository', service(BlockedSubmissionRepository::class))
        ->arg('$retentionDays', param('eekes_sulu_spam_protection.retention_days'))
        ->tag('console.command');
};
