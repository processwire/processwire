<?php namespace ProcessWire;

/**
 * Text Field (for FieldtypeText)
 *
 * Configured with FieldtypeText
 * ==============================
 * @property array $textformatters Array of Textformatter module class names applied during output formatting.
 * @property string $inputfieldClass Inputfield module class name to use instead of the default InputfieldText.
 *
 * Configured with InputfieldText
 * ==============================
 * @property int $size Displayed width of the input in characters, or 0 for full width (default=0).
 * @property int $minlength Minimum allowed length in characters, or 0 for no minimum (default=0).
 * @property int $maxlength Maximum allowed length in characters, or 0 for no maximum (default=2048).
 * @property string $placeholder Optional placeholder text shown in the input when blank.
 * @property string $pattern Optional HTML5 pattern attribute for client-side and server-side validation.
 * @property string $initValue Optional initial/default value pre-populated in the input.
 * @property bool $stripTags Strip HTML tags from value on input? (default=false)
 * @property bool $noTrim Disable automatic whitespace trimming from value? (default=false)
 * @property bool $useLanguages Provide one input per language when multi-language support is active? (default=false)
 * @property bool|int $requiredAttr Also apply HTML5 "required" attribute when field is required? (default=false)
 * @property int $showCount Show character counter (1), word counter (2), or neither (0) (default=0).
 * @property string|null $autocomplete HTML5 autocomplete attribute value, e.g. "on", "off", "email", "name" (default=null). Since 3.0.252.
 *
 * @since 3.0.258
 *
 */
class TextField extends Field {
}
