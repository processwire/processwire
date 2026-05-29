<?php namespace ProcessWire;

/**
 * Tests for ProcessWire WireArray
 *
 */
class WireTest_WireArray extends WireTest {

	public function execute() {
		$this->testAddRetrieveAndKeys();
		$this->testSelectorsAndFiltering();
		$this->testOrderingAndRemoval();
		$this->testOutputHelpers();
		$this->testExtraDataAndFactories();
		$this->testChangeTrackingAndIdentity();
	}

	/**
	 * Test adding items and retrieving them in common forms
	 *
	 */
	protected function testAddRetrieveAndKeys() {
		list($alpha, $beta, $gamma) = $this->makeItems();
		$items = $this->wire(new WireArray());
		$return = $items->add($alpha)->append($beta);
		$items[] = $gamma;

		$this->check('add() and append() return $this', true, $return === $items);
		$this->check('count() returns number of items', 3, $items->count());
		$this->check('Countable count() works', 3, count($items));
		$this->check('count property maps to count()', 3, $items->count);
		$this->check('first() returns first item', $alpha, $items->first());
		$this->check('last() returns last item', $gamma, $items->last());
		$this->check('first property maps to first()', $alpha, $items->first);
		$this->check('last property maps to last()', $gamma, $items->last);
		$this->check('get(numeric key) returns item', $beta, $items->get(1));
		$this->check('array access returns item', $gamma, $items[2]);
		$this->check('eq(0) returns first item', $alpha, $items->eq(0));
		$this->check('eq(-1) returns last item', $gamma, $items->eq(-1));
		$this->check('__invoke(first) returns first item', $alpha, $items('first'));
		$this->check('__invoke(numeric) returns item by numeric key', $beta, $items(1));
		$this->check('get(name) matches item name on numeric WireArray', $beta, $items->get('beta'));
		$this->check('get(pipe) returns first matching named item', $gamma, $items->get('missing|gamma'));
		$this->check('get(missing) returns null', null, $items->get('missing'));
		$this->check('array access missing returns false', false, $items['missing']);
		$this->check('has(object) detects item', true, $items->has($alpha));
		$this->check('has(name) detects named item', true, $items->has('beta'));
		$this->check('has(missing) returns false', false, $items->has('missing'));
		$this->check('getArray() preserves keys', array(0, 1, 2), array_keys($items->getArray()));
		$this->check('getValues() reindexes values', array($alpha, $beta, $gamma), $items->getValues());
		$this->check('getKeys() returns keys', array(0, 1, 2), $items->getKeys());
		$this->check('keys property maps to getKeys()', array(0, 1, 2), $items->keys);
		$this->check('values property maps to getValues()', array($alpha, $beta, $gamma), $items->values);

		$subset = $items->get(array(0, 2));
		$this->check('get(array of keys) returns matching keyed array', array(0 => $alpha, 2 => $gamma), $subset);
		$this->check('get(property[]) returns property array', array('Alpha', 'Beta', 'Gamma'), $items->get('title[]'));
		$this->check('get(format string) populates WireArray properties', 'Total: 3', $items->get('Total: {count}'));
	}

	/**
	 * Test selectors and destructive/non-destructive filtering
	 *
	 */
	protected function testSelectorsAndFiltering() {
		list($alpha, $beta, $gamma) = $this->makeItems();
		$items = $this->makeArray($alpha, $beta, $gamma);

		$this->check('get(selector) returns first matching item', $beta, $items->get('category=group-b'));
		$this->check('findOne(selector) returns first matching item', $alpha, $items->findOne('category=group-a'));
		$this->check('findOne(missing selector) returns false', false, $items->findOne('category=missing'));

		$matches = $items->find('category=group-a');
		$this->check('find() returns WireArray', true, $matches instanceof WireArray);
		$this->check('find() returns matching item count', 2, $matches->count());
		$this->check('find() does not modify original', 3, $items->count());

		$items->filter('category=group-a');
		$this->check('filter() modifies array in place', 2, $items->count());
		$this->check('filter() retains matching item', true, $items->has($alpha));
		$this->check('filter() removes non-matching item', false, $items->has($beta));

		$items = $this->makeArray($alpha, $beta, $gamma);
		$items->not('category=group-a');
		$this->check('not() removes matching selector items', 1, $items->count());
		$this->check('not() keeps non-matching item', $beta, $items->first());
	}

