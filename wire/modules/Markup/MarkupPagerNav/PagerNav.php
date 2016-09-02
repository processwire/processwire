<?php namespace ProcessWire;

/**
 * ProcessWire PagerNav support classes for MarkupPagerNav module
 *
 * Provides capability for determining pagination information
 *
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 *
 */


/**
 * An individual pager item
 *
 */
class PagerNavItem {
	const typeCurrent = 'current';
	const typeFirst = 'first';
	const typePrevious = 'previous';
	const typeNext = 'next';
	const typeLast = 'last';
	const typeSeparator = 'separator';

	protected $data = array(
		'label' => '',
		'pageNum' => 0,
		'type' => '', // first, previous, next, last, current, or separator
		); 

	public function __construct($label, $pageNum, $type = '') {
		$this->data['label'] = $label;
		$this->data['pageNum'] = $pageNum;
		$this->data['type'] = $type;
	}

	public function __get($property) {
		return isset($this->data[$property]) ? $this->data[$property] : false;
	}

	public function __set($property, $value) {
		$this->data[$property] = $value; 
	}
}

/**
 * Collection of Pager items that determines which pagination links should be used
 * 
 * USAGE EXAMPLE:
 * 
 * $pager = new PagerNav(100, 10, 0); 
 * 
 * foreach($pager as $pageLabel => $pageNum) {
 * 	$class = "action";
 * 	if($pageNum == $pager->getCurrentPage()) $class .= " on";
 * 	$out .= "<li><a class='$class' href='$baseUrl$pageNum/'>$pageLabel</a></li>";
 * }
 *
 */
class PagerNav implements \IteratorAggregate {

	protected $totalPages = 0;
	protected $currentPage = 0;
	protected $pager = NULL;
	protected $numPageLinks = 10;
	protected $totalItems = 0;
	protected $firstItem = 0;
	protected $itemsPerPage = 0;

	protected $labels = array(
		'previous' => 'prev', 
		'next' => 'next'
		); 

	protected $separator = NULL;

	/**
	 * Construct the PagerNav
 	 *
	 * @param int $totalItems Total number of items in the list to be paginated. 
	 * @param int $itemsPerPage The number of items you want to appear per page. 
	 * @param int $currentPage The current page number (NOTE: 0 based, not 1 based)
	 * @throws WireException if given itemsPerPage of 0
	 *
	 */
	public function __construct($totalItems, $itemsPerPage, $currentPage) {

		// note that the page numbers are zero based.
		// if you are one based, then subtract one from currentPage before passing in here

		if(!$itemsPerPage) throw new WireException("itemsPerPage must be more than 0"); 

		$this->totalItems = $totalItems; 
		$this->currentPage = $currentPage-1;
		$this->itemsPerPage = $itemsPerPage;

		$this->firstItem = $this->currentPage * $this->itemsPerPage;

		/*
		// commented and kept for future reference
		if($totalItems >= ($itemsPerPage * 2)) {
			$this->totalPages = floor($this->totalItems / $this->itemsPerPage)-1; 
		} else {
			//$this->totalPages = ceil($this->totalItems / $this->itemsPerPage)-1; 
			$this->totalPages = ceil($this->totalItems / $this->itemsPerPage); 
		}
		*/

		if($this->totalItems > 0) $this->totalPages = ceil($this->totalItems / $this->itemsPerPage)-1; 
			else $this->totalPages = 0; 

		/*
		// uncomment this section for debugging
		echo 	"totalItems: " . $this->totalItems . "<br />" . 
			"totalPages: " . $this->totalPages . "<br />" . 	
			"currentPage: " . $this->currentPage . "<br />" . 
			"itemsPerPage: " . $this->itemsPerPage . "<br />";
		*/

		if($this->totalPages && (($this->totalPages * $this->itemsPerPage) >= $this->totalItems)) 
			$this->totalPages--; // totalPages zero based

		$this->separator = new PagerNavItem('', 0, PagerNavItem::typeSeparator); 
	}	

