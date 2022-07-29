<?php namespace ProcessWire;

/**
 * ProcessWire Fieldtype Comments > Comment
 *
 * Class that contains an individual comment.
 * 
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 * 
 * @property int $id
 * @property int $parent_id 
 * @property string $text 
 * @property string|null $textFormatted Text value formatted for output (runtime use only, must be set manually)
 * @property int $sort 
 * @property int $status
 * @property int|null $prevStatus
 * @property int $flags 
 * @property int $created 
 * @property string $email 
 * @property string $cite 
 * @property string $website 
 * @property string $ip 
 * @property string $user_agent 
 * @property int $created_users_id 
 * @property string $code 
 * @property string $subcode 
 * @property int $upvotes 
 * @property int $downvotes 
 * @property int $stars
 * @property null|bool $isNew Was this comment added in this request? (since 3.0.169)
 * @property null|string $approvalNote Runtime approval note for newly added comment, internal use (since 3.0.169)
 * @property-read Comment|null $parent Parent comment when depth is enabled or null if no parent (since 3.0.149)
 * @property-read CommentArray $parents All parent comments (since 3.0.149)
 * @property-read CommentArray $children Immediate child comments (since 3.0.149)
 * @property-read int $depth Current comment depth (since 3.0.149)
 * @property-read bool $loaded True when comment is fully loaded from DB (since 3.0.149)
 * @property-read int $numChildren Number of children with no exclusions. See and use numChildren() method for more options. (since 3.0.154)
 * @property-read User $createdUser User that created the comment
 *
 */

class Comment extends WireData {

	/**	
	 * Status for Comment identified as spam
	 *
	 */
	const statusSpam = -2; 

	/**
	 * Status for Comment pending review
	 *
	 */
	const statusPending = 0; 

	/**
	 * Status for Comment that's been approved
	 *	
	 */
	const statusApproved = 1;

	/**
	 * Status for comment that's been approved and featured
	 * 
	 */
	const statusFeatured = 2;

	/**
	 * Status for Comment to indicate pending deletion
	 *
	 */
	const statusDelete = 999;

	/**
	 * Flag to indicate author of this comment wants to be notified of replies to their comment
	 * 
	 */
	const flagNotifyReply = 2;

	/**
	 * Flag to indicate author of this comment wants to be notified of all comments on page
	 *
	 */
	const flagNotifyAll = 4;
	
	/**
	 * Flag to indicate author of this comment wants notifications and request confirmed by double opt in
	 *
	 */
	const flagNotifyConfirmed = 8;

	/**
	 * Flag to indicate comment is queued for notifications to be sent later by 3rd party implementation
	 * 
	 */
	const flagNotifyQueue = 16;

	/**
	 * Max bytes that a Comment may use
	 *
	 */
	const maxCommentBytes = 81920; // 80k

	/**
	 * Previous Comment status, when it has been changed
	 * 
	 * @var int|null
	 *	
	 */ 
	protected $prevStatus = null;

	/**
	 * Page this comment lives on
	 * 
	 * @var null|Page
	 * 
	 */
	protected $page = null;

	/**
	 * Field this comment is for
	 * 
	 * @var null|Field|CommentField
	 * 
	 */
	protected $field = null;

	/**
	 * Is this comment finished loading?
	 * 
	 * @var bool
	 * 
	 */
	protected $loaded = false;

	/**
	 * Cached parent from parent() method
	 * 
	 * @var null
	 * 
	 */
	protected $_parent = null;

	/**
	 * Quiet mode, when true actions like notification emails aren't triggered when applicable
	 * 
	 * @var bool
	 * 
	 */
	protected $quiet = false;

	/**
	 * @var CommentArray|null
	 * 
	 */
	protected $pageComments = null;

	/**
	 * Cache of comment text for getformattedCommentText method
	 * 
	 * @var string|null
	 * 
	 */
	protected $formattedCommentText = null;

	/**
	 * Cache of options for getformattedCommentText method
	 * 
	 * @var array|null
	 * 
	 */
	protected $formattedCommentOptions = null;

	/**
	 * @var int|null
	 * 
	 */
	protected $numChildren = null;

