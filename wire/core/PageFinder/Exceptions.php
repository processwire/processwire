<?php namespace ProcessWire;

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
