<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Tests\Unit;

use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Symfony\DependencyInjection\Compiler\RecaptchaFormThemePass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Where the reCAPTCHA theme of this bundle ends up in twig.form_themes. A theme only overrides the
 * themes before it, so the order is the whole feature: one place too early and karser's own widget
 * comes back, one place too late and a form theme of the project is overridden.
 */
#[CoversClass(RecaptchaFormThemePass::class)]
final class RecaptchaFormThemePassTest extends TestCase
{
    private const string KARSER_THEME = '@KarserRecaptcha3/Form/karser_recaptcha3_widget.html.twig';

    public function testItMovesTheThemeBehindTheOneItHasToOverride(): void
    {
        $themes = $this->process([
            'form_div_layout.html.twig',
            RecaptchaFormThemePass::THEME,
            self::KARSER_THEME,
            '@LiveComponent/form_theme.html.twig',
            'form/theme.html.twig',
        ]);

        self::assertSame([
            'form_div_layout.html.twig',
            self::KARSER_THEME,
            RecaptchaFormThemePass::THEME,
            '@LiveComponent/form_theme.html.twig',
            'form/theme.html.twig',
        ], $themes);
    }

    /**
     * Everything a project lists itself has to keep coming last, or installing this bundle would
     * quietly change how a form of the project is rendered.
     */
    public function testAThemeOfTheProjectKeepsWinning(): void
    {
        $themes = $this->process([
            RecaptchaFormThemePass::THEME,
            self::KARSER_THEME,
            'form/theme.html.twig',
        ]);

        self::assertSame('form/theme.html.twig', end($themes));
    }

    public function testWithoutKarsersThemeNothingIsMoved(): void
    {
        $themes = ['form_div_layout.html.twig', RecaptchaFormThemePass::THEME];

        self::assertSame($themes, $this->process($themes));
    }

    public function testAProjectThatSwitchedTheThemeOffKeepsItsOwnOrder(): void
    {
        $themes = ['form_div_layout.html.twig', self::KARSER_THEME, 'form/theme.html.twig'];

        self::assertSame($themes, $this->process($themes));
    }

    public function testAnApplicationWithoutTwigIsLeftAlone(): void
    {
        $container = new ContainerBuilder();

        (new RecaptchaFormThemePass())->process($container);

        self::assertFalse($container->hasParameter('twig.form.resources'));
    }

    /**
     * @param list<string> $themes
     *
     * @return list<string>
     */
    private function process(array $themes): array
    {
        $container = new ContainerBuilder();
        $container->setParameter('twig.form.resources', $themes);

        (new RecaptchaFormThemePass())->process($container);

        $processed = $container->getParameter('twig.form.resources');
        self::assertIsArray($processed);

        $result = [];

        foreach ($processed as $theme) {
            self::assertIsString($theme);
            $result[] = $theme;
        }

        return $result;
    }
}
