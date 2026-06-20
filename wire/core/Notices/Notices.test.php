<?php namespace ProcessWire;

/**
 * Tests for ProcessWire Notices and Notice classes
 *
 */
class WireTest_Notices extends WireTest {

	protected $hookID = '';
	protected $logged = array();
	protected $debugOriginal = null;

	public function init() {
		$this->debugOriginal = $this->wire()->config->debug;
	}

	public function execute() {
		$this->testNoticeObjects();
		$this->testNoticesCollection();
		$this->testLoggingAndVisibility();
		$this->testFormattingAndRendering();
	}

	public function finish() {
		$this->stopLogCapture();
		$this->wire()->config->debug = $this->debugOriginal;
	}

	protected function testNoticeObjects() {
		$notice = $this->wire(new NoticeMessage('icon-check Saved', 'log noGroup icon-star'));

		$this->check('NoticeMessage getName() returns messages log name', 'messages', $notice->getName());
		$this->check('NoticeWarning getName() returns warnings log name', 'warnings', $this->wire(new NoticeWarning('Careful'))->getName());
		$this->check('NoticeError getName() returns errors log name', 'errors', $this->wire(new NoticeError('Failed'))->getName());
		$this->check('icon- prefix in text sets icon', 'star', $notice->icon);
		$this->check('icon- prefix is removed from text', 'Saved', $notice->text);
		$this->check('flagsStr lists active named flags', 'log noGroup', $notice->flagsStr);
		$this->check('flagsArray includes log flag', 'log', $notice->flagsArray[Notice::log]);
		$this->check('hasFlag() returns boolean true for active flag', true, $notice->hasFlag('log'));
		$this->check('hasFlag() returns boolean false for inactive flag', false, $notice->hasFlag('debug'));

		$notice->removeFlag('log');
		$this->check('removeFlag() removes named flag', false, $notice->hasFlag('log'));
		$notice->addFlag('markdown');
		$this->check('addFlag() accepts alias flag names', true, $notice->hasFlag('allowMarkdown'));
		$notice->flags(array('duplicate', Notice::prepend));
		$this->check('flags(array) accepts aliases and integers', true, $notice->hasFlag('allowDuplicate'));
		$this->check('flags(array) accepts integer flags', true, $notice->hasFlag('prepend'));

		$idStr = $notice->idStr;
		$this->check('idStr property matches getIdStr()', $idStr, $notice->getIdStr());
		$this->check('idStr has notice type prefix', 'NM', $idStr, '^=');
		$this->check('__toString() returns text for string notices', 'Saved', (string) $notice);
		$this->check('__toString() identifies array notices', 'array(2)', (string) $this->wire(new NoticeMessage(array('a', 'b'))));
		$this->check('__toString() identifies object notices', 'object:WireData', (string) $this->wire(new NoticeMessage(new WireData())), '^=');
	}

	protected function testNoticesCollection() {
		$notices = $this->wire(new Notices());

		$this->check('Notices is WireArray', true, $notices instanceof WireArray);
		$this->check('makeBlankItem() returns NoticeMessage', true, $notices->makeBlankItem() instanceof NoticeMessage);

		$message = $this->wire(new NoticeMessage('First'));
		$notices->add($message);
		$this->check('add() stores NoticeMessage', 1, count($notices));
		$this->check('add() increments qty on first add', 1, $message->qty);

		$notices->add(new NoticeMessage('First'));
		$this->check('duplicate notices collapse to one item', 1, count($notices));
		$this->check('duplicate notices increment retained qty', 2, $message->qty);

		$notices->add(new NoticeMessage('First', Notice::allowDuplicate));
		$notices->add(new NoticeMessage('First', Notice::allowDuplicate));
		$this->check('allowDuplicate keeps duplicate notices separate', 3, count($notices));

		$notices->add(new NoticeWarning('Warning'));
		$notices->add(new NoticeError('Error'));
		$this->check('hasWarnings() detects warning notices', true, $notices->hasWarnings());
		$this->check('hasErrors() detects error notices', true, $notices->hasErrors());

		$notices->add(new NoticeMessage('Last'));
		$notices->add(new NoticeMessage('Prepended', Notice::prepend));
		$this->check('prepend flag inserts notice at beginning', 'Prepended', $notices->first()->text);

		$from = $this->wire(new WireData());
		$to = $this->wire(new WireData());
		$from->message('Move message');
		$from->warning('Move warning');
		$from->error('Move error');
		$this->check('move() moves three notice types', 3, $notices->move($from, $to, array(
			'prefix' => '[',
			'suffix' => ']',
		)));
		$this->check('move() clears source messages', 0, count($from->messages()));
		$this->check('move() prefixes/suffixes moved message', '[Move message]', $to->messages('first')->text);
		$this->check('move() prefixes/suffixes moved warning', '[Move warning]', $to->warnings('first')->text);
		$this->check('move() prefixes/suffixes moved error', '[Move error]', $to->errors('first')->text);
	}

