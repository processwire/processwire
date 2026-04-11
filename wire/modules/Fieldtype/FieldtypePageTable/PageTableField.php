<?php namespace ProcessWire;

/**
 * Page Table Field (for FieldtypePageTable)
 *
 * // Fieldtype settings
 * @property int|array $template_id Template ID (or array of IDs) that PageTable items must use.
 * @property int $parent_id Parent page ID where items are stored, or 0 to use the page being edited as parent.
 * @property string $sortfields Comma-separated field names to auto-sort items by (e.g. 'title' or '-date'). Leave blank for manual drag-and-drop sort.
 * @property int $trashOnDelete What to do with items when the owning page is permanently deleted: 0=nothing, 1=trash items, 2=delete items.
 * @property int $unpubOnTrash What to do with items when the owning page is trashed: 0=nothing, 1=unpublish items.
 * @property int $unpubOnUnpub What to do with items when the owning page is unpublished: 0=nothing, 1=unpublish items, 2=hide items.
 * @property int|bool $autoTrash Deprecated, replaced by $trashOnDelete.
 *
 * // Inputfield settings
 * @property string $columns Column field names to display in the admin table view (one per line).
 * @property string $nameFormat Auto-format string for naming newly created item pages.
 * @property int $noclose If 1, keep the item editor open after saving rather than closing it.
 *
 * @since 3.0.221
 *
 */
class PageTableField extends Field {
	public function __construct() {
		parent::__construct();
		parent::set('distinctAutojoin', true);
	}
}
