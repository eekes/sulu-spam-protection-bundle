<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Tests\Double;

use Karser\Recaptcha3Bundle\ReCaptcha\RequestMethod;
use Karser\Recaptcha3Bundle\ReCaptcha\RequestParameters;

/**
 * Replaces the HTTP call to Google. Tests queue the answers that Google is supposed to give;
 * whether a score passes is still decided by the real threshold.
 */
final class FakeRecaptchaRequestMethod implements RequestMethod
{
    private const string TRANSPORT_FAILURE = '__transport_failure';

    /**
     * @var list<array<string, mixed>>
     */
    private array $responses = [];

    /**
     * @var list<string>
     */
    private array $submittedTokens = [];

    public function queueScore(float $score, string $action): void
    {
        $this->responses[] = ['success' => true, 'score' => $score, 'action' => $action];
    }

    /**
     * @param list<string> $errorCodes
     */
    public function queueErrors(array $errorCodes): void
    {
        $this->responses[] = ['success' => false, 'error-codes' => $errorCodes];
    }

    /**
     * Simulates Google being unreachable.
     */
    public function queueTransportFailure(string $message): void
    {
        $this->responses[] = [self::TRANSPORT_FAILURE => $message];
    }

    /**
     * @return list<string>
     */
    public function submittedTokens(): array
    {
        return $this->submittedTokens;
    }

    public function submit(RequestParameters $params): string
    {
        $token = $params->toArray()['response'] ?? '';
        $this->submittedTokens[] = \is_string($token) ? $token : '';

        $response = array_shift($this->responses) ?? ['success' => false, 'error-codes' => ['unknown-error']];

        $transportFailure = $response[self::TRANSPORT_FAILURE] ?? null;

        if (\is_string($transportFailure)) {
            throw new \RuntimeException($transportFailure);
        }

        return json_encode($response, \JSON_THROW_ON_ERROR);
    }
}
