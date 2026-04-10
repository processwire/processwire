<?php namespace ProcessWire;

/**
 * Integer Field (for FieldtypeInteger)
 *
 * Configured with FieldtypeInteger
 * ==============================
 * @property bool|int $zeroNotEmpty When true, 0 and blank are treated as different values in selectors (default=false).
 * @property int|string $defaultValue Default value assigned to the field on pages with no value entered (default='').
 *
 * Configured with InputfieldInteger
 * ==============================
 * @property string $inputType Input type to use, one of "text" or "number" (default=text).
 * @property int $size Displayed width of the input in characters, or 0 for full width (default=10).
 * @property string $placeholder Placeholder attribute text (default='').
 * @property int|float|string $min Minimum allowed value, or blank for no minimum (default='').
 * @property int|float|string $max Maximum allowed value, or blank for no maximum (default='').
 * @property int|float|string $step HTML5 step attribute value, or blank for no step (default='').
 *
 * @since 3.0.258
 *
 */
class IntegerField extends Field {
}

