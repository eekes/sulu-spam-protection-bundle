<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Infrastructure\Symfony\DependencyInjection;

/**
 * The processed configuration as typed values.
 *
 * The configuration component hands back a plain array of mixed, which the extension would
 * otherwise have to cast at every use. Reading it once, here, also puts the one default that
 * cannot be expressed in the tree - the path to the form field XML shipped with the bundle - next
 * to the values it belongs with.
 */
final readonly class SpamProtectionConfig
{
    public function __construct(
        public string $tableName,
        public int $retentionDays,
        public bool $storePayload,
        public bool $anonymizeIp,
        public string $fieldTitle,
        public string $fieldXmlPath,
        public string $successRedirect,
        public bool $registerFormTheme,
        public bool $adminEnabled,
        public int $navigationPosition,
    ) {
    }

    /**
     * @param array<mixed> $config the configuration as the processor returned it
     */
    public static function fromArray(array $config): self
    {
        $admin = $config['admin'] ?? null;
        $fieldXmlPath = $config['field_xml_path'] ?? null;

        if (!\is_array($admin)) {
            throw new \LogicException('The configuration has no "admin" section.');
        }

        return new self(
            tableName: self::readString($config, 'table_name'),
            retentionDays: self::readInt($config, 'retention_days'),
            storePayload: self::readBool($config, 'store_payload'),
            anonymizeIp: self::readBool($config, 'anonymize_ip'),
            fieldTitle: self::readString($config, 'field_title'),
            fieldXmlPath: \is_string($fieldXmlPath) ? $fieldXmlPath : self::defaultFieldXmlPath(),
            successRedirect: self::readString($config, 'success_redirect'),
            registerFormTheme: self::readBool($config, 'register_form_theme'),
            adminEnabled: self::readBool($admin, 'enabled'),
            navigationPosition: self::readInt($admin, 'navigation_position'),
        );
    }

    public static function defaultFieldXmlPath(): string
    {
        return \dirname(__DIR__, 4).'/config/form-fields/recaptcha.xml';
    }

    /**
     * @param array<mixed> $config
     */
    private static function readString(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        if (!\is_string($value)) {
            throw new \LogicException(\sprintf('Expected "%s" to be a string.', $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $config
     */
    private static function readInt(array $config, string $key): int
    {
        $value = $config[$key] ?? null;

        if (!\is_int($value)) {
            throw new \LogicException(\sprintf('Expected "%s" to be an integer.', $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $config
     */
    private static function readBool(array $config, string $key): bool
    {
        $value = $config[$key] ?? null;

        if (!\is_bool($value)) {
            throw new \LogicException(\sprintf('Expected "%s" to be a boolean.', $key));
        }

        return $value;
    }
}
