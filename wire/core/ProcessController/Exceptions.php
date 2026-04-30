<?php namespace ProcessWire;

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

