<?php namespace ProcessWire;

/**
 * ProcessWire FileLog
 *
 * Creates and maintains a text-based log file.
 * 
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */

class FileLog extends Wire {

	/**
	 * Default size of chunks used for reading from logs
	 * 
	 */
	const defaultChunkSize = 12288;

	/**
	 * Debug mode used during development of this class
	 * 
	 */
	const debug = false;

	/**
	 * Chunk size used when reading from logs and not overridden
	 * 
	 * @var int
	 * 
	 */
	protected $chunkSize = self::defaultChunkSize;

	/**
	 * Full path to log file or false when not yet set
	 * 
	 * @var bool|string
	 * 
	 */
	protected $logFilename = false;

	/**
	 * Log items saved during this request where array keys are md5 hash of log entries and values ignored
	 * 
	 * @var array
	 * 
	 */
	protected $itemsLogged = array();

	/**
	 * Delimiter used in log entries
	 * 
	 * @var string
	 * 
	 */
	protected $delimeter = "\t";

	/**
	 * Maximum allowed line length for a single log line
	 * 
	 * @var int
	 * 
	 */
	protected $maxLineLength = 8192;

	/**
	 * File extension used for log files
	 * 
	 * @var string
	 * 
	 */
	protected $fileExtension = 'txt';

	/**
	 * Path where log files are stored
	 * 
	 * @var string
	 * 
	 */
	protected $path = '';
	
	/**
	 * Construct the FileLog
 	 *
	 * @param string $path Path where the log will be stored (path should have trailing slash)
	 * 	This may optionally include the filename if you intend to leave the second param blank.
	 * @param string $identifier Basename for the log file, not including the extension. 
	 * 
	 */
	public function __construct($path, $identifier = '') {
		parent::__construct();
		
		if($identifier) {
			$path = rtrim($path, '/') . '/';
			$this->logFilename = "$path$identifier.{$this->fileExtension}";
		} else {
			$this->logFilename = $path; 
			$path = dirname($path) . '/';
		}
		$this->path = $path; 
	}

	/**
	 * Wired to API
	 * 
	 */
	public function wired() {
		parent::wired();
		$this->path();
	}

	/**
	 * @param string $name
	 * @return mixed
	 * 
	 */
	public function __get($name) {
		if($name == 'delimiter') return $this->delimeter; // @todo learn how to spell
		return parent::__get($name);
	}

	/**
	 * Clean a string for use in a log file entry
	 * 
	 * @param $str
	 * @return string
	 * 
	 */
	protected function cleanStr($str) {
		$str = str_replace(array("\r\n", "\r", "\n"), ' ', trim($str)); 
		if(strlen($str) > $this->maxLineLength) $str = substr($str, 0, $this->maxLineLength); 
		if(strpos($str, ' ^+') !== false) $str = str_replace(' ^=', ' ^ +', $str); // disallowed sequence
		return $str; 	
	}

