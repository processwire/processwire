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
}