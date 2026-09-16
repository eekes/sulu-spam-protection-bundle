<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Tests\Functional;

use Eekes\Sulu\SpamProtectionBundle\Application\Service\BlockedSubmissionLogger;
use Eekes\Sulu\SpamProtectionBundle\Application\Service\SpamCheckerInterface;
use Eekes\Sulu\SpamProtectionBundle\Domain\ValueObject\SubmissionSource;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\FormField\RecaptchaFieldType;
use Eekes\Sulu\SpamProtectionBundle\Tests\Double\SuluFormFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\FormBundle\Entity\Form;
use Sulu\Bundle\FormBundle\Entity\FormField;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Exercises the field exactly as Sulu uses it: the form is built through the field type and then
 * submitted, so the check runs in its POST_SUBMIT listener like it does on the website.
 *
 * sulu/form-bundle is not booted here - its Form and FormField are plain Doctrine entities, so the
 * test builds them by hand. See CLAUDE.md.
 */
#[CoversClass(RecaptchaFieldType::class)]
final class RecaptchaFieldTypeTest extends FunctionalTestCase
{
    private const int FORM_ID = 7;
    private const string FORM_TITLE = 'Contactformulier';
    private const string FORM_NAME = 'dynamic_form7';
    private const string CAPTCHA_KEY = 'recaptcha123';
    private const string EMAIL_KEY = 'email456';

    /**
     * What the widget asks Google for, and so what the token has to be generated for.
     */
    private const string ACTION = 'sulu_form_'.self::FORM_ID;

    private const string TOKEN = 'a-captcha-token';

    public function testEditorsCanAddTheFieldToAFormUnderARecognisableName(): void
    {
        $configuration = $this->fieldType()->getConfiguration();

        self::assertSame('Spam protection', $configuration->getTitle());
        self::assertFileExists($configuration->getXmlPath());
    }

    /**
     * The field may not disturb the layout of the form the editor built: no label, no row wrapper,
     * just the hidden input the widget writes the token into.
     */
    public function testTheFieldRendersNothingTheVisitorCanSee(): void
    {
        $builder = $this->formFactory()->createNamedBuilder(self::FORM_NAME);
        $this->fieldType()->build($builder, $this->captchaField(), SuluFormFactory::LOCALE, []);

        $twig = self::getContainer()->get('eekes_test.twig');
        \assert($twig instanceof Environment);

        $html = $twig->createTemplate('{{ form_rest(form) }}')
            ->render(['form' => $builder->getForm()->createView()]);

        self::assertStringContainsString('type="hidden"', $html);
        self::assertStringNotContainsString('<label', $html);
        // The widget has to ask Google for a token of the action the check expects back.
        self::assertStringContainsString("action: '".self::ACTION."'", $html);
    }

    public function testAGenuineVisitorPassesTheCheckWithoutBeingLogged(): void
    {
        $this->google()->queueScore(0.9, self::ACTION);

        $form = $this->submit();

        self::assertTrue($form->isValid());
        self::assertFalse($this->wasBlocked());
        self::assertSame([], $this->blockedSubmissions());
    }

    /**
     * The point of the whole field: a bot may never learn that it was caught, so the form stays
     * valid and the listener answers with the same success page a visitor gets.
     */
    public function testSpamIsLoggedAndDroppedWithoutAnyErrorTheBotCanSee(): void
    {
        $this->google()->queueScore(0.1, self::ACTION);

        $form = $this->submit();

        self::assertTrue($form->isValid());
        self::assertCount(0, $form->getErrors(true));
        self::assertTrue($this->wasBlocked());

        $blocked = $this->blockedSubmissions();
        self::assertCount(1, $blocked);
        self::assertSame(SubmissionSource::SULU_FORM, $blocked[0]->source);
        self::assertSame(self::FORM_ID, $blocked[0]->sourceId);
        self::assertSame(self::FORM_TITLE, $blocked[0]->sourceName);
        self::assertSame(SuluFormFactory::LOCALE, $blocked[0]->locale);
        self::assertSame(0.1, $blocked[0]->score);
        self::assertStringContainsString('score-threshold-not-met', (string) $blocked[0]->reason);
    }

    /**
     * A submission that was flagged by mistake still has to be reachable, so everything the visitor
     * typed is kept - but nothing Sulu added itself.
     */
    public function testTheLoggedPayloadHoldsTheAnswersWithoutTheTechnicalFields(): void
    {
        $this->google()->queueScore(0.1, self::ACTION);

        $this->submit();

        self::assertSame(
            [self::EMAIL_KEY => 'spam@example.com'],
            $this->blockedSubmissions()[0]->payload,
        );
    }

