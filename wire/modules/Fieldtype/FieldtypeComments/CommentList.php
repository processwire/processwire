<?php namespace ProcessWire;

/**
 * ProcessWire CommentListInterface and CommentList
 *
 * CommentListInterface defines an interface for CommentLists.
 * CommentList provides the default implementation of this interface. 
 *
 * Use of these is not required. These are just here to provide output for a FieldtypeComments field. 
 * Typically you would iterate through the field and generate your own output. But if you just need
 * something simple, or are testing, then this may fit your needs. 
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 *
 */

/*
 * CommentListInterface defines an interface for CommentLists.
 *
 */
interface CommentListInterface {
	public function __construct(CommentArray $comments, $options = array()); 
	public function render();
	public function renderItem(Comment $comment);
}

/**
 * CommentList provides the default implementation of the CommentListInterface interface. 
 * 
 * @method void renderItemReady(Comment $comment, $depth)
 *
 */
class CommentList extends Wire implements CommentListInterface {
	
	/**
	 * Reference to CommentsArray provided in constructor
	 *
	 */
	protected $comments = null;

	/**
	 * Current comment being rendered (or last one rendered) 
	 * 
	 * @var Comment|null
	 * 
	 */
	protected $comment = null;

	/**
	 * @var Page
	 * 
	 */
	protected $page;

	/**
	 * @var Field
	 * 
	 */
	protected $field;

	/**
	 * @var bool|null
	 * 
	 */
	protected $editable = null;

	/**
	 * Default options that may be overridden from constructor
	 *
	 */
	protected $options = array(
		'headline' => '', 	// '<h3>Comments</h3>', 
		'commentHeader' => '', 	// 'Posted by {cite} on {created} {stars}',
		'commentFooter' => '', 
		'dateFormat' => '', 	// 'm/d/y g:ia', 
		'encoding' => 'UTF-8', 
		'admin' => false, 	// shows unapproved comments if true
		'useGravatar' => '', 	// enable gravatar? if so, specify maximum rating: [ g | pg | r | x ] or blank = disable gravatar
		'useGravatarImageset' => 'mm',	// default gravatar imageset, specify: [ 404 | mm | identicon | monsterid | wavatar ]
		'usePermalink' => false, // @todo
		'useVotes' => 0,
		'useStars' => 0,
		'useRepliesLink' => false, 
		'upvoteFormat' => '&uarr;{cnt}',
		'downvoteFormat' => '&darr;{cnt}', 
		'depth' => 0,
		'replyLabel' => 'Reply',
		'repliesLabelOne' => '1 Reply',
		'repliesLabelMulti' => '%d Replies',
	);

	/**
	 * Other placeholders to populate 
	 * 
	 * @var array
	 * 
	 */
	protected $placeholders = array(
		// 'foo' => 'Bar' // replaces "{foo}" with "Bar"
	);

	/**
	 * Number of replies indexed by comment ID, populated by getReplies()
	 * 
	 * @var array
	 * 
	 */
	protected $numReplies = array(); 

	/**
	 * Construct the CommentList
	 *
	 * @param CommentArray $comments 
	 * @param array $options Options that may override those provided with the class (see CommentList::$options)
	 *
	 */
	public function __construct(CommentArray $comments, $options = array()) {

		$h3 = $this->_('h3'); // Headline tag
		$this->options['headline'] = "<$h3>" . $this->_('Comments') . "</$h3>"; // Header text
		$this->options['replyLabel'] = $this->_('Reply');
		$this->options['repliesLabelOne'] = $this->_('1 Reply');
		$this->options['repliesLabelMulti'] = $this->_('%d Replies'); 
		
		if(empty($options['commentHeader'])) {
			if(empty($options['dateFormat'])) {
				$this->options['dateFormat'] = 'relative';
			}
		} else {
			//$this->options['commentHeader'] = $this->('Posted by {cite} on {created}'); // Comment header // Include the tags {cite} and {created}, but leave them untranslated
			if(empty($options['dateFormat'])) {
				$this->options['dateFormat'] = $this->_('%b %e, %Y %l:%M %p'); // Date format in either PHP strftime() or PHP date() format // Example 1 (strftime): %b %e, %Y %l:%M %p = Feb 27, 2012 1:21 PM. Example 2 (date): m/d/y g:ia = 02/27/12 1:21pm.
			}
		}
		
		$this->comments = $comments; 
		$this->page = $comments->getPage();
		$this->field = $comments->getField();
		$this->options['useStars'] = $this->field->get('useStars');
		$this->options = array_merge($this->options, $options); 
	}

