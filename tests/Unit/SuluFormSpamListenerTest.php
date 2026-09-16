<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Tests\Unit;

use Eekes\Sulu\SpamProtectionBundle\Application\EventListener\SuluFormSpamListener;
use Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\FormField\RecaptchaFieldType;
use Eekes\Sulu\SpamProtectionBundle\Tests\Double\FakeSuluFormBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\FormBundle\Form\BuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * The guarantee the whole feature rests on: a submission the spam check stopped is answered with
 * the ordinary success page, so Sulu never saves or mails it and a bot learns nothing.
 */
#[CoversClass(SuluFormSpamListener::class)]
final class SuluFormSpamListenerTest extends TestCase
{
    private const string SUCCESS_REDIRECT = '?send=true';

    public function testSpamGetsTheSameSuccessPageAsAGenuineVisitor(): void
    {
        $event = $this->handle($this->request(blocked: true));

        $response = $event->getResponse();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(self::SUCCESS_REDIRECT, $response->getTargetUrl());
    }

    /**
     * Setting a response stops propagation, which is what keeps sulu_form.request_listener from
     * storing and mailing the submission.
     */
    public function testAnsweringSpamStopsSuluFromHandlingTheSubmission(): void
    {
        self::assertTrue($this->handle($this->request(blocked: true))->isPropagationStopped());
    }

    public function testAGenuineSubmissionIsLeftToSuluToHandle(): void
    {
        $event = $this->handle($this->request(blocked: false));

        self::assertNull($event->getResponse());
        self::assertFalse($event->isPropagationStopped());
    }

    public function testItIgnoresAnythingThatIsNotAPost(): void
    {
        $request = $this->request(blocked: true, method: 'GET');

        self::assertNull($this->handle($request)->getResponse());
    }

    public function testItIgnoresASubRequest(): void
    {
        $event = $this->handle($this->request(blocked: true), HttpKernelInterface::SUB_REQUEST);

        self::assertNull($event->getResponse());
    }

    public function testItIgnoresARequestThatCarriesNoSuluForm(): void
    {
        $event = $this->handle($this->request(blocked: true), builder: new FakeSuluFormBuilder(null));

        self::assertNull($event->getResponse());
    }

    /**
     * Sulu swallows this too; a form that cannot be built is not ours to answer.
     */
    public function testAFormThatCannotBeBuiltIsIgnoredInsteadOfBreakingThePage(): void
    {
        $builder = new FakeSuluFormBuilder(exception: new \RuntimeException('No form on this page.'));

        self::assertNull($this->handle($this->request(blocked: true), builder: $builder)->getResponse());
    }

    public function testAFormThatWasNeverSubmittedIsIgnored(): void
    {
        $builder = new FakeSuluFormBuilder($this->form(submitted: false));

        self::assertNull($this->handle($this->request(blocked: true), builder: $builder)->getResponse());
    }

    private function handle(
        Request $request,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
        ?BuilderInterface $builder = null,
    ): RequestEvent {
        $event = new RequestEvent($this->kernel(), $request, $requestType);

        $listener = new SuluFormSpamListener(
            $builder ?? new FakeSuluFormBuilder($this->form()),
            self::SUCCESS_REDIRECT,
        );
        $listener($event);

        return $event;
    }

    private function request(bool $blocked, string $method = 'POST'): Request
    {
        $request = Request::create('/contact', $method);

        if ($blocked) {
            $request->attributes->set(RecaptchaFieldType::BLOCKED_ATTRIBUTE, true);
        }

        return $request;
    }

    /**
     * @return FormInterface<mixed>
     */
    private function form(bool $submitted = true): FormInterface
    {
        $form = Forms::createFormFactory()->createNamed('dynamic_form7');

        if ($submitted) {
            $form->submit([]);
        }

        return $form;
    }

    private function kernel(): HttpKernelInterface
    {
        return new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
    }
}
