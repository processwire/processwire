<?php namespace ProcessWire;

/**
 * URL Field (for FieldtypeURL)
 *
 * FieldtypeURL extends FieldtypeText, so TextField settings also apply.
 *
 * Configured with FieldtypeURL / InputfieldURL
 * ==============================
 * @property bool|int $noRelative Disallow relative/local URLs without scheme? (default=false).
 * @property bool|int $allowIDN Allow internationalized domain names (IDNs)? (default=false).
 * @property bool|int $allowQuotes Allow single/double quote characters in URLs? (default=false).
 * @property bool|int $addRoot Prepend site's root path to relative URLs during output formatting? (default=false).
 *
 * @since 3.0.258
 *
 */
class URLField extends Field {
}
