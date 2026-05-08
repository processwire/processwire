<?php namespace ProcessWire;

/**
 * ProcessWire core files class map relative to wire/core/
 * 
 * This excludes classes that are loaded on every run, see wire/core/boot.php for those. 
 * 
 * Indexes are class names (in ProcessWire namespace)
 * 
 * Values are any of the following:
 * 
 * - Filename relative to wire/core/, i.e. `DirName/ClassName/ClassName.php`
 * - Dirname followed by `>`, i.e. `DirName>` to translate to: `DirName/ClassName/ClassName.php`
 * - Dirname followed by `/`, i.e. `DirName/` to translate to: `DirName/ClassName.php`
 * 
 */

return [ // please keep alphabetical A-Z
	'AdminThemeFramework' => 'Admin/',
	'Breadcrumb' => 'Admin/',
	'Breadcrumbs' => 'Admin/',
	'CacheFile' => 'WireCache/',
	'ConfigurableModule' => 'Module/',
	'Database' => 'Database/', // deprecated
	'DatabaseMysqli' => 'Database/', // deprecated
	'DatabaseQuerySelectFulltext' => 'DatabaseQuery/',
	'DatabaseStopwords' => 'WireDatabase/',
	'FieldsArray' => 'Fields/',
	'FieldSelectorInfo' => 'Field/',
	'FieldsTableTools' => 'Fields/',
	'FileCompiler' => 'Tools/',
	'FileCompilerModule' => 'Module/',
	'FileLog' => 'Log/',
	'FileValidatorModule' => 'Module>',
	'ImageInspector' => 'Image/',
	'ImageSizer' => 'Image/',
	'ImageSizerEngine' => 'Image/',
	'ImageSizerEngineGD' => 'Image/',
	'InputfieldsArray' => 'Inputfield/',
	'MarkupFieldtype' => 'Fieldtype/',
	'MarkupQA' => 'Tools/',
	'ModuleConfig' => 'Module>',
	'ModuleJS' => 'Module>',
	'NullField' => 'Field/',
	'PageAccess' => 'Page/',
	'PageAction' => 'Module/',
	'PageArrayIterator' => 'PageArray/',
	'Pagefile' => 'Pagefiles/',
	'PagefileExtra' => 'Pagefiles/',
	'Pagefiles' => 'Pagefiles/',
	'PagefilesManager' => 'Page/',
	'PageFinder' => 'PageFinder/',
	'PageFinder2' => 'PageFinder/',
	'Pageimage' => 'Pageimages/',
	'PageimageDebugInfo' => 'Pageimages/',
	'Pageimages' => 'Pageimages/',
	'PageimageVariations' => 'Pageimages/',
	'PageProperties' => 'Page/',
	'PageValues' => 'Page/',
	'Password' => 'Tools/',
	'ProcessWireCli' => 'Tools/',
	'Punycode' => 'Sanitizer/',
	'PWGIF' => 'Image/',
	'PWPNG' => 'Image/',
	'SearchableModule' => 'Module>',
	'Selector' => 'Selectors/',
	'Textformatter' => 'Module>',
	'Tfa' => 'Module>', // Trailing '>' translates to Module/Tfa/Tfa.php
	'WireAction' => 'Module/',
	'WireCacheDatabase' => 'WireCache/',
	'WireCacheInterface' => 'WireCache/',
	'WireDatabaseBackup' => 'WireDatabase/',
	'WireDatabaseDialect' => 'WireDatabase/',
	'WireDatabaseDialectMySQL' => 'WireDatabase/',
	'WireDatabasePDOStatement' => 'WireDatabase/',
	'WireDataDB' => 'WireData/',
	'WireDebugInfo' => 'Debug/',
	'WireHttp' => 'Tools>',
	'WireInputDataCookie' => 'WireInput/',
	'WireMail' => 'WireMail/',
	'WireMailInterface' => 'WireMail/',
	'WireMarkupFileRegions' => 'Tools/WireMarkupRegions/', // deprecated
	'WireMarkupRegions' => 'Tools>',
	'WireNumberTools' => 'Tools>',
	'WireRandom' => 'Tools>',
	'WireSessionHandler' => 'Session/',
	'WireSessionHandlerAdaptor' => 'Session/',
	'WireTempDir' => 'Tools/',
	'WireTextTools' => 'Tools>',
	'WireUpload' => 'Tools/',
];