	/**
	 * Test ordering, slicing, random helpers and removal
	 *
	 */
	protected function testOrderingAndRemoval() {
		list($alpha, $beta, $gamma, $delta) = $this->makeItems();
		$items = $this->makeArray($alpha, $gamma);

		$items->insertBefore($beta, $gamma);
		$this->check('insertBefore() inserts before existing item', array('alpha', 'beta', 'gamma'), $this->names($items));

		$items->insertAfter($delta, $gamma);
		$this->check('insertAfter() inserts after existing item', array('alpha', 'beta', 'gamma', 'delta'), $this->names($items));

		$items->replace($alpha, $delta);
		$this->check('replace() swaps items when both exist', array('delta', 'beta', 'gamma', 'alpha'), $this->names($items));

		$items->sort('sort');
		$this->check('sort() sorts ascending by property', array('alpha', 'beta', 'gamma', 'delta'), $this->names($items));
		$items->sort('-sort');
		$this->check('sort(-property) sorts descending', array('delta', 'gamma', 'beta', 'alpha'), $this->names($items));

		$slice = $items->slice(1, 2);
		$this->check('slice() returns WireArray', true, $slice instanceof WireArray);
		$this->check('slice() returns requested items', array('gamma', 'beta'), $this->names($slice));
		$this->check('slice() does not modify original', 4, $items->count());

		$reversed = $items->reverse();
		$this->check('reverse() returns reversed copy', array('alpha', 'beta', 'gamma', 'delta'), $this->names($reversed));
		$this->check('reverse() does not modify original', array('delta', 'gamma', 'beta', 'alpha'), $this->names($items));

		$this->check('getNext() returns following item', $gamma, $items->getNext($delta));
		$this->check('getPrev() returns preceding item', $delta, $items->getPrev($gamma));

		$random = $items->getRandom();
		$this->check('getRandom() returns an existing item', true, $items->has($random));
		$randomItems = $items->findRandom(2);
		$this->check('findRandom() always returns WireArray', true, $randomItems instanceof WireArray);
		$this->check('findRandom() returns requested count', 2, $randomItems->count());
		$timedA = $items->findRandomTimed(2, 123);
		$timedB = $items->findRandomTimed(2, 123);
		$this->check('findRandomTimed() fixed seed is repeatable', $this->names($timedA), $this->names($timedB));

		$last = $items->pop();
		$this->check('pop() removes and returns last item', $alpha, $last);
		$first = $items->shift();
		$this->check('shift() removes and returns first item', $delta, $first);
		$this->check('pop()/shift() removed items', array('gamma', 'beta'), $this->names($items));

		$items->remove($gamma);
		$this->check('remove(object) removes item', false, $items->has($gamma));
		$items->removeAll();
		$this->check('removeAll() clears array', 0, $items->count());
	}

	/**
	 * Test each(), implode(), explode() and magic property dispatch
	 *
	 */
	protected function testOutputHelpers() {
		list($alpha, $beta, $gamma) = $this->makeItems();
		$items = $this->makeArray($alpha, $beta, $gamma);

		$this->check('each(property) returns property values', array('Alpha', 'Beta', 'Gamma'), $items->each('title'));
		$this->check('each(array) returns property rows', array(
			array('name' => 'alpha', 'title' => 'Alpha'),
			array('name' => 'beta', 'title' => 'Beta'),
			array('name' => 'gamma', 'title' => 'Gamma'),
		), $items->each(array('name', 'title')));

		$this->check('each(template) renders item tags', '<b>Alpha</b><b>Beta</b><b>Gamma</b>', $items->each('<b>{title}</b>'));
		$this->check('each(callable) concatenates returned strings', 'alpha;beta;gamma;', $items->each(function($item) {
			return $item->name . ';';
		}));
		$this->check('each(callable with key) receives key first', '0:alpha;1:beta;2:gamma;', $items->each(function($key, $item) {
			return "$key:$item->name;";
		}));
		$return = $items->each(function($item) {
			$item->set('visited', true);
		});
		$this->check('each(callable with no return) returns $this', true, $return === $items);
		$this->check('each(callable with no return) still visits items', true, $gamma->visited);

		$this->check('implode(delimiter, property) joins property values', 'Alpha, Beta, Gamma', $items->implode(', ', 'title'));
		$this->check('implode(property omitted) accepts property as first argument', 'AlphaBetaGamma', $items->implode('title'));
		$this->check('implode(callable) joins callback values', 'ALPHA|BETA|GAMMA', $items->implode('|', function($item) {
			return strtoupper($item->name);
		}));
		$this->check('implode() applies prepend/append options', 'Items: Alpha, Beta, Gamma.', $items->implode(', ', 'title', array('prepend' => 'Items: ', 'append' => '.')));

		$this->check('explode(property) returns property values', array('alpha', 'beta', 'gamma'), $items->explode('name'));
		$this->check('explode(array) returns property rows', $items->each(array('name', 'title')), $items->explode(array('name', 'title')));
		$this->check('explode(property, key) keys by requested property', array(
			'alpha' => 'Alpha',
			'beta' => 'Beta',
			'gamma' => 'Gamma',
		), $items->explode('title', array('key' => 'name')));

		$this->check('magic method with no args delegates to explode()', array('Alpha', 'Beta', 'Gamma'), $items->title());
		$this->check('magic method with delimiter delegates to implode()', 'Alpha / Beta / Gamma', $items->title(' / '));
		$this->check('__invoke(template) delegates to each()', '<i>Alpha</i><i>Beta</i><i>Gamma</i>', $items('<i>{title}</i>'));
	}