	/**	
	 * Construct a Comment and set defaults
	 *
	 */
	public function __construct() {
		$this->set('id', 0); 
		$this->set('parent_id', 0); 
		$this->set('text', ''); 
		$this->set('sort', 0); 
		$this->set('status', self::statusPending); 
		$this->set('flags', 0); 
		$this->set('created', time()); 
		$this->set('email', ''); 
		$this->set('cite', ''); 
		$this->set('website', ''); 
		$this->set('ip', ''); 
		$this->set('user_agent', ''); 
		$this->set('created_users_id', 40);
		$this->set('code', ''); // approval code
		$this->set('subcode', ''); // subscriber code (for later user modifications to comment)
		$this->set('upvotes', 0); 
		$this->set('downvotes', 0);
		$this->set('stars', 0);
		$this->set('meta', array()); 
		parent::__construct();
	}

	/**
	 * Wired to API
	 * 
	 */
	public function wired() {
		$this->set('created_users_id', $this->wire()->config->guestUserPageID); 
		parent::wired();
	}

	/**
	 * Get property
	 * 
	 * @param string $key
	 * @return mixed
	 * 
	 */
	public function get($key) {
		
		if($key === 'user' || $key === 'createdUser') {
			return $this->wire()->users->get($this->created_users_id); 

		} else if($key === 'gravatar') {
			return $this->gravatar();
		
		} else if($key === 'page') {
			return $this->getPage();

		} else if($key === 'field') {
			return $this->getField();
			
		} else if($key === 'parent') {
			return $this->parent();

		} else if($key === 'parents') {
			return $this->parents();
			
		} else if($key === 'children') {
			return $this->children();
			
		} else if($key === 'url') {
			return $this->url();
			
		} else if($key === 'httpUrl' || $key == 'httpURL') {
			return $this->httpUrl();
			
		} else if($key === 'editUrl' || $key == 'editURL') {
			return $this->editUrl();
			
		} else if($key === 'prevStatus' || $key === 'statusPrevious') {
			return $this->prevStatus;
			
		} else if($key === 'textFormatted') {
			return $this->getFormattedCommentText();
			
		} else if($key === 'depth') {
			return $this->depth();
			
		} else if($key === 'loaded') {
			return $this->loaded;
			
		} else if($key === 'numChildren') {
			return $this->numChildren();
		}

		return parent::get($key); 
	}

	/**
	 * Same as get() but with output formatting applied
	 * 
	 * Note that we won't apply this to get() when $page->outputFormatting is active
	 * in order for backwards compatibility with older installations. 
	 *
	 * @param string $key One of: text, cite, email, user_agent, website
	 * @param array $options
	 * @return string
	 * 
	 */
	public function getFormatted($key, array $options = array()) {
		
		$value = trim($this->get($key)); 
		$sanitizer = $this->wire()->sanitizer;
		
		if($key === 'text') {
			$value = $this->getFormattedCommentText($options);
		} else if(in_array($key, array('cite', 'email', 'user_agent', 'website'))) {
			$value = $sanitizer->entities($value);
		} else {
			$value = $sanitizer->entities1($value);
		}
		
		return $value; 
	}

	/**
	 * Get comment text as formatted string
	 * 
	 * Note that the default options behavior is to return comment text with paragraphs split by `</p><p>`
	 * but without the first `<p>` and last `</p>` since it is assumed these will be the markup you wrap
	 * the comment in. If you want it to include the wrapping `<p>…</p>` tags then specify true for the
	 * `wrapParagraph` option in the `$options` argument. 
	 * 
	 * @param array $options
	 *  - `useParagraphs` (bool): Convert newlines to paragraphs? (default=true)
	 *  - `wrapParagraph` (bool): Use wrapping <p>…</p> tags around return value? (default=false)
	 *  - `useLinebreaks` (bool): Convert single newlines to <br> tags? (default=true)
	 * @return string
	 * @since 3.0.169
	 * 
	 */
	public function getFormattedCommentText(array $options = array()) {
		
		$defaults = array(
			'useParagraphs' => true,
			'wrapParagraph' => false,
			'useLinebreaks' => true,
		);
		
		$options = array_merge($defaults, $options);
		
		if($this->formattedCommentText !== null) { 
			if($this->formattedCommentOptions === null || $options == $this->formattedCommentOptions) {
				return $this->formattedCommentText;
			}
		}
		
		$sanitizer = $this->wire()->sanitizer;
		$value = trim($this->get('text')); 
		$textformatters = null;
		
		// $textformatters = $this->field ? $this->field->textformatters : null; // @todo
		
		if(is_array($textformatters) && count($textformatters)) {
			// output formatting with specified textformatters (@todo)
			// NOT CURRENTLY ACTIVE
			$modules = $this->wire()->modules;
			$value = strip_tags($value);
			foreach($textformatters as $name) {
				/** @var Textformatter $textformatter */
				if(!$textformatter = $modules->get($name)) continue;
				$textformatter->formatValue($this->page, $this->field, $value);
			}
		} else {
			// default output formatting
			$value = $sanitizer->entities($value);
			while(strpos($value, "\n\n\n") !== false) $value = str_replace("\n\n\n", "\n\n", $value);
			if($options['useParagraphs']) {
				$value = str_replace("\n\n", "</p><p>", $value);
			}
			if($options['wrapParagraph']) {
				$value = "<p>$value</p>";
			}
			$linebreak = $options['useLinebreaks'] ? "<br />" : " ";
			$value = str_replace("\n", $linebreak, $value);
		}
		
		$this->formattedCommentText = $value;
		$this->formattedCommentOptions = $options;

		return $value;
	}

