<?php namespace ProcessWire;

/**
 * Page Field (for FieldtypePage)
 *
 * Configured with FieldtypePage
 * ==============================
 * @property int $derefAsPage How to dereference the field value:
 *   0=PageArray (default), 1=Page or false when empty, 2=Page or NullPage when empty.
 *   Use FieldtypePage::derefAsPageArray, ::derefAsPageOrFalse, ::derefAsPageOrNullPage constants.
 * @property int|bool $allowUnpub Include unpublished pages in the field value? (default=false).
 *
 * Configured with InputfieldPage
 * ==============================
 * @property int $template_id ID of a single template to restrict selectable pages to (use template_ids for multiple).
 * @property array $template_ids Array of template IDs to restrict selectable pages to.
 * @property int|string $parent_id ID or path of the parent page whose children are selectable. Pipe-separated IDs or array also accepted.
 * @property string $inputfield Inputfield class used for selecting pages (e.g. InputfieldSelect, InputfieldAsmSelect, InputfieldPageAutocomplete).
 * @property string $labelFieldName Field name to use as the label for selectable pages (default='title'). Use '.' if labelFieldFormat is in use.
 * @property string $labelFieldFormat A $page->getMarkup() format string for building labels, used instead of labelFieldName when set.
 * @property string $findPagesSelector Custom selector string for finding selectable pages (alternative to template_id/parent_id).
 * @property string $findPagesSelect Same as findPagesSelector but configured interactively via InputfieldSelector in the admin.
 * @property string $findPagesCode PHP code returning a PageArray of selectable pages (alternative to findPagesSelector).
 * @property int|bool $addable Allow editors to create new pages inline from the field input? (default=false).
 * @property array $inputfieldClasses Available Inputfield classes for this field.
 * 
 * #property-read string $inputfieldClass Resolved Inputfield class name (does not appear to be implemented)
 *
 * @since 3.0.173
 *
 */
class PageField extends Field {
	
	/**
	 * Set the single template to use for this page field
	 * 
	 * If using multiple templates, or if templates are specified in a selector, avoid using this method. 
	 * To get current value use `$field->template_id`
	 * 
	 * @param Template|int|string $template Template instance, name or id
	 * @return self
	 * @throws WireException If template cannot be identified
	 * @since 3.0.258
	 * 
	 */
	public function setTemplate($template) {
		$_template = $template;
		if(!$template instanceof Template) {
			if(is_array($template)) return $this->setTemplates($template);
			if(ctype_digit("$template")) $template = (int) $template;
			$template = $this->wire()->templates->get($template);
		}
		if($template instanceof Template) {
			$this->set('template_id', $template->id); 
		} else {
			throw new WireException("Cannot find template: $_template"); 
		}
		return $this;
	}
	
	/**
	 * Set multiple templates to use for this page field
	 * 
	 * If using single template, or if templates are specified in a selector, avoid using this method.
	 * To get current value use `$field->template_ids`
	 * 
	 * @param array $templates Template instances, ids, or names
	 * @return self
	 * @since 3.0.258
	 * 
	 */
	public function setTemplates(array $templates) {
		$templateIds = [];
		foreach($templates as $template) {
			$_template = $template;
			if($template instanceof Template) {
				// ok
			} else if(ctype_digit("$template")) {
				$template = $this->wire()->templates->get((int) $template);
			} else if(is_string($template)) {
				$template = $this->wire()->templates->get($template);
			}
			if($template instanceof Template) {
				$templateIds[] = $template->id;
			} else {
				throw new WireException("Cannot find template: $_template"); 
			}
		}
		$this->set('template_ids', $templateIds);
		return $this;
	}
	
	/**
	 * Set parent for this page field
	 * 
	 * To get current value use `$field->parent_id`
	 * 
	 * @param Page|string|int|null $parent Parent Page, path or id; pass 0/null/'' to clear restriction
	 * @return self
	 * @throws WireException If a non-empty parent value cannot be resolved
	 * @since 3.0.258
	 * 
	 */
	public function setParent($parent) {
		if(!$parent) {
			$this->set('parent_id', 0);
			return $this;
		}
		$_parent = $parent;
		if($parent instanceof Page) {
			$parent = $parent->id;
		} else if(ctype_digit("$parent")) {
			$parent = (int) $parent;
		} else if(is_string($parent)) {
			$parent = $this->wire()->pages->get($parent)->id;
		}
		if($parent > 0) {
			$this->set('parent_id', $parent);
		} else {
			throw new WireException("Cannot find parent: $_parent");
		}
		return $this;
	}
	
	/**
	 * Set parent(s) for this page field
	 *
	 * @param PageArray|array|string|int $parents Parent Page instances, paths or ids
	 * @return self
	 * @throws WireException If parent cannot be identified
	 * @since 3.0.258
	 *
	 */
	public function setParents($parents) {
		$pages = $this->wire()->pages;
		$parentIds = [];
		
		if(is_string($parents)) $parents = explode('|', $parents);
		if(empty($parents)) return $this;
	
		if(!WireArray::iterable($parents)) $parents = [ $parents ];
		
		foreach($parents as $parent) {
			$parentId = 0;
			if($parent instanceof Page) {
				$parentId = $parent->id;
			} else if(ctype_digit("$parent")) {
				$parentId = (int) $parent;
			} else if(is_string($parent)) {
				$parentId = $pages->get($parent)->id;
			}
			if($parentId) {
				$parentIds[$parentId] = $parentId; 
			}
		}
	
		if(count($parentIds) === 1) $parentIds = reset($parentIds);
		$this->set('parent_id', $parentIds);
		
		return $this; 
	}
	
	/**
	 * Set Inputfield for this page reference field
	 *
	 * To get current value use `$field->inputfield`
	 *
	 * @param string|Inputfield $inputfield Inputfield name (i.e. 'InputfieldText' or 'text') or Inputfield instance
	 * @return self
	 * @throws WireException if given unknown/invalid Inputfield name
	 * @since 3.0.258
	 *
	 */
	public function setInputfield($inputfield) {
		if($inputfield instanceof Inputfield) {
			$inputfield = $inputfield->className();
		} else {
			if(strpos($inputfield, 'Inputfield') !== 0) {
				$inputfield = 'Inputfield' . ucfirst($inputfield);
			}
			$f = $this->wire()->modules->get($inputfield);
			if(!$f) throw new WireException("Inputfield not found: $inputfield");
			$inputfield = $f->name;
		}
		if($inputfield) $this->set('inputfield', $inputfield);
		return $this;
	}
	
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
