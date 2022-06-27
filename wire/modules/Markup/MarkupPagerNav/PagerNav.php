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
	protected $lastItem;
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
	 * @param int $currentPage The current page number (NOTE: 1-based NOW)
	 * @throws WireException if given itemsPerPage of 0
	 *
	 */
	public function __construct($totalItems, $itemsPerPage, $currentPage) {

		// note that the page numbers are NOW 1-based.

		if(!$itemsPerPage) throw new WireException("itemsPerPage must be more than 0");

		$this->totalItems   = max(0, (int)$totalItems);
		$this->currentPage  = max(1, (int)$currentPage);
		$this->itemsPerPage = max(1, (int)$itemsPerPage);

		$this->initialize();

		$this->separator = new PagerNavItem('', 0, PagerNavItem::typeSeparator);
	}

	/**
	 * Initialize/recalculate pager params based on current numPageLinks value
	 */
	private function initialize()
	{
		$this->totalPages = $this->totalItems > 0 ? ceil($this->totalItems / $this->itemsPerPage) : 0;
		$this->numPageLinks = min($this->totalPages, $this->numPageLinks);
		$this->currentPage = min($this->totalPages, $this->currentPage);

		$this->firstItem = ($this->currentPage - 1) * $this->itemsPerPage;
		$this->lastItem = $this->firstItem + $this->itemsPerPage;

		if($this->totalPages === $this->numPageLinks) {
			$this->firstItem = 1;
			$this->lastItem  = $this->totalPages;
		} else {
			$halfPageLinks = (int)floor($this->numPageLinks / 2);

			$this->firstItem = max(1, $this->currentPage - $halfPageLinks);
			$this->lastItem  = $this->firstItem  + ($this->numPageLinks - 1);

			if($this->lastItem > $this->totalPages) {
				$this->lastItem = $this->totalPages;
				$this->firstItem = $this->lastItem - ($this->numPageLinks - 1);
			}
		}
	}

	/**
 	 * Returns an array contantaining $label => $pageNum
	 *
	 * Rather than access this function directly, it is prefereable to iterate the object.
	 *
	 * @return array|PagerNavItem[]
 	 *
	 */
	public function getPager() {

		// returns array($pageLabel => $pageNum, ...)

		if (is_array($this->pager)) {
			return $this->pager;
		}

		if ($this->totalPages < 2 || $this->totalItems <= $this->itemsPerPage) {
			return $this->pager = array();
		}

		$this->pager = array();

		// previous item
		if($this->currentPage > 1) {
			$this->pager[] = new PagerNavItem($this->getLabel('previous'), $this->currentPage - 1, PagerNavItem::typePrevious);
		}
		// first item
		if($this->firstItem > 1) {
			$this->pager[] = new PagerNavItem(1, 1, PagerNavItem::typeFirst);
			$this->pager[] = clone $this->separator;
		}
		// items in num-links range
		for($pageNum = $this->firstItem; $pageNum <= $this->lastItem; $pageNum += 1) {
			$this->pager[] = new PagerNavItem(
				$pageNum,
				$pageNum,
				$pageNum == $this->currentPage ? PagerNavItem::typeCurrent : ''
			);
		}
		// last item
		if($this->lastItem < $this->totalPages) {
			$this->pager[] = clone $this->separator;
			$this->pager[] = new PagerNavItem($this->totalPages, $this->totalPages, PagerNavItem::typeLast);
		}
		// next item
		if($this->currentPage < $this->totalPages) {
			$this->pager[] = new PagerNavItem($this->getLabel('next'), $this->currentPage + 1, PagerNavItem::typeNext);
		}

		return $this->pager;
	}

	#[\ReturnTypeWillChange]
	public function getIterator() { return new \ArrayObject($this->getPager()); }
	public function getFirstItem() { return $this->firstItem; }
	public function getLastItem() { return $this->lastItem; }
	public function getItemsPerPage() { return $this->itemsPerPage; }
	public function getCurrentPage() { return $this->currentPage; }
	public function getTotalPages() { return $this->totalPages; }
	public function getLabel($key) { return isset($this->labels[$key]) ? $this->labels[$key] : ''; }

	public function setNumPageLinks($numPageLinks) {
		$numPageLinks = (int)$numPageLinks;
		if($this->numPageLinks !== $numPageLinks) {
			$this->numPageLinks = $numPageLinks;
			$this->pager = null; // reset cached pager items
			$this->initialize(); // recalculate params
		}
	}

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