	/**
	 * Set property
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return self|WireData
	 * 
	 */
	public function set($key, $value) {

		if(in_array($key, array('id', 'parent_id', 'status', 'flags', 'pages_id', 'created', 'created_users_id'))) {
			$value = (int) $value;
		} else if($key === 'text') {
			$value = $this->cleanCommentString($value);
			$this->formattedCommentText = null;
			$this->formattedCommentOptions = null;
		} else if($key === 'textFormatted') {
			$this->formattedCommentText = $value;
			$this->formattedCommentOptions = null;
			return $this;
		} else if($key === 'cite') {
			$value = str_replace(array("\r", "\n", "\t"), ' ', substr(strip_tags($value), 0, 128));
		} else if($key === 'email') {
			$value = $this->sanitizer->email($value);
		} else if($key === 'ip') {
			$value = filter_var($value, FILTER_VALIDATE_IP);
		} else if($key === 'user_agent') {
			$value = str_replace(array("\r", "\n", "\t"), ' ', substr(strip_tags($value), 0, 255));
		} else if($key === 'website') {
			$value = $this->wire('sanitizer')->url($value, array('allowRelative' => false, 'allowQuerystring' => false));
		} else if($key === 'upvotes' || $key === 'downvotes') {
			$value = (int) $value;
		} else if($key === 'numChildren') {
			$this->numChildren = (int) $value; 
			return $this;
		} else if($key === 'meta') {
			if(!is_array($value)) return $this; // array required for meta
		}
			
		// save the state so that modules can identify when a comment that was identified as spam 
		// is then set to not-spam, or when a misidentified 'approved' comment is actually spam
		if($key === 'status' && $this->loaded) {
			$this->prevStatus = $this->status;
		}

		if($key === 'stars') {
			$value = (int) $value;
			if($value < 1) $value = 0;
			if($value > 5) $value = 5; 
		}
		
		if($key === 'parent_id' && parent::get('parent_id') != $value) {
			// reset a cached parent value, if present
			$this->_parent = null; 
		}

		return parent::set($key, $value); 
	}

	/**
	 * Clean a comment string by issuing several filters
	 * 
	 * @param string $str
	 * @return string
	 *
	 */
	public function cleanCommentString($str) {
		$str = strip_tags(trim($str)); 
		if(strlen($str) > self::maxCommentBytes) $str = substr($str, 0, self::maxCommentBytes); 
		$str = str_replace(array("\r\n", "\r"), "\n", $str); 
		if(strpos($str, "\n\n\n") !== false) $str = preg_replace('{\n\n\n+}', "\n\n", $str); 
		return $str; 
	}

	/**
	 * String value of a Comment is it's database ID
	 *
	 */
	public function __toString() {
		return "$this->id"; 
	}

	/**
	 * Returns true if the comment is approved and thus appearing on the site
	 *
	 */
	public function isApproved() {
		return $this->status >= self::statusApproved; 
	}

