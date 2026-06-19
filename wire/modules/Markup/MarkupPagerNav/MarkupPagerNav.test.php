<?php namespace ProcessWire;

/**
 * Tests for ProcessWire MarkupPagerNav and PaginatedArray pagination helpers.
 *
 */
class WireTest_MarkupPagerNav extends WireTest {

	public function execute() {
		$this->testDirectRender();
		$this->testLastPageAndNoPagination();
		$this->testArrayGetVars();
		$this->testPaginatedArrayHelpers();
		$this->testPageArrayRenderOptionPassThrough();
	}

	protected function paginatedArray($count = 10, $total = 35, $limit = 10, $start = 0) {
		$items = $this->wire(new PaginatedArray());
		for($n = 1; $n <= $count; $n++) {
			$item = $this->wire(new WireData());
			$item->set('title', "Item $n");
			$items->add($item);
		}
		$items->setTotal($total);
		$items->setLimit($limit);
		$items->setStart($start);
		return $items;
	}

	protected function pageArray($total = 12, $limit = 2, $start = 0) {
		$items = $this->wire(new PageArray());
		foreach($this->wire()->pages->find('include=all, limit=2') as $page) {
			$items->add($page);
		}
		$items->setTotal($total);
		$items->setLimit($limit);
		$items->setStart($start);
		return $items;
	}

	protected function pager() {
		return $this->wire()->modules->get('MarkupPagerNav');
	}

	protected function pagerOptions(array $options = array()) {
		return array_merge(array(
			'page' => $this->wire()->pages->get('/'),
			'baseUrl' => '/pager-test/',
		), $options);
	}

	protected function testDirectRender() {
		$items = $this->paginatedArray(10, 35, 10, 10);
		$pager = $this->pager();

		$out = $pager->render($items, $this->pagerOptions(array(
			'numPageLinks' => 5,
			'getVars' => array(
				'q' => 'hello',
				'tags' => array('red', 'blue'),
			),
		)));

		$this->check('render() returns navigation markup', true, strpos($out, "role='navigation'") !== false);
		$this->check('render() marks current page item', true, strpos($out, 'MarkupPagerNavOn') !== false);
		$this->check('render() adds aria-current to current item', true, strpos($out, "aria-current='true'") !== false);
		$this->check('render() includes previous link', true, strpos($out, 'MarkupPagerNavPrevious') !== false);
		$this->check('render() includes next link', true, strpos($out, 'MarkupPagerNavNext') !== false);
		$this->check('render() includes scalar getVars', true, strpos($out, 'q=hello') !== false);
		$this->check('render() converts array getVars to CSV by default', true, strpos($out, 'tags=red%2Cblue') !== false);
		$this->check('render() uses supplied baseUrl', true, strpos($out, '/pager-test/') !== false);
		$this->check('isLastPage() false on middle page', false, $pager->isLastPage());
	}

	protected function testLastPageAndNoPagination() {
		$pager = $this->pager();
		$out = $pager->render($this->paginatedArray(5, 35, 10, 30), $this->pagerOptions());

		$this->check('render(last page) includes previous link', true, strpos($out, 'MarkupPagerNavPrevious') !== false);
		$this->check('render(last page) omits next link', false, strpos($out, 'MarkupPagerNavNext') !== false);
		$this->check('isLastPage() true on last page', true, $pager->isLastPage());

		$pager = $this->pager();
		$out = $pager->render($this->paginatedArray(5, 5, 10, 0), $this->pagerOptions());

		$this->check('render() returns blank when no pagination needed', '', $out);
		$this->check('isLastPage() true when no pagination needed', true, $pager->isLastPage());
	}

	protected function testArrayGetVars() {
		$out = $this->pager()->render($this->paginatedArray(), $this->pagerOptions(array(
			'arrayToCSV' => false,
			'getVars' => array(
				'tags' => array('red', 'blue'),
			),
		)));

		$this->check('arrayToCSV=false uses bracket array query vars', true, strpos($out, 'tags%5B%5D=red') !== false);
		$this->check('arrayToCSV=false includes second array value', true, strpos($out, 'tags%5B%5D=blue') !== false);
	}

	protected function testPaginatedArrayHelpers() {
		$items = $this->paginatedArray(10, 35, 10, 10);

		$this->check('getPaginationString(label) reports item range', 'Items 11 to 20 of 35', $items->getPaginationString('Items'));
		$this->check('getPaginationString(usePageNum) reports page range', 'Page 2 of 4', $items->getPaginationString('Page', true));
		$this->check('getPaginationString(options) supports zero label', 'No items found', $items->getPaginationString(array(
			'zeroLabel' => 'No items found',
			'total' => 0,
			'count' => 0,
		)));

		$out = $items->renderPagination($this->pagerOptions(array(
			'listClass' => 'pagination-test',
		)));

		$this->check('renderPagination() delegates to MarkupPagerNav', true, strpos($out, 'pagination-test') !== false);
		$this->check('renderPagination() includes current page', true, strpos($out, 'Page 2, current page') !== false);
	}

	protected function testPageArrayRenderOptionPassThrough() {
		$items = $this->pageArray();
		$out = $items->render(array(
			'pagerBottom' => true,
			'baseUrl' => '/page-array-test/',
			'getVars' => array(
				'q' => 'hello',
			),
			'numPageLinks' => 5,
		));

		$this->check('PageArray::render() includes rendered items', true, strpos($out, "class='PageArray'") !== false);
		$this->check('PageArray::render() passes baseUrl to pager', true, strpos($out, '/page-array-test/') !== false);
		$this->check('PageArray::render() passes getVars to pager', true, strpos($out, 'q=hello') !== false);
		$this->check('PageArray::render() passes numPageLinks to pager', true, strpos($out, 'page=6') !== false);
	}
}
