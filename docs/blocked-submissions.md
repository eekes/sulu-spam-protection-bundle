# Blocked submissions

Everything the spam check stops is written to one table and shown under **Settings → Blocked
submissions** in the Sulu admin.

Without that list the check is a black box: nobody notices when the threshold is too strict and a
real customer stopped getting through. The log is what lets you tune the threshold on evidence
instead of on feeling.

## What is stored

| | |
|:---|:---|
| Blocked at | When it happened |
| Form | The title of the Sulu form, for submissions that came from one |
| Source | Which form — `sulu_form`, or whatever name your own code passes |
| Score | What reCAPTCHA gave it, if it gave one |
| Reason | The error codes that made the check fail |
| IP address, browser, page, language | Request context, to recognise a bot across submissions |
| Answers | What the sender filled in, without the technical fields Sulu adds itself |

The answers and the IP address can be dialled back with `store_payload` and `anonymize_ip` — see
[configuration.md](configuration.md).

## Permissions

The security context is `sulu.settings.eekes_blocked_submissions`, with **view** and **delete**.

- **view** shows the navigation item and the list.
- **delete** also opens the detail page of a single submission, which is where the answers are.

That is not an oversight. A log entry is never edited, so the only action a detail page has is
deleting it — and Sulu refuses a form view without any toolbar action. A user with view rights
gets everything except the answers, which is the read-only half of the feature.

## Pruning

```bash
bin/console eekes:spam-protection:prune
```

Deletes everything older than `retention_days`. Options:

| | |
|:---|:---|
| `--days=30` | Overrides the configured retention for this run |
| `--dry-run` | Only reports how many submissions would go |

Run it from cron:

```
0 3 * * * cd /path/to/project && php bin/console eekes:spam-protection:prune
```

A spam log grows quickly on a busy site, so run it on a schedule rather than waiting for the table
to become a problem.

## The log file

Next to the table, the bundle writes to the `spam_protection` Monolog channel. Two things end up
there: a submission that was accepted **without** being checked because the provider could not be
reached, and a database error while storing a blocked submission.

Give the channel its own file to keep it out of the main log:

```yaml
# config/packages/monolog.yaml
monolog:
    handlers:
        spam_protection:
            type: rotating_file
            path: '%kernel.logs_dir%/spam_protection/log.log'
            max_files: 30
            channels: spam_protection
```

If that file starts filling up, reCAPTCHA is unreachable from the server and every submission is
going through unchecked.
