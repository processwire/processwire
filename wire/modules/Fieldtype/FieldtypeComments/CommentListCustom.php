<?php namespace ProcessWire;

/**
 * ProcessWire CommentListCustom
 * 
 * Manages custom CommentList implementations where you specify your own markup
 * 
 * ~~~~~~
 * $list = $page->comments->getCommentList([
 *   'className' => 'CommentListCustom',
 * ]); 
 * 
 * $list->setMarkup([
 *   'noticeMessage' => "<div id='{id}' class='uk-alert {class}'>{message}</div>",
 *   'noticeSuccessClass' => 'uk-alert-success',
 *   'noticeErrorClass' => 'uk-alert-danger',
 *   'list' => "<ul id='my-comments-list' class='{class}'>{comments}</ul>", 
 *   // and so on for any other $markup properties
 * ]); 
 * 
 * echo $list->render();
 * ~~~~~~
 *
 * ProcessWire 3.x, Copyright 2020 by Ryan Cramer
 * https://processwire.com
 *
 *
 */

class CommentListCustom extends CommentList {
	
	protected $markup = array(
		'gravatar' => "<img class='CommentGravatar' src='{src}' alt='{alt}' />",
		'website' => "<a href='{href}' rel='nofollow' target='_blank'>{cite}</a>",
		'permalink' => "<a class='CommentActionPermalink' href='{href}'>{label}</a>",
		'reply' => "<a class='CommentActionReply' data-comment-id='{id}' href='#Comment{id}'>{label}</a>",
		'list' => "<ul class='{class}'>{comments}</ul><!--/CommentList-->",
		'sublist' => "<ul id='CommentList{id}' class='{class}'>{comments}</ul><!--/CommentList-->",
		'replies' => "<a href='#CommentList{id}' class='CommentActionReplies'>{label}</a>",
		'item' => "
			<li id='Comment{id}' class='{class}' data-comment='{id}'>
				{gravatar}
				<p class='CommentHeader'>
					<span class='CommentCite'>{cite}</span> 
					<small class='CommentCreated'>{created}</small> 
					{stars}
					{votes}
				</p>
				<div class='CommentText'>
					<p>{text}</p>
				</div>
				<div class='CommentFooter'>
					<p class='CommentAction'>
						{reply}
						{permalink}
					</p>
				</div>
				{replies}
				{sublist}
			</li>
		",
		'subitem' => '', // blank means use same markup as item
		'noticeMessage' => "<p id='{id}' class='{class}'><strong>{message}</strong></p>",
		'noticeLink' => "<a href='{href}'>{label}</a>",
		'noticeSuccessClass' => 'success',
		'noticeErrorClass' => 'error',
	);

	/**
	 * Get markup property or all markup
	 * 
	 * @param string $property Specify property or omit to get all
	 * @return array|mixed|null
	 * 
	 */
	public function getMarkup($property = '') {
		if($property) return isset($this->markup[$property]) ? $this->markup[$property] : null;
		return $this->markup;
	}

	/**
	 * Set markup
	 * 
	 * Set any or all of the following markup properties via associative array:
	 * 
	 * list, sublist, item, subitem, gravtar, website, permalink, reply, 
	 * noticeMessage, noticeLink, noticeSuccessClass, noticeErrorClass
	 * 
	 * @param array $markup
	 * 
	 */
	public function setMarkup(array $markup) {
		$this->markup = array_merge($this->markup, $markup);
	}

	/**
	 * Get or set markup properties
	 * 
	 * @param string|array $key Property to get or set, or array of properties to set
	 * @param null|mixed $value Specify only if setting individual property
	 * @return mixed
	 * 
	 */
	public function markup($key = '', $value = null) {
		if(empty($key)) {
			// return all
			$value = $this->markup;
		} else if(is_array($key)) {
			// set multi
			$this->setMarkup($key); 
			$value = $this->markup;
		} else if($value !== null) {
			// set single
			$this->markup[$key] = $value;
		} else {
			// get single
			$value = isset($this->markup[$key]) ? $this->markup[$key] : null;
		}
		return $value;
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
		foreach($comments as $comment) {
			if(!$this->allowRenderItem($comment)) continue;
			$this->comment = $comment;
			$out .= $this->renderItem($comment, array('depth' => $depth));
		}
		if($out) {
			$class = implode(' ', $this->getCommentListClasses($parent_id));
			$listMarkup = ($depth ? $this->markup['sublist'] : $this->markup['list']);
			if($depth && $this->options['useRepliesLink']) {
				$listMarkup = str_replace(" class=", " hidden class=", $listMarkup);
			}
			$out = str_replace(
				array('{id}', '{class}', '{comments}'), 
				array($parent_id, $class, $out), 
				$listMarkup
			);
		}
		return $out;
	}
	
