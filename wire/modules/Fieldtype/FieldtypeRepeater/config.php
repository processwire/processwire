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
		parent::__construct();
	}

	/**
	 * @return Field
	 * @since 3.0.188
	 * 
	 */
	public function getField() {
		return $this->field;
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
		$languages = $this->wire()->languages;
		$modules = $this->wire()->modules;

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
		$f = $modules->get('InputfieldHidden');
		$f->attr('name', 'template_id');
		$f->label = 'Repeater Template ID';
		$f->attr('value', $template->id);
		$inputfields->add($f);

		$f = $modules->get('InputfieldHidden');
		$f->attr('name', 'parent_id');
		$f->label = 'Repeater Parent ID';
		$f->attr('value', $parent->id);
		$inputfields->add($f);

		// -------------------------------------------------
	
		/** @var ProcessTemplate $processTemplate */	
		$processTemplate = $modules->getModule('ProcessTemplate', array('noInit' => true));

		/** @var InputfieldAsmSelect $select */
		$select = $modules->get('InputfieldAsmSelect');
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
			$attrs = $processTemplate->getAsmListAttrs($f);
			$attrs['selected'] = 'selected';
			$select->addOption($f->id, $f->name, $attrs);
		}

		foreach($this->wire()->fields as $f) {
			if($template->fieldgroup->has($f)) continue;
			if($f->name == $this->field->name) continue;
			if(($f->flags & Field::flagPermanent) && !$this->wire('config')->advanced) continue;
			$name = $f->name;
			if($f->flags & Field::flagSystem) $name .= "*";
			$attrs = $processTemplate->getAsmListAttrs($f);
			$select->addOption($f->id, $name, $attrs);
		}

		if($this->wire('config')->debug) $select->notes = "This repeater uses template '$template' and parent '{$parent->path}'";
		$inputfields->add($select);
		
		if($this->isSingleMode()) return $inputfields; 
		
		// all of the following fields are not applicable to single-page mode (i.e. FieldtypeFieldsetPage)

		// -------------------------------------------------

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('name', 'repeaterTitle');
		$f->attr('value', $field->get('repeaterTitle'));
		$f->label = $this->_('Repeater item labels');
		$f->icon = 'list-ul';
		$f->collapsed = Inputfield::collapsedBlank;
		$f->description = $this->_('Enter the field name to use for repeater labels in the page editor, or leave blank to auto-generate.');
		$f->description .= ' ' . $this->_('To use multiple fields, or to specify your own format string, surround field names in {brackets}.');
		$f->description .= ' ' . $this->_('To include a repeater index number with each label, add "#n" somewhere in your format string.');
		$f->notes = $this->_('Example: #n: {title}');
		$f->themeOffset = 1;
		$inputfields->add($f);

		// -------------------------------------------------
		
		/** @var InputfieldFieldset $fs */
		$fs = $modules->get('InputfieldFieldset');
		$fs->label = $this->_('Repeater depths/indents');
		$fs->attr('name', '_repeaterDepthSettings');
		$fs->collapsed =  Inputfield::collapsedYes;
		$fs->icon = 'indent';
		$fs->themeOffset = 1;
		$inputfields->add($fs);

		$value = (int) $field->get('repeaterDepth');
		/** @var InputfieldInteger $f */
		$f = $modules->get('InputfieldInteger');
		$f->attr('name', 'repeaterDepth');
		$f->attr('value', $value > 0 ? $value : '');
		$f->label = $this->_('Item depth');
		$f->description = $this->_('To support items with depth, enter the max allowed depth, or leave blank to disable.');
		$f->description .= ' ' . $this->_('When editing a repeater, you can change item depth by clicking the repeater item drag arrows and dragging the item right or left.');
		$f->notes = $this->_('Depths are zero-based, meaning a depth of 3 allows depths 0, 1, 2 and 3.');
		$f->notes .= ' ' . $this->_('Depth can be accessed from a repeater page item via `$item->depth`.');
		$f->columnWidth = 50;
		$fs->add($f);

		/** @var InputfieldToggle $f */
		$f = $modules->get('InputfieldToggle');
		$f->attr('name', 'familyFriendly');
		$f->label = $this->_('Use family-friendly item depth?');
		$f->description =
			$this->_('This setting makes the admin page editor treat item depth as a parent/child relationship.') . ' ' .
			$this->_('This means that moving/sorting an item includes child items too.') . ' ' .
			$this->_('It also prevents a child item from being dragged to have a depth that exceeds its parent by more than 1.');
		$f->notes = $this->_('“Yes” recommended.'); 
		$f->val((int) $field->get('familyFriendly'));
		$f->columnWidth = 50;
		$fs->add($f);
		
		/** @var InputfieldToggle $f */
		$f = $modules->get('InputfieldToggle');
		$f->attr('name', 'familyToggle');
		$f->label = $this->_('Open/close items as a family?');
		$f->description =
			$this->_('When enabled, opening (or closing) a repeater item also opens (or closes) the next items that are visually children of it.') . ' ' . 
			$this->_('This is best combined with family-friendly item depth mode.'); 
		$f->notes = 
			$this->_('Note that this setting is not compatible with accordion mode.');
		$f->val((int) $field->get('familyToggle'));
		$fs->add($f);

		// -------------------------------------------------

		/** @var InputfieldFieldset $fs */	
		$fs = $modules->get('InputfieldFieldset');
		$fs->label = $this->_('Repeater editor settings');
		$fs->attr('name', '_repeaterEditorSettings');
		$fs->collapsed =  Inputfield::collapsedYes;
		$fs->icon = 'sliders';
		$fs->themeOffset = 1;
		$inputfields->add($fs);
		
		// -------------------------------------------------
	
		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
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
		$fs->add($f);

		// -------------------------------------------------

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
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
		$fs->add($f);

		// -------------------------------------------------

		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'repeaterLoading');
		$f->label = $this->_('Repeater dynamic loading (AJAX) in editor');
		$f->description = $this->_('Which items should be dynamically loaded with AJAX in the page editor? If you find your repeater uses a field that does not work with dynamic loading, it may be necessary to turn this feature off.');
		$f->addOption(FieldtypeRepeater::loadingAll, $this->_('Existing and new items (recommended, especially for repeaters that might have LOTS of items)'));
		$f->addOption(FieldtypeRepeater::loadingNew, $this->_('New items only (good for repeaters that will have only a FEW items)'));
		$f->addOption(FieldtypeRepeater::loadingOff, $this->_('Off'));
		$f->icon = 'refresh';
		$value = $field->get('repeaterLoading');
		if($field->get('noAjaxAdd') && is_null($value)) {
			$value = FieldtypeRepeater::loadingOff; // to account for previous noAjaxAdd option
		} else if(is_null($value)) {
			$value = FieldtypeRepeater::loadingAll;
		}
		$f->attr('value', (int) $value);
		$fs->add($f);
		
		// -------------------------------------------------
	
		/** @var InputfieldToggle $f */	
		$f = $modules->get('InputfieldToggle');
		$f->attr('name', 'rememberOpen');
		$f->label = $this->_('Remember which repeater items are open?');
		$f->description = $this->_('When enabled, opened repeater items remain open after saving or reloading from the page editor (unless the user closes them).');
		$f->icon = 'lightbulb-o';
		$f->val((int) $field->get('rememberOpen'));
		$fs->add($f);
		
		// -------------------------------------------------

		/** @var InputfieldToggle $f */	
		$f = $modules->get('InputfieldToggle');
		$f->attr('name', 'accordionMode');
		$f->label = $this->_('Use accordion mode?');
		$f->description = $this->_('When enabled, only one repeater item will be open at a time.');
		$f->icon = 'map-o';
		$f->val((int) $field->get('accordionMode'));
		$fs->add($f);
		
		// -------------------------------------------------

		/** @var InputfieldToggle $f */
		$f = $modules->get('InputfieldToggle');
		$f->attr('name', 'loudControls');
		$f->label = $this->_('When to show repeater item controls/actions');
		$f->labelType = InputfieldToggle::labelTypeCustom;
		$f->yesLabel = $this->_('Always');
		$f->noLabel = $this->_('Hover');
		$f->description = $this->_('The hover option can reduce clutter in the interface by showing the repeater item actions/controls (clone, insert, delete, etc.) only when the item header is hovered.');
		$f->notes = $this->_('Note that controls are always shown for touch devices regardless of this setting.'); 
		$f->icon = 'sliders';
		$f->val((int) $field->get('loudControls'));
		$fs->add($f);
		
		// -------------------------------------------------
		
		/** @var InputfieldToggle $f */
		$f = $modules->get('InputfieldToggle');
		$f->attr('name', 'noScroll');
		$f->label = $this->_('Scroll to new item when added?');
		$f->labelType = InputfieldToggle::labelTypeCustom;
		$f->yesLabel = $this->_('Disabled');
		$f->noLabel = $this->_('Enabled');
		$f->icon = 'arrow-down';
		$f->val((int) $field->get('noScroll'));
		$fs->add($f);

		// -------------------------------------------------
	
		$maxItems = (int) $field->get('repeaterMaxItems');
		$minItems = (int) $field->get('repeaterMinItems');
		
		/** @var InputfieldInteger $f */
		$f = $modules->get('InputfieldInteger');
		$f->attr('name', 'repeaterMaxItems');
		$f->attr('value', $maxItems > 0 ? $maxItems : '');
		$f->label = $this->_('Maximum number of items');
		$f->description = $this->_('If you need to limit the number of items allowed, enter the limit here (0=no limit).');
		$f->icon = 'hand-stop-o';
		$f->columnWidth = 50;
		$fs->add($f);
		
		// -------------------------------------------------
		
		$f = $modules->get('InputfieldInteger');
		$f->attr('name', 'repeaterMinItems');
		$f->attr('value', $minItems > 0 ? $minItems : '');
		$f->label = $this->_('Minimum number of items');
		$f->description = $this->_('This many items will always be open and ready-to-edit (0=no minimum).');
		$f->icon = 'hand-peace-o';
		$f->columnWidth = 50;
		$fs->add($f);
		
		// -------------------------------------------------
		
		if(strpos($this->field->type->className(), 'Fieldset') === false) {
			$this->getConfigInputfieldsStorage($inputfields);
		}
		
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
	 * @param InputfieldWrapper $inputfields
	 * @return InputfieldFieldset
	 * 
	 */
	protected function getConfigInputfieldsStorage(InputfieldWrapper $inputfields) {
		
		$modules = $this->wire()->modules;
		$session = $this->wire()->session;
		$input = $this->wire()->input;
		$fieldtype = $this->field->type; /** @var FieldtypeRepeater $fieldtype */
		$limit = 1000;

		/** @var InputfieldFieldset $fs */
		$fs = $modules->get('InputfieldFieldset');
		$fs->label = $this->_('Repeater storage');
		$fs->attr('name', '_repeaterStorageSettings');
		$fs->collapsed =  Inputfield::collapsedYes;
		$fs->icon = 'database';
		$fs->themeOffset = 1;

		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox');
		$f->attr('name', 'lazyParents');
		$f->label = $this->_('Use fewer pages for storage?');
		$f->icon = 'flask';
		$f->description =
			$this->_('When checked, repeater page parents will not be created until at least one child repeater item exists.') . ' ' .
			$this->_('In addition, repeater page parents with no repeater items will be removed when appropriate.');
		$f->notes =
			$this->_('Currently an experimental option for testing (lazyParents), but will later become default.');
		if($this->field->get('lazyParents')) $f->attr('checked', 'checked');
		$fs->add($f);
		
		// @todo setting focus first visible input in repeater item on open 
		// @todo setting to disable the auto-scroll when adding an item

		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox');
		$f->icon = 'trash';
		$findName = '_findUnnecessaryPages';
		$deleteName = '_deleteUnnecessaryPages';

		if($session->getFor($this, $findName)) {
			$fs->collapsed = Inputfield::collapsedNo;
			$inputfields->prepend($fs);
			set_time_limit(600);
			$parents = $fieldtype->findUnnecessaryParents($this->field, array('limit' => $limit));
			$qty = $parents->count();
			if($qty) {
				$f->attr('name', $deleteName);
				$f->label = sprintf($this->_('Delete %d unnecessary pages?'), $parents->count());
				$paths = array();
				if($qty < 100) {
					foreach($parents as $parent) {
						$note = $parent->numChildren ? '(orphan parent)' : '(0 repeater items)';
						$paths[] = $parent->path() . " $note";
					}
					$f->description = $this->_('Found the following unnecessary pages:') . "\n" . implode("\n", $paths);
				} else {
					$f->description = sprintf($this->_('Found %d unnecessary repeater parents that either had 0 repeater items or had no owning page.'), $qty);
				}
				$f->notes = $this->_('Always backup before performing mass deletions.');
				$this->warning(sprintf($this->_('Found %d unnecessary repeater parent pages'), $qty));
				$fs->prepend($f);
			} else {
				$this->warning($this->_('No unnecessary pages found'));
			}
			$session->removeFor($this, $findName);

		} else if($input->post($findName)) {
			$session->setFor($this, $findName, 1);

		} else if($input->post($deleteName)) {
			set_time_limit(600);
			$parents = $fieldtype->findUnnecessaryParents($this->field, array('limit' => $limit));
			$numDeleted = 0;
			if($parents->count() >= $limit) {
				$this->warning(sprintf($this->_('Max of %d items per request reached, you will want to run this again.'), $limit));
			}
			foreach($parents as $parent) {
				$numDeleted += $fieldtype->deleteRepeaterPage($parent, $this->field, true);
			}
			$this->warning(sprintf($this->_('Deleted %d unnecessary pages'), $numDeleted));

		} else {
			$f->attr('name', $findName);
			$f->label = $this->_('Find and optionally delete unnecessary pages?');
			$fs->add($f);
			$inputfields->add($fs);
		}

		// -------------------------------------------------

		if($input->requestMethod('GET')) {
			$numOldReady = $fieldtype->countOldReadyPages($this->field);
			if($numOldReady) {
				// @todo: should we just do this automatically?
				$f = $modules->get('InputfieldCheckbox');
				$f->attr('name', '_deleteOldReady');
				$f->label = $this->_('Delete old/unused repeater items?');
				$f->description = sprintf($this->_('There are **%d** old/unused repeater item(s), check this box to delete them.'), $numOldReady);
				$f->notes = $this->_('A repeater item is considered old if it is at least 3 days and not yet been populated or published.');
				$f->icon = 'warning';
				$fs->add($f);
			}
		}
		
		return $fs;
	}

	/**
	 * Helper to getConfigInputfields, handles adding and removing of repeater fields
	 *
	 * @param Template $template
	 * @throws WireException
	 *
	 */
	public function saveConfigInputfields(Template $template) {
		
		$input = $this->wire()->input;
		$fields = $this->wire()->fields;

		$field = $this->field;
		$fieldgroup = $template->fieldgroup;
		$ids = $input->post('repeaterFields');

		foreach($ids as $id) {
			if(!$f = $fields->get((int) $id)) continue;
			if(!$fieldgroup->has($f)) $this->message(sprintf($this->_('Added Field "%1$s" to "%2$s"'), $f, $field));
			$fieldgroup->add($f);
		}

		foreach($fieldgroup as $f) {
			if(in_array($f->id, $ids)) continue;
			$fieldgroup->remove($f);
			$this->message(sprintf($this->_('Removed Field "%1$s" from "%2$s"'), $f, $field));
		}

		$fieldgroup->save();

		if($input->post('_deleteOldReady')) {
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

	/**
	 * Advanced config
	 *
	 * @param InputfieldWrapper $inputfields
	 *
	 */
	public function getConfigAdvancedInputfields(InputfieldWrapper $inputfields) {
		// these two are potential troublemakers when it comes to repeaters
		$inputfields->remove($inputfields->get('autojoin'));
		$inputfields->remove($inputfields->get('global'));
	}

}
