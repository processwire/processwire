<?php namespace ProcessWire;

/**
 * Datetime Field (for FieldtypeDatetime)
 *
 * Configured with FieldtypeDatetime
 * ==============================
 * @property string $dateOutputFormat PHP date() format string used for formatted output (default='Y-m-d').
 *   Uses PHP date format as described here: <https://www.php.net/manual/en/datetime.format.php>.
 *   Per-language variants are stored as dateOutputFormat[languageId], e.g. $field->get('dateOutputFormat1234').
 *
 * Configured with InputfieldDatetime (common to all input types)
 * ==============================
 * @property string $inputType Input type to use: 'text' (default), 'select', or 'html'.
 * @property bool|int $defaultToday When no value is present, default to today's date/time? (default=false).
 * @property bool|int $requiredAttr When field is required, also add HTML5 required attribute? (default=false).
 *
 * Input type "text" — text input with optional jQuery UI datepicker
 * ==============================
 * @property string $dateInputFormat Date input format (PHP date syntax), e.g. 'Y-m-d' (default='Y-m-d').
 * @property string $timeInputFormat Time input format (PHP date syntax), e.g. 'g:i a', or blank for date-only (default='').
 * @property int $datepicker jQuery UI datepicker mode: 0=none, 1=on click, 2=on focus, 3=inline (default=0).
 * @property string $yearRange Selectable year range for datepicker, e.g. '-30:+20' (default='').
 * @property int $timeInputSelect Use a select for time input rather than a slider? 1=yes, 0=no (default=0).
 * @property string $placeholder Placeholder attribute text (default='').
 * @property string $showAnim Datepicker show animation type (default='fade').
 * @property bool|int $changeMonth Show month dropdown in datepicker? (default=true).
 * @property bool|int $changeYear Show year dropdown in datepicker? (default=true).
 * @property bool|int $showButtonPanel Show Today/Done buttons in datepicker? (default=false).
 * @property int $numberOfMonths Number of month calendars shown side by side in datepicker (default=1).
 * @property bool|int $showMonthAfterYear Show month after year in datepicker header? (default=false).
 * @property bool|int $showOtherMonths Show non-selectable days from adjacent months? (default=false).
 *
 * Input type "html" — HTML5 date/time inputs
 * ==============================
 * @property string $htmlType HTML5 input type: 'date', 'time', or 'datetime' (default='date').
 * @property int $dateStep Step attribute for date inputs (default=1).
 * @property string $dateMin Minimum selectable date, ISO-8601 format YYYY-MM-DD (default='').
 * @property string $dateMax Maximum selectable date, ISO-8601 format YYYY-MM-DD (default='').
 * @property int $timeStep Step attribute for time inputs in seconds (default=60).
 * @property string $timeMin Minimum selectable time, HH:MM format (default='').
 * @property string $timeMax Maximum selectable time, HH:MM format (default='').
 *
 * Input type "select" — dropdown selects for date/time components
 * ==============================
 * @property string $dateSelectFormat Order and style of date selects: 'mdy' (abbr month), 'Mdy' (full month), etc. (default='').
 * @property string $timeSelectFormat Format for time selects (default='').
 * @property int $yearFrom First selectable year (default=current year - 100).
 * @property int $yearTo Last selectable year (default=current year + 20).
 * @property bool|int $yearLock Disallow selection of years outside yearFrom/yearTo range? (default=false).
 *
 * @since 3.0.258
 *
 */
class DatetimeField extends Field {
}
