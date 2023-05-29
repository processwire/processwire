<?php namespace ProcessWire;

/**
 * ProcessWire Log
 *
 * WireLog represents the ProcessWire $log API variable.
 * It is an API-friendly interface to the FileLog class.
 * 
 * #pw-summary Enables creation of logs, logging of events, and management of logs. 
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 * @method bool save($name, $text, $options = array())
 * 
 * @todo option to disable logs by name
 *
 */

class WireLog extends Wire {

	protected $logExtension = 'txt';

	/**
	 * FileLog instances indexed by filename
	 * 
	 * @var array
	 * 
	 */
	protected $fileLogs = array();

	/**
	 * Names of logs that have been temporary silenced for this request
	 * 
	 * @var array Keys are log names, values are irrelevant
	 * 
	 */
	protected $disabled = array();

	/**
	 * Record an informational or 'success' message in the message log (messages.txt)
	 * 
	 * ~~~~~
	 * // Log message to messages.txt log
	 * $log->message("User updated profile"); 
	 * ~~~~~
	 * 
	 * @param string $text Message to log
	 * @param bool|int $flags Specify boolean true to also have the message displayed interactively (admin only).
	 * @return Wire|WireLog
	 *
	 */
	public function message($text, $flags = 0) {
		$flags = $flags === true ? Notice::log : $flags | Notice::logOnly;
		return parent::message($text, $flags);
	}

	/**
	 * Record an error message in the error log (errors.txt)
	 *
	 * Note: Fatal errors should instead always throw a WireException.
	 * 
	 * ~~~~~
	 * // Log an error message to errors.txt log
	 * $log->error("Login attempt failed"); 
	 * ~~~~~
	 * 
	 * @param string $text Text to save in the log
	 * @param int|bool $flags Specify boolean true to also display the error interactively (admin only).
	 * @return Wire|WireLog
	 *
	 */
	public function error($text, $flags = 0) {
		$flags = $flags === true ? Notice::log : $flags | Notice::logOnly;
		return parent::error($text, $flags);
	}

	/**
	 * Record a warning message in the warnings log (warnings.txt)
	 * 
	 * ~~~~~
	 * // Log an warning message to warnings.txt log
	 * $log->warning("This is a warning");
	 * ~~~~~
	 * 
	 * @param string $text Text to save in the log
	 * @param int|bool $flags Specify boolean true to also display the warning interactively (admin only).
	 * @return Wire|WireLog
	 *
	 */
	public function warning($text, $flags = 0) {
		$flags = $flags === true ? Notice::log : $flags | Notice::logOnly;
		return parent::warning($text, $flags);
	}
	
	/**
	 * Save text to a named log
	 * 
	 * - If the log doesn't currently exist, it will be created. 
	 * - The log filename is `/site/assets/logs/[name].txt`
	 * - Logs can be viewed in the admin at Setup > Logs
	 * 
	 * ~~~~~
	 * // Save text searches to custom log file (search.txt):
	 * $log->save("search", "User searched for: $phrase");
	 * ~~~~~
	 * 
	 * @param string $name Name of log to save to (word consisting of only `[-._a-z0-9]` and no extension)
	 * @param string $text Text to save to the log
	 * @param array $options Options to modify default behavior:
	 *   - `showUser` (bool): Include the username in the log entry? (default=true)
	 *   - `showURL` (bool): Include the current URL in the log entry? (default=true) 
	 *   - `user` (User|string|null): User instance, user name, or null to use current User. (default=null)
	 *   - `url` (bool): URL to record with the log entry (default=auto determine)
	 *   - `delimiter` (string): Log entry delimiter (default="\t" aka tab)
	 * @return bool Whether it was written or not (generally always going to be true)
	 * @throws WireException
	 * 
	 */
	public function ___save($name, $text, $options = array()) {
		
		if(isset($this->disabled[$name]) || isset($this->disabled['*'])) return false;
		
		$defaults = array(
			'showUser' => true,
			'showURL' => true,
			'user' => null, 
			'url' => '', // URL to show (default=blank, auto-detect)
			'delimiter' => "\t",
		);
		
		$options = array_merge($defaults, $options);
		// showURL option was previously named showPage
		if(isset($options['showPage'])) $options['showURL'] = $options['showPage'];
		$log = $this->getFileLog($name, $options); 
		$text = str_replace(array("\r", "\n", "\t"), ' ', $text);
		
		if($options['showURL']) {
			if($options['url']) {
				$url = $options['url'];
			} else {
				$input = $this->wire()->input;
				$sanitizer = $this->wire()->sanitizer;
				$url = $input ? $input->httpUrl() : '';
				if(strlen($url) && $input) {
					if(count($input->get)) {
						$url .= "?";
						foreach($input->get as $k => $v) {
							$k = $sanitizer->name($k);
							$v = $sanitizer->name($v);
							$url .= "$k=$v&";
						}
						$url = rtrim($url, "&");
					}
					if(strlen($url) > 500) $url = substr($url, 0, 500) . " ...";
				} else {
					$url = '?';
				}
			}
			$text = "$url$options[delimiter]$text";
		}
		
		if($options['showUser']) {
			$user = !empty($options['user']) ? $options['user'] : $this->wire('user');
			$userName = '';
			if($user instanceof Page) {
				$userName = $user->id ? $user->name : '?';
			} else if(is_string($user)) {
				$userName = $user;
			}
			if(empty($userName)) $userName = '?';
			$text = "$userName$options[delimiter]$text";
		}
		
		return $log->save($text);
	}

