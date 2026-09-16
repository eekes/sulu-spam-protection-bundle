<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Tests\Application;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Eekes\Sulu\SpamProtectionBundle\EekesSuluSpamProtectionBundle;
use Karser\Recaptcha3Bundle\KarserRecaptcha3Bundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * The smallest application this bundle can live in: no Sulu bundles at all.
 *
 * That is deliberate. Booting a full Sulu application needs PHPCR, webspaces and a content
 * repository, which would make the suite slow and brittle for what it actually tests. The classes
 * that touch Sulu only use its plain entities and interfaces, so they are exercised by building
 * them by hand in the tests. See CLAUDE.md for what that means when a new feature is added.
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new MonologBundle();
        yield new DoctrineBundle();
        yield new KarserRecaptcha3Bundle();
        yield new EekesSuluSpamProtectionBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return __DIR__.'/var/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return __DIR__.'/var/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import(__DIR__.'/config/config.yaml');
    }

    /**
     * The bundle's own routing file, imported exactly the way a project imports it, so a broken
     * resource path in it fails a test instead of only failing in a real Sulu admin.
     */
    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 2).'/config/routing_admin_api.php')->prefix('/admin/api');
    }
}
