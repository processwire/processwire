<?php namespace ProcessWire;

/**
 * ProcessWire FieldtypeComments > CommentArray
 *
 * Maintains an array of multiple Comment instances.
 * Serves as the value referenced when a FieldtypeComment field is reference from a Page.
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 *
 */

class CommentArray extends PaginatedArray implements WirePaginatable {

	/**
	 * Page that owns these comments, required to use the renderForm() or getCommentForm() methods. 
	 * 
	 * @var Page|null
	 *
	 */
	protected $page = null; 

	/**
	 * Field object associated with this CommentArray
	 * 
	 * @var Field|null
	 *
	 */
	protected $field = null;

	/**
	 * Total number of comments, including those here and others that aren't, but may be here in pagination.
	 * 
	 * @var int
	 * 
	 */
	protected $numTotal = 0;

	/**
	 * If this CommentArray is a partial representation of a larger set, this will contain the max number 
	 * of comments allowed to be present/loaded in the CommentArray at once.
	 *
	 * May vary from count() when on the last page of a result set.
	 * As a result, paging routines should refer to their own itemsPerPage rather than count().
	 * Applicable for paginated result sets. This number is not enforced for adding items to this CommentArray.
	 *
	 * @var int
	 * 
	 */
	protected $numLimit = 0;

	/**
	 * If this CommentArray is a partial representation of a larger set, this will contain the starting result 
	 * number if previous results preceded it.
	 *
	 * @var int
	 * 
	 */
	protected $numStart = 0;

	/**
	 * Per the WireArray interface, is the item a Comment
	 * 
	 * @param Wire|Comment $item
	 * @return bool
	 *
	 */
	public function isValidItem($item) {
		if($item instanceof Comment) {
			if($this->page) $item->setPage($this->page); 
			if($this->field) $item->setField($this->field); 
			return true; 
		} else {
			return false;
		}
	}

	/**
	 * Provides the default rendering of a comment list, which may or may not be what you want
 	 *
	 * @param array $options
	 * @return string
	 * @see CommentList class and override it to serve your needs
	 *
	 */
	public function render(array $options = array()) {
		$commentList = $this->getCommentList($options); 
		return $commentList->render();
	}

	/**
	 * Make a new blank CommentArray setup for the same Page/Field as the one it is called on
	 * 
	 * @return CommentArray
	 * 
	 */
	public function makeNew() {
		/** @var CommentArray $a */
		$a = parent::makeNew();
		if($this->page) $a->setPage($this->page);
		if($this->field) $a->setField($this->field);
		return $a;
	}

	/**
	 * Provides the default rendering of a comment form, which may or may not be what you want
 	 *
	 * @param array $options
	 * @return string
	 * @see CommentForm class and override it to serve your needs
	 *
	 */
	public function renderForm(array $options = array()) {
		$defaultOptions = array(
			'depth' => ($this->field ? (int) $this->field->get('depth') : 0)
			);
		$options = array_merge($defaultOptions, $options); 
		$form = $this->getCommentForm($options); 
		return $form->render();
	}

	/**
	 * Render all comments and a comments form below it
	 * 
	 * @param array $options
	 * @return string
	 * 
	 */
	public function renderAll(array $options = array()) {
		return $this->render($options) . $this->renderForm($options); 
	}

	/**
	 * Return instance of CommentList object
	 * 
	 * @param array $options See CommentList::$options for details
	 * @return CommentList
	 *
	 */
	public function getCommentList(array $options = array()) {
		$field = $this->field;
		if($field) {
			$defaults = array(
				'useGravatar' => $field->get('useGravatar'),
				'useVotes' => $field->get('useVotes'),
				'useStars' => $field->get('useStars'),
				'depth' => $field->get('depth'),
				'dateFormat' => $field->get('dateFormat'),
			);
		} else {
			$defaults = array(
				'dateFormat' => 'relative'
			);
		}
		$options = array_merge($defaults, $options);
		return $this->wire(new CommentList($this, $options)); 	
	}

	/**
	 * Return instance of CommentForm object
	 *
	 * @param array $options
	 * @return CommentForm
	 * @throws WireException
	 * 
	 */
	public function getCommentForm(array $options = array()) {
		if(!$this->page) throw new WireException("You must set a page to this CommentArray before using it i.e. \$ca->setPage(\$page)"); 
		return $this->wire(new CommentForm($this->page, $this, $options)); 
	}