	/**
	 * Log a deprecated method call to deprecated.txt log
	 * 
	 * This should be called directly from a deprecated method or function. 
	 * 
	 * #pw-internal
	 * 
	 */
	public function deprecatedCall() {
		if(!in_array('deprecated', $this->wire('config')->logs)) return;
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		array_shift($backtrace);
		$a = array_shift($backtrace);
		$b = array_shift($backtrace);
		$info = "Deprecated call: $a[class].$a[function]() ";
		if(!empty($b['class'])) {
			$info .= "from class $b[class].$b[function]() " . (isset($b['line']) ? "line $b[line]" : "");
		} else if(strpos($b['file'], 'TemplateFile.php') === false) {
			$info .= "from file $b[file] line $b[line]";
		}
		$this->save('deprecated', $info);
		$this->warning($info);
	}

	/**
	 * Return array of all logs, sorted by name
	 * 
	 * Each item in returned array is an associative array that includes the following:
	 * 
	 * 	- `name` (string): Name of log file, excluding extension.
	 * 	- `file` (string): Full path and filename of log file. 
	 * 	- `size` (int): Size in bytes
	 * 	- `modified` (int): Last modified date (unix timestamp)
	 * 
	 * #pw-group-retrieval
	 * 
	 * @param bool $sortNewest Sort by newest to oldest rather than by name? (default=false) Added 3.0.143
	 * @return array Indexed by log name
	 * 
	 */
	public function getLogs($sortNewest = false) {
		
		$logs = array();
		
		foreach($this->getFiles() as $name => $file) {
			
			if($sortNewest) {
				$sortKey = $file->getMTime();
				while(isset($logs[$sortKey])) $sortKey++;
			} else {
				$sortKey = $name;
			}
			
			$logs[$sortKey] = array(
				'name' => $name,
				'file' => $file->getPathname(),
				'size' => $file->getSize(), 
				'modified' => $file->getMTime(), 
			);
		}
	
		if($sortNewest) {
			krsort($logs);
			$a = array();
			foreach($logs as $log) {
				$a[$log['name']] = $log;
			}
			$logs = $a;
		} else {
			ksort($logs);
		}
	
		return $logs;	
	}

	/**
	 * Get the full filename (including path) for the given log name
	 * 
	 * #pw-group-retrieval
	 * 
	 * @param string $name Name of log (not including extension)
	 * @return string Filename to log file
	 * @throws WireException If given invalid log name
	 * 
	 */
	public function getFilename($name) {
		$name = strtolower($name); 
		if($name !== $this->wire()->sanitizer->pageName($name)) {
			throw new WireException("Log name must contain only [-_.a-z0-9] with no extension");
		}
		return $this->path() . $name . '.' . $this->logExtension;
	}

	/**
	 * Get SplFileInfo objects for each log file indexed by log name
	 * 
	 * #pw-internal
	 * 
	 * @return \SplFileInfo[]
	 * @throws WireException
	 * @since 3.0.214
	 * 
	 */
	public function getFiles() {
		
		$dir = new \DirectoryIterator($this->path());
		$sanitizer = $this->wire()->sanitizer;
		$files = array();

		foreach($dir as $file) {
			if($file->isDot() || $file->isDir()) continue;
			if($file->getExtension() != $this->logExtension) continue;
			$name = $file->getBasename(".$this->logExtension");
			if($name != $sanitizer->pageName($name)) continue;
			$files[$name] = clone $file;
		}
		
		return $files;
	}

	/**
	 * Does given log name exist?
	 * 
	 * @param string $name
	 * @return bool
	 * @since 3.0.176
	 * 
	 */
	public function exists($name) {
		$filename = $this->getFilename($name);
		return is_file($filename);
	}

