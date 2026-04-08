<?php namespace ProcessWire;

/**
 * Textarea Field (for FieldtypeTextarea)
 *
 * FieldtypeTextarea extends FieldtypeText, so TextField settings also apply.
 *
 * Configured with FieldtypeTextarea
 * ==============================
 * @property int $contentType Content type of field output: 0=plain/unknown (default), 1=HTML/Markup, 2=HTML with image management.
 * @property array $htmlOptions HTML management options (array of FieldtypeTextarea::html* constants), applicable when contentType >= 1.
 * @property string $inputfieldClass Inputfield module class name to use for editing (default=InputfieldTextarea). A common alternate is InputfieldTinyMCE. 
 *
 * Configured with InputfieldTextarea (or alternate inputfieldClass)
 * ==============================
 * @property int $rows Number of visible rows in the textarea (default=5).
 *
 * @since 3.0.258
 *
 */
class TextareaField extends Field {
}
