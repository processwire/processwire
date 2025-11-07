<?php namespace ProcessWire;

/**
 * ProcessWire Notice
 *
 * #pw-headline Notice
 * #pw-summary Manages notifications in the ProcessWire admin, primarily for internal use.
 * #pw-use-constants
 * #pw-use-constructor
 * #pw-body =
 * Base class that holds a message, source class, and timestamp.
 * Contains individual notices/messages used by the application to display to the user.
 * Notice items come in three different classes: NoticeMessage, NoticeWarning and NoticeError.
 * They are all identical in terms of API, with the only difference being that they render as
 * informational messages, warnings, or errors.
 * #pw-body
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

	/**
	 * Is this notice viewable at runtime?
	 * 
	 * @return bool
	 * @since 3.0.254
	 * 
	 */
	public function viewable() {
		$config = $this->wire()->config;
		$user = $this->wire()->user;
		$flags = $this->flags;
		
		if(($flags & Notice::debug) && !$config->debug) return false;
		if(($flags & Notice::login) && !$user->isLoggedin()) return false;
		if(($flags & Notice::superuser) && !$user->isSuperuser()) return false;
		if(($flags & Notice::admin) && (!$config->admin || !$user->isLoggedin())) return false;
		if($flags & Notice::logOnly) return false;
		
		return true;
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