	/**
	 * Return the given number of entries from the end of log file
	 * 
	 * This method is pagination aware.
	 * 
	 * #pw-group-retrieval
	 * 
	 * @param string $name Name of log 
	 * @param array $options Specify any of the following: 
	 * 	- `limit` (integer): Specify number of lines (default=100)
	 * 	- `text` (string): Text to find.
	 * 	- `dateFrom` (int|string): Oldest date to match entries.
	 * 	- `dateTo` (int|string): Newest date to match entries.
	 * 	- `reverse` (bool): Reverse order (default=true)
	 * 	- `pageNum` (int): Pagination number 1 or above (default=0 which means auto-detect)
	 * @return array 
	 * @see WireLog::getEntries()
	 * 
	 */
	public function getLines($name, array $options = array()) {
		$pageNum = !empty($options['pageNum']) ? $options['pageNum'] : $this->wire()->input->pageNum;
		unset($options['pageNum']); 
		$log = $this->getFileLog($name); 
		$limit = isset($options['limit']) ? (int) $options['limit'] : 100; 
		return $log->find($limit, $pageNum, $options); 
	}

	/**
	 * Return given number of entries from end of log file, with each entry as an associative array of components
	 * 
	 * This is effectively the same as the `getLines()` method except that each entry is an associative 
	 * array rather than a single line (string). This method is pagination aware.
	 * 
	 * #pw-group-retrieval
	 * 
	 * @param string $name Name of log file (excluding extension)
	 * @param array $options Optional options to modify default behavior: 
	 * 	- `limit` (integer): Specify number of lines (default=100)
	 * 	- `text` (string): Text to find. 
	 * 	- `dateFrom` (int|string): Oldest date to match entries. 
	 * 	- `dateTo` (int|string): Newest date to match entries. 
	 * 	- `reverse` (bool): Reverse order (default=true)
	 * 	- `pageNum` (int): Pagination number 1 or above (default=0 which means auto-detect)
	 * @return array Returns an array of associative arrays, each with the following components:
	 *  - `date` (string): ISO-8601 date string
	 *  - `user` (string): user name or boolean false if unknown
	 *  - `url` (string): full URL or boolean false if unknown
	 *  - `text` (string): text of the log entry
	 * @see WireLog::getLines()
	 * 
	 */
	public function getEntries($name, array $options = array()) {
		
		$log = $this->getFileLog($name);
		$limit = isset($options['limit']) ? $options['limit'] : 100; 
		$pageNum = !empty($options['pageNum']) ? $options['pageNum'] : $this->wire()->input->pageNum; 
		unset($options['pageNum']); 
		$lines = $log->find($limit, $pageNum, $options); 
		
		foreach($lines as $key => $line) {
			$entry = $this->lineToEntry($line); 
			$lines[$key] = $entry; 
		}
		
		return $lines; 
	}

	/**
	 * Convert a log line to an entry array
	 * 
	 * #pw-internal
	 * 
	 * @param $line
	 * @return array
	 * 
	 */
	public function lineToEntry($line) {
		
		$parts = explode("\t", $line, 4);
	
		if(count($parts) == 2) {
			$entry = array(
				'date' => $parts[0],
				'user' => '',
				'url'  => '',
				'text' => $parts[1]
			);
		} else if(count($parts) == 3) {
			$user = strpos($parts[1], '/') === false ? $parts[1] : '';
			$url = strpos($parts[2], '://') ? $parts[2] : '';
			$text = empty($url) ? $parts[2] : '';
			$entry = array(
				'date' => $parts[0],
				'user' => $user,
				'url'  => $url,
				'text' => $text
			);
		} else {
			$entry = array(
				'date' => isset($parts[0]) ? $parts[0] : '',
				'user' => isset($parts[1]) ? $parts[1] : '',
				'url'  => isset($parts[2]) ? $parts[2] : '',
				'text' => isset($parts[3]) ? $parts[3] : '',
			);
		}
		
		$entry['date'] = wireDate($this->wire('config')->dateFormat, strtotime($entry['date']));
		$entry['user'] = $this->wire('sanitizer')->pageNameUTF8($entry['user']); 
		
		if($entry['url'] == 'page?') $entry['url'] = false;
		if($entry['user'] == 'user?') $entry['user'] = false;
		
		return $entry; 
	}

	/**
	 * Get the total number of entries present in the given log
	 * 
	 * #pw-group-retrieval
	 * 
	 * @param string $name Name of log, not including path or extension
	 * @return int Total number of entries
	 * 
	 */
	public function getTotalEntries($name) {
		$log = $this->getFileLog($name); 
		return $log->getTotalLines(); 
	}
	
