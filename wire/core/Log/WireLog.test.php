<?php namespace ProcessWire;

/**
 * Tests for ProcessWire $log API variable
 *
 */
class WireTest_WireLog extends WireTest {

	protected $prefix = 'wiretests-wire-log';
	protected $names = array();
	protected $noticeHookID = '';
	protected $noticeSaves = array();
	protected $fileLogPath = '';

	public function init() {
		$this->fileLogPath = $this->wire()->config->paths->cache . 'WireTestsFileLog/';
		$this->cleanupLogs();
		if(is_dir($this->fileLogPath)) $this->wire()->files->rmdir($this->fileLogPath, true);
	}

	public function execute() {
		$log = $this->wire()->log;

		$this->check('$log is WireLog', true, $log instanceof WireLog);
		$this->check('$log->path() returns logs path', $this->wire()->config->paths->logs, $log->path());

		// ===== SAVING AND READING =====

		$name = $this->name('basic');
		$this->check('save() creates log entry', true, $log->save($name, 'Plain log entry', array(
			'showUser' => false,
			'showURL' => false,
		)));
		$this->check('exists() finds created log', true, $log->exists($name));
		$this->check('getTotalEntries() counts created entry', 1, $log->getTotalEntries($name));
		$entry = $this->lastEntry($name);
		$this->check('getEntries() returns saved text', 'Plain log entry', $entry['text']);
		$this->check('getEntries() omits user when showUser=false', '', $entry['user']);
		$this->check('getEntries() omits URL when showURL=false', '', $entry['url']);
		$lines = $log->getLines($name, array('limit' => 1));
		$this->check('getLines() returns raw log line', 'Plain log entry', reset($lines), '*=');

		$name = $this->name('metadata');
		$log->save($name, 'Entry with metadata', array(
			'user' => 'wiretests-user',
			'url' => '/wiretests/log/',
			'delimiter' => '|',
		));
		$line = $this->firstLine($name, array('delimiter' => '|'));
		$this->check('save() supports custom user option', '|wiretests-user|', $line, '*=');
		$this->check('save() supports custom url option', '|/wiretests/log/|', $line, '*=');
		$this->check('save() supports custom delimiter option', '|Entry with metadata', $line, '*=');
		$this->check('getEntries() reads custom delimiter when specified', 'Entry with metadata', $this->lastEntry($name, array('delimiter' => '|'))['text']);

		$name = $this->name('array');
		$log->save($name, array('id' => 123, 'status' => 'paid'), array(
			'showUser' => false,
			'showURL' => false,
		));
		$entry = $this->lastEntry($name);
		$this->check('save(array) stores JSON wrapper', '~~~', $entry['text'], '^=');
		$this->check('save(array) stores array data', '"status": "paid"', $entry['text'], '*=');

		$name = $this->name('newlines');
		$log->save($name, "Line one\nLine two", array(
			'showUser' => false,
			'showURL' => false,
			'allowNewlines' => true,
		));
		$this->check('allowNewlines restores newlines in getEntries()', "Line one\nLine two", $this->lastEntry($name)['text']);
		$this->check('allowNewlines stores placeholder in raw line', WireLog::newline, $this->firstLine($name), '*=');

		$name = $this->name('maxlength');
		$log->save($name, 'abcdefghijklmnopqrstuvwxyz', array(
			'showUser' => false,
			'showURL' => false,
			'maxLineLength' => 10,
		));
		$this->check('maxLineLength truncates log text', 'abcdefghij', $this->lastEntry($name)['text']);

		// ===== QUEUE AND DISABLE =====

		$name = $this->name('disabled');
		$log->disable($name);
		$this->check('disable(name) makes save() return false', false, $log->save($name, 'Disabled entry'));
		$this->check('disable(name) prevents file creation', false, $log->exists($name));
		$log->enable($name);
		$this->check('enable(name) restores saving', true, $log->save($name, 'Enabled entry', array(
			'showUser' => false,
			'showURL' => false,
		)));

		$name = $this->name('queue');
		$this->check('save(queue) returns false before deferred write', false, $log->save($name, 'Queued one', array(
			'queue' => true,
			'showUser' => false,
			'showURL' => false,
		)));
		$log->save($name, 'Queued two', array(
			'queue' => true,
			'showUser' => false,
			'showURL' => false,
		));
		$this->check('save(queue) defers file creation', false, $log->exists($name));
		$log->finished();
		$this->check('finished() writes queued entries as one entry', 1, $log->getTotalEntries($name));
		$this->check('finished() groups queued entries with newlines', "- Queued one\n- Queued two", $this->lastEntry($name)['text']);

		$log->disable('*');
		$name = $this->name('disabled-all');
		$this->check('disable(*) blocks all log names', false, $log->save($name, 'Blocked entry'));
		$log->enable('*');
		$this->check('enable(*) reverses disable(*)', true, $log->save($name, 'Unblocked entry', array(
			'showUser' => false,
			'showURL' => false,
		)));

		// ===== NOTICE AND WIRE LOG SHORTCUTS =====

		$this->startNoticeCapture();
		$log->message('WireTests captured message');
		$log->warning('WireTests captured warning');
		$log->error('WireTests captured error');
		$this->stopNoticeCapture();
		$this->check('message() routes to messages log', 'messages', $this->noticeSaves[0]['name']);
		$this->check('warning() routes to warnings log', 'warnings', $this->noticeSaves[1]['name']);
		$this->check('error() routes to errors log', 'errors', $this->noticeSaves[2]['name']);

		$name = $this->name('wire-shortcut');
		$wire = $this->wire(new WireData());
		$this->check('Wire::log() returns $log API variable', true, $wire->log() === $log);
		$wire->log('Logged from Wire::log()', array(
			'name' => $name,
			'showUser' => false,
			'showURL' => false,
		));
		$this->check('Wire::log() writes to override log name', 'Logged from Wire::log()', $this->lastEntry($name)['text']);

		// ===== INSPECTION, PRUNE AND DELETE =====

		$name = $this->name('inspect');
		$log->save($name, 'Inspect me', array('showUser' => false, 'showURL' => false));
		$logs = $log->getLogs();
		$this->check('getLogs() includes created log', true, isset($logs[$name]));
		$this->check('getLogs() reports log size', true, $logs[$name]['size'] > 0);
		$this->check('getFilename() returns txt path', "$name.txt", $log->getFilename($name), '$=');

		$threw = false;
		try {
			$log->getFilename('bad/name');
		} catch(WireException $e) {
			$threw = true;
		}
		$this->check('getFilename() rejects invalid log names', true, $threw);

		$name = $this->name('prune');
		file_put_contents($log->getFilename($name),
			date('Y-m-d H:i:s', strtotime('-10 days')) . "\tuser\turl\tOld entry\n" .
			date('Y-m-d H:i:s') . "\tuser\turl\tCurrent entry\n"
		);
		$this->check('prune() returns remaining entry count', 1, $log->prune($name, 1));
		$this->check('prune() keeps current entry', 'Current entry', $this->lastEntry($name)['text']);

		$this->check('delete() removes existing log', true, $log->delete($name));
		$this->check('delete() returns false for missing log', false, $log->delete($name));

		// ===== FILELOG BACKEND =====

		$this->testFileLogBackend();
		$this->testRepeatedLogLines();
	}

