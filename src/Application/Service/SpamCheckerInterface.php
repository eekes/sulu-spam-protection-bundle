<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Application\Service;

use Eekes\Sulu\SpamProtectionBundle\Domain\ValueObject\SpamCheckResult;

/**
 * Decides whether a submission can be trusted. The bundle ships a reCAPTCHA v3 implementation;
 * a project that has to move to another provider replaces the service behind this interface and
 * nothing that calls it changes.
 *
 * An implementation never throws: a provider that cannot be reached returns
 * SpamCheckResult::unavailable(), because an outage may never cost the customer a lead.
 */
interface SpamCheckerInterface
{
    /**
     * @param string $token  what the widget collected from the visitor, empty when it collected nothing
     * @param string $action the action the token was generated for; a mismatch is treated as spam
     */
    public function check(string $token, string $action): SpamCheckResult;
}
