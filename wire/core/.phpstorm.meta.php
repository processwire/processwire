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
			'config' instanceof Config,
			'wire' instanceof ProcessWire,
			'log' instanceof WireLog,
			'notices' instanceof Notices,
			'sanitizer' instanceof Sanitizer,
			'database' instanceof WireDatabasePDO,
			'db' instanceof DatabaseMysqli,
			'cache' instanceof MarkupCache,
			'modules' instanceof Modules,
			'procache' instanceof ProCache,
			'fieldtypes' instanceof Fieldtypes,
			'fields' instanceof Fields,
			'fieldgroups' instanceof Fieldgroups,
			'templates' instanceof Templates,
			'pages' instanceof Pages,
			'permissions' instanceof Permissions,
			'roles' instanceof Roles,
			'users' instanceof Users,
			'user' instanceof User,
			'session' instanceof Session,
			'input' instanceof WireInput,
			'languages' instanceof Languages,
			'page' instanceof Page,
			'hooks' instanceof WireHooks,
			'files' instanceof WireFileTools,
			'datetime' instanceof WireDateTime,
			'mail' instanceof WireMailTools
		],
		\Wire::wire('') => [ 
			// this one does not appear to work, leaving in case someone knows how to make it work
			'' == '@',
			'config' instanceof Config,
			'wire' instanceof ProcessWire,
			'log' instanceof WireLog,
			'notices' instanceof Notices,
			'sanitizer' instanceof Sanitizer,
			'database' instanceof WireDatabasePDO,
			'db' instanceof DatabaseMysqli,
			'cache' instanceof MarkupCache,
			'modules' instanceof Modules,
			'procache' instanceof ProCache,
			'fieldtypes' instanceof Fieldtypes,
			'fields' instanceof Fields,
			'fieldgroups' instanceof Fieldgroups,
			'templates' instanceof Templates,
			'pages' instanceof Pages,
			'permissions' instanceof Permissions,
			'roles' instanceof Roles,
			'users' instanceof Users,
			'user' instanceof User,
			'session' instanceof Session,
			'input' instanceof WireInput,
			'languages' instanceof Languages,
			'page' instanceof Page,
			'hooks' instanceof WireHooks,
			'files' instanceof WireFileTools,
			'datetime' instanceof WireDateTime,
			'mail' instanceof WireMailTools
		]
	];
}
