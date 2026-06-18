<?php namespace ProcessWire;

/**
 * Tests for ProcessWire Selectors and Selector
 *
 */
class WireTest_Selectors extends WireTest {

	public function execute() {
		$this->testStringParsing();
		$this->testArrayParsing();
		$this->testMatching();
		$this->testInspection();
		$this->testStaticHelpers();
		$this->testSelectorObjects();
	}

	/**
	 * Test selector string parsing
	 *
	 */
	protected function testStringParsing() {
		$selectors = $this->wire(new Selectors("template=basic-page, title%=about, sort=-modified, limit=5"));

		$this->check('Selectors can be constructed from string', true, $selectors instanceof Selectors);
		$this->check('Selectors count parsed conditions', 4, $selectors->count());

		$first = $selectors->first();
		$this->check('Selector field() returns field string', 'template', $first->field());
		$this->check('Selector operator() returns operator', '=', $first->operator());
		$this->check('Selector value() returns value', 'basic-page', $first->value());
		$this->check('Selectors casts back to selector string', 'template=basic-page, title%=about, sort=-modified, limit=5', (string) $selectors);

		$selector = $this->wire(new Selectors("title|body|summary%=foo|bar"))->first();
		$this->check('Selector fields() returns OR fields array', array('title', 'body', 'summary'), $selector->fields());
		$this->check('Selector field() joins OR fields by default', 'title|body|summary', $selector->field());
		$this->check('Selector field(false) returns OR fields array', array('title', 'body', 'summary'), $selector->field(false));
		$this->check('Selector values() returns OR values array', array('foo', 'bar'), $selector->values());
		$this->check('Selector value() joins OR values by default', 'foo|bar', $selector->value());
		$this->check('Selector value(false) returns OR values array', array('foo', 'bar'), $selector->value(false));

		$not = $this->wire(new Selectors('!status=hidden'))->first();
		$this->check('Selector detects NOT prefix', true, $not->not);
	}

	/**
	 * Test selector array parsing
	 *
	 */
	protected function testArrayParsing() {
		$selectors = $this->wire(new Selectors(array(
			'template' => 'basic-page',
			'title%=' => 'about',
			array('created', '>', '2024-01-01'),
			'sort=-date',
			'limit' => 3,
		)));

		$this->check('Array selector parses mixed formats', 5, $selectors->count());
		$this->check('Associative array defaults to equals operator', '=', $selectors->first()->operator());
		$this->check('Operator appended to key is parsed', '%=', $selectors->getSelectorByField('title')->operator());
		$this->check('Indexed array format parses operator', '>', $selectors->getSelectorByField('created')->operator());
		$this->check('Self-contained string selector parses field', '-date', $selectors->getSelectorByField('sort')->value());
		$this->check('Associative integer value stays integer', 3, $selectors->getSelectorByField('limit')->value());

		$verbose = $this->wire(new Selectors(array(
			array(
				'fields' => array('status', 'state'),
				'operator' => '=',
				'values' => array('active', 'pending'),
				'not' => true,
				'or' => 'workflow',
			),
		)));
		$selector = $verbose->first();
		$this->check('Verbose array accepts fields alias', array('status', 'state'), $selector->fields());
		$this->check('Verbose array accepts values alias', array('active', 'pending'), $selector->values());
		$this->check('Verbose array accepts not flag', true, $selector->not);
		$this->check('Verbose array accepts or group alias', 'workflow', $selector->group);
		$this->check('Verbose array group uses parenthesis quote', '(', $selector->quote);

		$find = $this->wire(new Selectors(array(
			array(
				'field' => 'children',
				'find' => array('title%=' => 'about'),
			),
		)));
		$value = $find->first()->getValue();
		$this->check('Verbose array find creates sub-selector value', true, $value instanceof Selectors);
		$this->check('Verbose array find marks value with bracket quote', '[', $find->first()->quote);

		try {
			$this->wire(new Selectors(array(
				array(
					'field' => 'status',
					'value' => 'draft',
					'whitelist' => array('active', 'pending'),
				),
			)));
			$this->fail('Whitelist should reject disallowed value');
		} catch(WireException $e) {
			$this->ok('Whitelist rejects disallowed value');
		}

		try {
			$this->wire(new Selectors(array('bad field' => 'value')));
			$this->fail('Invalid field name should throw');
		} catch(WireException $e) {
			$this->ok('Invalid array field name throws');
		}
	}

	/**
	 * Test matching WireData objects
	 *
	 */
	protected function testMatching() {
		$item = $this->wire(new WireData());
		$item->color = 'blue';
		$item->qty = 5;
		$item->title = 'About ProcessWire';
		$item->summary = 'Framework CMS';
		$item->parent = $this->wire(new WireData());
		$item->parent->title = 'Root';

		$this->check('matches() returns true when all conditions match', true, $this->wire(new Selectors('color=blue, qty>3'))->matches($item));
		$this->check('matches() returns false when condition fails', false, $this->wire(new Selectors('color=red, qty>3'))->matches($item));
		$this->check('matches() supports OR fields', true, $this->wire(new Selectors('headline|title%=ProcessWire'))->matches($item));
		$this->check('matches() supports OR values', true, $this->wire(new Selectors('color=red|blue'))->matches($item));
		$this->check('matches() supports NOT selectors', true, $this->wire(new Selectors('!color=red'))->matches($item));
		$this->check('matches() resolves dot syntax via getDot()', true, $this->wire(new Selectors('parent.title=Root'))->matches($item));

		$group = $this->wire(new Selectors(array(
			array(
				'title%=' => 'Missing',
				'summary%=' => 'CMS',
			),
		)));
		$this->check('matches() supports OR groups from nested arrays', true, $group->matches($item));
	}

