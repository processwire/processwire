<?php namespace ProcessWire;

if(!defined("PROCESSWIRE")) die();

/**
 * This file is just here for backwards compatibility
 *
 * It is called by /site/templates/admin.php on sites running a /site/templates/ dir from before the admin
 * controller was moved to the /wire/core/admin.php dir. 
 *
 * This file need not be present in new admin themes, and will eventually be removed from this theme.
 *
 */ 
/** @var Config $config */
require($config->paths->core . "admin.php"); 
