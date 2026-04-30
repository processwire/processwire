<?php namespace ProcessWire;

/**
 * CliModule interface
 * 
 * #pw-summary Interface for command-line module
 *
 * #pw-body =
 * A CliModule must have a 'cli' key in its getModuleInfo() that provides the name
 * to access the module by from the command line.
 *
 * When possible, define module class with `implements CliModule` but you can also
 * implement by making sure the 'cli' key is populated in the getModuleInfo() and
 * that the getCliCommand() method is implemented. 
 * 
 * Modules implementing CliModule do not need to be 'autoload' as ProcessWire will
 * load them on demand when they are requested. 
 * 
 * Since the CliModule interface was added in ProcessWire 3.0.259, it's best if 
 * you have your getModuleInfo() method (or ModuleName.info.php or ModuleName.info.json)
 * include a `'requires' => 'ProcessWire>=3.0.259'` in its info array. 
 *
 * ```php
 * // Example "Hello World" CliModule
 * //
 * // Usage: php index.php hi
 * //        php index.php hi Ryan
 * //        php index.php bye
 * //        php index.php bye Ryan
 *
 * class HelloWorldCli extends WireData implements Module, CliModule {
 *   public static function getModuleInfo() {
 *     return [
 *       'title' => 'Hello World CLI module',
 *       'description' => 'Just an example',
 *       'version' => 1,
 *       'cli' => 'hello', // Example: php index.php hello
 *     ];
 *   }
 *   public function executeCli(array $args) {
 *     $command = $args[0] ?? '';
 *     $name = isset($args[1]) ? $args[1] : 'friend';
 *     if($command === 'hi') {
 *       echo "Hello there $name!";
 *     } else if($command === 'bye') {
 *       echo "Goodbye $name, see you later!";
 *     } else {
 *       echo "Specify 'hi' or 'bye' optionally followed by a name";
 *     }
 *   }
 *   public function getCommands() {
 *     return [
 *       'hi' => 'Say hello',
 *       'bye' => 'Say goodbye',
 *     ];
 *   }
 * }
 *
 * ```
 * Please also see the `Module` interface, which a CliModule must also 
 * implement (in its class definition), though there are no required methods.
 * 
 * #pw-body
 * 
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 * @since 3.0.259
 *
 */
interface CliModule {
	
	/**
	 * Execute given command
	 *
	 * Output: standard output. This ensures CLI users see line-by-line output 
	 * rather than everything at once when execution finishes. 
	 *
	 * No need for a trailing newline in the output: ProcessWire adds one already.
	 *
	 * @param array $args Command line arguments passed, excluding module/cli name
	 *
	 */
	public function executeCli(array $args);
	
	/**
	 * Get array of allowed commands
	 * 
	 * This is used only for rendering help when user enters no command or
	 * when they request a list of commands. 
	 *
	 * Returned array keys are command names and values are 1-line labels
	 * Or it can be a regular PHP array of command names if labels are not needed.
	 * Or it can just be a string of whatever you want, and ProcessWire will output
	 * it as-is. 
	 *
	 * @return string[]|string Example: `[ 'hello' => 'Hello World' ]` or `[ 'hello' ]`
	 *
	 */
	public function getCliCommands();
}
