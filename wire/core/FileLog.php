<?php namespace ProcessWire;

/**
 * ProcessWire FileLog
 *
 * Creates and maintains a text-based log file.
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class FileLog extends Wire {
	
	const defaultChunkSize = 12288; 
	const debug = false; 

	protected $logFilename = false; 
	protected $itemsLogged = array(); 
	protected $delimeter = "\t";
	protected $maxLineLength = 8192;
	protected $fileExtension = 'txt';
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
		
		if($identifier) {
			$path = rtrim($path, '/') . '/';
			$this->logFilename = "$path$identifier.{$this->fileExtension}";
		} else {
			$this->logFilename = $path; 
			$path = dirname($path) . '/';
		}
		$this->path = $path; 
		if(!is_dir($path)) $this->wire('files')->mkdir($path);
	}
	
	public function __get($key) {
		if($key == 'delimiter') return $this->delimeter; // @todo learn how to spell
		return parent::__get($key);
	}

	/**
	 * Clean a string for use in a log file entry
	 * 
	 * @param $str
	 * @return mixed|string
	 * 
	 */
	protected function cleanStr($str) {
		$str = str_replace(array("\r\n", "\r", "\n"), ' ', trim($str)); 
		if(strlen($str) > $this->maxLineLength) $str = substr($str, 0, $this->maxLineLength); 
		return $str; 	
	}

	/**
	 * Save the given log entry string
	 * 
	 * @param $str
	 * @return bool Success state
	 * 
	 */
	public function save($str) {

		if(!$this->logFilename) return false; 

		$hash = md5($str); 

		// if we've already logged this during this instance, then don't do it again
		if(in_array($hash, $this->itemsLogged)) return true; 

		$ts = date("Y-m-d H:i:s"); 
		$str = $this->cleanStr($str);
		$fp = fopen($this->logFilename, "a");
		
		if($fp) {
			$trys = 0; 
			$stop = false;

			while(!$stop) {
				if(flock($fp, LOCK_EX)) {
					fwrite($fp, "$ts{$this->delimeter}$str\n"); 
					flock($fp, LOCK_UN); 
					$this->itemsLogged[] = $hash; 
					$stop = true; 
				} else {
					usleep(2000);
					if($trys++ > 20) $stop = true; 
				}
			}

			fclose($fp); 
			$this->wire('files')->chmod($this->logFilename);
			return true; 
		} else {
			return false;
		}

	}

	public function size() {
		return filesize($this->logFilename); 
	}

	public function filename() {
		return basename($this->logFilename);
	}

	public function pathname() {
		return $this->logFilename; 
	}

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
		if($chunkSize < 1) $chunkSize = self::defaultChunkSize;
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
	 * @param int $chunkNum Current pagination number (default=1)
	 * @param int $chunkSize Number of bytes to retrieve (default=12288)
	 * @param bool $reverse True=pull from end of file, false=pull from beginning (default=true)
	 * @return string
	 * 
	 */
	protected function getChunk($chunkNum = 1, $chunkSize = 0, $reverse = true) {

		if($chunkSize < 1) $chunkSize = self::defaultChunkSize;
	
		if($reverse) {
			$offset = -1 * ($chunkSize * $chunkNum);
		} else {
			$offset = $chunkSize * ($chunkNum-1);
		}
		
		if(self::debug) $this->message("chunkNum=$chunkNum, chunkSize=$chunkSize, offset=$offset, filesize=" . filesize($this->logFilename)); 
		
		$data = '';
		$totalChunks = $this->getTotalChunks($chunkSize); 
		if($chunkNum > $totalChunks) return $data; 

		if(!$fp = fopen($this->logFilename, "r")) return $data;
		
		fseek($fp, $offset, ($reverse ? SEEK_END : SEEK_SET));
	
		// make chunk include up to beginning of first line
		fseek($fp, -1, SEEK_CUR);
		while(ftell($fp) > 0) {
			$chr = fread($fp, 1);
			if($chr == "\n") break;
			fseek($fp, -2, SEEK_CUR);
			$data = $chr . $data;
		}
		
		// get the big part of the chunk
		fseek($fp, $offset, ($reverse ? SEEK_END : SEEK_SET));
		$data .= fread($fp, $chunkSize);
	
		// remove last partial line
		$pos = strrpos($data, "\n"); 
		if($pos) $data = substr($data, 0, $pos); 

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
		if($chunkSize < 1) $chunkSize = self::defaultChunkSize;
		$filesize = filesize($this->logFilename); 
		return ceil($filesize / $chunkSize);
	}

	/**
	 * Get total number of lines in the log file
	 * 
	 * @return int
	 * 
	 */
	public function getTotalLines() {
		
		if(filesize($this->logFilename) < self::defaultChunkSize) {
			$data = file($this->logFilename); 
			return count($data); 
		}
		
		if(!$fp = fopen($this->logFilename, "r")) return 0;
		$totalLines = 0;

		while(!feof($fp)) { 
			$data = fread($fp, self::defaultChunkSize);
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
			$toFile = $this->path . basename($options['toFile']); 
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
		$totalChunks = $this->getTotalChunks(self::defaultChunkSize); 
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
			$this->wire('files')->chmod($toFile); 
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
	 * @return bool|int Returns boolean true if valid, false if not.
	 * 	If valid as a result of a date comparison, the unix timestmap for the line is returned. 
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

		if($cnt) {
			$this->wire('files')->unlink($filename, true);
			$this->wire('files')->rename("$filename.new", $filename, true);
			$this->wire('files')->chmod($filename); 
		} else {
			$this->wire('files')->unlink("$filename.new", true);
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
			$this->wire('files')->unlink($this->logFilename, true);
			$this->wire('files')->rename($toFile, $this->logFilename, true);
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
		return $this->wire('files')->unlink($this->logFilename, true);
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
}