	/**
	 * Save the given log entry string
	 * 
	 * @param string $str
	 * @param array $options options to modify behavior (Added 3.0.143)
	 *  - `allowDups` (bool): Allow duplicating same log entry in same runtime/request? (default=true) 
	 *  - `mergeDups` (int): Merge previous duplicate entries that also appear near end of file?
	 *     To enable, specify int for quantity of bytes to consider from EOF, value of 1024 or higher (default=0, disabled) 
	 *  - `maxTries` (int): If log entry fails to save, maximum times to re-try (default=20) 
	 *  - `maxTriesDelay` (int): Micro seconds (millionths of a second) to delay between re-tries (default=2000)
	 * @return bool Success state: true if log written, false if not. 
	 * 
	 */
	public function save($str, array $options = array()) {
		
		$defaults = array(
			'mergeDups' => 0,
			'allowDups' => true, 
			'maxTries' => 20, 
			'maxTriesDelay' => 2000, 
		);

		if(!$this->logFilename) return false;
		
		$options = array_merge($defaults, $options);
		$hash = md5($str); 
		$ts = date("Y-m-d H:i:s"); 
		$str = $this->cleanStr($str);
		$line = $this->delimeter . $str; // log entry, excluding timestamp
		$hasLock = false; // becomes true when lock obtained
		$fp = false; // becomes resource when file is open
		
		// if we've already logged this during this instance, then don't do it again
		if(!$options['allowDups'] && isset($this->itemsLogged[$hash])) return true;

		// determine write mode		
		$mode = file_exists($this->logFilename) ? 'a' : 'w';
		if($mode === 'a' && $options['mergeDups']) $mode = 'r+';

		// open the log file
		for($tries = 0; $tries <= $options['maxTries']; $tries++) {
			$fp = fopen($this->logFilename, $mode);
			if($fp) break;
			// if unable to open for reading/writing, see if we can open for append instead
			if($mode === 'r+' && $tries > ($options['maxTries'] / 2)) $mode = 'a';
			usleep($options['maxTriesDelay']);
		}

		// if unable to open, exit now
		if(!$fp) return false;

		// obtain a lock
		for($tries = 0; $tries <= $options['maxTries']; $tries++) {
			$hasLock = flock($fp, LOCK_EX);
			if($hasLock) break;
			usleep($options['maxTriesDelay']);
		}
	
		// if unable to obtain a lock, we cannot write to the log
		if(!$hasLock) {
			fclose($fp);
			return false;
		}

		// if opened for reading and writing, merge duplicates of $line
		if($mode === 'r+' && $options['mergeDups']) {
			// do not repeat the same log entry in the same chunk
			$chunkSize = (int) $options['mergeDups']; 
			if($chunkSize < 1024) $chunkSize = 1024;
			fseek($fp, -1 * $chunkSize, SEEK_END);
			$chunk = fread($fp, $chunkSize); 
			// check if our log line already appears in the immediate earlier chunk
			if(strpos($chunk, $line) !== false) {
				// this log entry already appears 1+ times within the last chunk of the file
				// remove the duplicates and replace the chunk
				$chunkLength = strlen($chunk);
				$this->removeLineFromChunk($line, $chunk, $chunkSize);
				fseek($fp, 0, SEEK_END);
				$oldLength = ftell($fp);
				$newLength = $chunkLength > $oldLength ? $oldLength - $chunkLength : 0;
				ftruncate($fp, $newLength); 
				fseek($fp, 0, SEEK_END);
				fwrite($fp, $chunk);
			}	
		} else {
			// already at EOF because we are appending or creating
		}
		
		// add the log line
		$result = fwrite($fp, "$ts$line\n");
		
		// release the lock and close the file
		flock($fp, LOCK_UN);
		fclose($fp);
		
		if($result && !$options['allowDups']) $this->itemsLogged[$hash] = true;
		
		// if we were creating the file, make sure it has the right permission
		if($mode === 'w') {
			$files = $this->wire()->files;
			$files->chmod($this->logFilename);
		}
		
		return (int) $result > 0; 
	}

	/**
	 * Remove given $line from $chunk and add counter to end of $line indicating quantity that was removed
	 * 
	 * @param string $line
	 * @param string $chunk
	 * @param int $chunkSize
	 * @since 3.0.143
	 * 
	 */
	protected function removeLineFromChunk(&$line, &$chunk, $chunkSize) {
		
		$qty = 0;
		$chunkLines = explode("\n", $chunk);
		
		foreach($chunkLines as $key => $chunkLine) {

			$x = 1;
			if($key === 0 && strlen($chunk) >= $chunkSize) continue; // skip first line since it’s likely a partial line

			// check if line appears in this chunk line
			if(strpos($chunkLine, $line) === false) continue;
			
			// check if line also indicates a previous quantity that we should add to our quantity
			if(strpos($chunkLine, ' ^+') !== false) {
				list($chunkLine, $n) = explode(' ^+', $chunkLine, 2); 
				if(ctype_digit($n)) $x += (int) $n;
			}
			
			// verify that these are the same line
			if(strpos(trim($chunkLine) . "\n", trim($line) . "\n") === false) continue; 
			
			// remove the line
			unset($chunkLines[$key]);
		
			// update the quantity
			$qty += $x;
		}
		
		if($qty) {
			// append quantity to line, i.e. “^+2” indicating 2 more indentical lines were above
			$chunk = implode("\n", array_values($chunkLines));
			$line .= " ^+$qty";
		}
	}

	/**
	 * Get filesize
	 * 
	 * @return int|false
	 * 
	 */
	public function size() {
		return filesize($this->logFilename); 
	}

	/**
	 * Get file basename
	 * 
	 * @return string
	 * 
	 */
	public function filename() {
		return basename($this->logFilename);
	}

	/**
	 * Get file pathname
	 * 
	 * @return string|bool
	 * 
	 */
	public function pathname() {
		return $this->logFilename; 
	}

	/**
	 * Get file modification time
	 * 
	 * @return int|false
	 * 
	 */
	public function mtime() {
		return filemtime($this->logFilename); 
	}

