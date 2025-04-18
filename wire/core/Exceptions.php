<?php namespace ProcessWire;

/**
 * ProcessWire Exceptions
 *
 * Exceptions that aren't specific to a particular class. 
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 *
 * ProcessWire 3.x, Copyright 2025 by Ryan Cramer
 * https://processwire.com
 *
 */

/**
 * Throw a new WireException functionally
 * 
 * This can be used to facilitiate re-throwing a non-WireException as a WireException, 
 * notably \PDOException or other exception classes that might use string for `code` property.
 * 
 * ~~~~
 * // throw random WireException
 * WireException();
 * 
 * // throw with message
 * WireException('Hello world'); 
 * 
 * // throw WirePermissionException
 * WireException([ 'class' => 'WirePermissionException', 'message' => 'No access' ]); 
 * 
 * // re-throw previous exception as WireException (and inherit message and code)
 * WireException([ 'previous' => $exception ]);
 * ~~~~
 * 
 * @param array|string $options One of the following options, or string for just `message`: 
 *  - `class` (string): Class name of WireException to throw (default='WireException').
 *  - `message` (string): Exception message string (default='' or pulled from previous exception).
 *  - `code` (int|string): Exception code integer or alphanumeric string (default=0 or pulled from previous exception).
 *  - `previous` (\Throwable): Previous exception. When present, code and message will be pulled from it if not specified.
 * @throws WireException
 * @since 3.0.248
 * 
 * 
 */
function WireException($options = []) {
	$defaults = [
		'class' => 'WireException',
		'message' => is_string($options) ? $options : '', 
		'code' => 0, 
		'previous' => null, 
	];
	$options = is_array($options) ? array_merge($defaults, $options) : $defaults;
	if($options['previous'] instanceof \Throwable) {
		if(empty($options['message'])) {
			$options['message'] = $options['previous']->getMessage();
		}
		if(empty($options['code'])) {
			$options['code'] = $options['previous']->getCode();
		}
	} else {
		$options['previous'] = null;
	}
	$class = wireClassName($options['class'], true);
	$e = new $class($options['message'], 0, $options['previous']);
	if($e instanceof WireException && $options['code'] !== 0) {
		$e->setCode($options['code']);
	}
	throw $e; 
}

/**
 * Generic ProcessWire exception
 *
 */
class WireException extends \Exception {

	/**
	 * Exception code when a string
	 * 
	 * @var string 
	 * @since 3.0.248
	 * 
	 */
	protected $codeStr = '';
	
	/**
	 * Replace previously set message
	 * 
	 * Public since 3.0.248
	 * 
	 * @param string $message
	 * @since 3.0.150
	 * 
	 */
	public function setMessage($message) {
		$this->message = $message;
	}

	/**
	 * Replace previously set code
	 * 
	 * Public since 3.0.248
	 * 
	 * @param int|string $code
	 * @since 3.0.150
	 * 
	 */
	public function setCode($code) {
		if(is_string($code)) {
			$this->setCodeStr($code);
			if(ctype_digit($code)) $this->code = (int) $code;
		} else {
			$this->code = (int) $code;
		}
	}

	/**
	 * Set alphanumeric code string
	 * 
	 * @param string $codeStr
	 * @since 3.0.248
	 * 
	 */
	public function setCodeStr($codeStr) {
		$this->codeStr = (string) $codeStr;
	}

	/**
	 * Get alphanumeric/string code if set, blank string if not
	 * 
	 * @return string
	 * @since 3.0.248
	 * 
	 */
	public function getCodeStr() {
		return $this->codeStr;
	}
}

/**
 * Thrown when access to a resource is not allowed
 *
 */
class WirePermissionException extends WireException {}

/**
 * Thrown when a requested page does not exist, or can be thrown manually to show the 404 page
 *
 */
class Wire404Exception extends WireException {
	
	/**
	 * 404 is because core determined requested resource by URL does not physically exist
	 * 
	 * #pw-internal
	 *
	 */ 
	const codeNonexist = 404;
	
	/**
	 * 404 is a result of a resource that might exist but there is no access
	 * 
	 * Similar to a WirePermissionException except always still a 404 externally
	 * 
	 * #pw-internal
	 *
	 */
	const codePermission = 4041;
	
	/**
	 * 404 is a result of a secondary non-file asset that does not exist, even if page does
	 * 
	 * For example: /foo/bar/?id=123 where /foo/bar/ exists but 123 points to non-existent asset.
	 * 
	 * #pw-internal
	 *
	 */
	const codeSecondary = 4042;

	/**
	 * 404 is a result of content not available in requested language
	 * 
	 * #pw-internal
	 *
	 */
	const codeLanguage = 4043;
	
	/**
	 * 404 is a result of a physical file that does not exist on the file system
	 * 
	 * #pw-internal
	 *
	 */
	const codeFile = 4044;

	/**
	 * 404 is a result of a front-end wire404() function call
	 * 
	 * #pw-internal
	 * 
	 */
	const codeFunction = 4045;
	
	/**
	 * Anonymous 404 with no code provided 
	 *
	 * #pw-internal
	 *
	 */
	const codeAnonymous = 0;

}

/**
 * Thrown when ProcessWire is unable to connect to the database at boot
 *
 */
class WireDatabaseException extends WireException {}

/**
 * Thrown by DatabaseQuery classes on query exception
 * 
 * May have \PDOException populated with call to its getPrevious(); method, 
 * in which can it also has same getCode() and getMessage() as \PDOException.
 * Use getCodeStr() for PDOException string code. 
 * 
 * @since 3.0.156
 * 
 */
class WireDatabaseQueryException extends WireException {}

/**
 * Thrown when cross site request forgery detected by SessionCSRF::validate()
 *
 */
class WireCSRFException extends WireException {}

/**
 * Thrown when fatal error from $files API var (or related) occurs
 * 
 * @since 3.0.178
 *
 */
class WireFilesException extends WireException {}

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
