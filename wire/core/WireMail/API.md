# WireMailTools / $mail

`$mail` is the API variable for sending email in ProcessWire. It provides both a
one-call send interface and a fluent builder (`WireMail`) for composing more complex
messages. If a WireMail module (e.g. WireMailSMTP, WireMailgun) is installed, it is
used automatically in place of ProcessWire's default WireMail (which uses PHP's 
built-in `mail()` function.

`$mail` is accessible in templates as `$mail`, or `wire()->mail` and in modules 
as `$this->wire()->mail`. There is also the `wireMail()` function, which returns
a new `WireMail` instance.

---

## Quick send

### $mail->send($to, $from, $subject, $body)

Send an email in a single call.

- **Arguments:** `send(string|array $to, string $from, string $subject, string|array $body = '', array|string $options = [])`
- **Returns:** `int` — number of addresses successfully sent to (0 on failure)
- If no arguments are supplied, returns a new `WireMail` instance instead.
- `$to` may be a single address, a CSV string, or an array.
- `$from` may be a plain address or `"Name <email>"` format.
- `$body` may be a plain-text string or an `$options` array (skipping the text-body argument).

~~~~~php
// Simplest form
$mail->send('user@example.com', 'you@example.com', 'Hello', 'Message body');

// Multiple recipients
$mail->send(['a@example.com', 'b@example.com'], 'you@example.com', 'Hi', 'Body');

// Named sender
$mail->send('user@example.com', 'You <you@example.com>', 'Hi', 'Body');

// With options array (HTML + text)
$mail->send('user@example.com', 'you@example.com', 'Hello', [
    'body'     => 'Plain text version',
    'bodyHTML' => '<p>HTML version</p>',
    'replyTo'  => 'replies@example.com',
    'headers'  => ['X-Campaign' => 'newsletter-1'],
]);
~~~~~

---

### $mail->sendHTML($to, $from, $subject, $bodyHTML)

Like `send()` but the body argument is treated as HTML. A plain-text version is
auto-generated from the HTML if no `body` option is provided.

- **Arguments:** `sendHTML(string|array $to, string $from, string $subject, string $bodyHTML, array $options = [])`
- **Returns:** `int`

~~~~~php
$mail->sendHTML('user@example.com', 'you@example.com', 'Hello',
    '<h1>Hello world</h1><p>This is HTML email.</p>'
);
~~~~~

---

### $mail->mail($to, $subject, $message)

Drop-in replacement for PHP's `mail()` function using the same argument order. Useful
when converting existing `mail()` calls to ProcessWire's mail system.

- **Arguments:** `mail(string|array $to, string $subject, string|array $message, array|string $headers = [])`
- **Returns:** `bool`
- Pass an array for `$message` to use options (`bodyHTML`, `body`, `from`, `replyTo`, `headers`).
- The `$headers` argument accepts an associative array or a `"Name: Value\n"` string.

~~~~~php
// PHP mail() style
$mail->mail('user@example.com', 'Subject', 'Message body');

// With From header (PHP mail() style)
$mail->mail('user@example.com', 'Subject', 'Body', 'From: hello@example.com');

// With options array (HTML email)
$mail->mail('user@example.com', 'Subject', [
    'bodyHTML' => '<h1>Hello</h1>',
    'from'     => 'hello@example.com',
]);
~~~~~

---

### $mail->mailHTML($to, $subject, $messageHTML)

Same as `$mail->mail()` but the message is treated as HTML with auto-generated text body.
The `$headers` argument is accepted in the same formats as `$mail->mail()`.

~~~~~php
$mail->mailHTML('user@example.com', 'Subject', '<h1>Hello</h1>');
~~~~~

---

## Fluent builder

For more complex messages, get a `WireMail` instance and chain method calls.

### $mail->new($options)

Get a new `WireMail` instance, optionally pre-populated with settings.

- **Arguments:** `new(array|string $options = [])`
- **Returns:** `WireMail`
- Pass `'WireMail'` (string) or `['module' => 'WireMail']` to force PHP's `mail()` and skip
  any installed WireMail module.
