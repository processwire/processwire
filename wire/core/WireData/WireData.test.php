<?php namespace ProcessWire;

/**
 * Tests for ProcessWire WireData
 *
 */
class WireTest_WireData extends WireTest {

	public function execute() {
		$this->testGetSetAccess();
		$this->testLowLevelDataAccess();
		$this->testDotSyntax();
		$this->testIterationAndArrayAccess();
		$this->testChangeTracking();
		$this->testAndMethod();
	}

	/**
	 * Test normal get/set access
	 *
	 */
	protected function testGetSetAccess() {
		$item = $this->wire(new WireData());
		$return = $item->set('title', 'Hello')->set('status', 1);

		$this->check('set() returns $this', true, $return === $item);
		$this->check('get() returns set value', 'Hello', $item->get('title'));
		$this->check('property access returns set value', 'Hello', $item->title);
		$this->check('array access returns set value', 'Hello', $item['title']);

		$item->color = 'blue';
		$this->check('__set() stores direct property value', 'blue', $item->get('color'));

		$item['weight'] = 42;
		$this->check('offsetSet() stores array value', 42, $item->get('weight'));

		$item->setArray(array(
			'a' => '',
			'b' => 0,
			'c' => 'fallback',
		));
		$this->check('setArray() stores multiple values', 'fallback', $item->get('c'));
		$this->check('get(pipe) skips empty string and zero values', 'fallback', $item->get('a|b|c'));
		$this->check('get(missing) returns null', null, $item->get('missing'));
		$this->check('__invoke() returns get() value', 'Hello', $item('title'));
	}

	/**
	 * Test data() and getArray()
	 *
	 */
	protected function testLowLevelDataAccess() {
		$item = $this->wire(new WireTestWireDataOverride());
		$item->set('name', 'alice');
		$item->data('raw', 'stored');

		$this->check('data(key, value) stores raw value', 'stored', $item->data('raw'));
		$this->check('data() bypasses overridden get()', 'alice', $item->data('name'));
		$this->check('get() can be overridden independently from data()', 'ALICE', $item->get('name'));

		$item->data(array('color' => 'blue'));
		$this->check('data(array) merges values', 'blue', $item->data('color'));
		$this->check('data(array) preserves existing values by default', 'alice', $item->data('name'));

		$item->data(array('only' => 'value'), true);
		$this->check('data(array, true) replaces data array', array('only' => 'value'), $item->getArray());

		$item->set('data', array('merged' => 'yes'));
		$this->check("set('data', array) merges through setArray()", 'yes', $item->data('merged'));
		$this->check("set('data', array) does not create data key", false, array_key_exists('data', $item->getArray()));
	}

	/**
	 * Test dot syntax
	 *
	 */
	protected function testDotSyntax() {
		$parent = $this->wire(new WireData());
		$child = $this->wire(new WireData());
		$grandchild = $this->wire(new WireData());
		$grandchild->set('title', 'Grandchild title');
		$child->set('title', 'Child title');
		$child->set('grandchild', $grandchild);
		$parent->set('child', $child);

		$this->check('getDot() reads one nested WireData level', 'Child title', $parent->getDot('child.title'));
		$this->check('getDot() reads multiple nested WireData levels', 'Grandchild title', $parent->getDot('child.grandchild.title'));
		$this->check('getDot() returns null for missing nested value', null, $parent->getDot('child.missing.title'));

		$array = $this->wire(new WireArray());
		$array->add($child);
		$array->add($grandchild);
		$parent->set('items', $array);

		$this->check('getDot() reads WireArray count property', 2, $parent->getDot('items.count'));
		$this->check('getDot() maps WireArray item property values', array('Child title', 'Grandchild title'), $parent->getDot('items.title'));
		$this->check('getDot() does not expose API variables', null, $parent->getDot('pages.count'));
	}

	/**
	 * Test iteration and ArrayAccess details
	 *
	 */
	protected function testIterationAndArrayAccess() {
		$item = $this->wire(new WireData());
		$item->setArray(array(
			'title' => 'Hello',
			'empty' => '',
			'zero' => 0,
			'nothing' => null,
		));

		$values = array();
		foreach($item as $key => $value) {
			$values[$key] = $value;
		}

		$this->check('getIterator() iterates data values', $item->getArray(), $values);
		$this->check('offsetExists() true for empty string value', true, isset($item['empty']));
		$this->check('offsetExists() true for zero value', true, isset($item['zero']));
		$this->check('offsetExists() false for null value', false, isset($item['nothing']));
		$this->check('offsetGet() returns false for missing value', false, $item['missing']);
		$this->check('offsetGet() returns false for null value', false, $item['nothing']);

		$this->check('offsetUnset() returns true for existing value', true, $item->offsetUnset('title'));
		$this->check('offsetUnset() removes value', null, $item->get('title'));
		$this->check('offsetUnset() returns false for missing value', false, $item->offsetUnset('title'));
	}

	/**
	 * Test change tracking hooks in set/remove helpers
	 *
	 */
	protected function testChangeTracking() {
		$item = $this->wire(new WireData());
		$item->setTrackChanges(true);

		$item->set('title', 'Hello');
		$this->check('set() tracks changed property', true, in_array('title', $item->getChanges(), true));

		$item->resetTrackChanges();
		$item->setQuietly('title', 'Quiet');
		$this->check('setQuietly() does not track changed property', array(), $item->getChanges());

		$item->set('title', 'Tracked');
		$item->resetTrackChanges();
		$item->remove('title');
		$this->check('remove() tracks unset property', true, in_array('unset:title', $item->getChanges(), true));

		$item->set('title', 'Again');
		$item->resetTrackChanges();
		$item->removeQuietly('title');
		$this->check('removeQuietly() does not track unset property', array(), $item->getChanges());

		$item->resetTrackChanges();
		$item->data('raw', 'value');
		$this->check('data(key, value) bypasses change tracking', array(), $item->getChanges());
	}

	/**
	 * Test hookable and() method
	 *
	 */
	protected function testAndMethod() {
		$item = $this->wire(new WireData());
		$other = $this->wire(new WireData());
		$third = $this->wire(new WireData());
		$item->set('related', $other);

		$array = $item->and();
		$this->check('and() returns WireArray', true, $array instanceof WireArray);
		$this->check('and() with no argument wraps current item', 1, $array->count());
		$this->check('and() result contains current item', true, $array->has($item));

		$array = $item->and($other);
		$this->check('and(WireData) includes current item', true, $array->has($item));
		$this->check('and(WireData) includes provided item', true, $array->has($other));

		$items = $this->wire(new WireArray());
		$items->add($other);
		$items->add($third);
		$array = $item->and($items);
		$this->check('and(WireArray) prepends current item', $item, $array->first());
		$this->check('and(WireArray) includes all provided items', 3, $array->count());

		$array = $item->and('related');
		$this->check('and(property name) resolves WireData property', true, $array->has($other));
	}
}

/**
 * WireData subclass used to prove data() bypasses overridden get()
 *
 */
class WireTestWireDataOverride extends WireData {

	public function get($key) {
		$value = parent::get($key);
		if($key === 'name' && is_string($value)) return strtoupper($value);
		return $value;
	}
}
