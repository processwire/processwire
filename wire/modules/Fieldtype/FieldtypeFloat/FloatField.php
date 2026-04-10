<?php namespace ProcessWire;

/**
 * Float Field (for FieldtypeFloat)
 *
 * Configured with FieldtypeFloat
 * ==============================
 * @property int|string $precision Number of decimal digits to round to, or -1 to disable rounding (default=2).
 * @property string $colType Database column type, 'float' or 'double' (default='float').
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
 * @property bool|int $noE Convert "123E-3" style numbers to real numbers in the input? (default=false).
 *
 * @since 3.0.258
 *
 */
class FloatField extends Field {
}
