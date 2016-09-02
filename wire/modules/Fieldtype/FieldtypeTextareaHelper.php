<?php namespace ProcessWire;

/**
 * Helper class for FieldtypeTextarea configuration
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */
class FieldtypeTextareaHelper extends Wire {

	/**
	 * Field that apply HTML should be applied to
	 * 
	 * @var Field|null
	 * 
	 */	
	protected $applyFieldHTML = null;

	/**
	 * Handles field config for Textarea field
	 * 
	 * @param Field $field
	 * @param InputfieldWrapper $inputfields
	 * @return InputfieldWrapper
	 * @throws WireException
	 * 
	 */
	function getConfigInputfields(Field $field, InputfieldWrapper $inputfields) {
		$value = $field->get('inputfieldClass');
		/** @var InputfieldSelect $f */
		$f = $this->modules->get('InputfieldSelect');
		$f->attr('name', 'inputfieldClass');
		$f->attr('value', $value ? $value : FieldtypeTextarea::defaultInputfieldClass);
		$f->label = $this->_('Inputfield Type');
		$f->description = $this->_('The type of field that will be used to collect input (Textarea is the default). Note that if you change this and submit, the available configuration options in the "input" tab section may change.'); // Inputfield type description
		$f->required = true;

		$baseClass = "InputfieldTextarea";
		foreach($this->wire('modules')->find("className^=Inputfield") as $fm) {
			if("$fm" == $baseClass || is_subclass_of($fm->className(true), __NAMESPACE__ . "\\$baseClass")) {
				$f->addOption("$fm", str_replace("Inputfield", '', "$fm"));
			}
		}

		$inputfields->append($f);

		$htmlLabel = $this->_('Markup/HTML');
		$typeLabel = $this->_('Content Type');

		$f = $this->modules->get('InputfieldRadios');
		$f->attr('name', 'contentType');
		$f->label = $typeLabel;
		$f->addOption(FieldtypeTextarea::contentTypeUnknown, $this->_('Unknown/Text'));
		$f->addOption(FieldtypeTextarea::contentTypeHTML, $htmlLabel);
		$value = (int) $field->get('contentType');
		// note: if adding more content types, update the ">=" below to be just "="
		if($value >= FieldtypeTextarea::contentTypeImageHTML) $value = FieldtypeTextarea::contentTypeHTML;
		$f->attr('value', $value);
		$f->description = $this->_('The [u]Markup/HTML[/u] option is recommended for fields using rich text editors (like CKEditor) and those containing HTML. It provides additional runtime checks filtering for quality assurance.'); // Content type description
		$f->description .= ' ' . $this->_('If you select the [u]Unknown/Text[/u] option, it is strongly recommended [for security] that you also select the "HTML Entity Encoder" in the [u]Text Formatters[/u] field above.');
		$f->notes = sprintf($this->_('For more information about the options above see [description of content type options](%s).'), 'https://processwire.com/api/fieldtypes/textarea-fieldtype/#content-type');
		$inputfields->append($f);

		$fieldset = $this->wire('modules')->get('InputfieldFieldset');
		$fieldset->label = "$htmlLabel ($typeLabel)";
		$fieldset->icon = 'html5';
		$fieldset->showIf = 'contentType=' . FieldtypeTextarea::contentTypeHTML;
		$inputfields->add($fieldset);

		$f = $this->modules->get('InputfieldCheckboxes');
		$f->attr('name', 'htmlOptions');
		$f->label = $this->_('HTML Options');
		$f->description = $this->_('The following options provide additional quality assurance for HTML at runtime.');
		// For more information about these options see [description of Markup/HTML options](%s).'), 'https://processwire.com/api/fieldtypes/textarea-fieldtype/#markup-html-options');
		$f->notes = $this->_('**Note:** These options are currently experimental. Please watch for issues and report any errors.');
		$f->notes .= ' ' . $this->_('The options above log errors to Setup > Logs > markup-qa-errors.');
		$f->addOption(FieldtypeTextarea::htmlLinkAbstract,
			$this->_('Link abstraction:') . ' ' .
			'[span.description]' .
			$this->_('Update href attributes automatically when internal links change') .
			'[/span]'
		);
		$f->addOption(FieldtypeTextarea::htmlImageReplaceBlankAlt,
			$this->_('Update image alt attributes:') . ' ' .
			'[span.description]' .
			$this->_('Replace blank alt attributes with image description') .
			'[/span]'
		);
		$f->addOption(FieldtypeTextarea::htmlImageRemoveNoExists,
			$this->_('Fix broken images:') . ' ' .
			'[span.description]' .
			$this->_('Remove img tags that would result in a 404, or re-create images when possible') .
			'[/span]'
		);
		$f->addOption(FieldtypeTextarea::htmlImageRemoveNoAccess,
			$this->_('Image access control:') . ' ' .
			'[span.description]' .
			$this->_('Remove images from markup that user does not have view access to') .
			'[/span]'
		);
		$value = $field->get('htmlOptions');
		if(!is_array($value)) $value = array();
		if($field->get('contentType') == FieldtypeTextarea::contentTypeImageHTML) {
			// if previous contentTypeImageHTML is in use, all HTML image options are implied
			$value[] = FieldtypeTextarea::htmlImageReplaceBlankAlt;
			$value[] = FieldtypeTextarea::htmlImageRemoveNoExists;
			$value[] = FieldtypeTextarea::htmlImageRemoveNoAccess;
		}
		$f->attr('value', $value);
		$f->icon = 'sliders';
		$fieldset->add($f);

		/** @var InputfieldCheckbox $f */
		$f = $this->wire('modules')->get('InputfieldCheckbox');
		$f->attr('name', '_applyHTML');
		$f->label = $this->_('Apply HTML Options Now');
		$f->description = $this->_('To apply the above options to all existing pages right now, check this box. This primarily focuses on the link abstraction option.');
		$f->notes = $this->_('Warning, this performs an update across potentially hundreds of pages (max 300 per run) and updates page modification times. If your site has a lot of pages, you may have to run this multiple times.');
		$f->notes .= ' ' . $this->_('**As an extra precaution it is recommended that you backup your database before running this.**');
		$f->icon = 'bolt';
		$f->collapsed = Inputfield::collapsedYes;
		$f->showIf = 'htmlOptions=' . FieldtypeTextarea::htmlLinkAbstract;
		$fieldset->add($f);

		if($this->wire('input')->post('_applyHTML') && $this->wire('process') == 'ProcessField') {
			$this->applyFieldHTML = $field;
			$this->wire('session')->addHookBefore('redirect', $this, 'applyFieldHTML');
		}

		return $inputfields; 
	}
	
