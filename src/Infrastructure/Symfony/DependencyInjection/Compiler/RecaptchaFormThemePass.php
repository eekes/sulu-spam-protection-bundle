<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Infrastructure\Symfony\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Puts the reCAPTCHA form theme of this bundle directly after the one of karser.
 *
 * A form theme only overrides the themes listed before it, and prepending can never reach past
 * karser's own theme: prepended config lands at the front of the list, so the widget this bundle
 * ships would be overridden again by the very widget it is there to replace - silently, because
 * both render a working hidden input and only a Live Component notices the difference.
 *
 * Moving it one place further keeps the promise of the prepend: everything a project lists itself
 * still comes after this, so a form theme of the project keeps winning.
 */
final class RecaptchaFormThemePass implements CompilerPassInterface
{
    public const string THEME = '@EekesSuluSpamProtection/form/recaptcha_theme.html.twig';

    private const string KARSER_THEME = '@KarserRecaptcha3/Form/karser_recaptcha3_widget.html.twig';

    private const string PARAMETER = 'twig.form.resources';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::PARAMETER)) {
            return;
        }

        $themes = $container->getParameter(self::PARAMETER);

        if (!\is_array($themes) || !\in_array(self::THEME, $themes, true)) {
            // A project can switch the theme of this bundle off.
            return;
        }

        $themes = array_values(array_filter($themes, static fn (mixed $theme): bool => $theme !== self::THEME));

        $karserTheme = array_search(self::KARSER_THEME, $themes, true);

        if (!\is_int($karserTheme)) {
            // Without karser's own theme there is nothing to override, so the original place is
            // as good as any.
            return;
        }

        array_splice($themes, $karserTheme + 1, 0, [self::THEME]);

        $container->setParameter(self::PARAMETER, $themes);
    }
}
