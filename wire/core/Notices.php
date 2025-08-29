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
 * ProcessWire 3.x, Copyright 2025 by Ryan Cramer
 * https://processwire.com
 *
 * @property string|object|array $text Text or value of notice
 * @property string $class Class of notice
 * @property int $timestamp Unix timestamp of when the notice was generated
 * @property int $flags Bitmask using any of the Notice::constants
 * @property-read $flagsArray Current flags as an array where indexes are int flags and values are flag names (since 3.0.149)
 * @property-read $flagsStr Current flags as string of flag names (since 3.0.149)
 * @property string $icon Name of icon to use with Notice
 * @property string $idStr Unique ID string for Notice
 * @property int $qty Number of times this Notice was added. 
 *
 */
abstract class Notice extends WireData {

	/**
	 * Flag indicates notice should prepend (rather than append) to any existing notices
	 *
	 * @since 3.0.135
	 *
	 */
	const prepend = 1;

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
	 * Alias of allowMarkup flag
	 * 
	 * @since 3.0.208
	 * 
	 */
	const markup = 32;

	/**
	 * Make notice anonymous (not tied to a particular class)
	 * 
	 * @since 3.0.135
	 * 
	 */
	const anonymous = 65536;

	/**
	 * Indicate notice should not group/collapse with others of the same type (when supported by admin theme)
	 * 
	 * @since 3.0.146
	 * 
	 */
	const noGroup = 131072;

	/**
	 * Alias of noGroup flag
	 * 
	 * @since 3.0.208
	 * 
	 */
	const separate = 131072;

	/**
	 * Ignore notice unless it will be seen by a logged-in user
	 * 
	 * @since 3.0.149
	 * 
	 */
	const login = 262144;

	/**
	 * Ignore notice unless user is somewhere in the admin (login page included)
	 *
	 * @since 3.0.149
	 *
	 */
	const admin = 524288;

	/**
	 * Ignore notice unless current user is a superuser
	 * 
	 * @since 3.0.149
	 * 
	 */
	const superuser = 1048576;
	
	/**
	 * Make notice persist in session until removed with $notices->removeNotice() call
	 *
	 * (not yet fully implemented)
	 *
	 * #pw-internal
	 *
	 * @since 3.0.149
	 * @todo still needs an interactive way to remove
	 *
	 */
	const persist = 2097152;

	/**
	 * Allow parsing of basic/inline markdown and bracket markup per $sanitizer->entitiesMarkdown()
	 * 
	 * @since 3.0.165
	 * 
	 */
	const allowMarkdown = 4194304;

	/**
	 * Alias of allowMarkdown flag
	 * 
	 * @since 3.0.208
	 * 
	 */
	const markdown = 4194304;

	/**
	 * Present duplicate notices separately rather than collapsing them to one
	 * 
	 * String name can be referred to as 'allowDuplicate' or just 'duplicate'
	 * 
	 * @since 3.0.208
	 * 
	 */
	const allowDuplicate = 8388608;

	/**
	 * Alias of allowDuplicate flag
	 * 
	 * @since 3.0.208
	 * 
	 */
	const duplicate = 8388608; 

	/**
	 * Flag integers to flag names
	 * 
	 * @var array
	 * @since 3.0.149
	 * 
	 */
	static protected $flagNames = array(
		self::prepend => 'prepend',
		self::debug => 'debug',
		self::log => 'log',
		self::logOnly => 'logOnly',
		self::allowMarkup => 'allowMarkup',
		self::allowMarkdown => 'allowMarkdown',
		self::allowDuplicate => 'allowDuplicate',
		self::anonymous => 'anonymous',
		self::noGroup => 'noGroup',
		self::login => 'login',
		self::admin => 'admin',
		self::superuser => 'superuser',
		self::persist => 'persist',
	);

	/**
	 * Alternate names to flags
	 * 
	 * @var int[] 
	 * @since 3.0.208
	 * 
	 */
	static protected $flagNamesAlt = array(
		'duplicate' => self::allowDuplicate,
		'markup' => self::allowMarkup,
		'markdown' => self::allowMarkdown,
		'separate' => self::noGroup,
	);
	
