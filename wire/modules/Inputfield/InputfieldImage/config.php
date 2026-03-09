<?php namespace ProcessWire;

/**
 * ProcessWire Image Inputfield (configuration)
 *
 * ProcessWire 3.x, Copyright 2021 by Ryan Cramer
 * https://processwire.com
 *
 */
class InputfieldImageConfiguration extends Wire {

	public function getConfigInputfields(InputfieldImage $inputfield, InputfieldWrapper $inputfields) {
		
		$modules = $this->wire()->modules;
		
		$fs = $modules->get('InputfieldFieldset');
		$fs->attr('name', '_image_features');
		$fs->label = $this->_('Input features');
		$fs->themeOffset = 1;
		$fs->icon = 'sliders';
		$inputfields->add($fs);
		
		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'gridMode');
		$f->label = $this->_('Default image grid mode');
		$f->description = $this->_('In the admin, the list of images will appear in this mode by default. The user can change it at any time by clicking the icons in the top right corner of the field.');
		$f->notes = $this->_('If you have recently used this images field, you will have to clear your cookies before seeing any changes to this setting.');
		$f->icon = 'photo';
		$f->addOption('grid', '[i.fa.fa-th][/i] ' . $this->_('Square grid images'));
		$f->addOption('left', '[i.fa.fa-tasks][/i] ' . $this->_('Proportional grid images'));
		$f->addOption('list', '[i.fa.fa-th-list][/i] ' . $this->_('Vertical list (verbose)'));
		$f->attr('value', $inputfield->gridMode);
		$f->columnWidth = 50;
		$fs->add($f);

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'focusMode');
		$f->label = $this->_('Focus point selection');
		$f->description = $this->_('Enables a draggable focus point to select the subject of an image. This helps to generate non-proportional crops.') . ' ' .
			$this->_('A preview of the focus point is also shown when images are in the “Square grid images” mode.');
		$f->addOption('on', $this->_('Focus point'));
		$f->addOption('zoom', $this->_('Focus point and zoom'));
		$f->addOption('off', $this->_('Disabled'));
		$f->attr('value', $inputfield->focusMode);
		$f->icon = 'crosshairs';
		$f->columnWidth = 50;
		$fs->add($f);

		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->label = $this->_("Maximum image dimensions");
		$fieldset->icon = 'expand';
		$fieldset->description = $this->_("Optionally enter the max width and/or height of uploaded images. If specified, images will be resized at upload time when they exceed either the max width or height. The resize is performed at upload time, and thus does not affect any images in the system, or images added via the API."); // Max image dimensions description
		$fieldset->description .= ' ' . $this->_('Applies to JPG, PNG and GIF images.');
		$fieldset->themeOffset = 1;
		$inputfields->add($fieldset);

		$description = $this->_("Enter the value in number of pixels or leave blank for no limit."); // Min/Max width/height description
		/** @var InputfieldInteger $field */
		$field = $modules->get("InputfieldInteger");
		$field->attr('name', 'maxWidth');
		$field->attr('value', $inputfield->maxWidth ? (int) $inputfield->maxWidth : '');
		$field->label = $this->_("Max width for uploaded images");
		$field->icon = 'arrows-h';
		$field->description = $description;
		$field->columnWidth = 50;
		$field->appendMarkup = '&nbsp;px';
		$fieldset->add($field);

		$field = $modules->get("InputfieldInteger");
		$field->attr('name', 'maxHeight');
		$field->attr('value', $inputfield->maxHeight ? (int) $inputfield->maxHeight : '');
		$field->label = $this->_("Max height for uploaded images");
		$field->icon = 'arrows-v';
		$field->description = $description;
		$field->columnWidth = 50;
		$field->appendMarkup = '&nbsp;px';
		$fieldset->add($field);

		/** @var InputfieldRadios $field */
		$field = $modules->get('InputfieldRadios');
		$field->attr('name', 'resizeServer');
		$field->label = $this->_('How to resize to max dimensions');
		$field->description = $this->_('Using client-side resize enables you to reduce the file size and dimensions before uploading.');
		$field->notes = $this->_('When using client-side resize, please specify max width and/or max height in the fields above, or max megapixels in the field below.');
		$field->icon = 'object-group';
		$field->addOption(0, $this->_('Use client-side resize when possible'));
		$field->addOption(1, $this->_('Use only server-side resize'));
		$field->attr('value', (int) $inputfield->resizeServer);
		$fieldset->add($field);

		$field = $modules->get('InputfieldFloat');
		$field->attr('name', 'maxSize');
		$field->label = $this->_('Max megapixels for uploaded images');
		$field->description = $this->_('This can be used as an alternative to max width/height. Specify a floating point value.');
		$field->description .= ' ' . $this->_('Applicable to client-side resize only.');
		$field->notes = $this->_('A good value for websites is 1.7 which is roughly 1600x1000 pixels, where 1600 and 1000 can be either width or height.');
		$field->notes .= ' ' . $this->_('Other examples:') . ' 0.2=516x387, 2.0=1633x1225, 3.0=2000x1500, 12.0=4000x3000';
		$field->icon = 'camera';
		$field->attr('value', (float) $inputfield->maxSize > 0 ? (float) $inputfield->maxSize : '');
		$field->showIf = 'resizeServer=0';
		$field->columnWidth = 50;
		$fieldset->add($field);

		/** @var InputfieldInteger $field */
		$field = $modules->get('InputfieldInteger');
		$field->attr('name', 'clientQuality');
		$field->label = $this->_('Client-side resize quality percent for JPEGs');
		$field->description = $this->_('Specify a number between 10 (lowest quality/smallest file size) and 100 (highest quality/largest file size). Default is 90.');
		$field->icon = 'signal';
		$field->min = 10;
		$field->max = 100;
		$field->attr('size', 4);
		$field->attr('value', (int) $inputfield->clientQuality);
		$field->showIf = 'resizeServer=0';
		$field->columnWidth = 50;
		$field->appendMarkup = '&nbsp;%';
		$fieldset->add($field);

		// maxReject option comes from @JanRomero PR #1051
		/** @var InputfieldCheckbox $field */
		$field = $modules->get("InputfieldCheckbox");
		$field->attr('name', 'maxReject');
		$field->attr('value', (int) $inputfield->maxReject);
		$field->attr('checked', ((int) $inputfield->maxReject) ? 'checked' : '');
		$field->label = $this->_('Refuse images exceeding max dimensions?');
		$field->showIf = 'maxWidth|maxHeight>0';
		$field->icon = 'ban';
		$field->description = $this->_('If checked, images that exceed max width/height (that cannot be resized client-side) will be refused rather than resized.');
		if(!$inputfield->maxReject) $field->collapsed = Inputfield::collapsedYes;
		$fieldset->add($field);

		// min image dimensions
		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->label = $this->_("Minimum image dimensions");
		$fieldset->icon = 'compress';
		$fieldset->collapsed = $inputfield->minWidth || $inputfield->minHeight ? Inputfield::collapsedNo : Inputfield::collapsedYes;
		$fieldset->description = $this->_("Optionally enter the minimum width and/or height of uploaded images. If specified, images that don't meet these minimums will be refused."); // Max image dimensions description
		$fieldset->themeOffset = 1;
		$inputfields->add($fieldset);

		/** @var InputfieldInteger $field */
		$field = $modules->get("InputfieldInteger");
		$field->attr('name', 'minWidth');
		$field->attr('value', $inputfield->minWidth ? (int) $inputfield->minWidth : '');
		$field->label = $this->_("Min width for uploaded images");
		$field->description = $description;
		$field->columnWidth = 50;
		$field->icon = 'arrows-h';
		$field->appendMarkup = '&nbsp;px';
		$fieldset->add($field);

		/** @var InputfieldInteger $field */
		$field = $modules->get("InputfieldInteger");
		$field->attr('name', 'minHeight');
		$field->attr('value', $inputfield->minHeight ? (int) $inputfield->minHeight : '');
		$field->label = $this->_("Min height for uploaded images");
		$field->description = $description;
		$field->columnWidth = 50;
		$field->icon = 'arrows-v';
		$field->appendMarkup = '&nbsp;px';
		$fieldset->add($field);

		/** @var InputfieldCheckbox $field */
		$field = $modules->get("InputfieldCheckbox");
		$field->attr('name', 'dimensionsByAspectRatio');
		$field->attr('value', (int) $inputfield->dimensionsByAspectRatio);
		$field->attr('checked', ((int) $inputfield->dimensionsByAspectRatio) ? 'checked' : '');
		$field->label = $this->_("Swap min/max dimensions for portrait images?");
		$field->showIf = 'minWidth|minHeight|maxWidth|maxHeight>0';
		$field->description = $this->_('If checked, minimum width/height and maximum width/height dimensions will be swapped for portrait images to accommodate for the different aspect ratio.');
		$field->description .= ' ' . $this->_('Applies to server-side resizes only.');
		$field->collapsed = $inputfield->dimensionsByAspectRatio ? Inputfield::collapsedNo : Inputfield::collapsedYes;
		$field->icon = 'exchange';
		$field->themeOffset = 1;
		$inputfields->add($field);

	}
}
