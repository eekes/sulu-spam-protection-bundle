# Custom forms

The Sulu field type covers the forms editors build themselves. Everything else — a Live Component,
a plain Symfony form, an API endpoint — checks the token itself and decides what to answer.

## The token field

Add it to the form type **without a constraint**. The constraint of
`karser/karser-recaptcha3-bundle` would turn a failed check into a form error, which tells a bot it
was caught; that decision belongs to the code that handles the submission.

```php
use Karser\Recaptcha3Bundle\Form\Recaptcha3Type;

final class NewsletterSubscriptionType extends AbstractType
{
    /**
     * Must match the action passed to the checker, or Google reports an action mismatch.
     */
    public const string RECAPTCHA_ACTION = 'newsletter_subscribe';

    public const string CAPTCHA_FIELD = 'captcha';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class)
            ->add(self::CAPTCHA_FIELD, Recaptcha3Type::class, [
                'action_name' => self::RECAPTCHA_ACTION,
            ]);
    }
}
```

## Checking it

```php
use Eekes\Sulu\SpamProtectionBundle\Application\Service\BlockedSubmissionLogger;
use Eekes\Sulu\SpamProtectionBundle\Application\Service\SpamCheckerInterface;
use Eekes\Sulu\SpamProtectionBundle\Domain\ValueObject\SubmissionSource;

public function __construct(
    private readonly SpamCheckerInterface $spamChecker,
    private readonly BlockedSubmissionLogger $blockedSubmissionLogger,
) {
}

#[LiveAction]
public function subscribe(): void
{
    // Throws when the email is invalid, which re-renders the component with the form errors.
    $this->submitForm();

    $email = (string) $this->getForm()->get('email')->getData();
    $token = $this->getForm()->get(NewsletterSubscriptionType::CAPTCHA_FIELD)->getData();

    $result = $this->spamChecker->check(
        \is_string($token) ? $token : '',
        NewsletterSubscriptionType::RECAPTCHA_ACTION,
    );

    if ($result->isUnavailable()) {
        // Google could not be reached. The submission goes through unchecked rather than costing
        // the customer a lead; this only records that it happened.
        $this->blockedSubmissionLogger->logUnchecked(SubmissionSource::of('newsletter'), $result);
    }

    if ($result->isTokenStale()) {
        // The visitor submitted twice. Not a bot: let them try again with a fresh token.
        $this->status = Status::TemporaryFailure;

        return;
    }

    if (!$result->trusted) {
        $this->blockedSubmissionLogger->logBlocked(
            SubmissionSource::of('newsletter'),
            $result,
            ['email' => $email],
        );
        // Deliberately the success message: a bot may not learn it was caught.
        $this->succeed();

        return;
    }

    // ... the real work ...
}
```

Handle the three cases in this order. `isUnavailable()` first, because an unavailable result is
also a *trusted* one: the visitor is let through on purpose, and skipping the check would silently
lose the fact that nothing was verified.

## Naming a source

`SubmissionSource` is an open value, not an enum, so a project names its own forms without changing
the bundle:

```php
SubmissionSource::of('newsletter');
SubmissionSource::suluForm();                      // what the Sulu field type uses
SubmissionSource::fromEnum(MySource::Newsletter);  // if the project keeps its own enum
```

The value is stored as-is and shown in the admin list, so keep it short and stable: it is what you
filter on when a customer asks which form is being hit.

## Another provider

`SpamCheckerInterface` is the extension point. Point the alias at your own implementation and
nothing that calls it changes:

```yaml
services:
    App\SpamProtection\TurnstileChecker: ~

    Eekes\Sulu\SpamProtectionBundle\Application\Service\SpamCheckerInterface:
        alias: App\SpamProtection\TurnstileChecker
```

An implementation must never throw. A provider that cannot be reached returns
`SpamCheckResult::unavailable()`, so an outage never blocks a genuine submission.