	/**
	 * Render the comment 
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
		$created = wireDate($this->options['dateFormat'], $comment->created);
		$classes = $this->getCommentItemClasses($comment); 
		$gravatar = '';
		$permalink = '';
		$reply = '';
		$replies = '';

		if($this->options['useGravatar']) {
			$imgUrl = $comment->gravatar($this->options['useGravatar'], $this->options['useGravatarImageset']);
			if($imgUrl) {
				$gravatar = str_replace(array('{src}', '{alt}'), array($imgUrl, $cite), $this->markup['gravatar']);
			}
		}

		if($comment->website) {
			$website = $comment->getFormatted('website');
			$cite = str_replace(array('{href}', '{cite}'), array($website, $cite), $this->markup['website']);
		}

		$sublist = $this->options['depth'] > 0 ? $this->renderList($comment->id, $options['depth']+1) : '';

		if($this->options['usePermalink']) {
			$permalink = $comment->getPage()->httpUrl;
			$urlSegmentStr = $this->wire('input')->urlSegmentStr;
			if($urlSegmentStr) $permalink .= rtrim($permalink, '/') . $urlSegmentStr . '/';
			$permalink .= '#Comment' . $comment->id;
			$label = $this->_('Permalink');
			$permalink = str_replace(array('{href}', '{label}'), array($permalink, $label), $this->markup['permalink']);
		}

		if($this->options['depth'] > 0 && $options['depth'] < $this->options['depth']) {
			$label = $this->_('Reply');
			$reply = str_replace(array('{id}', '{label}'), array($comment->id, $label), $this->markup['reply']);
		}
	
		if($this->options['useRepliesLink']) {
			$numReplies = isset($this->numReplies[$comment->id]) ? $this->numReplies[$comment->id] : 0;  // must be after renderList()
			if($numReplies) {
				$repliesLabel = sprintf($this->_n('%d Reply', '%d Replies', $numReplies), $numReplies); 
				$replies = str_replace(
					array('{id}', '{href}', '{label}'), 
					array($comment->id, "#CommentList$comment->id", $repliesLabel), 
					$this->markup['replies']
				); 
			}
		}

		$placeholders = array(
			'id' => $comment->id,
			'cite' => $cite,
			'text' => $text,
			'created' => $created,
			'gravatar' => $gravatar,
			'stars' => $this->options['useStars'] ? $this->renderStars($comment) : '',
			'votes' => $this->options['useVotes'] ? $this->renderVotes($comment) : '',
			'class' => implode(' ', $classes),
			'permalink' => $permalink,
			'reply' => $reply,
			'replies' => $replies,
			'sublist' => $sublist,
		);

		if(count($options['placeholders'])) {
			$placeholders = array_merge($placeholders, $options['placeholders']); 
		}
		
		$itemMarkup = $options['depth'] ? $this->markup['subitem'] : $this->markup['item'];
		if(empty($itemMarkup)) $itemMarkup = $this->markup['item'];
		$out = $this->populatePlaceholders($comment, $itemMarkup, $placeholders);

		return $out;
	}

	public function renderCheckActions(array $options = array()) {

		$defaults = array(
			'messageIdAttr' => 'CommentApprovalMessage',
			'messageMarkup' => $this->markup['noticeMessage'],
			'linkMarkup' => $this->markup['noticeLink'],
			'successClass' => $this->markup['noticeSuccessClass'],
			'errorClass' => $this->markup['noticeErrorClass'],
		);
		
		$options = array_merge($defaults, $options);
		
		return parent::renderCheckActions($options);
	}		

}