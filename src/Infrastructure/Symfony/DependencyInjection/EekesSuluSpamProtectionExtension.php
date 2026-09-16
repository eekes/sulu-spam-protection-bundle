<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Infrastructure\Symfony\DependencyInjection;

use Eekes\Sulu\SpamProtectionBundle\Domain\Entity\BlockedSubmission;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller\BlockedSubmissionDetailController;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Controller\BlockedSubmissionListController;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Loads only the services a project can actually use: the Sulu form field type is skipped when
 * sulu/form-bundle is absent, and the admin views when the admin bundle is not registered (which
 * is also what keeps the bundle bootable in the light test kernel).
 */
final class EekesSuluSpamProtectionExtension extends Extension implements PrependExtensionInterface
{
    private const string PARAMETER_PREFIX = Configuration::ROOT_NODE.'.';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->config($configs);

        $container->setParameter(self::PARAMETER_PREFIX.'table_name', $config->tableName);
        $container->setParameter(self::PARAMETER_PREFIX.'retention_days', $config->retentionDays);
        $container->setParameter(self::PARAMETER_PREFIX.'store_payload', $config->storePayload);
        $container->setParameter(self::PARAMETER_PREFIX.'anonymize_ip', $config->anonymizeIp);
        $container->setParameter(self::PARAMETER_PREFIX.'field_title', $config->fieldTitle);
        $container->setParameter(self::PARAMETER_PREFIX.'field_xml_path', $config->fieldXmlPath);
        $container->setParameter(self::PARAMETER_PREFIX.'success_redirect', $config->successRedirect);
        $container->setParameter(self::PARAMETER_PREFIX.'navigation_position', $config->navigationPosition);

        $loader = new PhpFileLoader($container, new FileLocator(self::bundleRoot().'/config'));
        $loader->load('services.php');

        // Without sulu/form-bundle there is no dynamic form to hook into, and its interfaces are
        // not even autoloadable.
        if (self::hasBundle($container, 'SuluFormBundle')) {
            $loader->load('services_sulu_form.php');
        }

        if (self::hasBundle($container, 'SuluAdminBundle') && $config->adminEnabled) {
            $loader->load('services_sulu_admin.php');
        }
    }

    /**
     * Whether a project registered a bundle.
     *
     * Deliberately not hasExtension(): load() is handed a throwaway container that holds the
     * parameters but none of the extensions, so asking it about sulu_form or sulu_admin would
     * always answer no and silently leave half the bundle unregistered. prepend() does get the
     * real container, which is why it can ask there.
     */
    private static function hasBundle(ContainerBuilder $container, string $bundleName): bool
    {
        $bundles = $container->getParameter('kernel.bundles');

        return \is_array($bundles) && \array_key_exists($bundleName, $bundles);
    }

    public function prepend(ContainerBuilder $container): void
    {
        // prepend() runs before load(), so the configuration has to be resolved by hand here.
        $config = $this->config($container->getExtensionConfig($this->getAlias()));

        if ($container->hasExtension('doctrine')) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'EekesSpamProtection' => [
                            'type' => 'attribute',
                            'is_bundle' => false,
                            'dir' => self::bundleRoot().'/src/Domain/Entity',
                            'prefix' => 'Eekes\Sulu\SpamProtectionBundle\Domain\Entity',
                            'alias' => 'EekesSpamProtection',
                        ],
                    ],
                ],
            ]);
        }

        // Declaring the channel is what makes the monolog.logger tag in services.yaml resolve.
        if ($container->hasExtension('monolog')) {
            $container->prependExtensionConfig('monolog', [
                'channels' => ['spam_protection'],
            ]);
        }

        if ($container->hasExtension('twig') && $config->registerFormTheme) {
            // Prepended themes are applied first, so a project theme can still override the widget.
            $container->prependExtensionConfig('twig', [
                'form_themes' => ['@EekesSuluSpamProtection/form/recaptcha_theme.html.twig'],
            ]);
        }

        if ($container->hasExtension('sulu_admin') && $config->adminEnabled) {
            $container->prependExtensionConfig('sulu_admin', [
                'lists' => [
                    'directories' => [self::bundleRoot().'/config/lists'],
                ],
                'forms' => [
                    'directories' => [self::bundleRoot().'/config/forms'],
                ],
                'resources' => [
                    BlockedSubmission::SULU_RESOURCE_KEY => [
                        'routes' => [
                            'list' => BlockedSubmissionListController::ROUTE,
                            'detail' => BlockedSubmissionDetailController::ROUTE,
                        ],
                    ],
                ],
            ]);
        }
    }

    /**
     * The root of the bundle, where config/ and src/ live next to each other.
     */
    private static function bundleRoot(): string
    {
        return \dirname(__DIR__, 4);
    }

    /**
     * @param array<array<mixed>> $configs
     */
    private function config(array $configs): SpamProtectionConfig
    {
        return SpamProtectionConfig::fromArray($this->processConfiguration(new Configuration(), $configs));
    }

    public function getAlias(): string
    {
        return Configuration::ROOT_NODE;
    }
}