	/**
	 * Create the Notice
	 * 
	 * As of version 3.0.149 the $flags argument can also be specified as a space separated 
	 * string or array of flag names. Previous versions only accepted flags integer. 
	 *
	 * @param string $text Notification text
	 * @param int|string|array $flags Flags Flags for Notice 
	 *
	 */
	public function __construct($text, $flags = 0) {
		parent::__construct();
		$this->set('icon', '');
		$this->set('class', '');
		$this->set('timestamp', time());
		$this->set('flags', 0); 
		$this->set('text', $text); 
		$this->set('qty', 0);
		if($flags !== 0) $this->flags($flags);
	}

	/**
	 * Set property
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return $this|WireData
	 * 
	 */
	public function set($key, $value) {
		if($key === 'text' && is_string($value) && strpos($value, 'icon-') === 0 && strpos($value, ' ')) {
			list($icon, $value) = explode(' ', $value, 2);
			list(,$icon) = explode('-', $icon, 2);
			$icon = $this->wire()->sanitizer->name($icon);
			if(strlen($icon)) $this->set('icon', $icon);
		} else if($key === 'flags') {
			$this->flags($value);
			return $this;
		}
		return parent::set($key, $value);
	}

	/**
	 * Get property
	 * 
	 * @param string $key
	 * @return mixed
	 * 
	 */
	public function get($key) {
		if($key === 'flagsArray') return $this->flagNames(parent::get('flags')); 
		if($key === 'flagsStr') return $this->flagNames(parent::get('flags'), true);
		if($key === 'idStr') return $this->getIdStr();
		return parent::get($key);
	}

	/**
	 * Get or set flags 
	 * 
	 * @param string|int|array|null $value Accepts flags integer, or array of flag names, or space-separated string of flag names
	 * @return int
	 * @since 3.0.149
	 * 
	 */
	public function flags($value = null) {
		
		if($value === null) return parent::get('flags'); // get flags
		
		$flags = 0;
		
		if(is_int($value)) {
			$flags = $value;
		} else if(is_string($value)) {
			if(ctype_digit($value)) {
				$flags = (int) $value;
			} else {
				if(strpos($value, ',') !== false) $value = str_replace(array(', ', ','), ' ', $value);
				$value = explode(' ', $value);
			}
		}
		
		if(is_array($value)) {
			foreach($value as $flag) {
				if(empty($flag)) continue;
				$flag = $this->flag($flag);
				if($flag) $flags = $flags | $flag;
			}
		}
		
		parent::set('flags', $flags);
		
		return $flags;
	}

	/**
	 * Given flag name or int, return flag int
	 * 
	 * @param string|int $name
	 * @return int
	 * 
	 */
	protected function flag($name) {
		if(is_int($name)) return $name;
		$name = trim($name);
		if(ctype_digit("$name")) return (int) $name;
		$name = strtolower($name);
		if(isset(self::$flagNamesAlt[$name])) {
			return self::$flagNamesAlt[$name];
		} else if(strpos($name, 'icon-') === 0) {
			$this->icon = substr($name, 5); 
			$flag = 0;
		} else {
			$flag = array_search($name, array_map('strtolower', self::$flagNames));
		}
		return $flag ? $flag : 0;
	}

	/**
	 * Get string of names for given flags integer
	 * 
	 * @param null|int $flags Specify flags integer or omit to return all flag names (default=null)
	 * @param bool $getString Get a space separated string rather than an array (default=false)
	 * @return array|string
	 * @since 3.0.149
	 * 
	 */
	protected function flagNames($flags = null, $getString = false) {
		if($flags === null) {
			$flagNames = self::$flagNames;
		} else if(!is_int($flags)) {
			$flagNames = array();
		} else {
			$flagNames = array();
			foreach(self::$flagNames as $flag => $flagName) {
				if($flags & $flag) $flagNames[$flag] = $flagName;
			}
		}
		return $getString ? implode(' ', $flagNames) : $flagNames;	
	}

