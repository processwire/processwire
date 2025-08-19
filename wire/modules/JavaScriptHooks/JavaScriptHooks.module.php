<?php

namespace ProcessWire;

/**
 * @author Bernhard Baumrock, 10.07.2025
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class JavaScriptHooks extends WireData implements Module
{
  public function ready(): void {
	  if (wire()->config->ajax) return;
	  if (wire()->config->external) return;
	  $url = wire()->config->urls($this);
	  wire()->config->scripts->add($url . "dst/JavaScriptHooks.min.js");
  }
}