	/**
	 * Returns a URL to this user's gravatar image (static version, use non-static gravatar() function unless you specifically need static)
	 *
	 * @param string $email 
	 * @param string $rating Gravatar rating, one of [ g | pg | r | x ], default is g.
	 * @param string $imageset Gravatar default imageset, one of [ 404 | mm | identicon | monsterid | wavatar | retro | blank ], default is mm.
	 * @param int $size Gravatar image size, default is 80. 
	 * @return string
	 *
	 */
	public static function getGravatar($email, $rating = 'g', $imageset = 'mm', $size = 80) {
		if(!in_array($rating, array('g', 'pg', 'r', 'x'), true)) $rating = 'g';
		if(empty($imageset)) $imageset = 'mm';
		$size = (int) $size; 
		$http = wire()->config->https ? 'https' : 'http';
		$url = 	"$http://www.gravatar.com/avatar/" . 
			md5(strtolower(trim($email))) . 
			"?s=$size" . 
			"&d=" . htmlentities($imageset) . 
			"&r=$rating";
		return $url;	
	}

	/**
	 * Returns a URL to this user's gravatar image
	 *
	 * @param string $rating Gravatar rating, one of [ g | pg | r | x ], default is g.
	 * @param string $imageset Gravatar default imageset, one of [ 404 | mm | identicon | monsterid | wavatar | retro | blank ], default is mm.
	 * @param int $size Gravatar image size, default is 80. 
	 * @return string
	 *
	 */
	public function gravatar($rating = 'g', $imageset = 'mm', $size = 80) {
		return self::getGravatar($this->email, $rating, $imageset, $size); 
	}

	/**
	 * Set Page that this Comment belongs to
	 * 
	 * @param Page $page
	 * 
	 */
	public function setPage(Page $page) {
		$this->page = $page; 
	}

	/**
	 * Set Field that this Comment belongs to
	 * 
	 * @param Field $field
	 * 
	 */
	public function setField(Field $field) {
		$this->field = $field; 
	}

	/**
	 * Get Page that this Comment belongs to
	 * 
	 * @return null|Page
	 * 
	 */
	public function getPage() { 
		return $this->page;
	}

	/**
	 * Get Field that this Comment belongs to
	 * 
	 * @return null|Field|CommentField
	 * 
	 */
	public function getField() { 
		return $this->field;
	}

	/**
	 * Set whether Comment is fully loaded and ready for use
	 * 
	 * To get loaded state access the $loaded property of the Comment object. 
	 * 
	 * #pw-internal
	 * 
	 * @param bool $loaded
	 * 
	 */
	public function setIsLoaded($loaded) {
		$this->loaded = (bool) $loaded;
	}
	
	/**
	 * Get current comment depth
	 * 
	 * @return int
	 * @since 3.0.149
	 * 
	 */
	public function depth() {
		return count($this->parents());
	}

	/**
	 * Return the parent comment, if applicable
	 * 
	 * @return Comment|null
	 * 
	 */
	public function parent() {
		if(!is_null($this->_parent)) return $this->_parent;
		$parent_id = $this->parent_id; 
		if(!$parent_id) return null;
		$field = $this->getField();
		$comments = $this->getPage()->get($field->name); // no getPageComments() call intentional
		$parent = null;
		foreach($comments as $c) {
			if($c->id != $parent_id) continue;
			$parent = $c;
			break;
		}
		$this->_parent = $parent; 
		return $parent;
	}

	/**
	 * Get CommentArray of all parent comments for this one 
	 * 
	 * Order is closest parent to furthest parent
	 * 
	 * @return CommentArray
	 * @since 3.0.149
	 * 
	 */
	public function parents() {
		if(!$this->parent_id) return $this->wire(new CommentArray());
		$parents = $this->getPageComments()->makeNew();
		$parent = $this->parent();
		while($parent && $parent->id) {
			$parents->add($parent);
			$parent = $parent->parent();
		}
		return $parents;
	}

	/**
	 * Return children comments, if applicable
	 * 
	 * @return CommentArray
	 * 
	 */
	public function children() {
		/** @var CommentArray $comments */
		// $comments = $this->getPageComments();
		$page = $this->getPage();
		$field = $this->getField();
		$comments = $page->get($field->name);
		$children = $comments->makeNew();
		$children->setPage($this->getPage());
		if($field) $children->setField($this->getField()); 
		$id = $this->id; 
		foreach($comments as $comment) {
			/** @var Comment $comment */
			if(!$comment->parent_id) continue;
			if($comment->parent_id == $id) $children->add($comment);
		}
		return $children;
	}