	public function finish() {
		$this->stopNoticeCapture();
		$this->cleanupLogs();
		if($this->fileLogPath && is_dir($this->fileLogPath)) $this->wire()->files->rmdir($this->fileLogPath, true);
	}

	protected function name($suffix) {
		$name = $this->prefix . '-' . $suffix;
		$this->names[$name] = $name;
		return $name;
	}

	protected function cleanupLogs() {
		$log = $this->wire()->log;
		$files = $this->wire()->files;
		foreach($this->names as $name) {
			try {
				if($log->exists($name)) $log->delete($name);
			} catch(\Exception $e) {
				// Ignore cleanup exceptions so the original test failure remains visible.
			}
			$filename = $log->getFilename($name);
			foreach(array($filename, "$filename.lock", "$filename.new") as $file) {
				if(is_file($file)) $files->unlink($file, true);
			}
		}
	}

	protected function firstLine($name, array $options = array()) {
		$options = array_merge(array('limit' => 1), $options);
		$lines = $this->wire()->log->getLines($name, $options);
		if(!count($lines)) $this->fail("Expected log line in $name");
		return reset($lines);
	}

	protected function lastEntry($name, array $options = array()) {
		$options = array_merge(array('limit' => 1), $options);
		$entries = $this->wire()->log->getEntries($name, $options);
		if(!count($entries)) $this->fail("Expected log entry in $name");
		return reset($entries);
	}

	protected function startNoticeCapture() {
		$test = $this;
		$this->noticeSaves = array();
		$this->noticeHookID = $this->wire()->addHookBefore('WireLog::save', function(HookEvent $e) use ($test) {
			$test->noticeSaves[] = array(
				'name' => $e->arguments(0),
				'text' => $e->arguments(1),
			);
			$e->return = true;
			$e->replace = true;
		});
	}

	protected function stopNoticeCapture() {
		if(!$this->noticeHookID) return;
		$this->wire()->removeHook($this->noticeHookID);
		$this->noticeHookID = '';
	}

