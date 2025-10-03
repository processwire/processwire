<?php namespace ProcessWire;

/**
 * ProcessWire Bootstrap
 *
 * This file may be used to bootstrap either the http web accessible
 * version, or the command line client version of ProcessWire. 
 *
 * Note: if you happen to change any directory references in here, please
 * do so after you have installed the site, as the installer is not informed
 * of any changes made in this file. 
 * 
 * ProcessWire 3.x, Copyright 2025 by Ryan Cramer
 * https://processwire.com
 *
 * @version 3.0.2
 *
 * Index Versions
 * ==============
 * 302 Updated for PHP 8.4 deprecation notices, upgrade not necessary w/prior PHP versions.
 * 300 Moved much of this file to a ProcessWire::buildConfig() method.
 * 252 Extract all fuel to local API vars when in external or cli mode.
 * 251 Add $config->debugIf option.
 * 250 PW 2.5 support.
 *
 */

if(!defined("PROCESSWIRE")) define("PROCESSWIRE", 302); // index version
$rootPath = __DIR__;
if(DIRECTORY_SEPARATOR != '/') $rootPath = str_replace(DIRECTORY_SEPARATOR, '/', $rootPath);
$composerAutoloader = $rootPath . '/vendor/autoload.php'; // composer autoloader
if(file_exists($composerAutoloader)) require_once($composerAutoloader);
if(!class_exists("ProcessWire\\ProcessWire", false)) require_once("$rootPath/wire/core/ProcessWire.php");
$config = ProcessWire::buildConfig($rootPath);

if(!$config->dbName) {
	// If ProcessWire is not installed, go to the installer
	if(is_file("./install.php") && strtolower($_SERVER['REQUEST_URI']) == strtolower($config->urls->root)) {
		require("./install.php");
		exit(0);
	} else {
		header("HTTP/1.1 404 Page Not Found");
		echo "404 page not found (no site configuration or install.php available)";
		exit(0);
	}
}

$process = null;
$wire = null;

try { 
	// Bootstrap ProcessWire's core and make the API available with $wire
	$wire = new ProcessWire($config);
	$process = $wire->modules->get('ProcessPageView'); /** @var ProcessPageView $process */
	$wire->wire('process', $process); 
	echo $process->execute($config->internal);
	$config->internal ? $process->finished() : extract($wire->wire('all')->getArray());
	
} catch(\Throwable $e) {
	$wire ? $wire->trackException($e) : $config->trackException($e);
	if($process && $e instanceof \Exception) $process->finished();
	if($wire && $wire->shutdown) $wire->shutdown->setFatalError($e);
}