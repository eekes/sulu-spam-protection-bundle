<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Tests\Double;

use Sulu\Bundle\FormBundle\Entity\Form;
use Sulu\Bundle\FormBundle\Entity\FormField;
use Sulu\Bundle\FormBundle\Entity\FormFieldTranslation;
use Sulu\Bundle\FormBundle\Entity\FormTranslation;

/**
 * Builds the Sulu form entities a submission consists of, without a database and without booting
 * sulu/form-bundle: they are plain Doctrine entities, which is exactly what the field type needs.
 */
final class SuluFormFactory
{
    public const string LOCALE = 'nl';

    /**
     * @param list<array{type: string, key: string, title?: string}> $fields
     */
    public static function create(array $fields, string $title = 'Contactformulier', int $id = 7): Form
    {
        $form = new Form();
        $form->setDefaultLocale(self::LOCALE);

        (new \ReflectionProperty(Form::class, 'id'))->setValue($form, $id);

        $translation = new FormTranslation();
        $translation->setLocale(self::LOCALE);
        $translation->setTitle($title);
        $translation->setForm($form);
        $form->addTranslation($translation);

        foreach ($fields as $order => $field) {
            $form->addField(self::createField($form, $field, $order));
        }

        return $form;
    }

    public static function field(Form $form, string $type): FormField
    {
        foreach ($form->getFields() as $field) {
            if ($field->getType() === $type) {
                return $field;
            }
        }

        throw new \InvalidArgumentException(\sprintf('The form has no field of type "%s".', $type));
    }

    /**
     * @param array{type: string, key: string, title?: string} $field
     */
    private static function createField(Form $form, array $field, int $order): FormField
    {
        $formField = new FormField();
        $formField->setForm($form);
        $formField->setDefaultLocale(self::LOCALE);
        $formField->setKey($field['key']);
        $formField->setType($field['type']);
        $formField->setOrder($order);

        $translation = new FormFieldTranslation();
        $translation->setLocale(self::LOCALE);
        $translation->setTitle($field['title'] ?? $field['key']);
        $translation->setField($formField);
        $formField->addTranslation($translation);

        return $formField;
    }
}