	protected function testFileLogBackend() {
		$files = $this->wire()->files;
		if(!is_dir($this->fileLogPath)) $files->mkdir($this->fileLogPath, true);

		/** @var FileLog $fileLog */
		$fileLog = $this->wire(new FileLog($this->fileLogPath, 'wiretests-filelog'));
		$this->check('FileLog::save() writes a line', true, $fileLog->save('Direct file log entry'));
		$this->check('FileLog::filename() returns basename', 'wiretests-filelog.txt', $fileLog->filename());
		$this->check('FileLog::pathname() returns full path', $this->fileLogPath . 'wiretests-filelog.txt', $fileLog->pathname());
		$this->check('FileLog::size() reports bytes', true, $fileLog->size() > 0);
		$this->check('FileLog::getTotalLines() counts lines', 1, $fileLog->getTotalLines());

		$fileLog->save('No duplicate', array('allowDups' => false));
		$fileLog->save('No duplicate', array('allowDups' => false));
		$this->check('FileLog::save(allowDups=false) suppresses same-request duplicates', 2, $fileLog->getTotalLines());

		$fileLog->save('Duplicate marker ^+999');
		$lines = $fileLog->find(1);
		$line = reset($lines);
		$this->check('FileLog sanitizes duplicate count marker in user text', 'Duplicate marker ^ +999', $line, '*=');

		$this->check('FileLog::delete() removes file', true, $fileLog->delete());
	}

	protected function testRepeatedLogLines() {
		$log = $this->wire()->log;
		$name = $this->name('repeated');
		$filename = $log->getFilename($name);
		$old = date('Y-m-d H:i:s', strtotime('-10 days')) . "\tuser\turl\tOld entry";
		$repeated = date('Y-m-d H:i:s', time() - 60) . "\tuser\turl\tRepeated entry";
		$other = date('Y-m-d H:i:s', time() - 60) . "\tuser\turl\tOther entry";
		$keep = array($repeated, $repeated, $other, $repeated);
		file_put_contents($filename, implode("\n", array_merge(array($old), $keep)) . "\n");
		$this->check('getLines() retains identical entries', $keep, array_values($log->getLines($name, array(
			'limit' => 0, 'reverse' => false, 'text' => 'entry', 'dateFrom' => strtotime('-1 day'),
		))));
		$this->check('prune() counts all in-range repeated entries', count($keep), $log->prune($name, 1));
		$this->check('prune() preserves repeated entries on disk', $keep, file($filename, FILE_IGNORE_NEW_LINES));

		// Small chunks exercise partial lines and exact boundaries without a large fixture.
		$fileLog = $this->wire(new class($this->fileLogPath, 'repeated-chunks') extends FileLog {
			public function setChunkSize($size) { $this->chunkSize = $size; }
		});
		$entries = array();
		for($n = 0; $n < 20; $n++) {
			$entries[] = $n % 3 ? $repeated : $other;
		}
		foreach(array(true, false) as $newline) {
			file_put_contents($fileLog->pathname(), implode("\n", $entries) . ($newline ? "\n" : ''));
			clearstatcache(true, $fileLog->pathname());
			foreach(array(32, 64, 127, strlen($repeated) + 1, filesize($fileLog->pathname()), FileLog::defaultChunkSize) as $chunkSize) {
				$fileLog->setChunkSize($chunkSize);
				$label = "chunk size $chunkSize, trailing newline " . (int) $newline;
				$this->check("getTotalLines() counts all repeated entries ($label)", count($entries), $fileLog->getTotalLines());
				$this->check("find() preserves forward entries ($label)", $entries, array_values($fileLog->find(0, 1, array('reverse' => false))));
				$this->check("find() preserves reverse entries ($label)", array_reverse($entries), array_values($fileLog->find(0)));
				$this->check("find() paginates repeated entries ($label)", array_slice(array_reverse($entries), 5, 5), array_values($fileLog->find(5, 2)));
			}
		}

		// Exercise the real chunk size too, including pruneDate() writing across several chunks.
		$fileLog->setChunkSize(FileLog::defaultChunkSize);
		$entries = array();
		for($n = 0; $n < 1000; $n++) {
			$entries[] = $n % 3 ? $repeated : $other;
		}
		file_put_contents($fileLog->pathname(), $old . "\n" . implode("\n", $entries) . "\n");
		clearstatcache(true, $fileLog->pathname());
		$this->check('find() preserves repeated entries across default chunks', array_reverse(array_merge(array($old), $entries)), array_values($fileLog->find(0)));
		$this->check('pruneDate() counts repeated entries across default chunks', count($entries), $fileLog->pruneDate(strtotime('-1 day')));
		$this->check('pruneDate() writes all repeated entries in order across default chunks', $entries, file($fileLog->pathname(), FILE_IGNORE_NEW_LINES));
	}
}