	/**
	 * Add a flag 
	 * 
	 * @param int|string $flag
	 * @since 3.0.149
	 * 
	 */
	public function addFlag($flag) {
		$flag = $this->flag($flag);
		if($flag && !($this->flags & $flag)) $this->flags = $this->flags | $flag;
	}

	/**
	 * Remove a flag
	 *
	 * @param int|string $flag
	 * @since 3.0.149
	 *
	 */
	public function removeFlag($flag) {
		$flag = $this->flag($flag);
		if($flag && ($this->flags & $flag)) $this->flags = $this->flags & ~$flag;
	}

	/**
	 * Does this Notice have given flag?
	 *
	 * @param int|string $flag
	 * @return bool
	 * @since 3.0.149
	 *
	 */
	public function hasFlag($flag) {
		$flag = $this->flag($flag);
		return $flag ? $this->flags & $flag : false;
	}

	/**
	 * Get the name for this type of Notice
	 * 
	 * This name is used for notice logs when Notice::log or Notice::logOnly flag is used. 
	 * 
	 * @return string Name of log (basename)
	 * 
	 */
	abstract public function getName();
	
	/**
	 * Get a unique ID string based on properties of this Notice to identify it among others
	 * 
	 * #pw-internal
	 * 
	 * @return string
	 * @since 3.0.149
	 * 
	 */
	public function getIdStr() {
		$prefix = substr(str_replace('otice', '', $this->className()), 0, 2);
		$idStr = $prefix . md5("$prefix$this->flags$this->class$this->text"); 
		return $idStr;
	}
	
