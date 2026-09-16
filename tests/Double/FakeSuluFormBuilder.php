<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Tests\Double;

use Sulu\Bundle\FormBundle\Form\BuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Stands in for sulu_form.builder, which needs a whole Sulu request to do its work.
 */
final class FakeSuluFormBuilder implements BuilderInterface
{
    /**
     * @param null|FormInterface<mixed> $form
     */
    public function __construct(
        private readonly ?FormInterface $form = null,
        private readonly ?\Exception $exception = null,
    ) {
    }

    public function buildByRequest(Request $request): ?FormInterface
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->form;
    }

    public function build(int $id, string $type, string $typeId, ?string $locale = null, string $name = 'form'): ?FormInterface
    {
        return $this->form;
    }
}
