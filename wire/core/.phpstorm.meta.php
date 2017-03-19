<?php 
/**
 * ProcessWire PhpStorm Meta
 *
 * This file is not a CODE, it makes no sense and won't run or validate
 * Its AST serves PhpStorm IDE as DATA source to make advanced type inference decisions.
 *
 * @see https://confluence.jetbrains.com/display/PhpStorm/PhpStorm+Advanced+Metadata
 */


namespace PHPSTORM_META {

	$STATIC_METHOD_TYPES = [
		\wire('') => [
			'' == '@',
			'config' instanceof \ProcessWire\Config,
			'cache' instanceof \ProcessWire\WireCache,
			'wire' instanceof \ProcessWire\ProcessWire,
			'log' instanceof \ProcessWire\WireLog,
			'notices' instanceof \ProcessWire\Notices,
			'sanitizer' instanceof \ProcessWire\Sanitizer,
			'database' instanceof \ProcessWire\WireDatabasePDO,
			'db' instanceof \ProcessWire\DatabaseMysqli,
			'cache' instanceof \ProcessWire\MarkupCache,
			'modules' instanceof \ProcessWire\Modules,
			'procache' instanceof \ProCache,
			'fieldtypes' instanceof \ProcessWire\Fieldtypes,
			'fields' instanceof \ProcessWire\Fields,
			'fieldgroups' instanceof \ProcessWire\Fieldgroups,
			'templates' instanceof \ProcessWire\Templates,
			'pages' instanceof \ProcessWire\Pages,
			'permissions' instanceof \ProcessWire\Permissions,
			'roles' instanceof \ProcessWire\Roles,
			'users' instanceof \ProcessWire\Users,
			'user' instanceof \ProcessWire\User,
			'session' instanceof \ProcessWire\Session,
			'input' instanceof \ProcessWire\WireInput,
			'languages' instanceof \ProcessWire\Languages,
			'page' instanceof \ProcessWire\Page,
			'hooks' instanceof \ProcessWire\WireHooks,
			'files' instanceof \ProcessWire\WireFileTools,
			'datetime' instanceof \ProcessWire\WireDateTime,
			'mail' instanceof \ProcessWire\WireMailTools
		],
		\Wire::wire('') => [ 
			// this one does not appear to work, leaving in case someone knows how to make it work
			'' == '@',
			'config' instanceof \ProcessWire\Config,
			'cache' instanceof \ProcessWire\WireCache,
			'wire' instanceof \ProcessWire\ProcessWire,
			'log' instanceof \ProcessWire\WireLog,
			'notices' instanceof \ProcessWire\Notices,
			'sanitizer' instanceof \ProcessWire\Sanitizer,
			'database' instanceof \ProcessWire\WireDatabasePDO,
			'db' instanceof \ProcessWire\DatabaseMysqli,
			'cache' instanceof \ProcessWire\MarkupCache,
			'modules' instanceof \ProcessWire\Modules,
			'procache' instanceof \ProCache,
			'fieldtypes' instanceof \ProcessWire\Fieldtypes,
			'fields' instanceof \ProcessWire\Fields,
			'fieldgroups' instanceof \ProcessWire\Fieldgroups,
			'templates' instanceof \ProcessWire\Templates,
			'pages' instanceof \ProcessWire\Pages,
			'permissions' instanceof \ProcessWire\Permissions,
			'roles' instanceof \ProcessWire\Roles,
			'users' instanceof \ProcessWire\Users,
			'user' instanceof \ProcessWire\User,
			'session' instanceof \ProcessWire\Session,
			'input' instanceof \ProcessWire\WireInput,
			'languages' instanceof \ProcessWire\Languages,
			'page' instanceof \ProcessWire\Page,
			'hooks' instanceof \ProcessWire\WireHooks,
			'files' instanceof \ProcessWire\WireFileTools,
			'datetime' instanceof \ProcessWire\WireDateTime,
			'mail' instanceof \ProcessWire\WireMailTools
		]
	];
}