	/**
	 * Get or set options
	 * 
	 * @param string|null|array $key Use one of the following: 
	 *  - Omit to get array of all options
	 *  - Specify option name to get (and omit $value argument) 
	 *  - Specify option name to set and provide a non-null $value argument
	 *  - Specify array of one or more [ 'option' => 'value' ] to set and omit $value argument
	 * @param string|int|bool|null $value When setting an individual option, value should be specified here, otherwise omit
	 * @return array|string|int|bool|null When getting singe option, value is returned, otherwise array of all options is returned. 
	 * @since 3.0.138
	 * 
	 */
	public function options($key = null, $value = null) {
		if($key === null) {
			return $this->options;
		} else if(is_array($key)) {
			$this->options = array_merge($this->options, $key);
			return $this->options;
		} else if($value !== null) {
			$this->options[$key] = $value;
			return $this->options;
		} else if(isset($this->options[$key])) { 
			return $this->options[$key];
		} else {
			return null;
		}
	}

	/**
	 * Get replies to the given comment ID, or 0 for root level comments
	 * 
	 * @param int|Comment $commentID
	 * @return array
	 * 
	 */
	public function getReplies($commentID) {
		if(is_object($commentID)) $commentID = $commentID->id; 
		$commentID = (int) $commentID; 
		$replies = array();
		$comments = $this->comments;
		if($commentID && $comments->data('selectors') && $this->comment && $this->comment->id == $commentID) {
			// comment originated from a find()
			/** @var FieldtypeComments $fieldtype */
			$fieldtype = $this->field->type;
			$comments = $fieldtype->find("parent_id=$commentID"); 
		} else {
			$comments = $this->comments;
		}
		foreach($comments as $c) {
			if($c->parent_id != $commentID) continue;
			if(!$this->allowRenderItem($c)) continue;
			$replies[] = $c;
		}
		$this->numReplies[$commentID] = count($replies); 
		return $replies; 
	}

	/**
	 * Get classes to use with comment list 
	 * 
	 * @param int $parent_id
	 * @return array
	 * 
	 */
	protected function getCommentListClasses($parent_id) {
		$classes = array("CommentList");
		if($this->options['depth'] > 0) {
			$classes[] = "CommentListThread";
		} else {
			$classes[] = "CommentListNormal";
		}
		if($this->options['useGravatar']) $classes[] = "CommentListHasGravatar";
		if($parent_id) $classes[] = "CommentListReplies";
		return $classes;
	}

	/**
	 * Get classes to use with comment item
	 * 
	 * @param Comment $comment
	 * @return array
	 * 
	 */
	protected function getCommentItemClasses(Comment $comment) {
		$classes = array('CommentListItem');
		if($this->options['depth'] > 0 && $comment->numChildren() > 0) $classes[] = 'CommentHasReplies';
		if($comment->status == Comment::statusPending) {
			$classes[] = 'CommentStatusPending';
		} else if($comment->status == Comment::statusSpam) {
			$classes[] = 'CommentStatusSpam';
		}
		return $classes;
	}

	/**
	 * Rendering of comments for API demonstration and testing purposes (or feel free to use for production if suitable)
	 *
	 * @see Comment::render()
	 * @return string or blank if no comments
	 *
	 */
	public function render() {
		$out = $this->renderList(0); 
		if($out && $this->options['headline']) $out = "\n" . $this->options['headline'] . $out; 
		return $out;
	}

	/**
	 * Render comment list
	 * 
	 * @param int $parent_id
	 * @param int $depth
	 * @return string
	 * 
	 */
	protected function renderList($parent_id = 0, $depth = 0) {
		
		$out = $parent_id ? '' : $this->renderCheckActions();
		$comments = $this->options['depth'] > 0 ? $this->getReplies($parent_id) : $this->comments;
		
		if(!count($comments)) return $out;
		
		foreach($comments as $comment) {
			if(!$this->allowRenderItem($comment)) continue;
			$this->comment = $comment;
			$out .= $this->renderItem($comment, array('depth' => $depth));
		}
		
		if(!$out) return '';
		
		$class = implode(' ', $this->getCommentListClasses($parent_id)); 
		$attrs = "class='$class'";
		
		if($parent_id) {
			$attrs .= " id='CommentList$parent_id'";
			if($this->options['useRepliesLink']) $attrs .= " hidden";
		}
		
		$out = "<ul $attrs>$out\n</ul><!--/CommentList-->";
		
		return $out; 
	}

