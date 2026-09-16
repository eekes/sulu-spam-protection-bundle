<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Application\EventListener;

use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\FormField\RecaptchaFieldType;
use Sulu\Bundle\FormBundle\Form\BuilderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Answers a submission that the spam check stopped with exactly the page a real visitor gets, so a
 * bot cannot tell whether it got through.
 *
 * Building the form here is what runs the check: Sulu's own RequestListener would otherwise save
 * the submission and send the mails before anything could intervene. Running first and setting a
 * response stops that listener, and the form itself is cached by the builder, so the submission is
 * only ever processed once.
 *
 * The listener is registered with a priority above the 0 of sulu_form.request_listener and below
 * the router.
 */
final readonly class SuluFormSpamListener
{
    public function __construct(
        private BuilderInterface $formBuilder,
        private string $successRedirect,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getRequest()->isMethod('POST')) {
            return;
        }

        $request = $event->getRequest();

        try {
            $form = $this->formBuilder->buildByRequest($request);
        } catch (\Exception) {
            // A form that cannot be built is not ours to answer; Sulu swallows this too.
            return;
        }

        if ($form === null || !$form->isSubmitted()) {
            return;
        }

        if ($request->attributes->get(RecaptchaFieldType::BLOCKED_ATTRIBUTE) !== true) {
            return;
        }

        // The same redirect Sulu makes after a genuine submission.
        $event->setResponse(new RedirectResponse($this->successRedirect));
    }
}