	/**
	 * Get lines from the end of a file based on chunk size (deprecated)
	 * 
	 * @param int $chunkSize
	 * @param int $chunkNum
	 * @return array
	 * @deprecated Use find() instead
	 * 
	 */
	public function get($chunkSize = 0, $chunkNum = 1) {
		return $this->getChunkArray($chunkNum, $chunkSize); 
	}

	/**
	 * Get lines from the end of a file based on chunk size 
	 *
	 * @param int $chunkSize
	 * @param int $chunkNum
	 * @param bool $reverse 
	 * @return array
	 *
	 */
	protected function getChunkArray($chunkNum = 1, $chunkSize = 0, $reverse = true) {
		if($chunkSize < 1) $chunkSize = $this->chunkSize;
		$lines = explode("\n", $this->getChunk($chunkNum, $chunkSize, $reverse));
		foreach($lines as $key => $line) {
			$line = trim($line); 
			if(!strlen($line)) {
				unset($lines[$key]); 
			} else {
				$lines[$key] = $line;
			}
		}
		if($reverse) $lines = array_reverse($lines);
		return $lines; 
	}
	
	/**
	 * Get a chunk of data (string) from the end of the log file
	 * 
	 * Returned string is automatically adjusted at the beginning and 
	 * ending to contain only full log lines. 
	 *
	 * @param int $chunkNum Current chunk/pagination number (default=1, first)
	 * @param int $chunkSize Number of bytes to retrieve (default=0, which assigns default chunk size of 12288)
	 * @param bool $reverse True=pull from end of file, false=pull from beginning (default=true)
	 * @param bool $clean Get a clean chunk that starts at the beginning of a line? (default=true)
	 * @return string
	 * 
	 */
	protected function getChunk($chunkNum = 1, $chunkSize = 0, $reverse = true, $clean = true) {

		if($chunkSize < 1) $chunkSize = $this->chunkSize;
	
		if($reverse) {
			$offset = -1 * ($chunkSize * $chunkNum);
		} else {
			$offset = $chunkSize * ($chunkNum-1);
		}
		
		if(self::debug) {
			$this->message("chunkNum=$chunkNum, chunkSize=$chunkSize, offset=$offset, filesize=" . filesize($this->logFilename));
		}
		
		$data = '';
		$totalChunks = $this->getTotalChunks($chunkSize); 
		if($chunkNum > $totalChunks) return $data; 

		if(!$fp = fopen($this->logFilename, "r")) return $data;
		
		fseek($fp, $offset, ($reverse ? SEEK_END : SEEK_SET));

		if($clean) {
			// make chunk include up to beginning of first line
			fseek($fp, -1, SEEK_CUR);
			while(ftell($fp) > 0) {
				$chr = fread($fp, 1);
				if($chr == "\n") break;
				fseek($fp, -2, SEEK_CUR);
				$data = $chr . $data;
			}
			fseek($fp, $offset, ($reverse ? SEEK_END : SEEK_SET));
		}
		
		// get the big part of the chunk
		$data .= fread($fp, $chunkSize);

		if($clean) {
			// remove last partial line
			$pos = strrpos($data, "\n");
			if($pos) $data = substr($data, 0, $pos);
		}

		fclose($fp); 
		
		return $data;
	}

	/**
	 * Get the total number of chunks in the file
	 * 
	 * @param int $chunkSize
	 * @return int
	 * 
	 */
	protected function getTotalChunks($chunkSize = 0) {
		if($chunkSize < 1) $chunkSize = $this->chunkSize;
		$filesize = filesize($this->logFilename); 
		return $filesize > 0 ? ceil($filesize / $chunkSize) : 0;
	}

	/**
	 * Get total number of lines in the log file
	 * 
	 * @return int
	 * 
	 */
	public function getTotalLines() {

		if(!is_readable($this->logFilename)) return 0;
		
		if(filesize($this->logFilename) < $this->chunkSize) {
			$data = file($this->logFilename); 
			return count($data); 
		}
		
		if(!$fp = fopen($this->logFilename, "r")) return 0;
		$totalLines = 0;

		while(!feof($fp)) { 
			$data = fread($fp, $this->chunkSize);
			$totalLines += substr_count($data, "\n"); 
		}
		
		fclose($fp); 
		
		return $totalLines;
	}

