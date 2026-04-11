<?php namespace ProcessWire;

/**
 * Selector field (for FieldtypeSelector)
 *
 * Fieldtype settings (@property tags below) come from FieldtypeSelector.
 * Inputfield settings come from InputfieldSelector.
 *
 * @property string $initValue Enforced selector prefix always prepended to the field value at runtime (and stripped before saving).
 * @property string $previewColumns CSV of field names to display in the selector builder preview (e.g. 'name, template, parent').
 *
 * // InputfieldSelector settings
 * @property string $addIcon Icon used for the "Add Field" link (default: 'plus-circle').
 * @property string $addLabel Text used for the "Add Field" link (default: 'Add Field').
 * @property string $dateFormat PHP date() format for date fields (default: 'Y-m-d').
 * @property string $datePlaceholder Placeholder text for date field inputs (default: 'yyyy-mm-dd').
 * @property string $timeFormat PHP date() format for time component of date fields (default: 'H:i').
 * @property string $timePlaceholder Placeholder text for time component inputs (default: 'hh:mm').
 * @property string $exclude CSV of field names to disallow selection for.
 * @property string $optgroupsOrder Order and presence of field selection option groups (default: 'system,field,subfield,group,modifier,adjust').
 * @property bool $preview Whether to show a live selector preview in notes section (default: true).
 * @property bool $counter Whether to show a live match-count indicator (default: true).
 * @property bool $allowAddRemove Whether to allow adding/removing rows (default: true).
 * @property bool $allowSystemCustomFields Allow system custom fields in field selects? (default: false).
 * @property bool $allowSystemNativeFields Allow system native fields in field selects? (default: true).
 * @property bool $allowSystemTemplates Allow selection of system templates (user, role, etc.)? (default: false).
 * @property bool $allowSubselectors Allow use of subselectors? (default: true).
 * @property bool $allowSubfields Allow use of subfields? (default: true).
 * @property bool $allowSubfieldGroups Allow @grouping of subfields (e.g. for page references)? (default: true).
 * @property bool $allowModifiers Allow use of modifiers like include, limit? (default: true).
 * @property bool $allowBlankValues When no value is present, should it contribute to the selector? (default: false).
 * @property bool|int $showFieldLabels Show field labels rather than names? Integer 2 shows both. (default: false).
 * @property bool $showOptgroups Separate system/field/subfield types into optgroups? (default: true).
 * @property array $limitFields Selectable fields whitelist (field names). Empty array allows any (default: []).
 * @property bool $parseVars Whether variables in a selector should be parsed and converted to values (default: true).
 * @property int $maxUsers Maximum number of users selectable before switching to text input (default: 20).
 * @property int $maxSelectOptions If select option count exceeds this, use autocomplete for page reference fields.
 *
 * @since 3.0.258
 *
 */
class SelectorField extends Field {
}
