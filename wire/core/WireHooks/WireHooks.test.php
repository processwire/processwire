<?php namespace ProcessWire;

/**
 * Tests for ProcessWire hooks and HookEvent behavior
 *
 */
class WireTest_WireHooks extends WireTest {

	/**
	 * Hook IDs to remove during cleanup
	 *
	 * @var array
	 *
	 */
	protected $hookIds = array();

	public function execute() {
		$this->check('$hooks is WireHooks', true, $this->wire()->hooks instanceof WireHooks);
		$this->check('HookEvent has unique eid values', true, (new HookEvent())->eid < (new HookEvent())->eid);

		$this->testBeforeAfterAndReplace();
		$this->testHookPropertiesAndMethods();
		$this->testStaticLocalAndRemoval();
		$this->testPriorityCustomDataAndCancel();
		$this->testConditionalHooks();
		$this->testHookEventArguments();
	}

	public function finish() {
		foreach(array_reverse($this->hookIds) as $item) {
			list($object, $hookId) = $item;
			if($object instanceof Wire && $hookId) $object->removeHook($hookId);
		}
		$this->hookIds = array();
	}

	protected function testBeforeAfterAndReplace() {
		$target = $this->wire(new WireHooksTestTarget());

		$before = $this->addTestHook($target, 'addHookBefore', 'combine', function(HookEvent $event) {
			$event->arguments(0, strtoupper($event->arguments(0)));
			$event->arguments('second', strtoupper($event->arguments('second')));
		});

		$after = $this->addTestHook($target, 'addHookAfter', 'combine', function(HookEvent $event) {
			$event->return .= ':after';
		});

		$this->check('before hook modifies arguments and after hook modifies return', 'ALPHA:BETA:after', $target->combine('alpha', 'beta'));
		$this->check('Hook IDs are strings', true, is_string($before) && is_string($after) && strlen($before) && strlen($after));

		$target->removeHook($before);
		$target->removeHook($after);

		$replace = $this->addTestHook($target, 'addHookBefore', 'combine', function(HookEvent $event) {
			$event->return = 'replaced';
			$event->replace = true;
		});

		$this->check('before hook can replace original method', 'replaced', $target->combine('alpha', 'beta'));
		$target->removeHook($replace);
	}

	protected function testHookPropertiesAndMethods() {
		$target = $this->wire(new WireHooksTestTarget());

		$this->addTestHook($target, 'addHookProperty', 'codexProperty', function(HookEvent $event) {
			$event->return = 'property:' . $event->object->name;
		});

		$this->check('hook property can be read as property', 'property:target', $target->codexProperty);

		$this->addTestHook($target, 'addHookMethod', 'codexMethod', function(HookEvent $event) {
			$event->return = $event->arguments(0) + $event->arguments(1);
		});

		$this->check('hook method receives arguments and returns value', 12, $target->codexMethod(5, 7));
		$this->check('hasHook() reports hooked method', true, $target->hasHook('codexMethod()'));
		$this->check('hasHook() reports hooked property', true, $target->hasHook('codexProperty'));
	}

	protected function testStaticLocalAndRemoval() {
		$one = $this->wire(new WireHooksTestTarget());
		$two = $this->wire(new WireHooksTestTarget());

		$this->addTestHook($one, 'addHookAfter', 'echoValue', function(HookEvent $event) {
			$event->return .= ':local';
		});

		$this->check('local hook affects hooked instance', 'x:local', $one->echoValue('x'));
		$this->check('local hook does not affect another instance', 'x', $two->echoValue('x'));

		$staticId = $this->addTestHook($this->wire(), 'addHookAfter', 'WireHooksTestTarget::echoValue', function(HookEvent $event) {
			$event->return .= ':static';
		});

		$this->check('static hook affects first instance', 'x:static:local', $one->echoValue('x'));
		$this->check('static hook affects second instance', 'x:static', $two->echoValue('x'));
		$this->wire()->removeHook($staticId);

		$count = 0;
		$csvId = $this->addTestHook($one, 'addHookAfter', array('combine', 'echoValue'), function(HookEvent $event) use (&$count) {
			$count++;
		});

		$one->combine('a', 'b');
		$one->echoValue('a');
		$this->check('array hook method list attaches one callback to multiple methods', 2, $count);

		$one->removeHook($csvId);
		$one->combine('a', 'b');
		$one->echoValue('a');
		$this->check('removeHook() accepts CSV hook IDs from multiple method hook', 2, $count);

		$selfRemoveCount = 0;
		$this->addTestHook($one, 'addHookAfter', 'echoValue', function(HookEvent $event) use (&$selfRemoveCount) {
			$selfRemoveCount++;
			$event->removeHook(null);
		});

		$one->echoValue('a');
		$one->echoValue('b');
		$this->check('HookEvent::removeHook(null) removes current hook', 1, $selfRemoveCount);
	}

