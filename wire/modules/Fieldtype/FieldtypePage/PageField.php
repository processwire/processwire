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
 * @property-read string $inputfieldClass Resolved Inputfield class name (read-only alias of getInputfieldClass()).
 * @property array $inputfieldClasses Available Inputfield classes for this field.
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