	/**
	 * Get log lines that lie within a date range
	 * 
	 * @param int $dateFrom Starting date (unix timestamp or strtotime compatible string)
	 * @param int $dateTo Ending date (unix timestamp or strtotime compatible string)
	 * @param int $pageNum Current pagination number (default=1)
	 * @param int $limit Items per pagination (default=100), or specify 0 for no limit. 
	 * @return array
	 * 
	 */
	public function getDate($dateFrom, $dateTo = 0, $pageNum = 1, $limit = 100) {
		$options = array(
			'dateFrom' => $dateFrom,
			'dateTo' => $dateTo, 
		);
		return $this->find($limit, $pageNum, $options); 
	}

	/**
	 * Return lines from the end of the log file, with various options
	 *
	 * @param int $limit Number of items to return (per pagination), or 0 for no limit.
	 * @param int $pageNum Current pagination (default=1)
	 * @param array $options
	 * 	- text (string): Return only lines containing the given string of text
	 * 	- reverse (bool): True=find from end of file, false=find from beginning (default=true)
	 * 	- toFile (string): Send results to the given basename (default=none)
	 * 	- dateFrom (unix timestamp): Return only lines newer than the given date (default=oldest)
	 * 	- dateTo (unix timestamp): Return only lines older than the given date  (default=now)
	 * 		Note: dateFrom and dateTo may be combined to return a range.
	 * @return int|array of strings (associative), each indexed by string containing slash separated 
	 * 	numeric values of: "current/total/start/end/total" which is useful with pagination.
	 * 	If the 'toFile' option is used, then return value is instead an integer qty of lines written.
	 * @throws \Exception on fatal error
	 * 
	 */
	public function find($limit = 100, $pageNum = 1, array $options = array()) {
		
		$defaults = array(
			'text' => null, 
			'dateFrom' => 0,
			'dateTo' => 0,
			'reverse' => true, 
			'toFile' => '', 
		);
		
		$options = array_merge($defaults, $options); 
		$hasFilters = !empty($options['text']);

		if($options['dateFrom'] || $options['dateTo']) {
			if(!$options['dateTo']) $options['dateTo'] = time();
			if(!ctype_digit("$options[dateFrom]")) $options['dateFrom'] = strtotime($options['dateFrom']);
			if(!ctype_digit("$options[dateTo]")) $options['dateTo'] = strtotime($options['dateTo']);
			$hasFilters = true; 
		}
		
		if($options['toFile']) {
			$toFile = $this->path() . basename($options['toFile']); 
			$fp = fopen($toFile, 'w'); 
			if(!$fp) throw new \Exception("Unable to open file for writing: $toFile"); 
		} else {
			$toFile = '';
			$fp = null;
		}
		
		$lines = array();
		$start = ($pageNum-1) * $limit; 
		$end = $start + $limit; 
		$cnt = 0; // number that will be written or returned by this
		$n = 0; // number total
		$chunkNum = 0;
		$totalChunks = $this->getTotalChunks($this->chunkSize); 
		$stopNow = false;
		$chunkLineHashes = array();
		
		while($chunkNum <= $totalChunks && !$stopNow) {
			
			$chunk = $this->getChunkArray(++$chunkNum, 0, $options['reverse']);
			if(empty($chunk)) break;
			
			foreach($chunk as $line) {

				$line = trim($line); 
				$hash = md5($line); 
				$valid = !isset($chunkLineHashes[$hash]);
				$chunkLineHashes[$hash] = 1; 
				if($valid) $valid = $this->isValidLine($line, $options, $stopNow);
				if(!$hasFilters && $limit && count($lines) >= $limit) $stopNow = true;
				if($stopNow) break;
				if(!$valid) continue; 
				
				$n++;
				if($limit && ($n <= $start || $n > $end)) continue; 
				$cnt++;
				if($fp) {
					fwrite($fp, $line . "\n");
				} else {
					if(self::debug) $line .= " (line $n, chunk $chunkNum, hash=$hash)";
					$lines[$n] = $line;
				}
			}
		}
		
		$total = $hasFilters ? $n : $this->getTotalLines();
		$end = $start + count($lines); 
		if($end > $total) $end = $total;
		if(count($lines) < $limit && $total > $end) $total = $end; 
		
		if($fp) {
			fclose($fp);
			$this->wire()->files->chmod($toFile); 
			return $cnt;
		}
			
		foreach($lines as $key => $line) {
			unset($lines[$key]);
			$lines["$key/$total/$start/$end/$limit"] = $line;
		}
		return $lines; 
	}

