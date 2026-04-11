<?php namespace ProcessWire;

/**
 * Module Reference field (for FieldtypeModule)
 *
 * @property array $moduleTypes Array of module type prefixes or class names used to filter selectable modules.
 * @property string $matchType Module matching method: 'prefix' (by name prefix) or 'verbose' (by class inheritance).
 * @property bool|int $instantiateModule When true, field value is a live Module instance; when false (default), it is the module class name string.
 * @property string $labelField Label shown for each option: '' (class name, default), 'title', or 'title-summary'.
 * @property string $inputfieldClass Input type: '' (Select, default) or 'InputfieldRadios'.
 * @property bool|int $showNoneOption Show a "None" option? Applies to InputfieldRadios only.
 * @property string $blankType Empty value type: 'null' (default), 'zero', 'false', or 'placeholder'.
 *
 * @since 3.0.258
 *
 */
class ModuleField extends Field {
}