	/**
	 * Test selector inspection helpers
	 *
	 */
	protected function testInspection() {
		$selectors = $this->wire(new Selectors('title|body%=foo|bar, parent.name=home, limit=5, title=exact'));

		$this->check('getSelectorByField() excludes OR fields by default', null, $selectors->getSelectorByField('body'));
		$this->check('getSelectorByField(or=true) includes OR fields', '%=', $selectors->getSelectorByField('body', true)->operator());
		$this->check('getSelectorByField(all=true) returns array', true, is_array($selectors->getSelectorByField('body', true, true)));
		$this->check('getSelectorByFieldValue() finds exact field/value', '=', $selectors->getSelectorByFieldValue('title', 'exact')->operator());
		$this->check('getSelectorByFieldValue(or=true) finds OR value', '%=', $selectors->getSelectorByFieldValue('title', 'bar', true)->operator());

		$this->check('getAllFields() includes subfields', array(
			'title' => 'title',
			'body' => 'body',
			'parent.name' => 'parent.name',
			'limit' => 'limit',
		), $selectors->getAllFields());

		$this->check('getAllFields(false) collapses subfields', array(
			'title' => 'title',
			'body' => 'body',
			'parent' => 'parent',
			'limit' => 'limit',
		), $selectors->getAllFields(false));

		$values = $selectors->getAllValues();
		$this->check('getAllValues() includes first OR value', true, isset($values['foo']));
		$this->check('getAllValues() includes second OR value', true, isset($values['bar']));
		$this->check('getAllValues() includes scalar value', true, isset($values['home']));
	}

	/**
	 * Test static helper methods
	 *
	 */
	protected function testStaticHelpers() {
		$this->check('stringHasOperator() detects selector operator', true, Selectors::stringHasOperator('title=foo'));
		$this->check('stringHasOperator() rejects math-like string', false, Selectors::stringHasOperator('1+1'));
		$this->check('stringHasOperator(getOperator) returns operator', '%=', Selectors::stringHasOperator('title%=foo', true));
		$this->check('stringHasSelector() detects valid selector', true, Selectors::stringHasSelector('template=basic-page, limit=5'));
		$this->check('stringHasSelector() rejects non-selector text', false, Selectors::stringHasSelector('hello world'));
		$this->check('isOperator() detects valid operator', true, Selectors::isOperator('%='));
		$this->check('isOperator(returnOperator) corrects reversed operator', '~%=', Selectors::isOperator('%~=', true));
		$this->check('getOperatorType() returns type name', 'Equal', Selectors::getOperatorType('='));
		$this->check('getOperatorType(is=true) returns boolean', true, Selectors::getOperatorType('=', true));

		$operators = Selectors::getOperators(array('getIndexType' => 'none'));
		$this->check('getOperators(none) returns operator list', true, in_array('=', $operators, true));

		$info = Selectors::getOperators(array(
			'operator' => '%=',
			'getValueType' => 'verbose',
		));
		$this->check('getOperators(operator, verbose) returns operator info', '%=', $info['operator']);
		$this->check('getOperators(operator, verbose) includes class', 'SelectorContainsLike', $info['class']);
		$this->check('getOperatorChars() includes equals char', true, in_array('=', Selectors::getOperatorChars(), true));
		$this->check('getReservedChars() includes OR char', '|', Selectors::getReservedChars()['or']);

		$selector = Selectors::newSelector('title', '%=', 'about');
		$this->check('newSelector() creates matching subclass', true, $selector instanceof SelectorContainsLike);
		$this->check('newSelector() sets field', 'title', $selector->field());
		$this->check('newSelector() sets value', 'about', $selector->value());

		$this->check('getSelectorByOperator(instance) returns Selector', true, Selectors::getSelectorByOperator('%=') instanceof SelectorContainsLike);
		$this->check('getSelectorByOperator(class) returns short class', 'SelectorContainsLike', Selectors::getSelectorByOperator('%=', 'class'));
		$this->check('getSelectorByOperator(compareType) returns integer', true, is_int(Selectors::getSelectorByOperator('%=', 'compareType')));

		try {
			Selectors::newSelector('title', 'xyz', 'about');
			$this->fail('newSelector() should throw for invalid operator');
		} catch(WireException $e) {
			$this->ok('newSelector() throws for invalid operator');
		}
	}

	/**
	 * Test individual Selector object API
	 *
	 */
	protected function testSelectorObjects() {
		$selector = new SelectorEqual('title|headline', array('About', 'Home'));

		$this->check('SelectorEqual is Selector', true, $selector instanceof Selector);
		$this->check('Selector operator is fixed by subclass', '=', $selector->operator());
		$this->check('Selector magic field returns array for OR fields', array('title', 'headline'), $selector->field);
		$this->check('Selector magic fields returns array', array('title', 'headline'), $selector->fields);
		$this->check('Selector magic value returns array for OR values', array('About', 'Home'), $selector->value);
		$this->check('Selector magic values returns array', array('About', 'Home'), $selector->values);
		$this->check('Selector getField(string) joins fields', 'title|headline', $selector->getField('string'));
		$this->check('Selector getField(array) returns fields array', array('title', 'headline'), $selector->getField('array'));
		$this->check('Selector getValue(array) returns values array', array('About', 'Home'), $selector->getValue('array'));
		$this->check('Selector str property returns selector string', 'title|headline=About|Home', $selector->str);

		$selector->operator = '!=';
		$this->check('Selector operator cannot be changed', '=', $selector->operator());

		$item = $this->wire(new WireData());
		$item->title = 'About';
		$single = new SelectorEqual('title', 'About');
		$this->check('Single Selector matches item', true, $single->matches($item));
	}
}