	/**
	 * Returns whether the given log line is valid to be considered a log entry
	 * 
	 * @param $line
	 * @param array $options
	 * @param bool $stopNow Populates this with true when it can determine no more lines are necessary.
	 * @return bool Returns boolean true if valid, false if not.
	 * 
	 */
	protected function isValidLine($line, array $options, &$stopNow) {
		//              4  7  10 
		// $test = '2013-10-22 15:18:43';
		if(strlen($line) < 20) return false; 
		if($line[19] != $this->delimeter) return false; 
		if($line[4] != "-") return false;	
		if($line[7] != "-") return false;
		if($line[10] != " ") return false; 
		
		if(!empty($options['text'])) {
			if(stripos($line, $options['text']) === false) return false;
		}
		
		if(!empty($options['dateFrom']) && !empty($options['dateTo'])) {
			$parts = explode($this->delimeter, $line);
			$date = strtotime($parts[0]);
			if($date >= $options['dateFrom'] && $date <= $options['dateTo']) return true;
			if($date < $options['dateFrom'] && $options['reverse']) $stopNow = true; 
			if($date > $options['dateTo'] && !$options['reverse']) $stopNow = true; 
			return false;
		}
		
		return true; 	
	}
	
	/**
	 * Prune to number of bytes
	 * 
	 * @param $bytes
	 * @return bool|int
	 * @deprecated use pruneBytes() or pruneLines() instead
	 * 
	 */
	public function prune($bytes) {
		return $this->pruneBytes($bytes); 
	}

	/**
	 * Prune log file to specified number of bytes (from the end)
	 * 
	 * @param int $bytes
	 * @return int|bool positive integer on success, 0 if no prune necessary, or boolean false on failure.
	 * 
	 */
	public function pruneBytes($bytes) {

		$filename = $this->logFilename; 

		if(!$filename || !file_exists($filename) || filesize($filename) <= $bytes) return 0; 

		$fpr = fopen($filename, "r"); 	
		$fpw = fopen("$filename.new", "w"); 
		if(!$fpr || !$fpw) return false;

		fseek($fpr, ($bytes * -1), SEEK_END); 
		fgets($fpr, $this->maxLineLength); // first line likely just a partial line, so skip it
		$cnt = 0;

		while(!feof($fpr)) {
			$line = fgets($fpr, $this->maxLineLength); 
			fwrite($fpw, $line); 
			$cnt++;
		}

		fclose($fpw);
		fclose($fpr); 
		
		$files = $this->wire()->files;

		if($cnt) {
			$files->unlink($filename, true);
			$files->rename("$filename.new", $filename, true);
			$files->chmod($filename); 
		} else {
			$files->unlink("$filename.new", true);
		}
	
		return $cnt;	
	}

	/**
	 * Prune log file to contain only entries newer than $oldestDate
	 * 
	 * @param int|string $oldestDate
	 * @return int Number of lines written
	 * 
	 */
	public function pruneDate($oldestDate) {
		$toFile = $this->logFilename . '.new';
		$qty = $this->find(0, 1, array(
			'reverse' => false, 
			'toFile' => $toFile,
			'dateFrom' => $oldestDate, 
			'dateTo' => time(),
		));
		if(file_exists($toFile)) {
			$files = $this->wire()->files;
			$files->unlink($this->logFilename, true);
			$files->rename($toFile, $this->logFilename, true);
			return $qty; 
		}
		return 0;
	}

	/**
	 * Delete the log file
	 * 
	 * @return bool
	 * 
	 */
	public function delete() {
		return $this->wire()->files->unlink($this->logFilename, true);
	}

	public function __toString() {
		return $this->filename(); 
	}	

	public function setDelimiter($c) {
		$this->delimeter = $c; 
	}

	public function setDelimeter($c) {
		$this->setDelimiter($c); 
	}

	public function setMaxLineLength($c) {
		$this->maxLineLength = (int) $c; 
	}
	
	public function getMaxLineLength() {
		return $this->maxLineLength;
	}

	public function setFileExtension($ext) {
		$this->fileExtension = $ext; 
	}

	/**
	 * Get or set the default chunk size used when reading from logs and not overridden by method argument
	 * 
	 * @param int $chunkSize Specify chunk size to set, or omit to get
	 * @return int
	 * @since 3.0.143
	 * 
	 */
	public function chunkSize($chunkSize = 0) {
		if($chunkSize > 0) $this->chunkSize = (int) $chunkSize;
		return $this->chunkSize;
	}

	/**
	 * Get path where the log is stored (with trailing slash)
	 * @return string
	 * 
	 */
	public function path() {
		if(!is_dir($this->path)) $this->wire()->files->mkdir($this->path);
		return $this->path;
	}
}


