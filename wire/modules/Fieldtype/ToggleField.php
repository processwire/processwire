<?php namespace ProcessWire;

/**
 * Toggle Field (for FieldtypeToggle)
 *
 * Configured with FieldtypeToggle
 * ==============================
 * @property int $formatType Formatted value type when output formatting is on:
 *   0=integer (default), 1=boolean, 2=string label, 3=string label entity encoded.
 *
 * Configured with InputfieldToggle
 * ==============================
 * @property int $labelType Label set to use: 0=Yes/No, 1=True/False, 2=On/Off, 3=Enabled/Disabled, 100=custom (default=0).
 * @property string $yesLabel Custom label for yes/on state (default='✓').
 * @property string $noLabel Custom label for no/off state (default='✗').
 * @property string $otherLabel Custom label for the optional other state (default='?').
 * @property bool|int $useOther Enable an optional third "other" state? (default=false).
 * @property bool|int $useReverse Reverse the display order of Yes/No options? (default=false).
 * @property bool|int $useVertical Use vertically oriented radio buttons (applies when inputfieldClass is InputfieldRadios)? (default=false).
 * @property bool|int $useDeselect Allow de-selection to return to no-selection state? (default=false).
 * @property string $defaultOption Default selected option: 'none', 'yes', 'no', or 'other' (default='none').
 * @property string $inputfieldClass Inputfield class to render with, or blank for toggle buttons (default='').
 *
 * @since 3.0.258
 *
 */
class ToggleField extends Field {
}
