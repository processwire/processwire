<?php namespace ProcessWire;

/**
 * Repeater field
 * 
 * @property FieldtypeRepeater $type
 * @property int $parent_id Parent page ID for repeater items.
 * @property int $template_id Template ID used by repeater items.
 * @property array $repeaterFields Array of field IDs used in repeater.
 * @property int $repeaterMaxItems Maximum number of items allowed.
 * @property int $repeaterMinItems Minimum number of items allowed.
 * @property int|string $repeaterDepth Max allowed depth for repeater items.
 * @property bool|int $familyFriendly Use family friendly depth? Maintains expected parent/child relationships.
 * @property int $repeaterLoading Dynamic loading (ajax) in editor, see `FieldypeRepeater::loading*` constants.
 * @property int $repeaterCollapse Item collapse state, see `FieldtypeRepeater::collapse*` constants
 * @property string $repeaterAddLabel Label to use for adding new items.
 * @property string $repeaterTitle Field name to use for repeater item labels or format string with {fields}.
 * @property int|bool $rememberOpen Remember which items are open between requests?
 * @property int|bool $accordionMode Use accordion mode where only 1 item open at a time?
 * @property int|bool $lazyParents Avoid creating parents when there are no items to store?
 * @property int $repeaterReadyItems (deprecated)
 * 
 */
class RepeaterField extends Field {
	// example of custom class for Field object (not yet put to use in this case)
}