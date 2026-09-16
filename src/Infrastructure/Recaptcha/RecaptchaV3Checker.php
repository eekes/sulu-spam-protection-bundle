<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Infrastructure\Recaptcha;

use Eekes\Sulu\SpamProtectionBundle\Application\Service\SpamCheckerInterface;
use Eekes\Sulu\SpamProtectionBundle\Domain\ValueObject\SpamCheckResult;
use Karser\Recaptcha3Bundle\ReCaptcha\ReCaptcha;
use Karser\Recaptcha3Bundle\Services\IpResolverInterface;

/**
 * Verifies an invisible reCAPTCHA v3 token with Google and keeps the score.
 *
 * Whether a score passes is decided by the threshold configured on karser_recaptcha3, so a project
 * tunes how strict it is in one place.
 */
final readonly class RecaptchaV3Checker implements SpamCheckerInterface
{
    /**
     * The ReCaptcha service refuses to be built without a secret, so it is only resolved once a
     * token actually has to be verified.
     *
     * @param \Closure(): ReCaptcha $recaptchaFactory
     */
    public function __construct(
        private \Closure $recaptchaFactory,
        private IpResolverInterface $ipResolver,
        private bool $enabled,
    ) {
    }

    public function check(string $token, string $action): SpamCheckResult
    {
        if (!$this->enabled) {
            return SpamCheckResult::trusted();
        }

        if ($token === '') {
            return SpamCheckResult::rejected(null, ['missing-input-response']);
        }

        try {
            $response = ($this->recaptchaFactory)()
                ->setExpectedAction($action)
                ->verify($token, $this->ipResolver->resolveIp());
        } catch (\Throwable $exception) {
            // Never let an unreachable reCAPTCHA service block a genuine submission.
            return SpamCheckResult::unavailable($exception->getMessage());
        }

        if ($response->isSuccess()) {
            return SpamCheckResult::trusted($response->getScore());
        }

        $errorCodes = array_values(array_filter($response->getErrorCodes(), \is_string(...)));

        return SpamCheckResult::rejected($response->getScore(), $errorCodes);
    }
}
