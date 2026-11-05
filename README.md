# Sulu Spam Protection Bundle

Invisible reCAPTCHA v3 in front of website form submissions: the dynamic forms editors build in the
Sulu admin, Live Component forms and any Symfony form. Visitors never see a challenge. A bot that is
caught gets the normal success page, so it never learns it was blocked, and everything it sent is
logged in the Sulu admin, so a genuine visitor who was flagged by mistake can still be followed up.

## What's inside

- [**Sulu forms**](https://sulu.eekes.be/sulu-spam-protection-bundle/sulu-forms): add the invisible **Spam protection** field to any form editors build in the admin.
- [**Symfony forms & Live Components**](https://sulu.eekes.be/sulu-spam-protection-bundle/custom-forms): protect your own forms and endpoints with `SpamCheckerInterface`.
- [**Blocked submissions**](https://sulu.eekes.be/sulu-spam-protection-bundle/blocked-submissions): review, filter and delete everything that was blocked, with retention and IP anonymising.
- [**Custom spam checker**](https://sulu.eekes.be/sulu-spam-protection-bundle/custom-spam-checker): swap reCAPTCHA for another provider or add checks of your own.

## Documentation

[Installation](https://sulu.eekes.be/sulu-spam-protection-bundle/installation), [configuration](https://sulu.eekes.be/sulu-spam-protection-bundle/configuration) and everything else: **[sulu.eekes.be/sulu-spam-protection-bundle](https://sulu.eekes.be/sulu-spam-protection-bundle)**.

## License

MIT
