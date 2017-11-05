<?php namespace ProcessWire;

/**
 * ProcessWire Notices
 * 
 * #pw-summary Manages notifications in the ProcessWire admin, primarily for internal use.
 * #pw-use-constants
 * #pw-use-constructor
 * 
 * Base class that holds a message, source class, and timestamp.
 * Contains notices/messages used by the application to the user. 
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 * @property string $text Text of notice
 * @property string $class Class of notice
 * @property int $timestamp When the notice was generated
 * @property int $flags Optional flags bitmask of Notice::debug and/or Notice::warning
 * @property string $icon
 *
 */
abstract class Notice extends WireData {
	

	/**
	 * Flag indicates the notice is for when debug mode is on only
	 *
	 */
	const debug = 2;

	/**
	 * Flag indicates the notice is a warning
	 * 
	 * #pw-internal
	 * 
	 * @deprecated use NoticeWarning instead. 
	 *
	 */
	const warning = 4; 

	/**
	 * Flag indicates the notice will also be sent to the messages or errors log
	 *
	 */
	const log = 8; 

	/**
	 * Flag indicates the notice will be logged, but not shown
	 *
	 */
	const logOnly = 16;

	/**
	 * Flag indicates the notice is allowed to contain markup and won’t be automatically entity encoded
	 *
	 * Note: entity encoding is done by the admin theme at output time, which should detect this flag. 
	 *
	 */
	const allowMarkup = 32;
	
	/**
	 * Create the Notice
	 *
	 * @param string $text Notification text
	 * @param int $flags Flags
	 *
	 */
	public function __construct($text, $flags = 0) {
		$this->set('text', $text); 
		$this->set('class', ''); 
		$this->set('timestamp', time()); 
		$this->set('flags', $flags); 
		$this->set('icon', '');
	}

	/**
	 * Get the notice log
	 * 
	 * @return string Name of log (basename)
	 * 
	 */
	abstract public function getName();
	
	public function __toString() {
		return (string) $this->text; 
	}
}

/**
 * A notice that's indicated to be informational
 *
 */
class NoticeMessage extends Notice { 
	public function getName() {
		return 'messages';
	}
}

/**
 * A notice that's indicated to be an error
 *
 */
class NoticeError extends Notice { 
	public function getName() {
		return 'errors';
	}
}

/**
 * A notice that's indicated to be a warning
 *
 */
class NoticeWarning extends Notice {
	public function getName() {
		return 'warnings';
	}
}


/**
 * ProcessWire Notices
 * 
 * #pw-summary A class to contain multiple Notice instances, whether messages, warnings or errors
 * #pw-body =
 * This class manages notices that have been sent by `Wire::message()`, `Wire::warning()` and `Wire::error()` calls. 
 * The message(), warning() and error() methods are available on every `Wire` derived object. This class is primarily
 * for internal use in the admin. However, it may also be useful in some front-end contexts. 
 * ~~~~~
 * // Adding a NoticeMessage using object syntax
 * $notices->add(new NoticeMessage("Hello World"));
 * 
 * // Adding a NoticeMessage using regular syntax
 * $notices->message("Hello World"); 
 * 
 * // Adding a NoticeWarning, and allow markup in it
 * $notices->message("Hello <strong>World</strong>", Notice::allowMarkup); 
 * 
 * // Adding a NoticeError that only appears if debug mode is on
 * $notices->error("Hello World", Notice::debug); 
 * ~~~~~
 * Iterating and outputting Notices:
 * ~~~~~
 * foreach($notices as $notice) {
 *   // skip over debug notices, if debug mode isn't active
 *   if($notice->flags & Notice::debug && !$config->debug) continue;
 *   // entity encode notices unless the allowMarkup flag is set
 *   if($notice->flags & Notice::allowMarkup) {
 *     $text = $notice->text; 
 *   } else {
 *     $text = $sanitizer->entities($notice->text);
 *   }
 *   // output either an error, warning or message notice
 *   if($notice instanceof NoticeError) {
 *     echo "<p class='error'>$text</p>";
 *   } else if($notice instanceof NoticeWarning) {
 *     echo "<p class='warning'>$text</p>";
 *   } else {
 *     echo "<p class='message'>$text</p>";
 *   }
 * }
 * ~~~~~
 *
 * #pw-body
 * 
 *
 */
class Notices extends WireArray {
	
	const logAllNotices = false;  // for debugging/dev purposes

	/**
	 * #pw-internal
	 * 
	 * @param mixed $item
	 * @return bool
	 * 
	 */
	public function isValidItem($item) {
		return $item instanceof Notice; 
	}

	/**
	 * #pw-internal
	 *
	 * @return Notice
	 *
	 */
	public function makeBlankItem() {
		return $this->wire(new NoticeMessage('')); 
	}

