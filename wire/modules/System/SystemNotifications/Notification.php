<?php namespace ProcessWire;

/**
 * An individual notification item to be part of a NotificationArray for a Page
 * 
 * @class Notification
 * 
 * @property int $pages_id  page ID notification is for (likely a User page)
 * @property int $sort  sort value, as required by Fieldtype
 * @property int $src_id  page ID when notification was generated
 * @property string $title  title/headline
 * @property int $flags  flags: see flag constants 
 * @property int $created  datetime created (unix timestamp)
 * @property int $modified  datetime created (unix timestamp)
 * @property int $qty  quantity of times this notification has been repeated
 * 
 * data encoded vars, all optional
 * ===============================
 * @property int $id  unique ID (among others the user may have)
 * @property string $text  extended text
 * @property string $html  extended text as HTML markup 
 * @property string $from  "from" text where applicable, like a class name
 * @property string $icon  fa-icon when applicable
 * @property string $href  clicking notification goes to this URL
 * @property int $progress  progress percent 0-100
 * @property int $expires  datetime after which will automatically be deleted
 *
 */
class Notification extends WireData {

	/**
	 * Flag constants for Notification objects
	 * 
	 * Note that flags 2-32 line up with the same flags from Notice objects
	 * 
	 */

	const flagDebug = 2; 		// Show/save only when the system is in debug mode
	const flagLog = 8; 			// save to log and show
	const flagLogOnly = 16; 	// save to log but don't show
	const flagAllowMarkup = 32;	// allow markup in the title
	
	const flagMessage = 64; 	// informational
	const flagWarning = 4;		// warning 
	const flagError = 128;		// error 
	
	const flagNotice = 256; 	// Show only as a single-request notice (not stored in DB)
	const flagSession = 512; 	// Notification lasts for only this session (not stored in DB)
	const flagEmail = 1024; 	// title and body will also be emailed to user (if page is user)
	const flagOpen = 2048;		// notification will automatically open the text/html area (no click required)
	
	const flagNoGhost = 4096; 	// disable showing of a notification ghost
	const flagAnnoy = 8192; 	// rather than just update bug counter, notification will pop up at top of screen
	const flagShown = 16384; 	// has this flag once the notification has been sent to the UI at least once
	const flagAlert = 32768;	// show an alert that requires acknowledgement (use with flagSession only)

	/**
	 * Provides a name for each of the flags
	 * 
	 * @var array
	 * 
	 */
	static protected $_flagNames = array(
		self::flagDebug => 'debug',
		self::flagLog => 'log', 
		self::flagLogOnly => 'log-only', 
		self::flagAllowMarkup => 'markup', 
		self::flagMessage => 'message',
		self::flagWarning => 'warning',
		self::flagError => 'error',
		self::flagNotice => 'notice',
		self::flagSession => 'session',
		self::flagEmail => 'email',
		self::flagOpen => 'open',
		self::flagNoGhost => 'no-ghost', 
		self::flagAnnoy => 'annoy',
		self::flagShown => 'shown', 
		self::flagAlert => 'alert', 
		);

	/**
	 * Page that this Notification belongs to
	 * 
	 * @var Page
	 * 
	 */
	protected $page; 

	/**
	 * Construct a new Notification
	 *
	 */
	public function __construct() {

		// db native vars
		$this->set('pages_id', 0); 	// page ID notification is for (likely a User page)
		$this->set('sort', 0); 		// sort value, as required by Fieldtype
		$this->set('src_id', 0); 	// page ID when notification was generated
		$this->set('title', ''); 	// title/headline
		$this->set('flags', 0);		// flags: see flag constants 
		$this->set('created', 0); 	// datetime created (unix timestamp)
		$this->set('modified', 0); 	// datetime created (unix timestamp)
		$this->set('qty', 1); 		// quantity of times this notification has been repeated

		// data encoded vars, all optional
		$this->set('id', ''); 		// unique ID (among others the user may have)
		$this->set('text', ''); 	// extended text
		$this->set('html', ''); 	// extended text as HTML markup 
		$this->set('from', '');		// "from" text where applicable, like a class name
		$this->set('icon', ''); 	// fa-icon when applicable
		$this->set('href', ''); 	// clicking notification goes to this URL
		$this->set('progress', 0); 	// progress percent 0-100
		$this->set('expires', 0); 	// datetime after which will automatically be deleted
		
	}