- Any `WireMail` property (`from`, `fromName`, `subject`, etc.) may be pre-set via `$options`.

~~~~~php
// Returns new WireMail instance
$m = $mail->new();

// Pre-populate with defaults
$m = $mail->new(['from' => 'no-reply@example.com', 'fromName' => 'My Site']);

// Force PHP mail() regardless of installed modules
$m = $mail->new('WireMail');
~~~~~

Alternatively, since 3.0.113 you can start a chain directly on `$mail` without calling
`new()` first — `$mail` proxies `to()`, `from()`, and `subject()` as shorthand:

~~~~~php
// Both are equivalent:
$numSent = $mail->new()->to('user@example.com')->from('you@example.com')
    ->subject('Hello')->body('World')->send();

$numSent = $mail->to('user@example.com')->from('you@example.com')
    ->subject('Hello')->body('World')->send();
~~~~~

---

## Builder methods

All builder methods return `$this` (the `WireMail` instance) for chaining.

### ->to($email, $name)

Set one or more recipient addresses.

- `$email` may be a single address, `"Name <email>"` string, CSV string, plain array, or
  associative `[email => name]` array.
- Call multiple times to accumulate recipients.
- Pass `null` to clear all previously set addresses.

~~~~~php
$m->to('user@example.com');
$m->to('John Smith <john@example.com>');
$m->to('a@example.com, b@example.com');
$m->to(['a@example.com', 'b@example.com']);
$m->to(['a@example.com' => 'Alice', 'b@example.com' => 'Bob']);
$m->to('user@example.com', 'User Name'); // name as second argument
$m->to(null); // clear all recipients
~~~~~

---

### ->from($email, $name)

Set the sender address.

- Accepts a plain address or `"Name <email>"` string.
- `$name` as second argument is equivalent to calling `fromName()`.

~~~~~php
$m->from('you@example.com');
$m->from('You <you@example.com>');
$m->from('you@example.com', 'Your Name');
~~~~~

---

### ->toName($name), ->fromName($name), ->replyToName($name)

Set display names separately from their email addresses.

- `toName()` applies to the most recently added recipient and requires `to()` first.
- `fromName()` sets the sender display name.
- `replyToName()` updates the reply-to display name and refreshes the `Reply-To` header
  if a reply-to address is already set.

~~~~~php
$m->to('user@example.com')->toName('User Name');
$m->from('you@example.com')->fromName('Your Name');
$m->replyTo('replies@example.com')->replyToName('Reply Team');
~~~~~

---

### ->subject($subject)

Set the email subject.

~~~~~php
$m->subject('Welcome to My Site');
~~~~~

---

### ->body($body)

Set the plain-text body.

~~~~~php
$m->body('Hello, thanks for signing up.');
~~~~~

---

### ->bodyHTML($html)

Set the HTML body. Provide a full HTML document (not just a fragment). When `bodyHTML`
is set without a `body`, a plain-text version is auto-generated from the HTML.

~~~~~php
$m->bodyHTML('<html><body><h1>Hello</h1><p>Thanks for signing up.</p></body></html>');
~~~~~

---

### ->attachment($file, $filename)

Attach a file to the email.

- `$file` must be a full filesystem path to an existing file.
- `$filename` optionally overrides the name shown in the email.
- Call multiple times to attach multiple files.
- Pass `null` to clear all attachments.
- Support depends on the installed WireMail module.

~~~~~php
$m->attachment('/path/to/report.pdf');
$m->attachment('/path/to/report.pdf', 'Q1-Report.pdf');
$m->attachment(null); // clear all attachments
~~~~~

---

### ->param($value)

Add extra parameters for PHP's native `mail()` function, such as an envelope sender.

- Call multiple times to append multiple parameters.
- Pass `null` to clear all parameters.
- These parameters only matter when ProcessWire's default `WireMail` class sends through
  PHP `mail()`; third-party WireMail modules may ignore them.

~~~~~php
$m->param('-f bounce@example.com');
$m->param(null); // clear all params
~~~~~

---

### ->replyTo($email, $name)

Set the reply-to address.

