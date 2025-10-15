<?php namespace ProcessWire;

/**
 * ProcessWire PagesSortfields
 * 
 * #pw-headline Pages Sortfields
 * #pw-breadcrumb Pages
 * #pw-summary Manages the table for the sortfield property for Page children.
 * #pw-body = 
 * #pw-body
 * 
 * ProcessWire 3.x, Copyright 2021 by Ryan Cramer
 * https://processwire.com
 *
 */

class PagesSortfields extends Wire {

	/**
	 * Get sortfield for given Page from DB
	 * 
	 * @param int|Page $page Page or page ID
	 * @return string
	 * @since 3.0.172
	 * 
	 */
	public function get($page) {
		$pageId = $page instanceof Page ? $page->id : (int) $page;
		$sql = 'SELECT sortfield FROM pages_sortfields WHERE pages_id=:id';
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':id', $pageId, \PDO::PARAM_INT);
		$query->execute();
		if($query->rowCount()) {
			$sortfield = $query->fetchColumn();
			$sortfield = $this->decode($sortfield); 
		} else {
			$sortfield = '';
		}
		$query->closeCursor();
		return $sortfield;
	}

	/**
	 * Save the sortfield for a given Page
	 *
	 * @param Page
	 * @return bool
	 *
	 */
	public function save(Page $page) {

		if(!$page->id) return false; 
		if(!$page->isChanged('sortfield')) return true; 

		$page_id = (int) $page->id; 
		$database = $this->wire('database');
		$sortfield = $this->encode($page->sortfield); 

		if($sortfield == 'sort' || !$sortfield) return $this->delete($page); 

		$sql = 	"INSERT INTO pages_sortfields (pages_id, sortfield) " .
				"VALUES(:page_id, :sortfield) " .
				"ON DUPLICATE KEY UPDATE sortfield=VALUES(sortfield)";
		
		$query = $database->prepare($sql);
		$query->bindValue(":page_id", $page_id, \PDO::PARAM_INT);
		$query->bindValue(":sortfield", $sortfield, \PDO::PARAM_STR);
		$result = $query->execute();
		
		return $result;
	}

	/**
	 * Delete the sortfield for a given Page
	 *
	 * @param Page
	 * @return bool
	 *
	 */
	public function delete(Page $page) {
		$database = $this->wire('database');
		$query = $database->prepare("DELETE FROM pages_sortfields WHERE pages_id=:page_id"); // QA
		$query->bindValue(":page_id", $page->id, \PDO::PARAM_INT); 
		$result = $query->execute();
		return $result;
	}

	/**
	 * Decodes a sortfield from a signed integer or string to a field name 
	 *
	 * The returned fieldname is preceded with a dash if the sortfield is reversed. 
	 *
	 * @param string|int $sortfield
	 * @param string $default Default sortfield name (default='sort')
	 * @return string
	 *
	 */
	public function decode($sortfield, $default = 'sort') {

		$reverse = false;
		$sortfield = (string) $sortfield;

		if(substr($sortfield, 0, 1) == '-') {
			$sortfield = substr($sortfield, 1); 
			$reverse = true; 	
		}

		if(ctype_digit("$sortfield") || !Fields::isNativeName($sortfield)) {
			$field = $this->wire()->fields->get(ctype_digit($sortfield) ? (int) $sortfield : $sortfield);
			$sortfield = $field ? $field->name : '';
		}

		if(!$sortfield) {
			$sortfield = $default;
		} else if($reverse) {
			$sortfield = "-$sortfield";
		}

		return $sortfield; 
	}

	/**
	 * Encodes a sortfield from a fieldname to a signed integer (ID) representing a custom field, or native field name
	 *
	 * The returned value will be a negative value (or string preceded by a dash) if the sortfield is reversed. 
	 *
	 * @param string $sortfield
	 * @param string $default Default sortfield name (default='sort')
	 * @return string|int
	 *
	 */
	public function encode($sortfield, $default = 'sort') {

		$reverse = false; 
	
		if(substr($sortfield, 0, 1) == '-') {	
			$reverse = true; 
			$sortfield = substr($sortfield, 1); 
		}

		if($sortfield && !Fields::isNativeName($sortfield)) { 
			if($field = $this->wire('fields')->get($sortfield)) $sortfield = $field->id; 
				else $sortfield = '';
		}

		if($sortfield) {
			if($reverse) $sortfield = "-$sortfield";
		} else {
			$sortfield = $default;
		}

		return $sortfield; 
	}
}
