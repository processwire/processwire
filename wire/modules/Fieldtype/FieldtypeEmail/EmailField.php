<?php namespace ProcessWire;

/**
 * Email Field (for FieldtypeEmail)
 *
 * FieldtypeEmail extends FieldtypeText, so TextField settings also apply.
 *
 * Configured with InputfieldEmail or FieldtypeEmail
 * =================================================
 * @property int $confirm Include a second input for email confirmation? 1=yes (default=0).
 * @property string $confirmLabel Label for the confirmation input (default='Confirm').
 * @property int $maxlength Maximum length of the email address (default=max DB index length).
 * @property bool|int $allowIDN Allow IDN emails? 1=yes for domain, 2=yes for domain+local part (default=0).
 *
 * @since 3.0.258
 *
 */
class EmailField extends Field {
}