	/**
	 * Add a Notice object
	 * 
	 * ~~~~
	 * $notices->add(new NoticeError("An error occurred!"));
	 * ~~~~
	 * 
	 * @param Notice $item
	 * @return $this
	 * 
	 */
	public function add($item) {

		if($item->flags & Notice::debug) {
			if(!$this->wire('config')->debug) return $this;
		}
		
		if(is_array($item->text)) {
			$item->text = "<pre>" . trim(print_r($this->sanitizeArray($item->text), true)) . "</pre>";
			$item->flags = $item->flags | Notice::allowMarkup;
		} else if(is_object($item->text) && $item->text instanceof Wire) {
			$item->text = "<pre>" . $this->wire('sanitizer')->entities(print_r($item->text, true)) . "</pre>";
			$item->flags = $item->flag | Notice::allowMarkup;
		} else if(is_object($item->text)) {
			$item->text = (string) $item->text; 
		}

		// check for duplicates
		$dup = false; 
		foreach($this as $notice) {
			if($notice->text == $item->text && $notice->flags == $item->flags) $dup = true; 
		}

		if($dup) return $this;
		
		if(($item->flags & Notice::warning) && !$item instanceof NoticeWarning) {
			// if given a warning of either NoticeMessage or NoticeError, convert it to a NoticeWarning
			// this is in support of legacy code, as NoticeWarning didn't used to exist
			$warning = $this->wire(new NoticeWarning($item->text, $item->flags));
			$warning->class = $item->class;
			$warning->timestamp = $item->timestamp;
			$item = $warning;
		}

		if(self::logAllNotices || ($item->flags & Notice::log) || ($item->flags & Notice::logOnly)) {
			$this->addLog($item);
			$item->flags = $item->flags & ~Notice::log; // remove log flag, to prevent it from being logged again
			if($item->flags & Notice::logOnly) return $this;
		}
		
		return parent::add($item); 
	}
	
	protected function addLog($item) {
		/** @var Notice $item */
		$text = $item->text;
		if($item->flags & Notice::allowMarkup && strpos($text, '&') !== false) {
			$text = $this->wire('sanitizer')->unentities($text);
		}
		if($this->wire('config')->debug && $item->class) $text .= " ($item->class)"; 
		$this->wire('log')->save($item->getName(), $text); 
	}

	/**
	 * Are there NoticeError items present?
	 * 
	 * @return bool
	 * 
	 */
	public function hasErrors() {
		$numErrors = 0;
		foreach($this as $notice) {
			if($notice instanceof NoticeError) $numErrors++;
		}
		return $numErrors > 0;
	}

	/**
	 * Are there NoticeWarning items present?
	 * 
	 * @return bool
	 * 
	 */
	public function hasWarnings() {
		$numWarnings = 0;
		foreach($this as $notice) {
			if($notice instanceof NoticeWarning) $numWarnings++;
		}
		return $numWarnings > 0;
	}

	/**
	 * Recursively entity encoded values in arrays and convert objects to string
	 * 
	 * This enables us to safely print_r the string for debugging purposes 
	 * 
	 * #pw-internal
	 * 
	 * @param array $a
	 * @return array
	 * 
	 */
	public function sanitizeArray(array $a) {
		$sanitizer = $this->wire('sanitizer'); 
		$b = array();
		foreach($a as $key => $value) {
			if(is_array($value)) {
				$value = $this->sanitizeArray($value);
			} else {
				if(is_object($value)) $value = (string) $value;
				$value = $sanitizer->entities($value); 
			} 
			$key = $this->wire('sanitizer')->entities($key);
			$b[$key] = $value;
		}
		return $b; 
	}

	/**
	 * Move notices from one Wire instance to another
	 * 
	 * @param Wire $from
	 * @param Wire $to
	 * @param array $options Additional options:
	 *  - `types` (array): Types to move (default=['messages','warnings','errors'])
	 *  - `prefix` (string): Optional prefix to add to moved notices text (default='')
	 *  - `suffix` (string): Optional suffix to add to moved notices text (default='')
	 * @return int Number of notices moved
	 * 
	 */
	public function move(Wire $from, Wire $to, array $options = array()) {
		$n = 0;
		$types = isset($options['types']) ? $options['types'] : array('errors', 'warnings', 'messages'); 
		foreach($types as $type) {
			$method = rtrim($type, 's');
			foreach($from->$type('clear') as $notice) {
				$text = $notice->text; 
				if(isset($options['prefix'])) $text = "$options[prefix]$text";
				if(isset($options['suffix'])) $text = "$text$options[suffix]";
				$to->$method($text, $notice->flags);
				$n++;
			}
		}
		return $n;
	}
}