	/**
	 * Apply all htmlOptions to field values (hook to Session::redirect)
	 *
	 * @param HookEvent $event
	 * @throws WireException
	 *
	 */
	public function applyFieldHTML(HookEvent $event) {
		
		set_time_limit(3600);
		
		$field = $this->applyFieldHTML;
		if(!$field || !$field instanceof Field || !$field->type instanceof FieldtypeTextarea) return;
		
		$selector = "$field->name%=href|src, include=all";
		$total = $this->wire('pages')->count($selector);
		
		$applyMax = (int) $this->wire('config')->applyHTMLMaxItems;
		if(!$applyMax) $applyMax = 300;
		
		if($total > $applyMax) {
			// more than 300 pages to update, only update those that haven't been modified in the last hour
			$modified = time() - 3600;
			$selector .= ", limit=$applyMax, modified<=$modified";
		}
		
		$items = $this->wire('pages')->find($selector);
		$totals = array();
		
		foreach($items as $item) {
			$item->getUnformatted($field->name);
			$item->trackChange($field->name);
			$item->save($field->name);
			$info = $item->get('_markupQA');
			if(is_array($info) && isset($info[$field->name])) {
				$counts = $info[$field->name];
				foreach($counts as $key => $value) {
					if(!isset($totals[$key])) $totals[$key] = 0;
					$totals[$key] += $value;
				}
			}
		}
		
		$this->wire('pages')->touch($items, time());
		
		if(!count($items) || count($items) == $total) {
			$statusNote = ' ' . 
				$this->_('Looks like we are done! HTML options are now fully applied.') . ' ' . 
				$this->_('Future updates will be applied automatically when each page is saved.');
		} else {
			$statusNote = ' ' . 
				$this->_('There are still more pages to apply. Check the box again to apply remaining pages.') . ' ' . 
				$this->_('Need to apply more pages at a time? You can add a %s setting to your /site/config.php file.');
			$statusNote = '<code>' . sprintf($statusNote, '$config->applyHTMLMaxItems = ' . ($applyMax * 2)) . ';</code>';
		}
		
		$logFile = $this->wire('config')->paths->logs . 'markup-qa-errors.txt';
		$logInfo = '';
		if(is_file($logFile)) {
			$logURL = $this->wire('config')->urls->admin . 'setup/logs/view/markup-qa-errors/';
			$logInfo = ' ' . sprintf($this->_('(see %s log)'), "<a target='_blank' href='$logURL'>markup-qa-errors</a>");
		}

		$summary = array();
		$good = "<i class='fa fa-fw fa-check-square'></i> ";
		$fail = "<i class='fa fa-fw fa-warning'></i> ";
		$ques = "<i class='fa fa-fw fa-question-circle'></i> ";
		$html5 = "<i class='fa fa-fw fa-html5'></i> ";
		
		$types = array(
			'external' => $good . $this->_x('%d external a[href] tags', 'link-type'),
			'href' => $good . $this->_('%d local a[href] tags', 'link-type'), 
			'internal' => $good . $this->_x('%d internal/abstract page links', 'link-type'),
			'files' => $good . $this->_x('%d file/asset references', 'link-type'),
			'relative' => $good . $this->_x('%d relative a[href] tags updated', 'link-type'), 
			'other' => $ques . $this->_x('%d local a[href] non-page/unrecognized tags', 'link-type'),
			'nohttp' => $good . $this->_x('%d non-http a[href] links like mailto, tel, etc.', 'link-type'),
			'unresolved' => $fail . $this->_x('%d unresolved a[href] tags', 'link-type'),
			'src' => $good . $this->_('%d local img[src] tags', 'link-type'), 
			'img_unresolved' => $fail . $this->_x('%d unresolved img[src] tags', 'link-type'),
			'img_fixed' => $good . $this->_x('%d unresolved and fixed img[src] tags', 'link-type'),
			'img_noalt' => $good . $this->_x('%d blank img[alt] tags to be populated at runtime', 'link-type'), 
		);
		
		foreach($totals as $type => $count) {
			if(isset($types[$type])) {
				$typeLabel = sprintf($types[$type], $count);
				if($count) {
					if(strpos($typeLabel, $fail) !== false) $typeLabel .= $logInfo;
				} else {
					$typeLabel = str_replace(array($fail, $ques), $good, $typeLabel);
				}
				$summary[] = $typeLabel;
			} else {
				$summary[] = "$ques $count $type";
			}
		}
		
		$this->wire('session')->message('<strong>' . 
			sprintf($this->_('Updated %1$d out of %2$d pages for HTML options.'), count($items), $total) . '</strong><br />' . 
			"$statusNote<br />" . 
			"<strong>$html5 " . $this->_('Markup/HTML quality assurance summary:') . '</strong><br />' .
			implode('<br />', $summary) . '<br />' . $this->_('Other HTML options are applied at runtime.'),
			Notice::allowMarkup
		);
	}


