<?php

/**
 * ProcessWire boot
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

if(!defined("PROCESSWIRE")) define("PROCESSWIRE", 300); 
if(!defined("PROCESSWIRE_CORE_PATH")) define("PROCESSWIRE_CORE_PATH", __DIR__ . '/');

if(PROCESSWIRE < 300) die("Please update your /index.php file for ProcessWire 3.x");

/**
 * Common classes to preload as an optimization to bypass autoloader 
 * 
 */
$corePreloads = array(
	'Fuel.php',
	'Interfaces.php',
	'Exceptions.php',
	'Wire.php',
	'WireHooks.php',
	'WireData.php',
	'WireArray.php',
	'WireClassLoader.php',
	'FilenameArray.php',
	'Paths.php',
	'Config.php',
	'FunctionsWireAPI.php',
	'Functions.php',
	'LanguageFunctions.php',
	'WireShutdown.php',
	'Module.php',
	'ModuleConfig.php', 
	'Debug.php',
	'HookEvent.php',
	'WireLog.php',
	'Notices.php',
	'Sanitizer.php',
	'WireDateTime.php',
	'WireFileTools.php',
	'WireMailTools.php',
	'WireDatabasePDO.php',
	'DatabaseMysqli.php',
	'WireCache.php',
	'Modules.php',
	'ModulesDuplicates.php',
	'ModulePlaceholder.php',
	'Fieldtype.php',
	'FieldtypeMulti.php',
	'ConfigurableModule.php',
	'FileCompiler.php',
	'WireSaveableItems.php',
	'WireSaveableItemsLookup.php',
	'Fieldtypes.php',
	'Fields.php',
	'FieldsArray.php',
	'Fieldgroup.php',
	'FieldgroupsArray.php',
	'Fieldgroups.php',
	'TemplatesArray.php',
	'Templates.php',
	'Pages.php',
	'PagesSortfields.php',
	'PagesLoader.php',
	'PagesLoaderCache.php',
	'PagesEditor.php',
	'Field.php',
	'DatabaseQuery.php',
	'DatabaseQuerySelect.php',
	'Selectors.php',
	'Template.php',
	'Page.php',
	'NullPage.php',
	'PaginatedArray.php',
	'PageArray.php',
	'PageTraversal.php',
	'Permission.php',
	'Role.php',
	'User.php',
	'PagesType.php',
	'Permissions.php',
	'Roles.php',
	'Users.php',
	'Session.php',
	'WireInputData.php',
	'WireInput.php',
	'Process.php',
	'PageFinder.php',
	'PageComparison.php',
	'AdminTheme.php',
	'Inputfield.php',
	'TemplateFile.php',
	'ModuleJS.php',
	'Breadcrumb.php',
	'Breadcrumbs.php',
	'ProcessController.php',
	'InputfieldWrapper.php',
	'Textformatter.php',
	'SessionCSRF.php',
);	

foreach($corePreloads as $file) {
	/** @noinspection PhpIncludeInspection */
	include_once(PROCESSWIRE_CORE_PATH . $file);
}
unset($corePreloads);
