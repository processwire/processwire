<?php namespace ProcessWire;

/**
 * Tests for ProcessWire LazyCron module.
 *
 */
class WireTest_LazyCron extends WireTest {

	public function init() {
		// nothing to set up
	}

	public function execute() {
		$this->testCacheKeepsEveryInterval();
	}

	/**
	 * A due interval without hooks keeps its line in LazyCron.cache, which is read by position,
	 * so that the file does not shrink and hooked intervals do not run on every request
	 * (processwire/processwire-issues#2367)
	 *
	 */
	protected function testCacheKeepsEveryInterval() {
		$config = $this->wire()->config;
		$file = $config->paths->cache . 'LazyCron.cache';
		if(is_file($config->paths->cache . 'LazyCronLock.cache')) {
			$this->li('Skipped: a LazyCron run is in progress (lock file exists)');
			return;
		}
		$cron = $this->wire()->modules->get('LazyCron'); /** @var LazyCron $cron */
		$numIntervals = count($cron->getTimeFuncs());
		$backup = is_file($file) ? file_get_contents($file) : null;
		$runs = 0;
		$hookId = $this->wire()->addHook('LazyCron::every2Days', function() use(&$runs) { $runs++; });
		$lines = function() use($file) { return count(file($file, FILE_IGNORE_NEW_LINES)); };
		try {
			// every interval last ran 3 days ago, so all are due, but only every2Days has a hook
			file_put_contents($file, implode("\n", array_fill(0, $numIntervals, time() - (3 * 86400))));
			$cron->execute();
			$this->check('LazyCron.cache keeps a line for every interval', $numIntervals, $lines());
			$cron->execute();
			$cron->execute();
			$this->check('Hooked interval runs once when due, not on every run', 1, $runs);
		} finally {
			$this->wire()->removeHook($hookId);
			if($backup === null) {
				if(is_file($file)) unlink($file);
			} else {
				file_put_contents($file, $backup);
			}
		}
	}
}
