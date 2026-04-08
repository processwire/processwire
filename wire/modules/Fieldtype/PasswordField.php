<?php namespace ProcessWire;

/**
 * Password Field (for FieldtypePassword)
 *
 * Configured with InputfieldPassword
 * ==============================
 * @property array $requirements Password requirements (array of InputfieldPassword::require* constants, e.g. 'letter', 'digit') (default=['letter','digit']).
 * @property float $complexifyFactor Complexity threshold (0.0–1.0), lower values allow simpler passwords (default=0.7).
 * @property string $complexifyBanMode Complexify ban mode: 'loose' or 'strict' (default=loose).
 * @property int $requireOld Require current password when setting a new one: 0=auto, 1=yes, -1=no (default=0).
 * @property bool $unmask Allow user to reveal the password they are entering? (default=false).
 * @property bool $showPass Allow password to be rendered in renderValue? (default=false).
 * @property string $defaultLabel Label shown on the field when no label has been set (default='Set Password').
 * @property string $oldPassLabel Placeholder label for current password input (default='Current Password').
 * @property string $newPassLabel Placeholder label for new password input (default='New Password').
 * @property string $confirmLabel Placeholder label for confirm password input (default='Confirm').
 *
 * @since 3.0.258
 *
 */
class PasswordField extends Field {
}
