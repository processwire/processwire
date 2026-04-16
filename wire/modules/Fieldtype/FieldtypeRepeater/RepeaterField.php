<?php namespace ProcessWire;

/**
 * Repeater field
 *
 * @property FieldtypeRepeater $type
 *
 * // Fieldtype settings
 * @property int $parent_id Parent page ID for repeater items.
 * @property int $template_id Template ID used by repeater items.
 * @property array $repeaterFields Array of field IDs used in repeater.
 * @property int|bool $lazyParents Avoid creating parent pages when there are no items to store?
 *
 * // Inputfield settings
 * @property int $repeaterMaxItems Maximum number of items allowed (0=no limit).
 * @property int $repeaterMinItems Minimum number of items required (0=no minimum).
 * @property int $repeaterDepth Maximum allowed depth for repeater items (0=disabled).
 * @property bool|int $familyFriendly Treat item depth as parent/child relationships in the editor?
 * @property bool|int $familyToggle Opening/closing an item also opens/closes its visual children (requires familyFriendly)?
 * @property int $repeaterLoading Dynamic (ajax) loading mode; see `FieldtypeRepeater::loading*` constants.
 * @property int $repeaterCollapse Item collapse state; see `FieldtypeRepeater::collapse*` constants.
 * @property string $repeaterAddLabel Label to use for the "add item" button.
 * @property string $repeaterTitle Field name or `{field}` format string to use for repeater item labels.
 * @property bool|int $rememberOpen Remember which items are open between page-edit requests?
 * @property bool|int $accordionMode Allow only one item open at a time?
 * @property bool|int $loudControls Always show item controls regardless of hover state?
 * @property bool|int $noScroll Do not scroll to newly added items?
 * @property int $repeaterReadyItems (deprecated)
 *
 * @since 3.0.258
 *
 */
class RepeaterField extends Field {
	
	public function getRepeaterTemplate() {
		return $this->type->_getRepeaterTemplate($this);
	}
	
	/**
	 * Return the repeater parent used by this Field
	 * 
	 * i.e. /processwire/repeaters/for-field-123/
	 *
	 * Auto generate a repeater parent page named 'for-field-[id]', if it doesn't already exist
	 *
	 * @return Page
	 * @throws WireException
	 *
	 */
	public function getRepeaterParent() {
		return $this->type->getRepeaterParent($this);
	}
		
	/**
	 * Returns a blank page ready for use as a repeater
	 * 
	 * Also ensures that the parent repeater page exists.
	 * This is public so that the Inputfield can pull from it too.
	 *
	 * @param Page $page The page that the repeater field lives on
	 * @return Page|RepeaterPage
	 */
	public function getBlankRepeaterPage(Page $page) {
		return $this->type->getBlankRepeaterPage($page, $this);
	}
	
	/**
	 * Return the parent used by the repeater pages for the given $page
	 * 
	 * i.e. /processwire/repeaters/for-field-12/for-page-123/
	 *
	 * @param Page $page
	 * @param bool $create Create if not exists? (default=true)
	 * @return Page|NullPage
	 */
	public function getRepeaterPageParent(Page $page, $create = true) {
		return $this->type->getRepeaterPageParent($page, $this, $create);
		
	}
	
		
}