	/**
	 * Populate comment {variable} placeholders
	 * 
	 * @param Comment $comment
	 * @param string $out
	 * @param array $placeholders Additional placeholders to populate as name => value (exclude the brackets)
	 * @return string
	 * 
	 */
	protected function populatePlaceholders(Comment $comment, $out, $placeholders = array()) {

		$placeholders = array_merge($this->placeholders, $placeholders); 
		if(empty($out) || strpos($out, '{') === false) return $out;
		
		$removals = array(" href=''", ' href=""');
		
		foreach($placeholders as $key => $value) {
			$key = '{' . $key . '}';	
			if(strpos($out, $key) === false) continue;
			$value = str_replace(array('{', '}'), ' ', $value);
			$out = str_replace($key, $value, $out);
		}
		
		if(strpos($out, '{votes}') !== false) {
			$out = str_replace('{votes}', $this->renderVotes($comment), $out);
		}
		if(strpos($out, '{stars}') !== false) {
			$out = str_replace('{stars}', $this->renderStars($comment), $out);
		}
		
		if(strpos($out, '{url}') !== false || strpos($out, '{page.url}') !== false) {
			$page = $comment->getPage();
			if($page->id && $page->viewable() && !$page->isUnpublished()) {
				// page has a linkable URL
				$pageUrl = $page->url();
				$commentUrl = "$pageUrl#Comment$comment->id";
			} else {
				$pageUrl = '';
				$commentUrl = '';
			}
			$out = str_replace(array('{url}', '{page.url}'), array($commentUrl, $pageUrl), $out);
		}
		
		if(strpos($out, '{page.') !== false) {
			$page = $comment->getPage();
			$out = str_replace('{page.', '{', $out);
			$out = preg_replace('/\{[-_a-z0-9]*\.[-_.a-z0-9]*\}/i', '', $out); // remove any others
			$out = $page->getMarkup($out);
		}
	
		$out = str_replace($removals, '', $out); 
		
		return $out;
	}
	
	/**
	 * Allow comment to be rendered in list?
	 *
	 * @param Comment $comment
	 * @return bool
	 *
	 */
	protected function allowRenderItem(Comment $comment) {
		if($this->editable === null) $this->editable = $this->options['admin'] && $this->page->editable();
		if($this->editable || $comment->status >= Comment::statusApproved) return true;
		return false;
	}

	/**
	 * Render the comment
	 *
	 * This is the default rendering for development/testing/demonstration purposes
	 *
	 * It may be used for production, but only if it meets your needs already. Typically you'll want to render the comments
	 * using your own code in your templates.
	 * 
	 * @param Comment $comment
	 * @param array $options
	 * @return string
	 * @see CommentArray::render()
	 *
	 */
	public function renderItem(Comment $comment, $options = array()) {
		if(is_int($options)) $options = array('depth' => $options);
		if($this->wire('hooks')->isHooked("CommentList::renderItem()")) { 
			return $this->__call('renderItem', array($comment, $options));
		} else {
			return $this->___renderItem($comment, $options);
		}
	}
	