	/**
	 * Handle error condition when getInputfield() fails to retrieve requested Inputfield
	 *
	 * @param Field $field
	 *
	 */
	public function getInputfieldError(Field $field) {

		$editURL = $this->wire('config')->urls->admin . "setup/field/edit?id=$field->id";
		$modulesURL = $this->wire('config')->urls->admin . "module/";
		$inputfieldClass = $field->get('inputfieldClass');
		$findURL = "http://modules.processwire.com/search/?q=$inputfieldClass";
		$tab = '<br /> &nbsp; &nbsp; &nbsp;';

		$note =
			"<br /><small>TO INSTALL:$tab 1. <a href='$modulesURL'>Go to Modules</a>.$tab 2. click the \"New\" tab. " .
			"$tab 3. For \"Module Class Name\" paste in \"$inputfieldClass\". $tab 4. Click \"Download &amp; Install\"." .
			"<br />TO CHANGE: $tab 1. <a href='$editURL'>Edit the field</a>. $tab 2. Click the \"Details\" tab. " .
			"$tab 3. Select the \"Inputfield Type\". $tab 4. Click \"Save\".</small>";

		if($inputfieldClass == 'InputfieldTinyMCE') {
			$this->wire('modules')->getInstall('InputfieldCKEditor'); // install it so it's ready for them
			$this->error(
				"Field '$field->name' uses TinyMCE, which is no longer part of the core. " .
				"Please install <a target='_blank' href='$findURL'>TinyMCE</a> " .
				"or change it to use CKEditor (or another).$note",
				Notice::allowMarkup);

		} else if($inputfieldClass) {
			$this->error(
				"The module \"$inputfieldClass\" specified to provide input for field \"$field->name\" was not found. " .
				"Please <a target='_blank' href='$findURL'>install $inputfieldClass</a> " .
				"or convert the field to use another input type.$note",
				Notice::allowMarkup);
		}
	}
}