	/**
	 * Fluent interface methods
	 * 
	 */

	public function title($value) { return $this->set('title', $value); }
	public function text($value) { return $this->set('text', $value); }
	public function html($value) { return $this->set('html', $value); }
	public function from($value) { return $this->set('from', $value); }
	public function icon($value) { return $this->set('icon', $value); }
	public function href($value) { return $this->set('href', $value); }
	public function progress($value) { return $this->set('progress', $value); }
	public function expires($value) { return $this->set('expires', $value); }
	public function flag($value) { return $this->setFlag($value, true); }
	public function flags($value) { return $this->setFlags($value, true); }

	/**
	 * Does this Notification match the given flag name(s)?
	 * 
	 * @param string $name
	 * @return bool
	 * 
	 */
	public function is($name) {
		$flags = $this->flagNamesToFlags($name); 
		$is = 0;
		foreach($flags as $flag) { 
			if($this->flags & $flag) $is++;
		}
		return $is == count($flags); 
	}

	/**
	 * Given a flag name, return the corresponding flag value
	 * 
	 * @param string $name
	 * @return int mixed
	 * @throws WireException if given unknown flag
	 * 
	 */
	protected function flagNameToFlag($name) {
		if(is_string($name)) {
			$flag = array_search($name, self::$_flagNames); 
			if(!$flag) throw new WireException("Unknown flag: $name"); 
		} else {
			$flag = $name;
			if(!isset(self::$_flagNames[$flag])) throw new WireException("Unknown flag: $flag"); 
		}
		return $flag;
	}

	/**
	 * Given multiple space separated flag names, return array of flag values
	 * 
	 * @param string $names space separted, will also accept CSV
	 * @return array of flag name => flag value
	 * 
	 */
	protected function flagNamesToFlags($names) {
		if(strpos($names, ',') !== false) $names = str_replace(',', ' ', $names); 
		$names = explode(' ', $names); 
		$flags = array();
		foreach($names as $name) {
			if(empty($name)) continue; 
			$flag = $this->flagNameToFlag($name); 
			if($flag) $flags[$name] = $flag;
		}
		return $flags; 
	}

	/**
	 * Set a named flag
	 *
	 * @param string|int $name Flag to set
	 * @param bool $add True to add flag, false to remove
	 * @return this
	 *
	 */
	public function setFlag($name, $add = true) {

		$flag = ctype_digit("$name") ? (int) $name : $this->flagNameToFlag($name); 	
		$flags = parent::get('flags'); 

		if($add) {
			// add flag
			if($flags & $flag) {
				// flag is already set
			} else {
				$flags = $flags | $flag;
				parent::set('flags', $flags); 
			}
		} else {
			// remove flag
			if($flags & $flag) {
				// flag is set, remove it
				$flags = $flags & ~$flag;
				parent::set('flags', $flags); 
			} else {
				// flag is not set
			}
		}

		return $this; 
	}

	/**
	 * Add the given flag name(s) (shortcut for setFlag)
	 * 
	 * @param $name One or more space-separated flag names
	 * @return this
	 * 
	 */
	public function addFlag($name) {
		return $this->setFlags($name, true); 
	}

	/**
	 * Remove the given flag name(s) (shortcut for setFlag)
	 * 
	 * @param $name One or more space-separated flag names
	 * @return this
	 * 
	 */
	public function removeFlag($name) {
		return $this->setFlags($name, false); 
	}

	/**
	 * Set multiple flags
	 * 
	 * @param string $names space separated string of flag names
	 * @param bool $add True to add, false to remove
	 * @return $this
	 * 
	 */
	public function setFlags($names, $add = true) {
		
		if(ctype_digit("$names")) {
			// likely a flag or combined flags in bitmask
			$flags = (int) $names;
			// iterate through known flags to see which are set
			foreach(self::$_flagNames as $flag => $name) {
				// if it's a recognized/valid flag, set it 
				if($flags & $flag) $this->setFlag($flag, $add); 
			}
			return $this;
		}
	
		// optimization if this was called with just one flag name
		if(strpos($names, ',') === false && strpos($names, ' ') === false) {
			$this->setFlag($names, $add); 
		}
	
		// named flags
		$flags = $this->flagNamesToFlags($names); 
		foreach($flags as $name => $flag) {
			$this->setFlag($flag, $add); 	
		}
		
		return $this; 
	}

