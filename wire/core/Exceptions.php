<?php namespace ProcessWire;

/**
 * ProcessWire Exceptions
 *
 * Exceptions that aren't specific to a particular class. 
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

/**
 * Generic ProcessWire exception
 *
 */
class WireException extends \Exception {}

/**
 * Triggered when access to a resource is not allowed
 *
 */
class WirePermissionException extends WireException {}

/**
 * Triggered when a requested item does not exist and generates a fatal error
 *
 */
class Wire404Exception extends WireException {}

/**
 * WireDatabaseException is the exception thrown by the Database class
 *
 * If you use this class without ProcessWire, change 'extends WireException' below to be just 'extends Exception'
 *
 */
class WireDatabaseException extends WireException {}