	/**
 	 * Returns an array contantaining $label => $pageNum
	 *
	 * Rather than access this function directly, it is prefereable to iterate the object. 
	 *
	 * @return array
 	 *
	 */
	public function getPager() {

		// returns array($pageLabel => $pageNum, ...)

		if($this->totalItems <= $this->itemsPerPage) return array();
		if(!is_null($this->pager)) return $this->pager;
		$this->pager = array();


		if($this->numPageLinks) {
			$numPageLinks = $this->numPageLinks-1;
			//$numHalf = (int) round($numPageLinks / 2); 
			$numHalf = (int) floor($numPageLinks / 2); 
			$startPage = $this->currentPage - $numHalf; 

			if($startPage <= 0) {
				$startPage = 0;

			} else if($startPage > 0) {
				$numPageLinks--;
			}

			$endPage = $startPage + $numPageLinks;

			if($endPage > $this->totalPages) {
				$endPage = $this->totalPages; 
				$startPage = $endPage - $numPageLinks;
				if($startPage < 0) $startPage = 0;
			}		

		} else {
			$startPage = 0; 
			$endPage = $this->totalPages; 
		}

		/*
		// uncomment for debugging purposes
		echo 	"numPageLinks=$numPageLinks<br />" . 
			"numHalf=$numHalf<br />" . 
			"startPage=$startPage<br />" . 
			"endPage=$endPage<br />" . 
			"totalPages={$this->totalPages}<br />" . 
			"totalItems={$this->totalItems}<br />";
		*/
			

		for($n = $startPage; $n <= ($endPage+1); $n++) {
			$type = $n == ($this->currentPage+1) ? PagerNavItem::typeCurrent : '';
			if($n) $this->pager[] = new PagerNavItem($n, $n - 1, $type);
		}

		if($this->currentPage < $this->totalPages) {
			$useLast = true; 
			$item = null;
			$key = null;

			foreach($this->pager as $key => $item) {
				if($item->pageNum == $this->totalPages) $useLast = false;
			}

			/*
			if($item && $item->pageNum == ($this->totalPages-1)) {
				unset($this->pager[$key]); 
				$this->pager[] = $this->separator; 
				$this->pager[] = new PagerNavItem($this->totalPages+1, $this->totalPages); 
				$useLast = false; 
			}
			*/

			if($useLast) {
				$this->pager[] = $this->separator; 
				$this->pager[] = new PagerNavItem($this->totalPages+1, $this->totalPages, PagerNavItem::typeLast); 
			}

			if($this->getLabel('next')) $this->pager[] = new PagerNavItem($this->getLabel('next'), $this->currentPage+1, PagerNavItem::typeNext); 
		}

		if(count($this->pager) > 1) {

			$firstPageLink = false;

			foreach($this->pager as $key => $item) {
				// convert from 0-based to 1-based
				if($item->type != 'separator') $item->pageNum = $item->pageNum+1;
				if($item->pageNum == 1) $firstPageLink = true; 
			}


			if(!$firstPageLink) {

				// if the first page in pager is page 2, then get rid of it because we're already adding a page 1 (via typeFirst)
				// and leaving it here would result in 1 ... 2
				$item = reset($this->pager); 
				if($item->pageNum == 2) array_shift($this->pager); 

				array_unshift($this->pager, $this->separator); 
				array_unshift($this->pager, new PagerNavItem(1, 1, PagerNavItem::typeFirst)); // add reference to page 1
			}

			if($this->currentPage > 0 && $this->getLabel('previous')) {
				array_unshift($this->pager, new PagerNavItem($this->getLabel('previous'), $this->currentPage, PagerNavItem::typePrevious));
			}

		} else $this->pager = array(); 

	
		return $this->pager; 	
	}

	public function getIterator() { return new \ArrayObject($this->getPager()); }
	public function getFirstItem() { return $this->firstItem; }
	public function getItemsPerPage() { return $this->itemsPerPage; }
	public function getCurrentPage() { return $this->currentPage; }
	public function getTotalPages() { return $this->totalPages+1; }
	public function getLabel($key) { return isset($this->labels[$key]) ? $this->labels[$key] : ''; }
	
	public function setNumPageLinks($numPageLinks) { $this->numPageLinks = $numPageLinks; }

	/**
	 * Set the labels to use for the 'prev' and 'next' links
	 *
	 * @param string $previous 'Previous' label
	 * @param string $next 'Next' label
	 *
	 */
	public function setLabels($previous, $next) {
		$this->labels['previous'] = $previous;
		$this->labels['next'] = $next;
	}
}
