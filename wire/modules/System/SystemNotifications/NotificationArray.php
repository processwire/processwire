<?php namespace ProcessWire;

/**
 * Contains multiple Event objects for a single page
 * 
 * @class NotificationArray
 *
 */

class NotificationArray extends WireArray {
	
	/**
	 * User or Page that these notifications are for
	 * 
	 * @var Page|User
	 * 
	 */
	protected $page;

	/**
	 * Create a new NotificationArray
	 * 
	 * @param Page|User $page User (or page) that these notifications are for
	 * 
	 */
	public function __construct(Page $page) {
		$this->page = $page; 
	}

	/**
	 * Template method from WireArray
	 *
	 * @param Notification $item
	 * @return bool
	 * 
	 */
	public function isValidItem($item) {
		return $item instanceof Notification;
	}

	/**
	 * Add a Notification instance to this NotificationArray
	 * 
	 * @param Notification $item
	 * @return $this
	 * 
	 */
	public function add($item) {
		
		$item->page = $this->page; 
		$duplicate = false;
		$itemID = $item->getID();
		
		foreach($this as $notification) {
			if($notification === $item) continue; 

			if($notification->getID() == $itemID) { 
				// already have it
				$duplicate = $notification; 
				break;
			}
		}

		// don't add if it's a duplicate, just update it
		if($duplicate) {
			$item = $duplicate;
			$item->modified = time();
			$item->progress = $duplicate->progress; 
			$item->qty++;
		}

		return parent::add($item); 
	}

	/**
	 * Retrieve a Notification by index or by id
	 * 
	 * @param int|string $key
	 * @return Notification|null
	 * 
	 */
	public function get($key) {
		if(is_string($key) && strpos($key, 'noID') === 0) {
			$found = null;
			foreach($this as $notification) {
				if($notification->getID() === $key) {
					$found = $notification; 	
					break;
				}
			}
			return $found;
			
		} 
		return parent::get($key); 
	}

	/**
	 * Get a notification that contains the given value for $property
	 * 
	 * @param string $property
	 * @param mixed $value
	 * @return null|Notification
	 * 
	 */
	public function getBy($property, $value) {
		$found = null;
		foreach($this as $notification) {
			if($notification->get($property) == $value) {
				$found = $notification;
				break;
			}
		}
		return $found;
	}

	/**
	 * Save any changes or additions that were made to these Notifications
	 * 
	 * @return bool
	 * 
	 */
	public function save() {
		$of = $this->page->of();	
		if($of) $this->page->of(false); 
		$result = $this->page->save(SystemNotifications::fieldName, array('quiet' => true)); 	
		if($of) $this->page->of(true); 
		return $result;
	}

	/**
	 * Get string value of this NotificationArray
	 * 
	 * @return string
	 * 
	 */
	public function __toString() {
		$out = '';
		foreach($this as $item) $out .= "\n$item"; 
		return trim($out); 
	}

	/**
	 * Get a new Notification
	 * 
	 * @param string $flag Specify any flag, flag name or space-separated combination of flag names
	 * @param bool $addNow Add it to this NotificationArray now?
	 * @return Notification
	 * 
	 */
	public function getNew($flag = 'message', $addNow = true) {
		
		$notification = $this->wire(new Notification());
		
		$notification->setFlags($flag, true); 
		$notification->created = time();
		$notification->modified = time();
		$notification->title = 'Untitled';
		$notification->page = $this->page; 
		$notification->src_id = $this->wire('page')->id; 
		
		if($addNow) parent::add($notification); 
		
		return $notification; 
	}

	
	/**************************************************************************************
	 * The following methods are based on those in the base Wire class, but they override
	 * them to replace Notices functionality with Notifications
	 * 
	 */

	/**
	 * Record an informational or 'success' message
	 *
	 * @param string $text
	 * @param int|bool $flags See Notification flags
	 * @return Notification
	 *
	 */
	public function message($text, $flags = 0) {
		$notification = $this->getNew(Notification::flagMessage, true); 
		$notification->title = $text;
		if($flags) $notification->setFlags($flags); 
		return $notification;
	}
	
	/**
	 * Record a warning notification
	 *
	 * @param string $text
	 * @param int|bool $flags See Notification flags
	 * @return Notification
	 *
	 */
	public function warning($text, $flags = 0) {
		$notification = $this->getNew(Notification::flagWarning, true);
		$notification->title = $text;
		if($flags) $notification->setFlags($flags); 
		return $notification;
	}

	/**
	 * Record an error notification
	 *
	 * @param string $text
	 * @param int|bool $flags See Notification flags
	 * @return Notification
	 *
	 */
	public function error($text, $flags = 0) {
		$notification = $this->getNew(Notification::flagError, true);
		$notification->title = $text;
		if($flags) $notification->setFlags($flags);
		return $notification;
	}
	
	/**
	 * Return all error Notifications
	 *
	 * @param string|array $options One or more of array elements or space separated string of:
	 * 	first: only first item will be returned (string)
	 * 	last: only last item will be returned (string)
	 * 	clear: clear out all items that are returned from this method (includes both local and global)
	 * @return Notices|string Array of NoticeError error messages or string if last, first or str option was specified.
	 *
	 */
	public function errors($options = array()) {
		return parent::errors($options); 
	}

	/**
	 * Return warnings recorded by this object
	 *
	 * @param string|array $options One or more of array elements or space separated string of:
	 * 	first: only first item will be returned (string)
	 * 	last: only last item will be returned (string)
	 * 	clear: clear out all items that are returned from this method (includes both local and global)
	 * @return Notices|string Array of NoticeError error messages or string if last, first or str option was specified.
	 *
	 */
	public function warnings($options = array()) {
		return parent::warnings($options); 
	}

	/**
	 * Return messages recorded by this object
	 *
	 * @param string|array $options One or more of array elements or space separated string of:
	 * 	first: only first item will be returned (string)
	 * 	last: only last item will be returned (string)
	 * 	clear: clear out all items that are returned from this method (includes both local and global)
	 * 	errors: returns errors rather than messages.
	 * 	warnings: returns warnings rather than messages.
	 * @return Notices|string Array of NoticeError error messages or string if last, first or str option was specified.
	 *
	 */
	public function messages($options = array()) {
		
		if(!is_array($options)) $options = explode(' ', strtolower($options));
		
		if(in_array('errors', $options)) $type = 'error';
			else if(in_array('warnings', $options)) $type = 'warning';
			else $type = 'message';
		
		$clear = in_array('clear', $options);
		
		$value = $this->wire(new NotificationArray($this->page));
		
		foreach($this as $notification) {
			if(!$notification->is($type)) continue;
			/** @var Notification $notification */
			$value->add($notification);
			if($clear) $this->remove($notification); // clear global
		}
		
		if(in_array('first', $options)) $value = $clear ? $value->shift() : $value->first();
			else if(in_array('last', $options)) $value = $clear ? $value->pop() : $value->last();
		
		return $value;
	}
	
}

