<?php

/**
 * ProcessWire Configuration File
 *
 * Site-specific configuration for ProcessWire.
 * https://processwire.com/api/ref/config/
 *
 * Please see the file /wire/config.php which contains all configuration options you may
 * specify here. Simply copy any of the configuration options from that file and paste
 * them into this file in order to modify them.
 *
 * ProcessWire 3.x
 * Copyright (C) 2019 by Ryan Cramer
 *
 * https://processwire.com
 *
 */

if(!defined("PROCESSWIRE")) die();

/*** SITE CONFIG *************************************************************************/

$config->prependTemplateFile = '_init.php';
$config->appendTemplateFile = '_main.php';
$config->useMarkupRegions = true;
$config->useFunctionsAPI = true; 


/*** INSTALLER CONFIG ********************************************************************/


