# Sulu Spam Protection Bundle

Invisible reCAPTCHA v3 in front of website form submissions — the dynamic forms editors build in
the Sulu admin, a Live Component form, or any Symfony form.

The visitor never sees a challenge. A submission that fails the check is answered with the ordinary
success page, so a bot never learns it was caught, and everything the sender typed is logged
instead — so a genuine visitor who was flagged by mistake can still be helped.

## Installation

```bash
composer require eekes/sulu-spam-protection-bundle
```

Register the bundle in `config/bundles.php`:

```php
Eekes\Sulu\SpamProtectionBundle\EekesSuluSpamProtectionBundle::class => ['all' => true],
```

### 1. reCAPTCHA v3 keys

Create a **v3** site at <https://www.google.com/recaptcha/admin>. A v2 key does not work on v3.

```dotenv
# .env
RECAPTCHA3_KEY=
RECAPTCHA3_SECRET=
RECAPTCHA3_SCORE_THRESHOLD=0.5
RECAPTCHA3_ENABLED=true
```

`karser/karser-recaptcha3-bundle` comes with this bundle; configure it once:

```yaml
# config/packages/karser_recaptcha3.yaml
karser_recaptcha3:
    site_key: '%env(RECAPTCHA3_KEY)%'
    secret_key: '%env(RECAPTCHA3_SECRET)%'
    score_threshold: '%env(float:RECAPTCHA3_SCORE_THRESHOLD)%'
    enabled: '%env(bool:RECAPTCHA3_ENABLED)%'
```

`enabled: false` turns the whole check off — handy on a local machine. The score threshold decides
how strict it is; 0.5 is Google's own advice. Start there, then look at the blocked submissions
before tightening it.

### 2. Database

The entity mapping is registered by the bundle, so only a migration is left:

```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

### 3. Admin routes

Add one import so the administration interface can reach the list:

```yaml
# config/routes/sulu_admin.yaml
eekes_sulu_spam_protection_api:
    resource: "@EekesSuluSpamProtectionBundle/config/routing_admin_api.php"
    prefix: /admin/api
```

Then give the role permission on **Settings → Blocked submissions** in the Sulu user management.

## Using it

### In a Sulu form

Open the form in the admin and add the **Spam protection** field. From then on every submission of
that form is checked. The field is invisible to visitors and does not affect the layout, so it can
sit anywhere in the form.

Adding the field also switches Sulu's CSRF protection off for that form — Sulu does that itself,
because a cached page cannot carry a valid CSRF token. The reCAPTCHA check takes its place.

### Anywhere else

Inject `SpamCheckerInterface`, check the token, and decide what to answer. See
[docs/custom-forms.md](docs/custom-forms.md) for the full pattern.

## Documentation

- [Configuration](docs/configuration.md)
- [Custom forms](docs/custom-forms.md)
- [Blocked submissions](docs/blocked-submissions.md)

## Compatibility

PHP 8.3+, Symfony 7.2+, `sulu/sulu` 3.0. `sulu/form-bundle` is optional: its integration is only
loaded when the bundle is registered, so the checker and the log work in a plain Symfony
application too.

## License

MIT. See [LICENSE](LICENSE).
