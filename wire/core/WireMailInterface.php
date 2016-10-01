<?php namespace ProcessWire;

/**
 * ProcessWire WireMail Interface
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

interface WireMailInterface {

	/**
	 * Set the email to address
	 *
	 * Each added email addresses appends to any addresses already supplied, unless
	 * you specify NULL as the email address, in which case it clears them all.
	 *
	 * @param string|array|null $email Specify any ONE of the following: 
	 *	1. Single email address or "User Name <user@example.com>" string. 
	 * 	2. CSV string of #1. 
	 * 	3. Non-associative array of #1. 
	 * 	4. Associative array of (email => name)
	 *	5. NULL (default value, to clear out any previously set values)
	 * @param string $name Optionally provide a FROM name, applicable
	 *	only when specifying #1 (single email) for the first argument. 
	 * @return this 
	 * @throws WireException if any provided emails were invalid
	 *
	 */
	public function to($email = null, $name = null);	

	/**
	 * Set the email from address
	 *
	 * @param string Must be a single email address or "User Name <user@example.com>" string.
	 * @param string|null An optional FROM name (same as setting/calling fromName)
	 * @return this 
	 * @throws WireException if provided email was invalid
	 *
	 */
	public function from($email, $name = null); 

	/**
	 * Set the email subject
	 *
	 * @param string $subject 
	 * @return this 
	 *
	 */
	public function subject($subject); 

	/**
	 * Set the email message body (text only)
	 *
	 * @param string $body in text only
	 * @return this 
	 *
	 */
	public function body($body); 

	/**
	 * Set the email message body (HTML only)
	 *
	 * @param string $body in HTML
	 * @return this 
	 *
	 */
	public function bodyHTML($body); 

	/**
	 * Set any email header
	 *
	 * @param string $key
	 * @param string $value
	 * @return this 
	 *
	 */
	public function header($key, $value); 

	/**
	 * Set any email param 
	 *
	 * See $additional_parameters at: http://www.php.net/manual/en/function.mail.php
	 *
	 * @param string $value
	 * @return this 
	 *
	 */
	public function param($value); 

	/**
	 * Add a file to be attached to the email
	 *
	 *
	 * @param string $value
	 * @param string $filename
	 * @return this 
	 *
	public function attachment($value, $filename = ''); 
	 */

	/**
	 * Send the email
	 *
	 * @return int Returns number of messages sent or 0 on failure
	 *
	 */
	public function ___send(); 
}
