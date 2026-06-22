<?php namespace ProcessWire;

/**
 * Tests for ProcessWire $mail API variable
 *
 */
class WireTest_WireMailTools extends WireTest {

	protected $hookID = '';
	protected $sent = array();
	protected $attachmentFile = '';
	protected $originalWireMailConfig = null;

	public function init() {
		$wire = $this->wire();
		$test = $this;

		$this->originalWireMailConfig = $wire->config->wireMail;
		$wire->config->wireMail('module', 'WireMail');
		$this->attachmentFile = $wire->config->paths->cache . 'wiretests-mail-attachment.txt';
		file_put_contents($this->attachmentFile, 'WireMail attachment test');

		$this->hookID = $wire->addHookBefore('WireMail::send', null, function(HookEvent $e) use ($test) {
			/** @var WireMail $wireMail */
			$wireMail = $e->object;
			$emails = $wireMail->get('to');
			$test->recordSend($wireMail);
			$e->return = count($emails);
			$e->replace = true;
		}, array('priority' => 1));
	}

	public function execute() {
		$mail = $this->wire()->mail;

		// ===== API VARIABLE AND NEW INSTANCES =====

		$this->check('$mail is WireMailTools', true, $mail instanceof WireMailTools);
		$this->check('$mail->new() returns WireMail', true, $mail->new('WireMail') instanceof WireMail);
		$this->check('$mail->send() with no arguments returns WireMail', true, $mail->send() instanceof WireMail);
		$this->check('wireMail() returns WireMail', true, wireMail() instanceof WireMail);
		$this->check('$mail->new property returns WireMail', true, $mail->new instanceof WireMail);

		$m = $mail->new(array(
			'module' => 'WireMail',
			'to' => array('alpha@example.com' => 'Alpha User'),
			'from' => 'sender@example.com',
			'fromName' => 'Sender Name',
			'subject' => 'Preset subject',
			'headers' => array('X-Preset' => 'one'),
		));
		$this->check('new(options) pre-populates from', 'sender@example.com', $m->from);
		$this->check('new(options) pre-populates fromName', 'Sender Name', $m->fromName);
		$this->check('new(options) pre-populates subject', 'Preset subject', $m->subject);
		$this->check('new(options) pre-populates to', array('alpha@example.com' => 'alpha@example.com'), $m->to);
		$this->check('new(options) pre-populates toName', 'Alpha User', $m->toName['alpha@example.com']);
		$this->check('new(options) pre-populates headers', 'one', $m->headers['X-Preset']);

		// ===== BUILDER RECIPIENTS AND PROPERTIES =====

		$m = $mail->new('WireMail');
		$this->check('builder to() returns same WireMail instance', true, $m->to('one@example.com') === $m);
		$m->to('Two User <two@example.com>');
		$m->to('three@example.com, Four User <four@example.com>');
		$m->to(array('five@example.com', 'six@example.com' => 'Six User'));
		$m->to('seven@example.com', 'Seven User');
		$this->check('to() accumulates unique recipients', 7, count($m->to));
		$this->check('to() named-address string stores name', 'Two User', $m->toName['two@example.com']);
		$this->check('to() CSV named-address string stores name', 'Four User', $m->toName['four@example.com']);
		$this->check('to() associative array stores name', 'Six User', $m->toName['six@example.com']);
		$this->check('to() second argument stores name', 'Seven User', $m->toName['seven@example.com']);
		$m->to(null);
		$this->check('to(null) clears recipients', array(), $m->to);

		$m->to('named@example.com')->toName('Named User');
		$this->check('toName() sets name for last recipient', 'Named User', $m->toName['named@example.com']);
		$m->from('From User <from@example.com>');
		$this->check('from() extracts address from named string', 'from@example.com', $m->from);
		$this->check('from() extracts name from named string', 'From User', $m->fromName);
		$m->from('other@example.com', 'Other User');
		$this->check('from(email, name) sets address', 'other@example.com', $m->from);
		$this->check('from(email, name) sets name', 'Other User', $m->fromName);
		$m->replyTo('Replies <reply@example.com>');
		$this->check('replyTo() extracts address', 'reply@example.com', $m->replyTo);
		$this->check('replyTo() extracts name', 'Replies', $m->replyToName);
		$headers = $m->headers;
		$this->check('replyTo() adds Reply-To header', true, array_key_exists('Reply-To', $headers));
		$m->replyToName('Reply Team');
		$this->check('replyToName() updates Reply-To name', 'Reply Team', $m->replyToName);
		$m->subject("Hello\r\nBcc: bad@example.com");
		$this->check('subject() strips header injection newlines', false, strpos($m->subject, "\n") !== false);
		$m->body('Plain body')->bodyHTML('<html><body><h1>HTML body</h1></body></html>');
		$this->check('body() sets plain text body', 'Plain body', $m->body);
		$this->check('bodyHTML() sets HTML body', '<h1>HTML body</h1>', $m->bodyHTML, '*=');

		// ===== HEADERS, PARAMS AND ATTACHMENTS =====

		$m = $mail->new('WireMail');
		$m->header('x-custom-header', "Value\r\nInjected: no");
		$headers = $m->headers;
		$this->check('header() normalizes header name', true, array_key_exists('X-Custom-Header', $headers));
		$this->check('header() sanitizes header value', false, strpos($m->headers['X-Custom-Header'], "\n") !== false);
		$m->headers(array('X-One' => '1', 'X-Two' => '2'));
		$this->check('headers() sets first header', '1', $m->headers['X-One']);
		$this->check('headers() sets second header', '2', $m->headers['X-Two']);
		$m->header('X-One', null);
		$headers = $m->headers;
		$this->check('header(name, null) removes header', false, array_key_exists('X-One', $headers));
		$m->param('-f bounce@example.com')->param('-odb');
		$this->check('param() appends parameters', array('-f bounce@example.com', '-odb'), $m->param);
		$m->param(null);
		$this->check('param(null) clears parameters', array(), $m->param);
		$m->attachment($this->attachmentFile, 'Custom Name.txt');
		$this->check('attachment() stores existing file with basename override', $this->attachmentFile, $m->attachments['Custom Name.txt']);
		$m->attachment('/path/does/not/exist.txt');
		$this->check('attachment() ignores missing files', 1, count($m->attachments));
		$m->attachment(null);
		$this->check('attachment(null) clears attachments', array(), $m->attachments);

		// ===== SENDING WITHOUT REAL MAIL DELIVERY =====

		$this->clearSends();
		$numSent = $mail->send(array('a@example.com', 'b@example.com'), 'Sender <sender@example.com>', 'Quick subject', 'Quick body');
		$sent = $this->lastSend();
		$this->check('send() returns recipient count from WireMail::send()', 2, $numSent);
		$this->check('send() configured to recipients', array('a@example.com' => 'a@example.com', 'b@example.com' => 'b@example.com'), $sent['to']);
		$this->check('send() configured from address', 'sender@example.com', $sent['from']);
		$this->check('send() configured from name', 'Sender', $sent['fromName']);
		$this->check('send() configured subject', 'Quick subject', $sent['subject']);
		$this->check('send() configured body', 'Quick body', $sent['body']);

		$this->clearSends();
		$numSent = $mail->send('html@example.com', 'sender@example.com', 'HTML subject', array(
			'body' => 'Text body',
			'bodyHTML' => '<html><body><p>HTML body</p></body></html>',
			'replyTo' => 'reply@example.com',
			'headers' => array('X-Campaign' => 'wiretests'),
			'customOption' => 'custom value',
		));
		$sent = $this->lastSend();
		$this->check('send(options body) returns count', 1, $numSent);
		$this->check('send(options body) configured plain body', 'Text body', $sent['body']);
		$this->check('send(options body) configured HTML body', '<p>HTML body</p>', $sent['bodyHTML'], '*=');
		$this->check('send(options body) configured Reply-To header', 'reply@example.com', $sent['headers']['Reply-To']);
		$this->check('send(options body) configured custom header', 'wiretests', $sent['headers']['X-Campaign']);
		$this->check('send(options body) passes unknown options to WireMail', 'custom value', $sent['customOption']);

		$this->clearSends();
		$numSent = $mail->send('html2@example.com', 'sender@example.com', 'HTML string option', 'Text body', '<p>HTML string</p>');
		$sent = $this->lastSend();
		$this->check('send(body, bodyHTML string) returns count', 1, $numSent);
		$this->check('send(body, bodyHTML string) configured bodyHTML', '<p>HTML string</p>', $sent['bodyHTML']);

		$this->clearSends();
		$numSent = $mail->sendHTML('html3@example.com', 'sender@example.com', 'sendHTML subject', '<h1>Hello</h1>', array('body' => 'Hello'));
		$sent = $this->lastSend();
		$this->check('sendHTML() returns count', 1, $numSent);
		$this->check('sendHTML() treats body argument as HTML', '<h1>Hello</h1>', $sent['bodyHTML']);
		$this->check('sendHTML() preserves supplied text body option', 'Hello', $sent['body']);

		$this->clearSends();
		$ok = $mail->mail('mail@example.com', 'PHP style', 'PHP body', "From: php@example.com\nX-Mode: test");
		$sent = $this->lastSend();
		$this->check('mail() returns boolean true when send count > 0', true, $ok);
		$this->check('mail() extracts From header', 'php@example.com', $sent['from']);
		$this->check('mail() preserves non-From string headers', 'test', $sent['headers']['X-Mode']);
		$this->check('mail() configured body', 'PHP body', $sent['body']);

		$this->clearSends();
		$ok = $mail->mail('mail2@example.com', 'Options style', array(
			'bodyHTML' => '<strong>Options HTML</strong>',
			'body' => 'Options text',
			'from' => 'options@example.com',
			'headers' => array('X-Options' => 'yes'),
		));
		$sent = $this->lastSend();
		$this->check('mail(message options) returns boolean true', true, $ok);
		$this->check('mail(message options) configured from', 'options@example.com', $sent['from']);
		$this->check('mail(message options) configured bodyHTML', '<strong>Options HTML</strong>', $sent['bodyHTML']);
		$this->check('mail(message options) configured header', 'yes', $sent['headers']['X-Options']);

		$this->clearSends();
		$ok = $mail->mailHTML('mailhtml@example.com', 'mailHTML subject', '<em>HTML</em>', array('From' => 'mailhtml@example.com'));
		$sent = $this->lastSend();
		$this->check('mailHTML() returns boolean true', true, $ok);
		$this->check('mailHTML() configured HTML body', '<em>HTML</em>', $sent['bodyHTML']);
		$this->check('mailHTML() accepts headers array', 'mailhtml@example.com', $sent['from']);

		$this->clearSends();
		$numSent = $mail->to('shortcut@example.com')->from('shortcut-sender@example.com')->subject('Shortcut')->body('Shortcut body')->send();
		$sent = $this->lastSend();
		$this->check('$mail->to()->from()->subject() shortcut sends one', 1, $numSent);
		$this->check('$mail shortcut configured recipient', array('shortcut@example.com' => 'shortcut@example.com'), $sent['to']);
		$this->check('$mail shortcut configured from', 'shortcut-sender@example.com', $sent['from']);
		$this->check('$mail shortcut configured subject', 'Shortcut', $sent['subject']);

		// ===== BLACKLIST AND HELPERS =====

		$blacklist = array(
			'blocked@example.com',
			'@badhost.example.com',
			'.subblocked.example.com',
			'/\\+alias@/',
		);
		$this->check('isBlacklistEmail() returns false when not matched', false, $mail->isBlacklistEmail('ok@example.com', array('blacklist' => $blacklist)));
		$this->check('isBlacklistEmail() matches exact address', true, $mail->isBlacklistEmail('blocked@example.com', array('blacklist' => $blacklist)));
		$this->check('isBlacklistEmail(why=true) returns matching rule', '@badhost.example.com', $mail->isBlacklistEmail('user@badhost.example.com', array('blacklist' => $blacklist, 'why' => true)));
		$this->check('isBlacklistEmail() matches subdomain rule', true, $mail->isBlacklistEmail('user@mail.subblocked.example.com', array('blacklist' => $blacklist)));
		$this->check('isBlacklistEmail() matches regex rule', true, $mail->isBlacklistEmail('user+alias@example.com', array('blacklist' => $blacklist)));
		$this->check('isBlacklistEmail() treats invalid address as blacklisted', true, $mail->isBlacklistEmail('not-an-email', array('blacklist' => $blacklist)));

		$this->wire()->config->wireMail('blacklist', array('blocked@example.com'));
		$threw = false;
		try {
			$mail->new('WireMail')->to('blocked@example.com');
		} catch(\Exception $e) {
			$threw = true;
		}
		$this->check('to() throws when address is blacklisted by config', true, $threw);

		$m = $mail->new('WireMail');
		$this->check('htmlToText() converts markup to text', 'Hello', $m->htmlToText('<p>Hello</p>'), '*=');
		$this->check('encodeSubject() returns string', true, is_string($m->encodeSubject('Hello subject')));
		$this->check('encodeMimeHeader() returns string', true, is_string($m->encodeMimeHeader('Sender Name')));
		$this->check('quotedPrintableString() wraps quoted-printable marker', '=?utf-8?Q?', $m->quotedPrintableString('Hello'), '^=');
	}

	public function finish() {
		if($this->hookID) $this->wire()->removeHook($this->hookID);
		if($this->attachmentFile && is_file($this->attachmentFile)) $this->wire()->files->unlink($this->attachmentFile);
		$this->wire()->config->wireMail = $this->originalWireMailConfig;
	}

	public function recordSend(WireMail $mail) {
		$this->sent[] = array(
			'to' => $mail->get('to'),
			'toName' => $mail->get('toName'),
			'from' => $mail->get('from'),
			'fromName' => $mail->get('fromName'),
			'replyTo' => $mail->get('replyTo'),
			'replyToName' => $mail->get('replyToName'),
			'subject' => $mail->get('subject'),
			'body' => $mail->get('body'),
			'bodyHTML' => $mail->get('bodyHTML'),
			'headers' => $mail->get('headers'),
			'param' => $mail->get('param'),
			'attachments' => $mail->get('attachments'),
			'customOption' => $mail->get('customOption'),
		);
	}

	protected function clearSends() {
		$this->sent = array();
	}

	protected function lastSend() {
		if(!count($this->sent)) $this->fail('Expected intercepted WireMail::send() call');
		return $this->sent[count($this->sent) - 1];
	}
}
