<?php namespace ProcessWire;

/**
 * ProcessWire Configuration File
 *
 * Site-specific configuration for ProcessWire
 *
 * Please see the file /wire/config.php which contains all configuration options you may
 * specify here. Simply copy any of the configuration options from that file and paste
 * them into this file in order to modify them.
 * 
 * SECURITY NOTICE
 * In non-dedicated environments, you should lock down the permissions of this file so
 * that it cannot be seen by other users on the system. For more information, please
 * see the config.php section at: https://processwire.com/docs/security/file-permissions/
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 *
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 *
 */

if(!defined("PROCESSWIRE")) die();

/** @var Config $config */

/*** SITE CONFIG *************************************************************************/

// Let core API vars also be functions? So you can use $page or page(), for example.
$config->useFunctionsAPI = true;

// Use custom Page classes in /site/classes/ ? (i.e. template "home" => HomePage.php)
$config->usePageClasses = true;

// Use Markup Regions? (https://processwire.com/docs/front-end/output/markup-regions/)
$config->useMarkupRegions = true;

// Prepend this file in /site/templates/ to any rendered template files
$config->prependTemplateFile = '_init.php';

// Append this file in /site/templates/ to any rendered template files
$config->appendTemplateFile = '_main.php';

// Allow template files to be compiled for backwards compatibility?
$config->templateCompile = false;

/*** INSTALLER CONFIG ********************************************************************/

