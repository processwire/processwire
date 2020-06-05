<?php namespace ProcessWire;

/**
 * ProcessWire multi-domain configuration file (optional)
 *
 * If used, this file should be copied/moved to the ProcessWire installation root directory.
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 */

if(!defined("PROCESSWIRE")) die();

/**
 * Multi-domain configuration: Optionally define alternate /site/ dirs according to host
 *
 * If used, this file should be placed in your web root and then edited as follows. 
 *
 * This function returns an array that should be in the format where the array key
 * is the hostname (including domain) and the value is the /site/ directory you want to use.
 * This value must start with 'site-', i.e. 'site-domain' or 'site-something'. This is to
 * ensure that ProcessWire's htaccess file can recognize and protect files in that directory.
 *
 * Note that if your site may be accessed at either domain.com OR www.domain.com, then you'll
 * want to include entries for both, pointing to the same /site-domain/ directory. 
 * 
 * Each /site/ dir has its own /site/config.php file that should be pointing to a separate
 * database. You shouldn't have two different /site/ dirs sharing the same database. 
 *
 */
function ProcessWireHostSiteConfig() {
	return array(
		/*
		 * Some Examples (you should remove/replace them if used).
		 * Just note that the values must begin with 'site-'.
		 *
		 */
		 'mydomain.com' => 'site-mydomain',
		 'www.mydomain.com' => 'site-mydomain',
		 'dev.mydomain.com' => 'site-dev',
		 'www.otherdomain.com' => 'site-other',

		/*
		 * Default for all others (typically /site/)
		 *
		 */
		'*' => 'site',
	);
}