    /**
     * Not spam but a visitor submitting twice, so this one is told to send the form again.
     */
    public function testAReusedTokenAsksTheVisitorToRetryAndIsNotLoggedAsSpam(): void
    {
        $this->google()->queueErrors(['timeout-or-duplicate']);

        $form = $this->submit();

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->getErrors(true));
        // The message comes from the validators catalogue the bundle ships.
        self::assertSame(
            'The spam check expired. Please send the form again.',
            $form->getErrors(true)[0]->getMessage(),
        );
        self::assertFalse($this->wasBlocked());
        self::assertSame([], $this->blockedSubmissions());
    }

    public function testAnUnreachableGoogleLetsTheSubmissionThroughInsteadOfLosingTheLead(): void
    {
        $this->google()->queueTransportFailure('Connection refused');

        $form = $this->submit();

        self::assertTrue($form->isValid());
        self::assertFalse($this->wasBlocked());
        self::assertSame([], $this->blockedSubmissions());
        self::assertTrue($this->spamProtectionLog()->hasWarningThatContains('without a spam check'));
    }

    public function testATokenGeneratedForAnotherFormIsTreatedAsSpam(): void
    {
        $this->google()->queueScore(0.9, 'sulu_form_999');

        $form = $this->submit();

        self::assertTrue($form->isValid());
        self::assertTrue($this->wasBlocked());
        self::assertStringContainsString('action-mismatch', (string) $this->blockedSubmissions()[0]->reason);
    }

    /**
     * Builds the same form Sulu builds for a page and submits it, so the POST_SUBMIT check of the
     * field type runs exactly as it does on the website.
     *
     * @return FormInterface<mixed>
     */
    private function submit(string $email = 'spam@example.com'): FormInterface
    {
        $submitted = [
            self::EMAIL_KEY => $email,
            self::CAPTCHA_KEY => self::TOKEN,
            // Sulu adds these itself, they are no answer of the visitor.
            'locale' => SuluFormFactory::LOCALE,
            'checksum' => 'a-checksum',
            'formId' => (string) self::FORM_ID,
        ];

        $this->requestStack()->push(Request::create('/contact', 'POST', [self::FORM_NAME => $submitted]));

        $builder = $this->formFactory()->createNamedBuilder(self::FORM_NAME);
        $this->fieldType()->build($builder, $this->captchaField(), SuluFormFactory::LOCALE, []);

        $form = $builder->getForm();
        $form->submit([self::CAPTCHA_KEY => self::TOKEN]);

        return $form;
    }

    private function fieldType(): RecaptchaFieldType
    {
        $container = self::getContainer();

        $spamChecker = $container->get('eekes_test.spam_checker');
        \assert($spamChecker instanceof SpamCheckerInterface);

        $blockedSubmissionLogger = $container->get('eekes_test.blocked_submission_logger');
        \assert($blockedSubmissionLogger instanceof BlockedSubmissionLogger);

        $translator = $container->get('eekes_test.translator');
        \assert($translator instanceof TranslatorInterface);

        $xmlPath = $container->getParameter('eekes_sulu_spam_protection.field_xml_path');
        \assert(\is_string($xmlPath));

        $title = $container->getParameter('eekes_sulu_spam_protection.field_title');
        \assert(\is_string($title));

        return new RecaptchaFieldType(
            $spamChecker,
            $blockedSubmissionLogger,
            $this->requestStack(),
            $translator,
            $xmlPath,
            $title,
        );
    }

    private function captchaField(): FormField
    {
        return SuluFormFactory::field($this->form(), RecaptchaFieldType::ALIAS);
    }

    private function form(): Form
    {
        return SuluFormFactory::create(
            [
                ['type' => 'email', 'key' => self::EMAIL_KEY, 'title' => 'E-mailadres'],
                ['type' => RecaptchaFieldType::ALIAS, 'key' => self::CAPTCHA_KEY, 'title' => 'Spam protection'],
            ],
            title: self::FORM_TITLE,
            id: self::FORM_ID,
        );
    }

    private function formFactory(): FormFactoryInterface
    {
        $formFactory = self::getContainer()->get('eekes_test.form_factory');
        \assert($formFactory instanceof FormFactoryInterface);

        return $formFactory;
    }

    private function requestStack(): RequestStack
    {
        $requestStack = self::getContainer()->get('eekes_test.request_stack');
        \assert($requestStack instanceof RequestStack);

        return $requestStack;
    }

    private function wasBlocked(): bool
    {
        return $this->requestStack()->getCurrentRequest()?->attributes->get(RecaptchaFieldType::BLOCKED_ATTRIBUTE) === true;
    }
}
