<?php namespace ProcessWire;

/**
 * Class FieldtypeRepeaterConfigHelper
 * 
 * Helper class for configuring repeater fields
 *
 */

class FieldtypeRepeaterConfigHelper extends Wire {

	/**
	 * @var Field
	 * 
	 */
	protected $field;

	/**
	 * Construct
	 * 
	 * @param Field $field
	 * 
	 */
	public function __construct(Field $field) {
		$this->field = $field;
	}

	/**
	 * @return bool
	 * 
	 */
	protected function isSingleMode() {
		$schema = $this->field->type->getDatabaseSchema($this->field);
		$singleMode = !isset($schema['count']); 
		return $singleMode;
	}
	/**
	 * Return configuration fields definable for each FieldtypePage
	 *
	 * @param InputfieldWrapper $inputfields
	 * @param Template $template
	 * @param Page $parent
	 * @return InputfieldWrapper
	 *
	 */
	public function getConfigInputfields(InputfieldWrapper $inputfields, Template $template, Page $parent) {

		$field = $this->field;
		$languages = $this->wire('languages');

		if(count($template->fieldgroup)) {
			foreach($template->fieldgroup as $f) {
				$f = $template->fieldgroup->getFieldContext($f);
				if($f->get('required') && $f->get('requiredIf')) {
					$this->warning(sprintf(
						$this->_('Repeater field "%s" has a "required only if" dependency setting, and this is not supported in Repeaters.'),
						$f->name
					));
				}
			}
		} else {
			$this->message($this->_('Please add fields to this repeater from the "details" tab.'));
		}

		/** @var InputfieldHidden $f */
		$f = $this->modules->get('InputfieldHidden');
		$f->attr('name', 'template_id');
		$f->label = 'Repeater Template ID';
		$f->attr('value', $template->id);
		$inputfields->add($f);

		$f = $this->modules->get('InputfieldHidden');
		$f->attr('name', 'parent_id');
		$f->label = 'Repeater Parent ID';
		$f->attr('value', $parent->id);
		$inputfields->add($f);

		// -------------------------------------------------

		/** @var InputfieldAsmSelect $select */
		$select = $this->modules->get('InputfieldAsmSelect');
		$select->label = $this->_x('Repeater fields', 'field-label');
		$select->icon = 'cube';
		$select->description = $this->_('Define the fields that are used by this repeater. You may also drag and drop fields to the desired order.'); // Fields definition, description
		$select->attr('name', 'repeaterFields');
		$select->attr('id', 'repeaterFields');
		$select->attr('title', $this->_('Add Field'));
		$select->setAsmSelectOption('sortable', true);
		$select->setAsmSelectOption('fieldset', true);
		$select->setAsmSelectOption('editLink', $this->wire('config')->urls->admin . "setup/field/edit?id={value}&fieldgroup_id={$template->fieldgroup->id}&modal=1&process_template=1");
		$select->setAsmSelectOption('hideDeleted', false);

		foreach($template->fieldgroup as $f) {
			/** @var Field $f */
			$f = $template->fieldgroup->getField($f->id, true); // get in context
			$columnWidth = (int) $f->get('columnWidth');

			$attrs = array(
				'selected' => 'selected',
				'data-status' => str_replace('Fieldtype', '', $f->type) . ' ' . ($columnWidth > 0 ? $columnWidth . '%': '100%'),
				'data-desc' => $f->getLabel(),
			);
			$icon = $f->getIcon();
			if($icon) $attrs['data-handle'] = "<i class='fa fa-fw fa-$icon'></i>";
			$select->addOption($f->id, $f->name, $attrs);
		}

		foreach($this->wire('fields') as $f) {
			if($template->fieldgroup->has($f)) continue;
			if($f->name == $this->field->name) continue;
			if(($f->flags & Field::flagPermanent) && !$this->wire('config')->advanced) continue;
			$name = $f->name;
			if($f->flags & Field::flagSystem) $name .= "*";
			$columnWidth = (int) $f->get('columnWidth');
			$attrs = array(
				'data-desc' => $f->getLabel(),
				'data-status' => str_replace('Fieldtype', '', $f->type) . ' ' . ($columnWidth > 0 ? $columnWidth . '%': '100%'),
			);
			$icon = $f->getIcon();
			if($icon) $attrs['data-handle'] = "<i class='fa fa-fw fa-$icon'></i>";
			$select->addOption($f->id, $name, $attrs);
		}

		if($this->wire('config')->debug) $select->notes = "This repeater uses template '$template' and parent '{$parent->path}'";
		$inputfields->add($select);
		
		if($this->isSingleMode()) return $inputfields; 
		
		// all of the following fields are not applicable to single-page mode (i.e. FieldtypeFieldsetPage)

		// -------------------------------------------------

		$f = $this->wire('modules')->get('InputfieldText');
		$f->attr('name', 'repeaterTitle');
		$f->attr('value', $field->get('repeaterTitle'));
		$f->label = $this->_('Repeater item labels');
		$f->icon = 'list-ul';
		$f->collapsed = Inputfield::collapsedBlank;
		$f->description = $this->_('Enter the field name to use for repeater labels in the page editor, or leave blank to auto-generate.');
		$f->description .= ' ' . $this->_('To use multiple fields, or to specify your own format string, surround field names in {brackets}.');
		$f->description .= ' ' . $this->_('To include a repeater index number with each label, add "#n" somewhere in your format string.');
		$f->notes = $this->_('Example: #n: {title}');
		$inputfields->add($f);

		// -------------------------------------------------

		$f = $this->wire('modules')->get('InputfieldText');
		$f->attr('name', 'repeaterAddLabel');
		$f->attr('value', $field->get('repeaterAddLabel'));
		$f->label = $this->_('Label for adding new item');
		$f->icon = 'plus-circle';
		$f->collapsed = Inputfield::collapsedBlank;
		$f->description = $this->_('Enter the label you want to use for the "Add New Item" text/button, or leave blank for the default.');
		if($languages) {
			$f->useLanguages = true;
			foreach($languages as $language) {
				if($language->isDefault()) continue;
				$f->set("value$language", $field->get("repeaterAddLabel$language"));
			}
		}
		$inputfields->add($f);

		// -------------------------------------------------

		/** @var InputfieldRadios $f */
		$f = $this->wire('modules')->get('InputfieldRadios');
		$f->attr('name', 'repeaterCollapse');
		$f->label = $this->_('Repeater item visibility in editor');
		$f->description = $this->_('Repeater items can be open or collapsed (requiring a click to open). Collapsed mode is more convenient for sorting and seeing all your items together.');
		$f->icon = 'sun-o';
		$f->addOption(FieldtypeRepeater::collapseExisting, $this->_('New items open, existing items collapsed (recommended for most cases)'));
		$f->addOption(FieldtypeRepeater::collapseNone, $this->_('Items always open (disables dynamic loading for existing items)'));
		$f->addOption(FieldtypeRepeater::collapseAll, $this->_('Items always collapsed'));
		$value = (int) $field->get('repeaterCollapse');
		if($value == 2) $value = FieldtypeRepeater::collapseExisting; // to account for previous version selection
		if($value == 1) $value = FieldtypeRepeater::collapseAll; // to account for prev
		$f->attr('value', $value);
		$inputfields->add($f);

		// -------------------------------------------------

		$f = $this->wire('modules')->get('InputfieldRadios');
		$f->attr('name', 'repeaterLoading');
		$f->label = $this->_('Repeater dynamic loading (AJAX) in editor');
		$f->description = $this->_('Which items should be dynamically loaded with AJAX in the page editor? If you find your repeater uses a field that does not work with dynamic loading, it may be necessary to turn this feature off.');
		$f->addOption(FieldtypeRepeater::loadingAll, $this->_('Existing and new items (recommended, especially for repeaters that might have LOTS of items)'));
		$f->addOption(FieldtypeRepeater::loadingNew, $this->_('New items only (good for repeaters that will have only a FEW items)'));
		$f->addOption(FieldtypeRepeater::loadingOff, $this->_('Off'));
		$f->icon = 'refresh';
		$value = $field->get('repeaterLoading');
		if($field->get('noAjaxAdd') && is_null($value)) $value = FieldtypeRepeater::loadingOff; // to account for previous noAjaxAdd option
			else if(is_null($value)) $value = FieldtypeRepeater::loadingAll;
		$f->attr('value', (int) $value);
		$inputfields->add($f);
		
		// -------------------------------------------------
		
		$f = $this->wire('modules')->get('InputfieldCheckbox');
		$f->attr('name', 'rememberOpen');
		$f->label = $this->_('Remember which repeater items are open?');
		$f->description = $this->_('When checked, opened repeater items remain open after saving or reloading from the page editor (unless the user closes them).');
		$f->icon = 'lightbulb-o';
		if((int) $field->get('rememberOpen')) $f->attr('checked', 'checked');
		$f->columnWidth = 50;
		$inputfields->add($f);
		
		// -------------------------------------------------
		
		$f = $this->wire('modules')->get('InputfieldCheckbox');
		$f->attr('name', 'accordionMode');
		$f->label = $this->_('Use accordion mode?');
		$f->description = $this->_('When checked, only one repeater item will be open at a time.');
		$f->icon = 'map-o';
		if((int) $field->get('accordionMode')) $f->attr('checked', 'checked');
		$f->columnWidth = 50;
		$inputfields->add($f);

		// -------------------------------------------------
	
		$value = (int) $field->get('repeaterMaxItems');
		$f = $this->wire('modules')->get('InputfieldInteger');
		$f->attr('name', 'repeaterMaxItems');
		$f->attr('value', $value > 0 ? $value : '');
		$f->label = $this->_('Maximum number of items');
		$f->description = $this->_('If you need to limit the number of items allowed, enter the limit here (0=no limit).');
		$f->icon = 'hand-stop-o';
		$f->columnWidth = 50;
		$inputfields->add($f);
		
		// -------------------------------------------------
		
		$value = (int) $field->get('repeaterMinItems');
		$f = $this->wire('modules')->get('InputfieldInteger');
		$f->attr('name', 'repeaterMinItems');
		$f->attr('value', $value > 0 ? $value : '');
		$f->label = $this->_('Minimum number of items');
		$f->description = $this->_('This many items will always be open and ready-to-edit (0=no minimum).');
		$f->icon = 'hand-peace-o';
		$f->columnWidth = 50;
		$inputfields->add($f);
		
		// -------------------------------------------------
		
		$value = (int) $field->get('repeaterDepth');
		$f = $this->wire('modules')->get('InputfieldInteger');
		$f->attr('name', 'repeaterDepth');
		$f->attr('value', $value > 0 ? $value : '');
		$f->label = $this->_('Item depth');
		$f->collapsed = Inputfield::collapsedBlank;
		$f->description = $this->_('To support items with depth, enter the max allowed depth, or leave blank to disable.');
		$f->description .= ' ' . $this->_('When editing a repeater, you can change item depth by clicking the repeater item drag arrows and dragging the item right or left.');
		$f->notes = $this->_('Depths are zero-based, meaning a depth of 3 allows depths 0, 1, 2 and 3.');
		$f->notes .= ' ' . $this->_('Depth can be accessed from a repeater page item via `$item->depth`.');
		$f->icon = 'indent';
		$inputfields->add($f);

		// -------------------------------------------------

		/** @var FieldtypeRepeater $fieldtype */
		$fieldtype = $this->field->type;
		$numOldReady = $fieldtype->countOldReadyPages($field);
		if($numOldReady) {
			// @todo: should we just do this automatically?
			$f = $this->wire('modules')->get('InputfieldCheckbox');
			$f->attr('name', '_deleteOldReady');
			$f->label = $this->_('Delete old/unused repeater items?');
			$f->description = sprintf($this->_('There are **%d** old/unused repeater item(s), check this box to delete them.'), $numOldReady);
			$f->notes = $this->_('A repeater item is considered old if it is at least 3 days and not yet been populated or published.');
			$f->icon = 'warning';
			$inputfields->add($f);
		}

		// -------------------------------------------------

		/** TBA
		if(is_null($field->repeaterMaxItems)) $field->repeaterMaxItems = self::defaultRepeaterMaxItems; 
		$input = wire('modules')->get('InputfieldInteger'); 
		$input->attr('id+name', 'repeaterMaxItems'); 
		$input->attr('value', (int) abs($field->repeaterMaxItems)); 
		$input->label = $this->_('Max Repeater Items') . " ({$field->repeaterMaxItems})";
		$input->description = $this->_('The maximum number of repeater items allowed.');
		$input->notes = 
		$this->_('If set to 0, there will be no maximum limit.') . " \n" . 
		$this->_('If set to 1, this field will act as a single item [Page] rather than multiple items [PageArray].') . " \n" . 
		$this->_('Note that when outputFormatting is off, it will always behave as a PageArray regardless of the setting here.');
		$input->collapsed = Inputfield::collapsedYes;
		$inputfields->add($input); 
		 */

		// -------------------------------------------------

		/* TBA
		$input = wire('modules')->get('InputfieldRadios'); 
		$input->attr('id+name', 'repeaterDetached'); 
		$input->addOption(0, 'Attached (recommended)'); 
		$input->addOption(1, 'Detached'); 
		$input->attr('value', $parent->is(Page::statusSystem) ? 0 : 1); 
		$input->label = $this->_('Repeater Type'); 
		$input->description = 
			$this->_("When 'attached' the repeater will manage it's own template and parent page without you having to see or think about it.") . " " . 
			$this->_("When 'detached' you may move and modify the repeater parent page and template as you see fit."); 
		$input->notes = $this->_("Note that once detached, ProcessWire will not delete the parent or template when/if the field is deleted."); 
		*/

		return $inputfields;
	}

