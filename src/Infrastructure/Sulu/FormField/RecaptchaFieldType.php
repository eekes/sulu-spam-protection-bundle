<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\FormField;

use Eekes\Sulu\SpamProtectionBundle\Application\Service\BlockedSubmissionLogger;
use Eekes\Sulu\SpamProtectionBundle\Application\Service\SpamCheckerInterface;
use Eekes\Sulu\SpamProtectionBundle\Domain\ValueObject\SubmissionSource;
use Karser\Recaptcha3Bundle\Form\Recaptcha3Type;
use Sulu\Bundle\FormBundle\Dynamic\FormFieldTypeConfiguration;
use Sulu\Bundle\FormBundle\Dynamic\FormFieldTypeInterface;
use Sulu\Bundle\FormBundle\Entity\FormField;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The spam protection field: adding it to a form in the Sulu admin puts an invisible reCAPTCHA v3
 * check in front of every submission of that form. The visitor never sees a challenge; the token
 * the widget fetches is verified when the form is submitted.
 *
 * Sulu itself disables CSRF protection as soon as a form has a field of this type, which is why
 * the alias has to stay 'recaptcha'.
 */
final readonly class RecaptchaFieldType implements FormFieldTypeInterface
{
    public const string ALIAS = 'recaptcha';

    /**
     * Set on the request when a submission was recognised as spam. SuluFormSpamListener picks it
     * up and answers with the success page, so a bot never learns it was caught.
     */
    public const string BLOCKED_ATTRIBUTE = '_spam_protection_blocked';

    /**
     * The translation key of the message a visitor gets when their token was already used.
     */
    public const string RETRY_MESSAGE = 'eekes_spam_protection.error.retry';

    /**
     * Runs after the validation listener, so the blocked submission is logged with the rest of the
     * form errors already known.
     */
    private const int VERIFY_LISTENER_PRIORITY = -1024;

    /**
     * Request keys Sulu adds itself, which are no answer of the visitor.
     */
    private const array TECHNICAL_KEYS = ['_token', 'checksum', 'locale', 'type', 'typeId', 'formId', 'formName', 'submit'];

    public function __construct(
        private SpamCheckerInterface $spamChecker,
        private BlockedSubmissionLogger $blockedSubmissionLogger,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
        private string $xmlPath,
        private string $title,
    ) {
    }

    /**
     * Sulu does not translate form field metadata, so the title is literal text.
     */
    public function getConfiguration(): FormFieldTypeConfiguration
    {
        return new FormFieldTypeConfiguration(
            $this->title,
            $this->xmlPath,
            'special',
        );
    }

    /**
     * The options Sulu passes in describe a field the visitor fills in, which is exactly what this
     * one is not, so they are deliberately not forwarded.
     *
     * @param FormBuilderInterface<mixed> $builder
     * @param mixed[]                     $options
     */
    public function build(FormBuilderInterface $builder, FormField $field, string $locale, array $options): void
    {
        $builder->add($field->getKey(), Recaptcha3Type::class, [
            'label' => false,
            'required' => false,
            'action_name' => self::actionName($field),
            'locale' => $locale,
        ]);

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (PostSubmitEvent $event) use ($field, $locale): void {
                $this->verify($event->getForm(), $field, $locale);
            },
            self::VERIFY_LISTENER_PRIORITY,
        );
    }

    public function getDefaultValue(FormField $field, string $locale): ?string
    {
        return null;
    }

    /**
     * Must be identical when the widget fetches the token and when it is verified, or Google
     * reports an action mismatch. Both run off the same form, so its id is enough.
     */
    public static function actionName(FormField $field): string
    {
        return 'sulu_form_'.$field->getForm()->getId();
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function verify(FormInterface $form, FormField $field, string $locale): void
    {
        $captchaField = $form->get($field->getKey());
        $token = $captchaField->getData();

        $result = $this->spamChecker->check(
            \is_string($token) ? $token : '',
            self::actionName($field),
        );

        if ($result->isUnavailable()) {
            // An outage at the provider may not cost the customer a lead, so the submission goes
            // through.
            $this->blockedSubmissionLogger->logUnchecked(SubmissionSource::suluForm(), $result);

            return;
        }

        if ($result->isTokenStale()) {
            // A reused token means the visitor submitted twice, not that they are a bot, so this
            // one does get an error telling them to send the form again.
            $captchaField->addError(
                new FormError($this->translator->trans(self::RETRY_MESSAGE, domain: 'validators')),
            );

            return;
        }

        if ($result->trusted) {
            return;
        }

        $this->blockedSubmissionLogger->logBlocked(
            SubmissionSource::suluForm(),
            $result,
            $this->extractPayload($form, $field->getKey()),
            $field->getForm()->getId(),
            $field->getForm()->getTranslation($locale)?->getTitle(),
            $locale,
        );

        // Deliberately no form error: telling a bot it was recognised only helps it retry until it
        // gets through. The form is left valid and the listener fakes the success page, so the
        // submission is dropped without anyone noticing.
        $this->requestStack->getCurrentRequest()?->attributes->set(self::BLOCKED_ATTRIBUTE, true);
    }

    /**
     * Everything the visitor typed, so a submission that was blocked by mistake can still be
     * followed up on.
     *
     * @param FormInterface<mixed> $form
     *
     * @return array<string, mixed>
     */
    private function extractPayload(FormInterface $form, string $captchaKey): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request) {
            return [];
        }

        $ignoredKeys = [...self::TECHNICAL_KEYS, $captchaKey];
        $payload = [];

        foreach ($request->request->all($form->getName()) as $key => $value) {
            if (!\is_string($key) || \in_array($key, $ignoredKeys, true)) {
                continue;
            }

            $payload[$key] = $value;
        }

        return $payload;
    }
}
