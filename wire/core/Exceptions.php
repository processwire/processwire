<?php namespace ProcessWire;

/**
 * ProcessWire Exceptions
 *
 * Exceptions that aren't specific to a particular class. 
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 *
 * ProcessWire 3.x, Copyright 2018 by Ryan Cramer
 * https://processwire.com
 *
 */

/**
 * Generic ProcessWire exception
 *
 */
class WireException extends \Exception {}

/**
 * Thrown when access to a resource is not allowed
 *
 */
class WirePermissionException extends WireException {}

/**
 * Thrown when a requested page does not exist, or can be thrown manually to show the 404 page
 *
 */
class Wire404Exception extends WireException {}

/**
 * Thrown when ProcessWire is unable to connect to the database at boot
 *
 */
class WireDatabaseException extends WireException {}

/**
 * Thrown when cross site request forgery detected by SessionCSRF::validate()
 *
 */
class WireCSRFException extends WireException {}

/**
 * Thrown when a requested Process or Process method is requested that doesn’t exist
 *
 */
class ProcessController404Exception extends Wire404Exception { }

/**
 * Thrown when the user doesn’t have access to execute the requested Process or method
 *
 */
class ProcessControllerPermissionException extends WirePermissionException { }

/**
 * Thrown by PageFinder when an error occurs trying to find pages
 * 
 */
class PageFinderException extends WireException { }

/**
 * Thrown by PageFinder when it detects an error in the syntax of a given page-finding selector
 *
 */
class PageFinderSyntaxException extends PageFinderException { }