~~~~~php
$m->replyTo('replies@example.com');
$m->replyTo('Replies <replies@example.com>');
~~~~~

---

### ->header($name, $value)

Set a custom email header.

- Call multiple times to set multiple headers.
- Pass `null` as `$value` to remove a header.

~~~~~php
$m->header('X-Campaign', 'newsletter-may');
$m->header('X-Mailer', null); // remove header
~~~~~

---

### ->headers(array $headers)

Set multiple headers at once from an associative array.

~~~~~php
$m->headers(['X-Campaign' => 'newsletter-may', 'X-Priority' => '1']);
~~~~~

---

### ->send()

Send the composed email. Call after all properties are set.

- **Returns:** `int` — number of addresses successfully sent to (0 on failure)
- This is a hookable method (`___send()`), making it the right place for WireMail modules
  to override delivery behavior.

~~~~~php
$numSent = $m->to('user@example.com')
    ->from('you@example.com')
    ->subject('Hello')
    ->body('Plain text')
    ->bodyHTML('<p>HTML version</p>')
    ->send();

if(!$numSent) {
    // handle send failure
}
~~~~~

---

## Configuration

Default WireMail settings are set in `$config->wireMail` in `/site/config.php`:

~~~~~php
// Set a site-wide default from address and name
$config->wireMail('from', 'noreply@example.com');
$config->wireMail('fromName', 'My Site');

// Force a specific WireMail module
$config->wireMail('module', 'WireMailSMTP');

// Set default headers for all outgoing mail
$config->wireMail('headers', ['X-Mailer' => 'My Site']);
~~~~~

Any `WireMail` property may be set as a default here (e.g. `from`, `fromName`,
`subject`, `headers`). These become the starting values for every `$mail->new()` call.

---

## Email blacklist

Prevent email from being sent to certain addresses, domains, or patterns via
`$config->wireMail['blacklist']` in `/site/config.php`:

~~~~~php
$config->wireMail('blacklist', [
    'spam@example.com',         // exact address
    '@bad-host.example.com',    // all addresses at this host
    '@example.com',             // all addresses at this domain
    'example.com',              // any address ending with example.com
    '.example.com',             // any subdomain of example.com
    '/\+.*@/',                  // PCRE regex: block addresses with + alias
]);
~~~~~

Test an address against the blacklist with `$mail->isBlacklistEmail()`:

~~~~~php
$result = $mail->isBlacklistEmail('user@example.com', ['why' => true]);
if($result === false) {
    echo "Not blacklisted";
} else {
    echo "Blacklisted by rule: $result"; // string describes the matching rule
}
~~~~~

---

## Backend modules

When `$mail->new()` is called, ProcessWire auto-detects any installed module that
extends `WireMail` (e.g. WireMailSMTP, WireMailgun, WireMailPHPMailer) and uses it
automatically. No code change is required — install the module and it takes over.

To force a specific module in code:

~~~~~php
$m = $mail->new(['module' => 'WireMailGmail']);
$m = $mail->new('WireMailGmail'); // shorter alias
~~~~~

To force PHP's `mail()` and bypass any installed module:

~~~~~php
$m = $mail->new('WireMail');
~~~~~

---

## Notes

- Source files: `wire/core/WireMail/WireMail.php` (builder/sender) and
  `wire/core/WireMail/WireMailTools.php` (`$mail` API variable).
- `send()` returns the **count of addresses** emailed, not a boolean.
  Check `$numSent > 0` rather than just `$numSent` when the to-list may be empty.
- When only `bodyHTML` is set (no `body`), a plain-text version is automatically
  generated from the HTML via the hookable `htmlToText()` method.
- All builder methods sanitize their input — header names and values are stripped of
  control characters; email addresses are validated and rejected if invalid or blacklisted.
- The `X-Mailer` header is set automatically. Override it with `$m->header('X-Mailer', 'My App')`.
- `param()` passes additional parameters to PHP's `mail()` function (e.g. envelope-from
  `-f you@example.com`). This has no effect when a WireMail module handles delivery.
- A list of WireMail modules can be found here: <https://processwire.com/search/?q=WireMail&t=Modules>
