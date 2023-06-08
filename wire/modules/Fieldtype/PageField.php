<?php namespace ProcessWire;

/**
 * Page Field (for FieldtypePage)
 * 
 * Configured with FieldtypePage
 * ==============================
 * @property int $derefAsPage
 * @property int|bool $allowUnpub
 *
 * Configured with InputfieldPage 
 * ==============================
 * @property int $template_id
 * @property array $template_ids
 * @property int $parent_id
 * @property string $inputfield Inputfield class used for input
 * @property string $labelFieldName Field name to use for label (note: this will be "." if $labelFieldFormat is in use).
 * @property string $labelFieldFormat Formatting string for $page->getMarkup() as alternative to $labelFieldName
 * @property string $findPagesCode
 * @property string $findPagesSelector
 * @property string $findPagesSelect Same as findPageSelector, but configured interactively with InputfieldSelector.
 * @property int|bool $addable
 * @property-read string $inputfieldClass Public property alias of protected getInputfieldClass() method
 * @property array $inputfieldClasses
 * 
 * @since 3.0.173
 * 
 */
class PageField extends Field {
	
	/**
	 * Return array configured template and parent IDs identified in field configuration
	 *
	 * #pw-internal
	 *
	 * @return array
	 *
	 */
	public function getTemplateAndParentIds() {
		
		$pages = $this->wire()->pages;
		$templates = $this->wire()->templates;

		$parentId = $this->get('parent_id');
		$parentIds = array();
		$templateIds = array();

		if(empty($parentId)) {
			// $parentIds = array();
		} else if(is_string($parentId)) {
			if(ctype_digit($parentId)) {
				$parentIds = array((int) $parentId);
			} else if(strpos($parentId, '|') !== false) {
				$parentIds = explode('|', $parentId);
			}
		} else if(is_int($parentId)) {
			$parentIds = array($parentId);
		} else if(is_array($parentId)) {
			$parentIds = array_values($parentId);
		}
		
		foreach(array('template_id', 'template_ids') as $key) {
			$value = $this->get($key);
			if(empty($value)) continue;
			if(!is_array($value)) $value = array($value);
			foreach($value as $id) {
				$id = (int) $id;
				if($id > 0) $templateIds[$id] = $id;
			}
		}

		foreach(array('findPagesSelect', 'findPagesSelector') as $key) {
			
			$selector = $this->get($key);
			if(empty($selector)) continue;
			if(strpos($selector, 'parent') === false || strpos($selector, 'template') === false) continue;
			
			foreach(new Selectors($selector) as $s) {
				if(!$s instanceof SelectorEqual) continue;
				
				if($s->field() === 'parent') {
					foreach($s->values() as $v) {
						if(ctype_digit("$v")) {
							$parentIds[] = (int) $v;
						} else if(strpos($v, '/')) {
							$p = $pages->get($v);
							if($p->id) $parentIds[] = $p->id;
						}
					}
					
				} else if($s->field() === 'parent_id') {
					$parentIds = array_merge($parentIds, $s->values());
					
				} else if($s->field() === 'template' || $s->field() === 'templates_id') {
					foreach($s->values() as $v) {
						if(ctype_digit("$v")) {
							$templateIds[] = (int) $v;
						} else if($v) {
							$template = $templates->get($v);
							if($template instanceof Template) $templateIds[] = $template->id;
						}
					}
				}
			}
		}

		if(count($parentIds)) {
			foreach($parentIds as $key => $id) {
				$parentIds[$key] = (int) $id;
			}
			$parentIds = array_unique($parentIds);
		}
		
		if(count($templateIds)) {
			foreach($templateIds as $key => $id) {
				$templateIds[$key] = (int) $id;
			}
			$templateIds = array_unique($templateIds);
		}

		return array(
			'parentIds' => $parentIds, 
			'templateIds' => $templateIds, 
		);
	}

}