	/**
	 * Render the comment (hookable version)
	 * 
	 * Hookable since 3.0.138
	 *
	 * @param Comment $comment
	 * @param array|int $options Options array 
	 * @return string
	 * @see CommentArray::render()
	 *
	 */
	protected function ___renderItem(Comment $comment, $options = array()) {
		
		$defaults = array(
			'depth' => is_int($options) ? $options : 0,
			'placeholders' => array(),
		);

		$options = is_array($options) ? array_merge($defaults, $options) : $defaults;
		$text = $comment->getFormatted('text'); 
		$cite = $comment->getFormatted('cite'); 

		$gravatar = '';
		if($this->options['useGravatar']) {
			$imgUrl = $comment->gravatar($this->options['useGravatar'], $this->options['useGravatarImageset']); 
			if($imgUrl) $gravatar = "\n\t\t<img class='CommentGravatar' src='$imgUrl' alt='$cite' />";
		}

		$website = '';
		if($comment->website) $website = $comment->getFormatted('website'); 
		if($website) $cite = "<a href='$website' rel='nofollow' target='_blank'>$cite</a>";
		$created = wireDate($this->options['dateFormat'], $comment->created); 
		$placeholders = array(
			'cite' => $cite, 
			'created' => $created, 
			'gravatar' => $gravatar
		);
		if(count($options['placeholders'])) {
			$placeholders = array_merge($placeholders, $options['placeholders']);
		}
		
		if(empty($this->options['commentHeader'])) {
			$header = "<span class='CommentCite'>$cite</span> <small class='CommentCreated'>$created</small> ";
			if($this->options['useStars']) $header .= $this->renderStars($comment);
			if($this->options['useVotes']) $header .= $this->renderVotes($comment); 
		} else {
			$header = $this->populatePlaceholders($comment, $this->options['commentHeader'], $placeholders);
		}

		$footer = $this->populatePlaceholders($comment, $this->options['commentFooter'], $placeholders); 
		$liClass = '';
		$replies = $this->options['depth'] > 0 ? $this->renderList($comment->id, $options['depth']+1) : ''; 
		
		if($replies) $liClass .= ' CommentHasReplies';
		if($comment->status == Comment::statusPending) {
			$liClass .= ' CommentStatusPending';
		} else if($comment->status == Comment::statusSpam) {
			$liClass .= ' CommentStatusSpam';
		}
		
		$out = 
			"\n\t<li id='Comment{$comment->id}' class='CommentListItem$liClass' data-comment='$comment->id'>" . $gravatar . 
			"\n\t\t<p class='CommentHeader'>$header</p>" . 
			"\n\t\t<div class='CommentText'>" . 
			"\n\t\t\t<p>$text</p>" . 
			"\n\t\t</div>";
		
		if($this->options['usePermalink']) {
			$permalink = $comment->getPage()->httpUrl;
			$urlSegmentStr = $this->wire('input')->urlSegmentStr;
			if($urlSegmentStr) $permalink .= rtrim($permalink, '/') . $urlSegmentStr . '/';
			$permalink .= '#Comment' . $comment->id;
			$permalink = "<a class='CommentActionPermalink' href='$permalink'>" . $this->_('Permalink') . "</a>";
		} else {
			$permalink = '';
		}

		if($this->options['depth'] > 0 && $options['depth'] < $this->options['depth']) {
			$numReplies = isset($this->numReplies[$comment->id]) ? $this->numReplies[$comment->id] : 0;	
			if($replies && $numReplies && $this->options['useRepliesLink']) {
				$repliesLabel = ($numReplies == 1 ? $this->options['repliesLabelOne'] : $this->options['repliesLabelMulti']);
				if(strpos($repliesLabel, '%d') !== false) $repliesLabel = sprintf($repliesLabel, $numReplies); 
				$repliesLink = "<a class='CommentActionReplies' href='#CommentList{$comment->id}'>$repliesLabel</a>";
			} else {
				$repliesLink = '';
			}	
			$out .=
				"\n\t\t<div class='CommentFooter'>" . $footer . 
				"\n\t\t\t<p class='CommentAction'>" .
				"\n\t\t\t\t<a class='CommentActionReply' data-comment-id='$comment->id' href='#Comment{$comment->id}'>" . $this->options['replyLabel'] . "</a> " .
				($repliesLink ? "\n\t\t\t\t$repliesLink" : "") . 
				($permalink ? "\n\t\t\t\t$permalink" : "") . 
				"\n\t\t\t</p>" . 
				"\n\t\t</div>";
			
			if($replies) $out .= $replies;
			
		} else {
			$out .= "\n\t\t<div class='CommentFooter'>$footer</div>";
		}
	
		$out .= "\n\t</li>";
	
		return $out; 	
	}
	
	public function renderVotes(Comment $comment) {
		
		if(!$this->options['useVotes']) return '';
		
		$upvoteFormat = str_replace('{cnt}', "<small class='CommentUpvoteCnt'>$comment->upvotes</small>", $this->options['upvoteFormat']);
		$upvoteURL = "{$this->page->url}?comment_success=upvote&amp;comment_id=$comment->id&amp;field_id={$this->field->id}#Comment$comment->id";
		$upvoteLabel = $this->_('Like this comment');

		$downvoteFormat = str_replace('{cnt}', "<small class='CommentDownvoteCnt'>$comment->downvotes</small>", $this->options['downvoteFormat']);
		$downvoteURL = "{$this->page->url}?comment_success=downvote&amp;comment_id=$comment->id&amp;field_id={$this->field->id}#Comment$comment->id";
		$downvoteLabel = $this->_('Dislike this comment');

		// note that data-url attribute stores the href (rather than href) so that we can keep crawlers out of auto-following these links
		$out = "<span class='CommentVotes'>";
		$out .= "<a class='CommentActionUpvote' title='$upvoteLabel' data-url='$upvoteURL' href='#Comment$comment->id'>$upvoteFormat</a>";
		
		if($this->options['useVotes'] == FieldtypeComments::useVotesAll) {
			$out .= "<a class='CommentActionDownvote' title='$downvoteLabel' data-url='$downvoteURL' href='#Comment$comment->id'>$downvoteFormat</a>";
		}
		
		$out .= "</span> ";
		
		return $out; 
	}