	/**
	 * Helper to getConfigInputfields, handles adding and removing of repeater fields
	 *
	 * @param Template $template
	 * @throws WireException
	 *
	 */
	public function saveConfigInputfields(Template $template) {

		$field = $this->field;
		$fieldgroup = $template->fieldgroup;
		$ids = $this->wire('input')->post->repeaterFields;

		foreach($ids as $id) {
			if(!$f = $this->wire('fields')->get((int) $id)) continue;
			if(!$fieldgroup->has($f)) $this->message(sprintf($this->_('Added Field "%1$s" to "%2$s"'), $f, $field));
			$fieldgroup->add($f);
		}

		foreach($fieldgroup as $f) {
			if(in_array($f->id, $ids)) continue;
			$fieldgroup->remove($f);
			$this->message(sprintf($this->_('Removed Field "%1$s" from "%2$s"'), $f, $field));
		}

		$fieldgroup->save();

		if($this->wire('input')->post('_deleteOldReady')) {
			/** @var FieldtypeRepeater $fieldtype */
			$fieldtype = $this->field->type;
			$cnt = $fieldtype->countOldReadyPages($field, true);
			$this->message(sprintf($this->_('Deleted %d old/unused repeater item(s)'), $cnt));
		}

		/* TBA
		$detached = (int) $input->post->repeaterDetached;
		if($parent->is(Page::statusSystem) && $detached) {
			$parent->addStatus(Page::statusSystemOverride); 
			$parent->removeStatus(Page::statusSystem);
			$parent->removeStatus(Page::statusSystemOverride);
			$parent->save();
			$this->message(sprintf($this->_('Parent page %s is now detached and may be moved or modified.'), $parent->path)); 
			$template->flags = $template->flags | Template::flagSystemOverride; 
		}	
		*/
	}


}