	/**
	 * Test extra data and factory helpers
	 *
	 */
	protected function testExtraDataAndFactories() {
		list($alpha, $beta) = $this->makeItems();
		$items = $this->makeArray($alpha);

		$items->data('total', 10);
		$this->check('data(key, value) stores extra data', 10, $items->data('total'));
		$items->data(array('page' => 2, 'limit' => 25));
		$this->check('data(array) merges extra data', 25, $items->data('limit'));
		$this->check('data(array of keys) returns requested values', array('total' => 10, 'missing' => null), $items->data(array('total', 'missing')));
		$items->removeData('total');
		$this->check('removeData() removes extra data value', null, $items->data('total'));
		$items->data(false, 'page');
		$this->check('data(false, key) removes extra data value', null, $items->data('page'));
		$items->data(array('only' => 'value'), true);
		$this->check('data(array, true) replaces all extra data', array('only' => 'value'), $items->data());

		$new = $items->makeNew();
		$this->check('makeNew() returns same concrete class', get_class($items), get_class($new));
		$this->check('makeNew() returns empty array', 0, $new->count());

		$copy = $items->makeCopy();
		$this->check('makeCopy() preserves item count', $items->count(), $copy->count());
		$this->check('makeCopy() is a distinct object', false, $copy === $items);

		$static = WireArray::new(array($alpha, $beta));
		$this->check('WireArray::new(array) imports items', 2, $static->count());

		$function = WireArray(array($alpha, $beta));
		$this->check('WireArray() function imports items', 2, $function->count());
	}

	/**
	 * Test identity and change tracking
	 *
	 */
	protected function testChangeTrackingAndIdentity() {
		list($alpha, $beta, $gamma) = $this->makeItems();
		$items = $this->makeArray($alpha, $beta);
		$same = $this->makeArray($alpha, $beta);
		$other = $this->makeArray($beta, $alpha);

		$this->check('isIdentical() true for same items/order', true, $items->isIdentical($same));
		$this->check('isIdentical() false for different order', false, $items->isIdentical($other));
		$this->check('isIdentical(strict=false) compares string representation', true, $items->isIdentical($same, false));

		$items->setTrackChanges(true);
		$items->add($gamma);
		$this->check('getItemsAdded() tracks added item', array($gamma), $items->getItemsAdded());
		$items->remove($alpha);
		$this->check('getItemsRemoved() tracks removed item', array($alpha), $items->getItemsRemoved());
		$items->resetTrackChanges();
		$this->check('resetTrackChanges() clears added items', array(), $items->getItemsAdded());
		$this->check('resetTrackChanges() clears removed items', array(), $items->getItemsRemoved());

		$combined = $same->and($gamma);
		$this->check('and(item) returns new WireArray', true, $combined instanceof WireArray);
		$this->check('and(item) leaves original unchanged', 2, $same->count());
		$this->check('and(item) appends provided item', true, $combined->has($gamma));
	}

	/**
	 * Create test items
	 *
	 * @return array
	 *
	 */
	protected function makeItems() {
		return array(
			$this->item('alpha', 'Alpha', 1, 'group-a'),
			$this->item('beta', 'Beta', 2, 'group-b'),
			$this->item('gamma', 'Gamma', 3, 'group-a'),
			$this->item('delta', 'Delta', 4, 'group-b'),
		);
	}

	/**
	 * Create one test item
	 *
	 * @param string $name
	 * @param string $title
	 * @param int $sort
	 * @param string $category
	 * @return WireTestArrayItem
	 *
	 */
	protected function item($name, $title, $sort, $category) {
		$item = $this->wire(new WireTestArrayItem());
		$item->setArray(array(
			'name' => $name,
			'title' => $title,
			'sort' => $sort,
			'category' => $category,
		));
		return $item;
	}

	/**
	 * Create a WireArray containing items
	 *
	 * @return WireArray
	 *
	 */
	protected function makeArray() {
		$items = $this->wire(new WireArray());
		foreach(func_get_args() as $item) {
			if($item) $items->add($item);
		}
		return $items;
	}

	/**
	 * Return item names with numeric indexes
	 *
	 * @param WireArray $items
	 * @return array
	 *
	 */
	protected function names(WireArray $items) {
		return array_values($items->explode('name'));
	}
}

/**
 * Stringable WireData item for WireArray tests
 *
 */
class WireTestArrayItem extends WireData {

	public function __toString() {
		return (string) $this->get('name');
	}
}
