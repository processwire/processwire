<?php namespace ProcessWire;

// Initialization file, called before rendering any template file.
// This is defined by $config->prependTemplateFile in /site/config.php.
// Variables defined here are available to template files and to _main.php.
//
// Define functions in _func.php (not here), since this file is included
// again whenever another page is rendered during the same request.

/** @var Pages $pages */

include_once(__DIR__ . '/_func.php');

/** @var HomePage $home */
$home = $pages->get('/');
