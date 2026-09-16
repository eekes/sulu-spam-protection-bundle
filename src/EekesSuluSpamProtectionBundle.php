<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle;

use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Symfony\DependencyInjection\Compiler\RecaptchaFormThemePass;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Symfony\DependencyInjection\EekesSuluSpamProtectionExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * The bundle follows the directory layout Symfony recommends for new bundles: src/ holds nothing
 * but PHP, and config/, templates/ and translations/ sit next to it at the root.
 *
 * Both overrides below exist because that layout parts ways with what the base class assumes.
 * getPath() is what makes config/, templates/ and translations/ discoverable - the
 * '@EekesSuluSpamProtection' template namespace, the routing file a project imports, and the
 * translations Symfony registers on its own all hang off it.
 */
class EekesSuluSpamProtectionBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Runs after every extension has been loaded, which is the only moment the full list of
        // form themes exists.
        $container->addCompilerPass(new RecaptchaFormThemePass());
    }

    /**
     * The container extension is Symfony glue like everything else under Infrastructure, so it does
     * not sit in the DependencyInjection/ folder the base class would look in.
     */
    public function getContainerExtension(): ExtensionInterface
    {
        return new EekesSuluSpamProtectionExtension();
    }
}
