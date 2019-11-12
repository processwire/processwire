<?php namespace ProcessWire;

/**
 * ProcessWire Comments Field
 *
 * Custom “Field” class for Comments fields. 
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

class CommentField extends Field {

	/**
	 * Find comments matching given selector
	 * 
	 * @param $selectorString
	 * @param array $options
	 * @return CommentArray
	 * 
	 */
	public function find($selectorString, array $options = array()) {
		return $this->getFieldtype()->find($selectorString, $this, $options); 
	}
	
	/**
	 * Return total quantity of comments matching the selector
	 *
	 * @param string|null $selectorString Selector string with query
	 * @return int
	 *
	 */
	public function count($selectorString) {
		return $this->getFieldtype()->count($selectorString, $this); 
	}
	
	/**
	 * Given a comment code or subcode, return the associated comment ID or 0 if it doesn't exist
	 *
	 * @param Page|int|string $page
	 * @param string $code
	 * @return Comment|null
	 *
	 */
	public function getCommentByCode($page, $code) {
		return $this->getFieldtype()->getCommentByCode($page, $this, $code);
	}

	/**
	 * Get a comment by ID or NULL if not found
	 *
	 * @param Page|int|string $page
	 * @param int $id
	 * @return Comment|null
	 *
	 */
	public function getCommentByID($page, $id) {
		return $this->getFieldtype()->getCommentByID($page, $this, $id); 
	}
	
	/**
	 * Update specific properties for a comment
	 *
	 * @param Page $page
	 * @param Comment $comment
	 * @param array $properties Associative array of properties to update
	 * @return mixed
	 *
	 */
	public function updateComment(Page $page, Comment $comment, array $properties) {
		return $this->getFieldtype()->updateComment($page, $this, $comment, $properties);
	}

	/**
	 * Delete a given comment
	 *
	 * @param Page $page
	 * @param Comment $comment
	 * @param string $notes
	 * @return mixed
	 *
	 */
	public function deleteComment(Page $page, Comment $comment, $notes = '') {
		return $this->getFieldtype()->deleteComment($page, $this, $comment, $notes);
	}
	
	/**
	 * Add a vote to the current comment from the current user/IP
	 *
	 * @param Page $page
	 * @param Comment $comment
	 * @param bool $up Specify true for upvote, or false for downvote
	 * @return bool Returns true on success, false on failure or duplicate
	 *
	 */
	public function voteComment(Page $page, Comment $comment, $up = true) {
		return $this->getFieldtype()->voteComment($page, $this, $comment, $up); 
	}

	/**
	 * @return FieldtypeComments|Fieldtype
	 *
	 */
	public function getFieldtype() {
		return parent::getFieldtype();
	}
}	