	protected function testPriorityCustomDataAndCancel() {
		$target = $this->wire(new WireHooksTestTarget());
		$order = array();

		$this->addTestHook($target, 'addHookAfter', 'echoValue', function(HookEvent $event) use (&$order) {
			$order[] = '100.2';
			$event->return .= ':b';
		}, array('priority' => 100.2));

		$this->addTestHook($target, 'addHookAfter', 'echoValue', function(HookEvent $event) use (&$order) {
			$order[] = '50';
			$event->return .= ':a';
		}, array('priority' => 50));

		$this->addTestHook($target, 'addHookAfter', 'echoValue', function(HookEvent $event) use (&$order) {
			$order[] = '100.1';
			$event->return .= ':c';
		}, array('priority' => 100.1));

		$value = $target->echoValue('x');
		$this->check('hook priorities run lower numbers first with decimal tie-breaks', array('50', '100.1', '100.2'), $order);
		$this->check('priority hook return modifications run in priority order', 'x:a:c:b', $value);

		$target2 = $this->wire(new WireHooksTestTarget());
		$this->addTestHook($target2, 'addHookBefore', 'echoValue', function(HookEvent $event) {
			$event->codexCustom = 'before-data';
		});
		$this->addTestHook($target2, 'addHookAfter', 'echoValue', function(HookEvent $event) {
			$event->return .= ':' . $event->codexCustom;
		});
		$this->check('custom HookEvent data carries from before hook to after hook', 'x:before-data', $target2->echoValue('x'));

		$target3 = $this->wire(new WireHooksTestTarget());
		$cancelled = false;
		$this->addTestHook($target3, 'addHookAfter', 'echoValue', function(HookEvent $event) {
			$event->return .= ':first';
			$event->cancelHooks = 'after';
		}, array('priority' => 50));
		$this->addTestHook($target3, 'addHookAfter', 'echoValue', function() use (&$cancelled) {
			$cancelled = true;
		}, array('priority' => 100));
		$this->check('cancelHooks=after cancels remaining after hooks', 'x:first', $target3->echoValue('x'));
		$this->check('cancelled after hook did not run', false, $cancelled);
	}