	/**
	 * Return number of children (replies) for this comment
	 * 
	 * ~~~~~
	 * $qty = $comment->numChildren([ 'minStatus' => Comment::statusApproved ]); 
	 * ~~~~~
	 * 
	 * @param array $options Limit return value by specific properties (below):
	 *  - `status` (int): Specify Comment::status* constant to include only this status
	 *  - `minStatus` (int): Specify Comment::status* constant to include only comments with at least this status
	 *  - `maxStatus` (int): Specify Comment::status* constant or include only comments up to this status
	 *  - `minCreated` (int): Minimum created unix timestamp
	 *  - `maxCreated` (int): Maximum created unix timestamp
	 *  - `stars` (int): Number of stars to match (1-5)
	 *  - `minStars` (int): Minimum number of stars to match (1-5)
	 *  - `maxStars` (int): Maximum number of stars to match (1-5)
	 * @return int
	 * @since 3.0.153
	 * 
	 */
	public function numChildren(array $options = array()) {
		if(empty($options) && $this->numChildren !== null) return $this->numChildren;
		$options['parent'] = $this->id;
		$field = $this->getField();
		if(!$field) return null;
		/** @var FieldtypeComments $fieldtype */
		$fieldtype = $field->type;
		if(!$fieldtype) return 0;
		$numChildren = $fieldtype->getNumComments($this->getPage(), $field, $options); 
		if(empty($options)) $this->numChildren = $numChildren;
		return $numChildren;
	}

	/**
	 * Are child comments (replies) allowed?
	 * 
	 * @return bool
	 * @since 3.0.204
	 * 
	 */
	public function allowChildren() {
		$field = $this->getField();
		if(!$field) return false;
		$maxDepth = $field->depth;
		if(!$maxDepth) return false;
		return $this->depth() < $maxDepth;
	}

	/**
	 * Does this comment have the given child comment?
	 * 
	 * @param int|Comment $comment Comment or Comment ID
	 * @param bool $recursive Check all descending children recursively? Use false to check only direct children. (default=true)
	 * @return bool
	 * @since 3.0.149
	 * 
	 */
	public function hasChild($comment, $recursive = true) {
		
		$id = $comment instanceof Comment ? $comment->id : (int) $comment;
		$has = false;
		$children = $this->children();
	
		// direct children
		foreach($children as $child) {
			/** @var Comment $child */
			if($child->id == $id) $has = true;
			if($has) break;
		}	
	
		if($has || !$recursive) return $has;
	
		// recursive children
		foreach($children as $child) {
			/** @var Comment $child */
			if($child->hasChild($id, true)) $has = true;
			if($has) break;
		}
		
		return $has;
	}	

	/**
	 * Get CommentArray that holds all the comments for the current Page/Field
	 * 
	 * #pw-internal
	 * 
	 * @param bool $autoDetect Autodetect from Page and Field if not already set? (default=true)
	 * @return CommentArray|null
	 * 
	 */
	public function getPageComments($autoDetect = true) {
		
		$pageComments = $this->pageComments;
		$page = $this->getPage();
		$field = $this->getField();
		
		if($pageComments && $autoDetect) {
			// check if the CommentsArray doesn't share the same Page/Field as the Comment
			// this could be the case if CommentsArray was from search results rather than Page value
			$pageCommentsPage = $pageComments->getPage();
			$pageCommentsField = $pageComments->getField();
			if($page && $pageCommentsPage && "$page" !== "$pageCommentsPage") {
				$pageComments = null;
			} else if($field && $pageCommentsField && "$field" !== "$pageCommentsField") {
				$pageComments = null;
			}
		}
		
		if(!$pageComments && $autoDetect) {
			if($page && $field) {
				$pageComments = $page->get($field->name);
				$this->pageComments = $pageComments;
			}
		}
		
		return $pageComments;
	}

	/**
	 * Set the CommentArray that holds all comments for the curent Page/Field
	 * 
	 * #pw-internal
	 * 
	 * @param CommentArray $pageComments
	 * 
	 */
	public function setPageComments(CommentArray $pageComments) {
		$this->pageComments = $pageComments;
	}

	/**
	 * Render stars markup
	 *
	 * @param array $options See CommentArray::renderStars for $options
	 * @return string
	 *
	 */
	public function renderStars(array $options = array()) {
		$field = $this->getField();
		/** @var CommentArray $comments */
		$comments = $this->getPage()->get($field->name);
		if(!isset($options['stars'])) $options['stars'] = $this->stars;
		if(!isset($options['blank'])) $options['blank'] = false;
		return $comments->renderStars(false, $options);
	}