	/**
	 * Set a value to the Notification
	 * 
	 * Note: setting the 'expires' value accepts either a future date, or a quantity of seconds 
	 * in the future relative to now. 
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return this
	 *
	 */
	public function set($key, $value) {
 
		if($key == 'page') {
			$this->page = $value; 
			return $this; 

		} else if($key == 'created' || $key == 'modified' || $key == 'expires') {
			// convert date string to unix timestamp
			if($value && !ctype_digit("$value")) $value = strtotime($value); 	

			// sanitized date value is always an integer
			$value = (int) $value; 
			
			if($key == 'expires' && $value > 0 && $value < strtotime("-10 YEARS")) {
				// assume this is a time relative to now
				$value = time() + $value;
			}

		} else if($key == 'title') {
			if($this->flags & self::flagAllowMarkup) {
				// accept value as-is
			} else {
				// regular text sanitizer
				$value = $this->sanitizer->text($value);
			}
				
		} else if($key == 'from') {
			// regular text sanitizer
			$value = $this->sanitizer->text($value); 

		} else if($key == 'text') {
			// regular text sanitizer
			$value = $this->sanitizer->textarea($value); 

		} else if(in_array($key, array('pages_id', 'sort', 'src_id', 'flags', 'progress'))) {
			$value = (int) $value; 
		}

		return parent::set($key, $value); 
	}

	/**
	 * Return an ID string/hash unique to this Notification within the page that its on
	 * 
	 * The text/html, modified date, expires date, and icon may change without affecting the id. 
	 * 
	 * @return mixed|null|string
	 * 
	 */
	public function getID() {

		$id = parent::get('id'); 
		if($id) return $id; 

		$id = 	parent::get('title') . ',' . 
				parent::get('created') . ',' . 
				parent::get('from') . ',' . 
				parent::get('src_id') . ',' . 
				($this->page ? $this->page->id : '?'); // . ',' . 
				//parent::get('flags');

		return 'noID' . md5($id); 
	}
	
	/**
	 * Return an string hash for comparing other notifications to see if they contain the same content
	 * 
	 * Hash specifically excludes consideration of dates (created, modified, expires)
	 *
	 * @return string
	 *
	 */
	public function getHash() {

		$id = 	trim(parent::get('title')) . ',' .
				// parent::get('from') . ',' .
				// parent::get('src_id') . ',' .
				// ($this->page ? $this->page->id : '?') . ',' . 
				// parent::get('flags') . ',' . 
				// parent::get('icon') . ',' . 
				trim(parent::get('text')) . ',' . 
				trim(parent::get('html'));

		return md5($id);
	}

	/**
	 * Retrieve a value from the Notification
	 * 
	 * @param string $key
	 * @return mixed
	 *
	 */
	public function get($key) {

		if($key == 'id') return $this->getID();
		if($key == 'page') return $this->page; 
		if($key == 'hash') return $this->getHash();

		if($key == 'flagNames') {
			$flags = parent::get('flags');
			$flagNames = array();
			foreach(self::$_flagNames as $val => $name) {
				if($flags & $val) $flagNames[$val] = $name;
			}
			return $flagNames;
		}

		$value = parent::get($key); 

		// if the page's output formatting is on, then we'll return formatted values
		if($this->page && $this->page->of()) {

			if($key == 'created' || $key == 'expires' || $key == 'modified') {
				// format a unix timestamp to a date string
				$value = date('Y-m-d H:i:s', $value); 				

			} else if($key == 'title' || $key == 'text' || $key == 'from') {
				// return entity encoded versions of strings
				if($key == 'title' && ($this->flags & self::flagAllowMarkup)) {
					// leave title alone when markup is allowed
				} else {
					$value = $this->sanitizer->entities($value); 
				}
			}
		} else {
			if($key == 'created' && !$value) $value = time();
		}

		return $value; 
	}

	/**
	 * Is this Notification expired?
	 * 
	 * @return bool
	 * 
	 */
	public function isExpired() {
		return ($this->expires > 0 && $this->expires <= time()); 
	}

	/**
	 * String value of a Notification
	 * 
	 * @return string
	 * 
	 */
	public function __toString() {
		$str = $this->title; 
		$str .= " (" . implode(', ', $this->get('flagNames')) . ")";
		return $str; 
	}


}