	/**
	 * Get lines from log file (deprecated)
	 * 
	 * #pw-internal
	 *
	 * @param $name
	 * @param int|array $limit Limit, or specify $options array instead ('limit' can be in options array). 
	 * @param array $options Array of options to affect behavior, may also be specified as 2nd argument. 
	 * @deprecated Use getLines() or getEntries() intead.
	 * @return array
	 *
	 */
	public function get($name, $limit = 100, array $options = array()) {
		if(is_array($limit)) {
			$options = $limit;
		} else {
			$options['limit'] = $limit;
		}
		return $this->getLines($name, $options);
	}

	/**
	 * Return an array of log lines that exist in the given range of dates
	 * 
	 * Pagination aware. 
	 * 
	 * #pw-internal
	 * 
	 * @param string $name Name of log 
	 * @param int|string $dateFrom Unix timestamp or string date/time to start from 
	 * @param int|string $dateTo Unix timestamp or string date/time to end at (default = now)
	 * @param int $limit Max items per pagination
	 * @return array
	 * @deprecated Use getLines() or getEntries() with dateFrom/dateTo $options instead. 
	 * 
	 */
	public function getDate($name, $dateFrom, $dateTo = 0, $limit = 100) {
		$log = $this->getFileLog($name); 
		$pageNum = $this->wire()->input->pageNum();
		return $log->getDate($dateFrom, $dateTo, $pageNum, $limit); 
	}
	
	/**
	 * Delete a log file
	 * 
	 * #pw-group-manipulation
	 *
	 * @param string $name Name of log, excluding path and extension.
	 * @return bool True on success, false on failure.
	 *
	 */
	public function delete($name) {
		if(!$this->exists($name)) return false;
		$log = $this->getFileLog($name);
		if($log) return $log->delete();
		return false;
	}

	/**
	 * Delete all log files
	 * 
	 * @param bool $throw Throw WireException if any delete fails? (default=false)
	 * @return array Basenames of deleted log files
	 * @since 3.0.214
	 * 
	 */
	public function deleteAll($throw = false) {
		
		$deleted = array();
		$failed = array();

		foreach($this->getFiles() as $name => $file) {
			$log = $this->getFileLog($name);
			if($log && $log->delete()) {
				$deleted[] = $name;
			} else {
				$failed[] = $name;
			}
		}
		
		if($throw && count($failed)) {
			throw new WireException("Failed to delete logs: " . implode(', ', $failed));
		}
		
		return $deleted;
	}

	/**
	 * Prune log file to contain only entries from last [n] days
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param string $name Name of log file, excluding path and extension.
	 * @param int $days Number of days
	 * @return int Number of items in newly pruned log file or boolean false on failure
	 * @throws WireException
	 * 
	 */
	public function prune($name, $days) {
		if(!$this->exists($name)) return false;
		$log = $this->getFileLog($name);
		if($days < 1) throw new WireException("Prune days must be 1 or more"); 
		$oldestDate = strtotime("-$days DAYS"); 
		return $log->pruneDate($oldestDate); 
	}

	/**
	 * Prune all log files to given number of days
	 * 
	 * @param int $days
	 * @return array
	 * @since 3.0.214
	 * 
	 */
	public function pruneAll($days) {
		$result = array();
		foreach($this->getFiles() as $name => $filename) {
			$result[$name] = $this->prune($name, $days);
		}
		return $result;
	}
	
	/**
	 * Returns instance of FileLog for given log name
	 * 
	 * #pw-internal
	 * 
	 * @param $name
	 * @param array $options
	 * @return FileLog
	 * 
	 */
	public function getFileLog($name, array $options = array()) {
		$delimiter = isset($options['delimiter']) ? $options['delimiter'] : "\t";
		$filename = $this->getFilename($name);
		$key = "$filename$delimiter";
		if(isset($this->fileLogs[$key])) return $this->fileLogs[$key];
		/** @var FileLog $log */
		$log = $this->wire(new FileLog($filename));
		$log->setDelimiter($delimiter);
		$this->fileLogs[$key] = $log;
		return $log;
	}

	/**
	 * Disable the given log name temporarily so that save() calls do not record entries during this request
	 * 
	 * @param string $name Log name or specify '*' to disable all
	 * @return self
	 * @since 3.0.148
	 * @see WireLog::enable()
	 * 
	 */
	public function disable($name) {
		if(!empty($name)) $this->disabled[$name] = true;
		return $this;
	}

	/**
	 * Enable a previously disabled log
	 *
	 * @param string $name Log name or specify '*' to reverse a previous disable('*') call. 
	 * @return self
	 * @since 3.0.148
	 * @see WireLog::disable()
	 *
	 */
	public function enable($name) {
		unset($this->disabled[$name]); 
		return $this;
	}

	/**
	 * Return disk path to log files
	 * 
	 * @return string
	 * @since 3.0.214
	 * 
	 */
	public function path() {
		return $this->wire()->config->paths->logs;
	}
}
