<?php

/**
 * ProcessWire boot
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 */

if(!defined('PROCESSWIRE_CORE_PATH')) die('ProcessWire not defined');
if(PROCESSWIRE < 300) die("Please update your /index.php file for ProcessWire 3.x");

/**
 * Common classes to preload as an optimization to bypass autoloader 
 * 
 */
$corePreloads = array(
	'Fuel/Fuel.php',
	'Interfaces.php',
	'WireException.php',
	'Wire.php',
	'WireHooks/WireHooks.php',
	'WireData/WireData.php',
	'WireArray/WireArray.php',
	'WireClassLoader/WireClassLoader.php',
	'Config/FilenameArray.php',
	'Config/Paths.php',
	'Config/Config.php',
	'Functions/FunctionsWireAPI.php',
	'Functions/Functions.php',
	'Functions/LanguageFunctions.php',
	'WireShutdown.php',
	'Module/Module.php',
	'Module/ModuleConfig/ModuleConfig.php',
	'Module/CliModule/CliModule.php',
	'Debug/Debug.php',
	'WireHooks/HookEvent.php',
	'Log/WireLog.php',
	'Notices/Notices.php',
	'Sanitizer/Sanitizer.php',
	'WireDateTime/WireDateTime.php',
	'WireFileTools/WireFileTools.php',
	'WireMail/WireMailTools.php',
	'WireDatabase/WireDatabaseDialect.php',
	'WireDatabase/WireDatabaseDialectMySQL.php',
	'WireDatabase/WireDatabasePDO.php',
	'WireCache/WireCache.php',
	'Module/ModulePlaceholder.php',
	'Modules/Modules.php',
	'Fieldtype/Fieldtype.php',
	'Fieldtype/FieldtypeMulti.php',
	'WireSaveableItems/WireSaveableItems.php',
	'WireSaveableItems/WireSaveableItemsLookup.php',
	'Fieldtypes/Fieldtypes.php',
	'Fields/Fields.php',
	'Fieldgroup/Fieldgroup.php',
	'Fieldgroups/Fieldgroups.php',
	'Templates/TemplatesArray.php',
	'Templates/Templates.php',
	'Pages/Pages.php',
	'Field/Field.php',
	'DatabaseQuery/DatabaseQuery.php',
	'DatabaseQuery/DatabaseQuerySelect.php',
	'Selectors/Selectors.php',
	'Template/Template.php',
	'Page/Page.php',
	'Page/NullPage.php',
	'WireArray/PaginatedArray.php',
	'PageArray/PageArray.php',
	'Page/PageTraversal.php',
	'Users/Permission.php',
	'Users/Role.php',
	'Users/User.php',
	'Pages/PagesType.php',
	'Users/Permissions.php',
	'Users/Roles.php',
	'Users/Users.php',
	'Session/Session.php',
	'WireInput/WireInputData.php',
	'WireInput/WireInput.php',
	'Module/Process/Process.php',
	'Page/PageComparison.php',
	'Admin/AdminTheme.php',
	'Inputfield/Inputfield.php',
	'TemplateFile/TemplateFile.php',
	'ProcessController/ProcessController.php',
	'Inputfield/InputfieldWrapper.php',
	'Session/SessionCSRF.php',
);	

foreach($corePreloads as $file) {
	/** @noinspection PhpIncludeInspection */
	include_once(PROCESSWIRE_CORE_PATH . $file);
}
unset($corePreloads);
