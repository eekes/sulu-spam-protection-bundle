# Contributing

Thanks for looking. This is a small, focused bundle: one feature, done properly. The conventions
below are what keeps it that way.

## Getting started

```bash
composer install
composer test      # PHPUnit
composer phpstan   # level max
composer cs-fix    # PHP CS Fixer
composer psr       # PSR-4 check, see below
```

All four have to pass before a pull request is ready.

`composer psr` runs `dump-autoload --strict-psr`. PHPUnit loads test files by path, so a test whose
namespace does not match its directory keeps passing silently — this is what catches it.

## Layout

```
config/                             # services*.php, routing, lists/, forms/, form-fields/
templates/                          # the Twig form theme
translations/                       # admin.<locale>.json, validators.<locale>.yaml
src/
    EekesSuluSpamProtectionBundle.php
    Domain/                         # Entity, Repository, ValueObject - no framework, no Sulu
    Application/                    # Service, EventListener, Command - the use cases
    Infrastructure/                 # Everything that talks to something concrete
        Recaptcha/
        Doctrine/
        Sulu/{Admin,Controller,FormField}
        Symfony/DependencyInjection/    # Configuration, Extension, SpamProtectionConfig
```

The root is the [structure Symfony recommends for a bundle](https://symfony.com/doc/current/bundles/best_practices.html#directory-structure):
`config/`, `templates/`, `translations/` and `src/` next to each other, with `src/` holding nothing
but PHP. That is why the layers are the only thing in it.

The bundle class overrides two methods, and both are worth understanding before moving anything:

- `getPath()` returns the root instead of `src/`. Symfony's own note says to do this for a bundle
  that extends `Bundle` rather than `AbstractBundle`; it is what makes `config/`, `templates/` and
  `translations/` discoverable — the `@EekesSuluSpamProtection` template namespace, the routing
  file a project imports, and the translations Symfony registers on its own all hang off it.
- `getContainerExtension()` points at `Infrastructure\Symfony\DependencyInjection`. **This is a
  deliberate deviation.** Symfony documents `src/DependencyInjection/` for container extensions,
  and the base class looks there. The extension is Symfony glue like everything else under
  `Infrastructure`, so it lives with the rest of it — the same choice Sulu makes for
  `SuluTrashBundle`, `SuluReferenceBundle` and `SuluPreviewBundle`. Deviating costs exactly this
  one override; keep it if you move the class again.

Where a class goes:

| | |
|:---|:---|
| Entity, repository, value object | `Domain` — concrete repositories, no interfaces over them |
| A use case, a service other code calls, a console command, an event listener | `Application` |
| A port another implementation can fill (`SpamCheckerInterface`) | `Application/Service` |
| An implementation of such a port, a Doctrine listener, anything Sulu | `Infrastructure` |
| The configuration tree, the container extension | `Infrastructure/Symfony/DependencyInjection` |

## Conventions

### Never require a bundle you can do without

`sulu/form-bundle` and the Sulu admin bundle are optional. Services that need them live in their
own `services_sulu_*.yaml` and are loaded conditionally:

```php
if (self::hasBundle($container, 'SuluFormBundle')) {
    $loader->load('services_sulu_form.php');
}
```

Gate on the `kernel.bundles` parameter, not on `class_exists()`: the classes can be autoloadable
while the bundle is not registered, which is exactly the case in the test kernel.

Not on `hasExtension()` either, however tempting it reads. `load()` is handed a throwaway container
that carries the parameters but none of the other extensions, so `hasExtension('sulu_form')` always
answers no and the services are silently never registered — spam protection that installs cleanly
and checks nothing. `prepend()` does get the real container, so there it is fine.

### Tag the admin controllers by hand

A controller of this bundle is registered with an explicit `controller.service_arguments` tag and
never with `autoconfigure()`. Autoconfiguration answers the `#[Route]` attribute with a
`routing.controller` tag as well, and an application that imports `resource: routing.controllers` -
what the Symfony skeleton does - publishes every route a second time, without the `/admin/api`
prefix a project imports them under. Those copies sit outside the firewall that guards the
administration interface, which leaves the log of blocked submissions readable and deletable by
anyone who knows the path.

A new controller therefore gets the tag added in `config/services_sulu_admin.php`, and
`EekesSuluSpamProtectionExtensionTest` guards that it stays that way.

### Everything a project might want to change is configuration

Table names, titles, retention, redirect targets. Defaults must be what a normal Sulu site needs,
so installing the bundle and adding the field to a form is enough. Add the option to
`Infrastructure/Symfony/DependencyInjection/Configuration.php`, read it through
`SpamProtectionConfig`, and document it in `docs/configuration.md`.

Do not read the processed configuration as a raw array beyond the extension: turn it into typed
values and pass scalars into services as parameters.

### No migrations

Projects generate their own with `doctrine:migrations:diff`. The entity mapping is registered by
the extension; the table name stays changeable through a `loadClassMetadata` listener.

### Anything that touches stored submission data is a documented decision

What is stored, for how long, and what can be switched off belongs in `docs/configuration.md`
before it belongs in the code.

### Code style

- PHP 8.3. `declare(strict_types=1)` everywhere, constructor property promotion, `final` unless
  something is meant to be extended, `readonly` for services and value objects.
- **No alignment padding.** One space around `=>` and `=`, never padded into columns.
- **No Yoda conditions.** `if ($user === null)`, never `if (null === $user)`.
- PHPStan runs at **level max** over `src/` and `tests/`. Fix the cause, never add a baseline or an
  ignore. Narrowing a value that genuinely arrives as `mixed` — a container parameter, a console
  option — with an explicit check is the fix, not a cast.
- Everything in English: code, comments, commit messages, documentation. Text an editor or visitor
  sees may be translated; the identifiers around it stay English.

### Comments

Explain **why**, never what the next line already says. A comment earns its place when it records a
decision that is not visible in the code: why a listener has that priority, why a failure is
swallowed, why a bot gets a success page.

## Tests

**Every change in behaviour ships with tests.**

- `tests/Unit` — plain `TestCase`, no container. Value objects, and anything whose collaborators
  can be built by hand.
- `tests/Functional` — extends `FunctionalTestCase`, which boots `tests/Application/Kernel`. Every
  test gets an empty SQLite database built from the entity metadata.

### The test application has no Sulu bundles

On purpose. Booting a real Sulu application needs PHPCR, webspaces and a content repository, which
would make the suite slow and brittle for what it actually tests. The consequences:

- classes that only use Sulu's plain entities and interfaces (`Form`, `FormField`,
  `BuilderInterface`) are tested by **building those objects by hand** — see `tests/Double`;
- services behind `SuluAdminBundle` in `kernel.bundles` are not in the container during tests, so a
  test builds them itself with the real collaborators from the container — see
  `RecaptchaFieldTypeTest`, and `EekesSuluSpamProtectionExtensionTest` for the gating itself;
- a framework service a test needs gets a public `eekes_test.*` alias in
  `tests/Application/config/config.yaml`.

The Sulu admin classes are therefore covered by review rather than by the suite. Changes there
deserve a manual check in a real Sulu project.

### What to test

Test the guarantee, not the implementation: spam gets the same answer as a genuine visitor, is
never stored as a submission, and is logged with everything the sender typed. Name tests after that
guarantee — `testSpamIsLoggedAndDroppedWithoutAnyErrorTheBotCanSee`, not `testCheckReturnsFalse`.

Prefer real objects and hand-written doubles over mocks. An outside service (Google) is replaced by
a double that queues answers, so the real code path keeps running.

## Before opening a pull request

1. Tests for the behaviour you changed.
2. `composer test && composer phpstan && composer cs-fix && composer psr`.
3. The relevant page under `docs/` updated.