	protected function testConditionalHooks() {
		$ready = $this->wire(new WireHooksTestDataTarget());
		$ready->status = 'ready';
		$draft = $this->wire(new WireHooksTestDataTarget());
		$draft->status = 'draft';
		$count = 0;

		$id = $this->addTestHook($this->wire(), 'addHookAfter', 'WireHooksTestDataTarget(status=ready)::value', function(HookEvent $event) use (&$count) {
			$count++;
			$event->return .= ':matched';
		});

		$this->check('object selector match fires for matching object', 'x:matched', $ready->value('x'));
		$this->check('object selector match skips non-matching object', 'x', $draft->value('x'));
		$this->check('object selector match ran once', 1, $count);
		$this->wire()->removeHook($id);

		$target = $this->wire(new WireHooksTestTarget());
		$this->addTestHook($this->wire(), 'addHookAfter', 'WireHooksTestTarget::inspect(foo=bar)', function(HookEvent $event) {
			$event->return .= ':arg0';
		});
		$this->check('argument selector match works with WireData argument', 'inspect:arg0', $target->inspect($this->newData(array('foo' => 'bar'))));
		$this->check('argument selector match skips non-matching WireData argument', 'inspect', $target->inspect($this->newData(array('foo' => 'baz'))));

		$this->addTestHook($this->wire(), 'addHookAfter', 'WireHooksTestTarget::inspect(0:foo=bar, 1:limit=10)', function(HookEvent $event) {
			$event->return .= ':indexed';
		});
		$this->check('indexed argument selector match works with object and array arguments', 'inspect:arg0:indexed', $target->inspect($this->newData(array('foo' => 'bar')), array('limit' => 10)));
		$this->check('indexed argument selector match skips non-matching array argument', 'inspect:arg0', $target->inspect($this->newData(array('foo' => 'bar')), array('limit' => 5)));

		$stringMatchCount = 0;
		$this->addTestHook($this->wire(), 'addHookAfter', 'WireHooksTestTarget::inspect(foo=bar)', function() use (&$stringMatchCount) {
			$stringMatchCount++;
		});
		$target->inspect('foo=bar');
		$this->check('selector-style argument match does not parse raw selector strings', 0, $stringMatchCount);

		$this->addTestHook($this->wire(), 'addHookAfter', 'WireHooksTestTarget::inspect(<WireData|Page>)', function(HookEvent $event) {
			$event->return .= ':type';
		});
		$this->check('argument type match fires for matching type', 'inspect:arg0:type', $target->inspect($this->newData(array('foo' => 'bar'))));
		$this->check('argument type match skips non-matching type', 'inspect', $target->inspect('foo=bar'));

		$this->addTestHook($this->wire(), 'addHookAfter', 'WireHooksTestTarget::makeData:(name=match)', function(HookEvent $event) {
			$event->return->matched = true;
		});
		$this->check('return value selector match fires for matching return object', true, $target->makeData('match')->matched);
		$this->check('return value selector match skips non-matching return object', null, $target->makeData('skip')->matched);

		$this->addTestHook($this->wire(), 'addHookAfter', 'WireHooksTestTarget::makeData:<WireData>', function(HookEvent $event) {
			$event->return->typeMatched = true;
		});
		$this->check('return value type match fires for matching return type', true, $target->makeData('anything')->typeMatched);
	}

	protected function testHookEventArguments() {
		$target = $this->wire(new WireHooksTestTarget());

		$this->addTestHook($target, 'addHookBefore', 'combine', function(HookEvent $event) {
			$named = $event->argumentsByName();
			$event->arguments('first', $named['first'] . '1');
			$event->arguments('second', $event->argumentsByName('second') . '2');
		});

		$this->check('HookEvent argumentsByName() and named arguments() read/write arguments', 'a1:b2', $target->combine('a', 'b'));
	}

	protected function addTestHook(Wire $object, $methodName, $hookName, $callback, array $options = array()) {
		if($methodName === 'addHookBefore' || $methodName === 'addHookAfter') {
			$hookId = $object->$methodName($hookName, $callback, $options);
		} else {
			$hookId = $object->$methodName($hookName, $callback);
		}
		$this->hookIds[] = array($object, $hookId);
		return $hookId;
	}

	protected function newData(array $data) {
		$item = $this->wire(new WireData());
		$item->setArray($data);
		return $item;
	}
}

class WireHooksTestTarget extends WireData {

	public function __construct() {
		parent::__construct();
		$this->name = 'target';
	}

	public function ___combine($first, $second = '') {
		return "$first:$second";
	}

	public function ___echoValue($value) {
		return $value;
	}

	public function ___inspect($value, $options = array()) {
		return 'inspect';
	}

	public function ___makeData($name) {
		$data = $this->wire(new WireData());
		$data->name = $name;
		return $data;
	}
}

class WireHooksTestDataTarget extends WireData {

	public function ___value($value) {
		return $value;
	}
}