	/**
	 * Set the page that these comments are on 
	 * 
	 * @param Page $page
	 *
	 */ 
	public function setPage(Page $page) {
		$this->page = $page; 
	}

	/**
	 * Set the Field that these comments are on 
	 * 
	 * @param Field $field
	 *
	 */ 
	public function setField(Field $field) {
		$this->field = $field; 
	}
	
	/**
	 * Get the page that these comments are on
	 * 
	 * @return Page
	 *
	 */
	public function getPage() { 
		return $this->page; 
	}

	/**
	 * Get the Field that these comments are on
	 * 
	 * @return Field
	 *
	 */
	public function getField() {
		return $this->field;
	}

	/**
	 * Get the total number of comments
	 *
	 * Used for pagination.
	 *
	 * @return int
	 *
	 */
	public function getTotal() {
		if(!$this->numTotal) return $this->count();
		return $this->numTotal;
	}

	/**
	 * Get the imposed limit on number of comments.
	 *
	 * If no limit set, then return number of comments currently here.
	 *
	 * Used for pagination.
	 *
	 * @return int
	 *
	 */
	public function getLimit() {
		if($this->numLimit) return $this->numLimit;
			else return $this->count();
	}

	/**
	 * Is the given CommentArray identical to this one?
	 *
	 * @param WireArray $items
	 * @param bool|int $strict
	 * @return bool
	 *
	 */
	public function isIdentical(WireArray $items, $strict = true) {
		$isIdentical = parent::isIdentical($items, $strict);
		if($isIdentical && $strict && $items instanceof CommentArray) {
			/** @var CommentArray $items */
			if(((string) $this->getPage()) != ((string) $items->getPage())) return false;
			if(((string) $this->getField()) != ((string) $items->getField())) return false;
		}
		return $isIdentical;
	}

	/**
	 * Get an average of all star ratings for all comments in this CommentsArray
	 *
	 * @param bool $allowPartial Allow partial stars? If true, returns a float. If false, returns int.
	 * @param bool $getCount If true, this method returns an array(stars, count) where count is number of ratings.
	 * @return int|float|false|array Returns false for stars value if no ratings yet.
	 *
	 */
	public function stars($allowPartial = true, $getCount = false) {
		$total = 0;
		$count = 0;
		$stars = false;
		foreach($this as $comment) {
			if(!$comment->stars) continue;
			$total += $comment->stars;
			$count++;
		}
		if($count) {
			$stars = $total / $count;
			$stars = $allowPartial ? round($stars, 2) : (int) round($stars);
		}
		if($getCount) return array($stars, $count);
		return $stars;
	}
	
	/**
	 * Render combined star rating for all comments in this CommentsArray
	 *
	 * @param bool $showCount Specify true to include how many ratings the average is based on
	 * @param array $options Overrides of stars and/or count, see $defaults in method
	 * @return string
	 *
	 */
	public function renderStars($showCount = false, $options = array()) {
		$defaults = array(
			'stars' => null, // optionally override the combined stars value (stars and count must both be specified)
			'count' => null, // optionally override the combined count value (stars and count must both be specified)
			'blank' => true, // return blank string if no ratings yet?
			'partials' => true, // allow partial stars?
			'schema' => '', // may be 'rdfa', 'microdata' or blank. Used only if showCount=true. 
			'input' => false, // allow input? (may not be combined with 'partials' option)
		);
		$options = array_merge($defaults, $options);
		if(!is_null($options['stars'])) {
			$stars = $options['stars'];
			$count = (int) $options['count'];
		} else {
			list($stars, $count) = $this->stars($options['partials'], true);
		}
		if(!$count && $options['blank']) return '';
		$commentStars = new CommentStars();
		$out = $commentStars->render($stars, $options['input']);
		if($showCount) $out .= $commentStars->renderCount((int) $count, $stars, $options['schema']);
		return $out;
	}
	
	/**
	 * Track an item added
	 *
	 * @param Comment $item
	 * @param int|string $key
	 *
	 */
	protected function trackAdd($item, $key) {
		parent::trackAdd($item, $key);
		if(!$item->getPageComments(false)) $item->setPageComments($this);
	}
}