	public function __toString() {
		$text = $this->text;
		if(is_object($text)) {
			$value = method_exists($text, '__toString') ? (string) $text : '';
			$class = $text->className();
			$text = "object:$class";
			if($value !== '' && $value !== $class) $text .= "($value)";
		} else if(is_array($text)) {
			$text = 'array(' . count($text) . ')';
		}
		return $text;
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
	
	public function __construct() {
		parent::__construct();
		$this->usesNumericKeys = true;
		$this->indexedByName = false;
	}

	/**
	 * Initialize Notices API var
	 * 
	 * #pw-internal
	 * 
	 */
	public function init() {
		// @todo 
		// $this->loadStoredNotices();
	}

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
	 * Allow given Notice to be added?
	 * 
	 * @param Notice $item
	 * @return bool
	 * 
	 */
	protected function allowNotice(Notice $item) {

		// intentionally not using $this->wire()->user; in case this gets called early in boot
		$user = $this->wire('user'); 
		
		if($item->flags & Notice::debug) {
			if(!$this->wire()->config->debug) return false;
		}

		if($item->flags & Notice::superuser) {
			if(!$user || !$user->isSuperuser()) return false;
		}
		
		if($item->flags & Notice::login) {
			if(!$user || !$user->isLoggedin()) return false;
		}
		
		if($item->flags & Notice::admin) {
			$page = $this->wire()->page;
			if(!$page || !$page->template || $page->template->name != 'admin') return false;
		}
	
		if($item->flags & Notice::allowDuplicate) {
			// allow it
		} else if($this->isDuplicate($item)) {
			$item->qty = $item->qty+1;
			return false;
		}
		
		if(self::logAllNotices || ($item->flags & Notice::log) || ($item->flags & Notice::logOnly)) {
			$this->addLog($item);
			$item->flags = $item->flags & ~Notice::log; // remove log flag, to prevent it from being logged again
			if($item->flags & Notice::logOnly) return false;
		}

		return true;
	}

	/**
	 * Format Notice text
	 * 
	 * @param Notice $item
	 * 
	 */
	protected function formatNotice(Notice $item) {
		$text = $item->text;
		$label = '';
		
		if(is_array($text)) {
			// if text is associative array with 1 item, we consider the 
			// key to be the notice label and value to be the notice text
			if(count($text) === 1) {
				$value = reset($text);
				$key = key($text);
				if(is_string($key)) {
					$label = $key;
					$text = $value;
					$item->text = $text;
					if($this->wire()->config->debug) {
						$item->class = $label;
						$label = '';
					}
				}
			}	
		}
		
		if(is_object($text) || is_array($text)) {
			$text = Debug::toStr($text, array('html' => true));
			$item->flags = $item->flags | Notice::allowMarkup;
			$item->text = $text;
		}
		
		if($item->hasFlag('allowMarkdown')) {
			$item->text = $this->wire()->sanitizer->entitiesMarkdown($text, array('allowBrackets' => true)); 
			$item->addFlag('allowMarkup');
			$item->removeFlag('allowMarkdown'); 
		}
		
		if($label) {
			if($item->hasFlag('allowMarkup')) {
				$label = $this->wire()->sanitizer->entities($label);
				$item->text = "<strong>$label:</strong> $item->text";
			} else {
				$item->text = "$label: \n$item->text";
			}
		}
	}

	/**
	 * Add a Notice object
	 * 
	 * ~~~~
	 * $notices->add(new NoticeError("An error occurred!"));
	 * ~~~~
	 * 
	 * @param Notice $item
	 * @return Notices|WireArray
	 * 
	 */
	public function add($item) {
		
		if(!($item instanceof Notice)) {
			$item = new NoticeError("You attempted to add a non-Notice object to \$notices: $item", Notice::debug); 
		}
		
		if(!$this->allowNotice($item)) return $this;

		$item->qty = $item->qty+1;
		$this->formatNotice($item);

		if($item->flags & Notice::anonymous) {
			$item->set('class', '');
		}
		
		if($item->flags & Notice::persist) {
			$this->storeNotice($item);
		}
		
		if($item->flags & Notice::prepend) {
			return parent::prepend($item);	
		} else {
			return parent::add($item);
		}
	}

	/**
	 * Store a persist Notice in Session
	 * 
	 * @param Notice $item
	 * @return bool
	 *
	 */
	protected function storeNotice(Notice $item) {
		$session = $this->wire()->session;
		if(!$session) return false;
		$items = $session->getFor($this, 'items');
		if(!is_array($items)) $items = array();
		$str = $this->noticeToStr($item);
		$idStr = $item->getIdStr();
		if(isset($items[$idStr])) return false;
		$items[$idStr] = $str;
		$session->setFor($this, 'items', $items);
		return true;
	}

	/**
	 * Load persist Notices stored in Session
	 * 
	 * @return int Number of Notices loaded
	 * 
	 */
	protected function loadStoredNotices() {
		
		$session = $this->wire()->session;
		$items = $session->getFor($this, 'items');
		$qty = 0;
		
		if(empty($items) || !is_array($items)) return $qty;
		
		foreach($items as $idStr => $str) {
			if(!is_string($str)) continue;
			$item = $this->strToNotice($str);
			if(!$item) continue;
			$persist = $item->hasFlag(Notice::persist) ? Notice::persist : 0;
			// temporarily remove persist flag so Notice does not get re-stored when added
			if($persist) $item->removeFlag($persist);
			$this->add($item);
			if($persist) $item->addFlag($persist);
			$item->set('_idStr', $idStr);
			$qty++;
		}
	
		return $qty;
	}

	/**
	 * Remove a Notice
	 * 
	 * Like the remove() method but also removes persist notices. 
	 * 
	 * @param string|Notice $item Accepts a Notice object or Notice ID string.
	 * @return self
	 * @since 3.0.149
	 * 
	 */
	public function removeNotice($item) {
		if($item instanceof Notice) {
			$idStr = $item->get('_idStr|idStr'); 
		} else if(is_string($item)) {
			$idStr = $item;
			$item = $this->getByIdStr($idStr); 
		} else {
			return $this;
		}
		if($item) parent::remove($item);
		$session = $this->wire()->session;
		$items = $session->getFor($this, 'items');
		if(is_array($items) && isset($items[$idStr])) {
			unset($items[$idStr]);
			$session->setFor($this, 'items', $items);
		}
		return $this;
	}

	/**
	 * Is the given Notice a duplicate of one already here?
	 * 
	 * @param Notice $item
	 * @return bool|Notice Returns Notice that it duplicate sor false if not a duplicate
	 * 
	 */
	protected function isDuplicate(Notice $item) {
		$duplicate = false;
		foreach($this as $notice) {
			/** @var Notice $notice */
			if($notice === $item) {
				$duplicate = $notice;
				break;
			}
			if($notice->className() === $item->className() && $notice->flags === $item->flags
				&& $notice->icon === $item->icon && $notice->text === $item->text) {
				$duplicate = $notice;
				break;
			}
		}
		return $duplicate;
	}

	/**
	 * Add Notice to log
	 * 
	 * @param Notice $item
	 * 
	 */
	protected function addLog(Notice $item) {
		$text = $item->text;
		if(strpos($text, '&') !== false) {
			$text = $this->wire()->sanitizer->unentities($text);
		}
		if($this->wire()->config->debug && $item->class) $text .= " ($item->class)"; 
		$this->wire()->log->save($item->getName(), $text); 
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
		$sanitizer = $this->wire()->sanitizer; 
		$b = array();
		foreach($a as $key => $value) {
			if(is_array($value)) {
				$value = $this->sanitizeArray($value);
			} else {
				if(is_object($value)) {
					if($value instanceof Wire) {
						$value = (string) $value;
						$class = wireClassName($value);
						if($value !== $class) $value = "object:$class($value)";
					} else {
						$value = 'object:' . wireClassName($value);
					}
				}
				$value = $sanitizer->entities($value); 
			} 
			$key = $sanitizer->entities($key);
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

	/**
	 * Get a Notice by ID string
	 * 
	 * #pw-internal
	 * 
	 * @param string $idStr
	 * @return Notice|null
	 * @since 3.0.149
	 * 
	 */
	protected function getByIdStr($idStr) {
		$notice = null;	
		if(strlen($idStr) < 33) return null;
		$prefix = substr($idStr, 0, 1);
		foreach($this as $item) {
			/** @var Notice $item */
			if(strpos($item->className(), $prefix) !== 0) continue;
			if($item->getIdStr() !== $idStr) continue;
			$notice = $item;
			break;
		}
		return $notice;
	}

	/**
	 * Get all notices visible to current user
	 *
	 * @return Notices Returns a new Notices object
	 * @since 3.0.252
	 *
	 */
	public function getVisible() {
		$config = $this->wire()->config;
		$user = $this->wire()->user;
		$items = new Notices();
		$this->wire($items);
		foreach($this as $notice) {
			/** @var Notice $notice */
			$flags = $notice->flags;
			if(($flags & Notice::superuser) && !$user->isSuperuser()) continue;
			if(($flags & Notice::login) && !$user->isLoggedin()) continue;
			if(($flags & Notice::debug) && !$config->debug) continue;
			if(($flags & Notice::admin) && !$config->admin) continue;
			if(($flags & Notice::logOnly)) continue;
			$items->add($notice);
		}
		return $items;
	}

	/**
	 * Export Notice object to string
	 * 
	 * #pw-internal
	 * 
	 * @param Notice $item
	 * @return string
	 * @since 3.0.149
	 * 
	 */
	protected function noticeToStr(Notice $item) {
		$type = str_replace('Notice', '', $item->className());
		$a = array(
			'type' => $type,
			'flags' => $item->flags,
			'timestamp' => $item->timestamp,
			'class' => $item->class,
			'icon' => $item->icon,
			'text' => $item->text,
		);
		return implode(';', $a);
	}

	/**
	 * Import Notice object from string
	 * 
	 * #pw-internal
	 * 
	 * @param string $str
	 * @return Notice|null
	 * @since 3.0.149
	 * 
	 */
	protected function strToNotice($str) {
		if(substr_count($str, ';') < 5) return null;
		list($type, $flags, $timestamp, $class, $icon, $text) = explode(';', $str, 6);
		$type = __NAMESPACE__ . "\\Notice$type";
		if(!wireClassExists($type)) return null;
		/** @var Notice $item */
		$item = new $type($text, (int) $flags);
		$item->setArray(array(
			'timestamp' => (int) $timestamp,
			'class' => $class,
			'icon' => $icon,
		));
		return $item;
	}
}