	/**
	 * Get or set quiet mode
	 * 
	 * When quiet mode is active, comment additions/changes don't trigger notifications and such. 
	 * 
	 * @param bool $quiet Specify only if setting
	 * @return bool The current quiet mode
	 * 
	 */
	public function quiet($quiet = null) {
		if(is_bool($quiet)) $this->quiet = $quiet; 
		return $this->quiet; 
	}

	/**
	 * Return URL to view comment
	 * 
	 * @param bool $http
	 * @return string
	 * 
	 */
	public function url($http = false) {
		if($this->page && $this->page->id) {
			$url = $http ? $this->page->httpUrl() : $this->page->url;
		} else {
			$config = $this->wire()->config;
			$url = $http ? $config->urls->httpRoot : $config->urls->root;
		}
		return $url . "#Comment$this->id";
	}

	/**
	 * Return full http URL to view comment
	 * 
	 * @return string
	 * 
	 */
	public function httpUrl() {
		return $this->url(true);
	}

	/**
	 * Return URL to edit comment
	 * 
	 * @return string
	 * 
	 */
	public function editUrl() {
		if(!$this->page || !$this->page->id || !$this->id) return '';
		if(!$this->field) return '';
		if($this->wire()->modules->isInstalled('ProcessCommentsManager')) {
			return $this->wire()->config->urls->admin . "setup/comments/list/{$this->field->name}/?id=$this->id";  
		} else {
			return $this->page->editUrl() . "?field={$this->field->name}#CommentsAdminItem$this->id";
		}
	}
	
	/**
	 * Set meta data (custom fields for comments)
	 *
	 * To set multiple properties at once specify an associative array for $key and omit $value.
	 *
	 * @param string|array $key Property name to set or assoc array of them
	 * @param null|string|array|int|float|mixed $value Value to set for $key or omit of you used an array.
	 * @return self
	 * @since 3.0.203
	 *
	 */
	public function setMeta($key, $value = null) {
		$meta = parent::get('meta');
		$changed = false;

		if(is_array($key)) {
			// set multiple properties
			$changed = $meta != $key;
			if($changed) $meta = count($meta) ? array_merge($meta, $key) : $key;

		} else if($value === null) {
			if($key === '*') {
				// remove all
				$changed = count($meta) > 0;
				$meta = array();
			} else if(isset($meta[$key])) {
				// remove property
				unset($meta[$key]);
				$changed = true;
			}

		} else {
			// set property
			$changed = !isset($meta[$key]) || $meta[$key] !== $value;
			$meta[$key] = $value;
		}

		parent::set('meta', $meta);
		if($changed) $this->trackChange('meta');

		return $this;
	}

	/**
	 * Get meta data property value (custom fields for comments)
	 * 
	 * Note: values returned are exactly as they were set and do not go through any runtime
	 * formatting for HTML entities or anything like that. Be sure to provide your own formatting
	 * where necessary. 
	 *
	 * @param null|string $key Name of property to get
	 * @return string|array|int|float|mixed|null Returns value or null if not found
	 * @since 3.0.203
	 *
	 */
	public function getMeta($key = null) {
		$meta = parent::get('meta');
		if(empty($key)) return $meta;
		if(isset($meta[$key])) return $meta[$key];
		return null;
	}

	/**
	 * Remove given meta data property or '*' to remove all
	 *
	 * @param string $key
	 * @return self
	 * @since 3.0.203
	 *
	 */
	public function removeMeta($key) {
		return $this->setMeta($key);
	}

	/**
	 * Get or set meta data property
	 * 
	 * @param string|array $key Property to get/set or omit to get all.
	 * @param mixed|null $value Value to set for given property ($key) or omit if getting.
	 * @return array|string|int|mixed Returns value for $key or null if it does not exist. Returns array when getting all.
	 * @since 3.0.203
	 * 
	 */
	public function meta($key = null, $value = null) {
		if($key === null) {
			// get all
			$value = $this->getMeta();
		} else if($value === null) {
			// get one property
			if(!is_string($key)) throw new WireException('Expected string for $key to Comment::meta()');
			$value = $this->getMeta($key);
		} else {
			// set one property
			$this->setMeta($key, $value);
		}
		return $value;
	}

}



