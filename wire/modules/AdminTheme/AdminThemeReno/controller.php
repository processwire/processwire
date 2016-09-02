<?php namespace ProcessWire;

/**
 * This file is just here for backwards compatibility
 *
 * It is called by /site/templates/admin.php on sites running a /site/templates/ dir from before the admin
 * controller was move to the /wire/core/admin.php dir. 
 *
 * This file need not be present in new admin themes, and will eventually be removed from this theme.
 *
 */ 

require($config->paths->core . "admin.php"); 
