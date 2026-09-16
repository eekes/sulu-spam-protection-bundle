# Configuration

Every option has a working default, so a project only writes down what it wants to change.
`bin/console config:dump-reference eekes_sulu_spam_protection` prints the full tree.

```yaml
# config/packages/eekes_sulu_spam_protection.yaml
eekes_sulu_spam_protection:
    table_name: 'eekes_blocked_submission'
    retention_days: 90
    store_payload: true
    anonymize_ip: false
    field_title: 'Spam protection'
    field_xml_path: null
    success_redirect: '?send=true'
    register_form_theme: true
    admin:
        enabled: true
        navigation_position: 40
```

## `table_name`

The table the blocked submissions are stored in. Change it before the first migration; afterwards
it needs a rename migration of your own.

The entity declares its table in a PHP attribute, which can only hold a constant, so the bundle
applies the configured name through a Doctrine `loadClassMetadata` listener.

## `retention_days`

How long `bin/console eekes:spam-protection:prune` keeps a blocked submission. `0` keeps
everything forever, which means nothing is deleted until you pass `--days` yourself.

## `store_payload`

Whether what the sender filled in is stored alongside the technical context.

Keep it on unless a customer says otherwise: the payload is what makes a false positive
recoverable, which is the whole reason the log exists. With it off the reason, score, page, IP
address and browser are still stored, so you can see *that* something was blocked and tune the
threshold.

Long answers are cut off at 10 000 characters per field either way, so a bot posting a megabyte
cannot fill the table.

## `anonymize_ip`

Masks the host part of the address before storing it: an IPv4 address is kept as a `/24`
(`203.0.113.9` → `203.0.113.0`), an IPv6 address as a `/48`
(`2001:db8:1234:5678::1` → `2001:db8:1234::`).

Off by default, because the full address is what lets you recognise a single bot hammering a form.
Switch it on when the network is all you need. An address that cannot be parsed is dropped rather
than stored raw.

## `field_title`

The name of the field editors add to a Sulu form. Sulu does not translate form field metadata, so
this is literal text — set it to the language of the administration interface.

## `field_xml_path`

The form field XML of that field, which holds the label and the help text an editor sees. The one
shipped with the bundle is English; to change the wording or the language, copy it and point this
option at the copy:

```bash
cp vendor/eekes/sulu-spam-protection-bundle/config/form-fields/recaptcha.xml \
   config/form-fields/recaptcha.xml
```

```yaml
eekes_sulu_spam_protection:
    field_title: 'Spambeveiliging'
    field_xml_path: '%kernel.project_dir%/config/form-fields/recaptcha.xml'
```

## `success_redirect`

What a blocked Sulu form submission is answered with. It has to be exactly the page a genuine
visitor gets after submitting, or a bot can tell the difference — which defeats the point. The
default is Sulu's own `?send=true`.

## `register_form_theme`

The widget of `karser/karser-recaptcha3-bundle` writes the token into a hidden input with plain
JavaScript. Inside a Live Component that is not enough: the component never notices the value
changed, and the input disappears once the form is replaced by a success message. The bundle ships
a form theme that fixes both and registers it automatically.

It is registered directly after karser's own theme and before anything the project lists itself: it
has to override the widget it replaces, while a form theme of the project still wins over both. Set
this to `false` to opt out entirely and handle the widget yourself.

## `admin.enabled`

Registers the list of blocked submissions under Settings. Switch it off to keep the log
database-only — the check and the logging keep working.

The admin services are also skipped automatically when the Sulu admin bundle is not registered at
all, so this option is only needed to hide a list that *could* be shown.

## `admin.navigation_position`

Where the item sits within Settings. Sulu sorts on this number, not on registration order.
