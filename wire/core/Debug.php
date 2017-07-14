<?php namespace ProcessWire;

/**
 * ProcessWire Debug 
 *
 * Provides methods useful for debugging or development. 
 *
 * Currently only provides timer capability. 
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class Debug {

	/**
	 * Current timers
	 * 
	 * @var array
	 * 
	 */
	static protected $timers = array();

	/**
	 * Timers that have been saved
	 * 
	 * @var array
	 * 
	 */
	static protected $savedTimers = array();

	/**
	 * Notes for saved timers
	 * 
	 * @var array
	 * 
	 */
	static protected $savedTimerNotes = array();

	/**
	 * Measure time between two events
	 *
	 * First call should be to $key = Debug::timer() with no params, or provide your own key that's not already been used
	 * Second call should pass the key given by the first call to get the time elapsed, i.e. $time = Debug::timer($key).
	 * Note that you may make multiple calls back to Debug::timer() with the same key and it will continue returning the 
	 * elapsed time since the original call. If you want to reset or remove the timer, call removeTimer or resetTimer.
	 *
	 * @param string $key 
	 * 	Leave blank to start timer. 
	 *	Specify existing key (string) to return timer. 
	 *	Specify new made up key to start a named timer. 
	 * @param bool $reset If the timer already exists, it will be reset when this is true. 
	 * @return string|int
	 *
	 */
	static public function timer($key = '', $reset = false) {
		// returns number of seconds elapsed since first call
		if($reset && $key) self::removeTimer($key);

		if(!$key || !isset(self::$timers[$key])) {
			// start new timer
			$startTime = -microtime(true);
			if(!$key) {
				$key = (string) $startTime; 
				while(isset(self::$timers[$key])) $key .= "0";
			}
			self::$timers[(string) $key] = $startTime; 
			$value = $key; 
		} else {
			// return existing timer
			$value = number_format(self::$timers[$key] + microtime(true), 4);
		}

		return $value; 
	}

	/**
	 * Save the current time of the given timer which can be later retrieved with getSavedTimer($key)
	 * 
	 * Note this also stops/removes the timer. 
	 * 
	 * @param string $key
	 * @param string $note Optional note to include in getSavedTimer
	 * @return bool Returns false if timer didn't exist in the first place
	 *
	 */
	static public function saveTimer($key, $note = '') {
		if(!isset(self::$timers[$key])) return false;
		self::$savedTimers[$key] = self::timer($key); 
		self::removeTimer($key); 
		if($note) self::$savedTimerNotes[$key] = $note; 
		return true; 
	}

	/**
	 * Return the time recorded in the saved timer $key
	 * 
	 * @param string $key
	 * @return string Blank if timer not recognized
	 *
	 */
	static public function getSavedTimer($key) {
		$value = isset(self::$savedTimers[$key]) ? self::$savedTimers[$key] : null;	
		if(!is_null($value) && isset(self::$savedTimerNotes[$key])) $value = "$value - " . self::$savedTimerNotes[$key];
		return $value; 
	}

	/**
	 * Return all saved timers in associative array indexed by key
	 *
	 * @return array
	 *
	 */
	static public function getSavedTimers() {
		$timers = self::$savedTimers;
		arsort($timers); 
		foreach($timers as $key => $timer) {
			$timers[$key] = self::getSavedTimer($key); // to include notes
		}
		return $timers; 
	}

	/**
	 * Reset a timer so that it starts timing again from right now
	 * 
	 * @param string $key
	 * @return string|int
	 *
	 */
	static public function resetTimer($key) {
		self::removeTimer($key); 
		return self::timer($key); 
	}

	/**
	 * Remove a timer completely
	 * 
	 * @param string $key
	 *
	 */
	static public function removeTimer($key) {
		unset(self::$timers[$key]); 
	}

	/**
	 * Remove all active timers
	 *
	 */
	static public function removeAll() {
		self::$timers = array();
	}

}
