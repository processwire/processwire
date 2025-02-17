<?php namespace ProcessWire;

/**
 * Admin template just loads the admin application controller, 
 * and admin is just an application built on top of ProcessWire. 
 *
 * This demonstrates how you can use ProcessWire as a front-end 
 * to another application. 
 *
 * Feel free to hook admin-specific functionality from this file, 
 * but remember to leave the require() statement below at the end.
 * 
 * Note: this template file does not use the _init.php or _main.php
 * 
 */

// PLACE YOUR HOOKS HERE

/** @var Config $config */
require($config->paths->core . "admin.php"); 
// END OF FILE!