	public function renderStars(Comment $comment) {
		if(!$this->options['useStars']) return '';
		if(!$comment->stars) return '';
		$commentStars = new CommentStars();
		return $commentStars->render($comment->stars, false);
	}

	/**
	 * Check for URL-based comment approval actions
	 *
	 * Note that when it finds an actionable approval code, it performs a
	 * redirect back to the same page after completing the action, with
	 * ?comment_success=2 on successful action, or ?comment_success=3 on
	 * error.
	 *
	 * It also populates a session variable 'CommentApprovalMessage' with
	 * a text message of what occurred.
	 *
	 * @param array $options
	 * @return string
	 *
	 */
	public function renderCheckActions(array $options = array()) {
		
		$defaults = array(
			'messageIdAttr' => 'CommentApprovalMessage',
			'messageMarkup' => "<p id='{id}' class='{class}'><strong>{message}</strong></p>", 
			'linkMarkup' => "<a href='{href}'>{label}</a>",
			'successClass' => 'success',
			'errorClass' => 'error',
		);
		
		$options = array_merge($defaults, $options);
		$action = $this->wire('input')->get('comment_success');
		if(empty($action) || $action === "1") return '';

		if($action === '2' || $action === '3') {
			$message = $this->wire('session')->get('CommentApprovalMessage');
			if($message) {
				$this->wire('session')->remove('CommentApprovalMessage');
				$class = $action === '2' ? $options['successClass'] : $options['errorClass'];
				$commentID = (int) $this->wire('input')->get('comment_id');
				$message = $this->wire('sanitizer')->entities($message);
				if($commentID) {
					$link = str_replace(
						array('{href}', '{label}'), 
						array("#Comment$commentID", $commentID), 
						$options['linkMarkup']
					);
					$message = str_replace($commentID, $link, $message);
				}
				return str_replace(
					array('{id}', '{class}', '{message}'), 
					array($options['messageIdAttr'], $class, $message), 
					$options['messageMarkup']
				);
			}
		}

		if(!$this->field) return '';

		require_once(dirname(__FILE__) . '/CommentNotifications.php');
		$no = $this->wire(new CommentNotifications($this->page, $this->field));
		$info = $no->checkActions();
		if($info['valid']) { 
			$url = $this->page->url . '?'; 
			if($info['commentID']) $url .= "comment_id=$info[commentID]&";
			$url .= "comment_success=" . ($info['success'] ? '2' : '3');
			$this->wire('session')->set('CommentApprovalMessage', $info['message']);
			$this->wire('session')->redirect($url . '#' . $options['messageIdAttr']);
		}

		return '';
	}

	/**
	 * Get or set placeholders that will be populated by populatePlaceholders() method
	 * 
	 * @param string|array|false $name Specify placeholder name to get or set, array of placeholders to set, false to unset all, omit to get all
	 * @param string|bool $value Specify placeholder value to set or boolean false to unset, or omit when getting
	 * @return string|array
	 * @since 3.0.153
	 * 
	 */
	public function placeholders($name = '', $value = null) {
		if(is_array($name)) {
			// set multiple
			foreach($name as $k => $v) {
				$this->placeholders[trim($k, '{}')] = $v;
			}
			$value = $name;
		} else if(empty($name)) {
			// return all
			$value = $this->placeholders;
		} else if($value === null) {
			// get one
			$value = isset($this->placeholders[$name]) ? $this->placeholders[$name] : null;
		} else if($value === false) {
			// unset one
			$value = isset($this->placeholders[$name]) ? $this->placeholders[$name] : null;
			unset($this->placeholders[$name]); 
		} else {
			// set one
			$name = trim($name, '{}');
			$this->placeholders[$name] = $value;
		}
		return $value; 
	}

}

