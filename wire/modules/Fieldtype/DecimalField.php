<?php namespace ProcessWire;

/**
 * Decimal Field (for FieldtypeDecimal)
 *
 * Configured with FieldtypeDecimal
 * ==============================
 * @property int $digits Total number of supported digits, including both before and after the decimal point (default=10).
 * @property int $precision Number of digits after the decimal point (default=2).
 * @property bool|int $zeroNotEmpty When true, 0 and blank are treated as different values in selectors (default=false).
 *
 * Configured with InputfieldFloat
 * ==============================
 * @property string $inputType Input type to use, one of "text" or "number" (default=text).
 * @property int $size Size attribute for the input, or 0 for full width (default=0).
 * @property string $placeholder Placeholder attribute text (default='').
 * @property int|float $min Minimum allowed value, or blank for no minimum (default='').
 * @property int|float $max Maximum allowed value, or blank for no maximum (default='').
 * @property int|float|string $step HTML5 step attribute value (default=any).
 *
 * @since 3.0.258
 *
 */
class DecimalField extends Field {
}
