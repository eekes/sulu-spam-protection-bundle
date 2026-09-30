<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Translation\TranslatorBagInterface;

/**
 * English is the reference: every other language the bundle ships has to cover the same keys, or
 * an editor or visitor in that language silently gets the English fallback.
 */
#[CoversNothing]
final class TranslationsTest extends FunctionalTestCase
{
    private const string PREFIX = 'eekes_spam_protection.';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function shippedLanguages(): iterable
    {
        foreach (['admin', 'validators'] as $domain) {
            foreach (['nl', 'fr', 'de'] as $locale) {
                yield $domain.' '.$locale => [$domain, $locale];
            }
        }
    }

    #[DataProvider('shippedLanguages')]
    public function testEveryShippedLanguageTranslatesEveryText(string $domain, string $locale): void
    {
        $reference = $this->keys($domain, 'en');
        self::assertNotEmpty($reference);

        self::assertSame($reference, $this->keys($domain, $locale));
    }

    /**
     * @return list<string>
     */
    private function keys(string $domain, string $locale): array
    {
        $translator = self::getContainer()->get('eekes_test.translator');
        \assert($translator instanceof TranslatorBagInterface);

        // The catalogue of a locale holds only its own messages; the fallback lives in a separate
        // catalogue, so a missing translation is not papered over here.
        $keys = array_values(array_filter(
            array_keys($translator->getCatalogue($locale)->all($domain)),
            static fn (string $key): bool => str_starts_with($key, self::PREFIX),
        ));
        sort($keys);

        return $keys;
    }
}
