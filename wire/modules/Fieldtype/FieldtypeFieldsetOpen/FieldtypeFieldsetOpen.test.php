<?php namespace ProcessWire;

/**
 * Tests for FieldtypeFieldsetOpen, FieldtypeFieldsetTabOpen, and FieldtypeFieldsetClose
 *
 */
class WireTest_FieldtypeFieldsetOpen extends WireTest {

	protected $prefix = WireTests::fieldPrefix . 'fieldset';

	public function init() {
		$this->cleanup();
	}

	public function allow() {
		$fields = $this->wire()->fields;
		return $fields->getFieldtype('FieldsetOpen') && $fields->getFieldtype('FieldsetTabOpen') && $fields->getFieldtype('FieldsetClose');
	}

	public function execute() {
		$fields = $this->wire()->fields;

		$this->check('getFieldtype(FieldsetOpen) resolves opener', 'FieldtypeFieldsetOpen', $fields->getFieldtype('FieldsetOpen')->className());
		$this->check('getFieldtype(FieldsetTabOpen) resolves tab opener', 'FieldtypeFieldsetTabOpen', $fields->getFieldtype('FieldsetTabOpen')->className());
		$this->check('getFieldtype(FieldsetClose) resolves closer', 'FieldtypeFieldsetClose', $fields->getFieldtype('FieldsetClose')->className());

		$this->testOpenCreatesClose();
		$this->testTabCreatesClose();
		$this->testSaveRepairsMissingClose();
		$this->testFieldgroupOrder();
	}

	public function finish() {
		$this->cleanup();
	}

	protected function testOpenCreatesClose() {
		$fields = $this->wire()->fields;
		$name = $this->name('open');

		$open = $fields->new('FieldsetOpen', $name, 'WireTests Fieldset');
		$close = $fields->get($name . '_END');

		$this->check('$fields->new(FieldsetOpen) returns generic Field', true, $open instanceof Field);
		$this->check('created opener has FieldtypeFieldsetOpen', 'FieldtypeFieldsetOpen', $open->type->className());
		$this->check('saving opener creates close field', true, $close instanceof Field && $close->id > 0);
		$this->check('created close field has FieldtypeFieldsetClose', 'FieldtypeFieldsetClose', $close->type->className());
		$this->check('opener records closeFieldID', $close->id, (int) $open->get('closeFieldID'));
		$this->check('closer records openFieldID', $open->id, (int) $close->get('openFieldID'));
	}

	protected function testTabCreatesClose() {
		$fields = $this->wire()->fields;
		$name = $this->name('tab');

		$tab = $fields->new('FieldsetTabOpen', $name, array(
			'label' => 'WireTests Tab',
			'modal' => true,
		));
		$close = $fields->get($name . '_END');

		$this->check('$fields->new(FieldsetTabOpen) creates opener', 'FieldtypeFieldsetTabOpen', $tab->type->className());
		$this->check('tab opener saves modal setting', true, (bool) $tab->get('modal'));
		$this->check('saving tab opener creates close field', true, $close instanceof Field && $close->id > 0);
		$this->check('tab close field has FieldtypeFieldsetClose', 'FieldtypeFieldsetClose', $close->type->className());
	}

	protected function testSaveRepairsMissingClose() {
		$fields = $this->wire()->fields;
		$name = $this->name('repair');

		$open = $fields->new('FieldsetOpen', $name, 'WireTests Repair');
		$close = $fields->get($name . '_END');
		$this->check('repair setup created close field', true, $close instanceof Field && $close->id > 0);

		$fields->delete($close);
		$this->check('repair setup deleted close field', null, $fields->get($name . '_END'));

		$fields->save($open);
		$repaired = $fields->get($name . '_END');
		$this->check('saving opener repairs missing close field', true, $repaired instanceof Field && $repaired->id > 0);
		$this->check('repaired close field has FieldtypeFieldsetClose', 'FieldtypeFieldsetClose', $repaired->type->className());
		$this->check('opener closeFieldID updated to repaired close', $repaired->id, (int) $open->get('closeFieldID'));
	}

	protected function testFieldgroupOrder() {
		$fields = $this->wire()->fields;
		$fieldgroups = $this->wire()->fieldgroups;
		$openName = $this->name('group');
		$textName = $this->name('text');
		$fieldgroupName = $this->name('fg');

		$open = $fields->new('FieldsetOpen', $openName, 'WireTests Group');
		$close = $fields->get($openName . '_END');
		$text = $fields->new('text', $textName, 'WireTests Text');

		$fieldgroup = $fieldgroups->newFieldgroup($fieldgroupName, array('title'));
		$fieldgroup->add($open);
		$fieldgroup->add($text);
		$fieldgroup->add($close);
		$fieldgroup->save();

		$fieldgroup = $fieldgroups->get($fieldgroupName);
		$this->check('fieldgroup contains opener', true, $fieldgroup->hasField($open));
		$this->check('fieldgroup contains inner field', true, $fieldgroup->hasField($text));
		$this->check('fieldgroup contains closer', true, $fieldgroup->hasField($close));
		$this->check('fieldgroup order is opener, inner field, closer', array('title', $openName, $textName, $openName . '_END'), $this->fieldNames($fieldgroup));
	}

	protected function name($suffix) {
		return $this->prefix . '_' . $suffix;
	}

	protected function fieldNames(Fieldgroup $fieldgroup) {
		$names = array();
		foreach($fieldgroup as $field) {
			$names[] = $field->name;
		}
		return $names;
	}

	protected function cleanup() {
		$fields = $this->wire()->fields;
		$fieldgroups = $this->wire()->fieldgroups;

		foreach($fieldgroups as $fieldgroup) {
			if(strpos($fieldgroup->name, $this->prefix . '_') !== 0) continue;
			$fieldgroups->delete($fieldgroup);
		}

		$deleteFields = array();
		foreach($fields as $field) {
			if(strpos($field->name, $this->prefix . '_') !== 0) continue;
			$deleteFields[] = $field;
		}

		foreach($deleteFields as $field) {
			foreach($field->getFieldgroups() as $fieldgroup) {
				$fieldgroup->remove($field);
				$fieldgroup->save();
			}
		}

		foreach($deleteFields as $field) {
			if($fields->get($field->id)) $fields->delete($field);
		}
	}
}