	protected function testLoggingAndVisibility() {
		$this->startLogCapture();

		$notices = $this->wire(new Notices());
		$logged = $this->wire(new NoticeMessage('Logged notice', Notice::log));
		$notices->add($logged);
		$this->check('Notice::log writes to matching log name', 'messages', $this->logged[0]['name']);
		$this->check('Notice::log keeps notice visible', 1, count($notices));
		$this->check('Notice::log flag is removed after logging', false, $logged->hasFlag('log'));

		$notices->add(new NoticeWarning('Log only notice', Notice::logOnly));
		$this->check('Notice::logOnly writes to matching log name', 'warnings', $this->logged[1]['name']);
		$this->check('Notice::logOnly does not add visible notice', 1, count($notices));

		$this->stopLogCapture();

		$config = $this->wire()->config;
		$config->debug = true;
		$debugNotice = $this->wire(new NoticeMessage('Debug only', Notice::debug));
		$notices->add($debugNotice);
		$this->check('debug notice added when debug mode is on', 2, count($notices));
		$this->check('viewable() true for debug notice when debug mode is on', true, $debugNotice->viewable());
		$this->check('getVisible() includes debug notice when debug mode is on', 2, count($notices->getVisible()));
		$this->check('getVisible() does not mutate notice qty', 1, $debugNotice->qty);

		$config->debug = false;
		$this->check('viewable() false for debug notice when debug mode is off', false, $debugNotice->viewable());
		$this->check('getVisible() excludes debug notice when debug mode is off', 1, count($notices->getVisible()));

		$notices->add(new NoticeMessage('Skipped debug', Notice::debug));
		$this->check('add() skips debug notice when debug mode is off', 2, count($notices));
	}

	protected function testFormattingAndRendering() {
		$notices = $this->wire(new Notices());
		$this->wire()->config->debug = false;

		$notices->add(new NoticeMessage(array('Label' => 'Value')));
		$this->check('single-key array notice adds text label', "Label: \nValue", $notices->last()->text);

		$notices->add(new NoticeMessage(array('Bold' => '<em>Value</em>'), Notice::allowMarkup));
		$this->check('single-key markup notice uses strong label', '<strong>Bold:</strong>', $notices->last()->text, '*=');

		$notices->add(new NoticeMessage(array('alpha' => '<tag>', 'nested' => array('beta' => '&')), Notice::allowDuplicate));
		$this->check('array notice is formatted as markup', true, $notices->last()->hasFlag('allowMarkup'));
		$this->check('array notice text contains sanitized key', 'alpha', $notices->last()->text, '*=');

		$notices->add(new NoticeMessage('**Markdown**', Notice::allowMarkdown));
		$this->check('allowMarkdown is converted to allowMarkup', true, $notices->last()->hasFlag('allowMarkup'));
		$this->check('allowMarkdown flag is removed after formatting', false, $notices->last()->hasFlag('allowMarkdown'));

		$notices->add(new NoticeWarning('Plain warning'));
		$notices->add(new NoticeError('<strong>Markup error</strong>', Notice::allowMarkup));
		$text = $notices->renderText();
		$this->check('renderText() includes message text', 'Label:', $text, '*=');
		$this->check('renderText() includes warning text', 'Plain warning', $text, '*=');
		$this->check('renderText() strips markup from markup notices', 'Markup error', $text, '*=');
		$this->check('renderText() indents multiline notice text', "\n  Value", $text, '*=');
	}

	protected function startLogCapture() {
		$test = $this;
		$this->logged = array();
		$this->hookID = $this->wire()->addHookBefore('WireLog::save', function(HookEvent $e) use ($test) {
			$test->logged[] = array(
				'name' => $e->arguments(0),
				'text' => $e->arguments(1),
			);
			$e->return = true;
			$e->replace = true;
		});
	}

	protected function stopLogCapture() {
		if(!$this->hookID) return;
		$this->wire()->removeHook($this->hookID);
		$this->hookID = '';
	}
}
