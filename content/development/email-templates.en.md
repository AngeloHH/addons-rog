# Email templates

These HTML design references are stored outside `public/` and are included when `api/`
is copied into a build. Their source directory is `api/templates/emails/`.

- [Blank.html](../../api/templates/emails/Blank.html) is the general layout.
- [Verification Code.html](../../api/templates/emails/Verification%20Code.html) is the account verification design.

## Email format

The HTML files contain sample text, URLs, a username and a verification code for visual
reference. Runtime messages are generated as plain text by `NotificationService` and
delivered by `Mailer.php`. The HTML references are separate from the delivery pipeline.
